<?php

declare(strict_types=1);

namespace Jengo\Schema\Commands\Variants\Schema;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Commands\Contracts\CommandVariantInterface;
use Tatter\Schemas\Drafter\Handlers\DatabaseHandler;
use Throwable;

class GenerateVariant implements CommandVariantInterface
{
    public static function name(): string
    {
        return 'generate';
    }

    public static function description(): string
    {
        return 'Generate physical Jengo Schema classes from the database structure.';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return [
            '--table' => 'Generate schema for a specific table only',
            '--force' => 'Force overwrite of existing schema files',
        ];
    }

    public function run(array $params): void
    {
        CLI::write('Drafting database schema...', 'cyan');

        try {
            $db      = (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') ? 'tests' : 'default';
            $handler = new DatabaseHandler(null, $db);
            $schemas = service('schemas');
            $schema  = $schemas->draft($handler)->get();
        } catch (Throwable $e) {
            CLI::error('Failed to map database: ' . $e->getMessage());

            return;
        }

        $targetTable = CLI::getOption('table');
        $force       = CLI::getOption('force') !== null;

        $outputDir = APPPATH . 'Schemas';
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        helper('inflector');

        $generatedCount = 0;

        foreach ($schema->tables as $tableName => $table) {
            if ($targetTable && strtolower($tableName) !== strtolower((string) $targetTable)) {
                continue;
            }

            if ($tableName === 'migrations') {
                continue;
            }

            $singularName    = pascalize(singular($tableName));
            $schemaClassName = $singularName . 'Schema';
            $filePath        = $outputDir . '/' . $schemaClassName . '.php';

            if (file_exists($filePath) && ! $force) {
                CLI::write("Skipping existing schema file for [{$tableName}] at [{$filePath}]. Use --force to overwrite.", 'yellow');

                continue;
            }

            // Guess Model Class name
            $modelClass  = "App\\Models\\{$singularName}Model";
            $entityClass = "App\\Entities\\{$singularName}";

            // Map Primary Key & Fields
            $fieldsBlock = '';
            $primaryKey  = null;

            foreach ($table->fields as $field) {
                $type = $this->mapPhpType($field->type ?? 'string');
                if ($field->primary_key) {
                    $fieldsBlock .= "    #[PrimaryKey()]\n";
                    $fieldsBlock .= "    public {$type} \${$field->name};\n\n";
                    $primaryKey = $field->name;
                } else {
                    $fieldsBlock .= "    #[Field(searchable: true)]\n";
                    $fieldsBlock .= "    public {$type} \${$field->name};\n\n";
                }
            }

            // Map Relations
            $relationsBlock = '';

            foreach ($table->relations as $relation) {
                $relSingular    = pascalize(singular($relation->table));
                $relSchemaClass = $relSingular . 'Schema';

                $fromField = '';
                $toField   = '';

                if (! empty($relation->pivots)) {
                    $pivot     = $relation->pivots[0];
                    $fromField = $pivot[1];
                    $toField   = $pivot[3];
                } else {
                    if ($relation->type === 'belongsTo') {
                        $fromField = singular($relation->table) . '_id';
                        $toField   = $primaryKey ?? 'id';
                    } else {
                        $fromField = $primaryKey ?? 'id';
                        $toField   = singular($tableName) . '_id';
                    }
                }

                if ($relation->type === 'belongsTo') {
                    $relationsBlock .= "    #[BelongsTo(\n";
                    $relationsBlock .= "        {$relSchemaClass}::class,\n";
                    $relationsBlock .= "        '{$fromField}',\n";
                    $relationsBlock .= "        '{$toField}'\n";
                    $relationsBlock .= "    )]\n";
                    $relationsBlock .= "    public \${$relation->table};\n\n";
                } else {
                    $relationsBlock .= "    #[HasMany(\n";
                    $relationsBlock .= "        {$relSchemaClass}::class,\n";
                    $relationsBlock .= "        '{$fromField}',\n";
                    $relationsBlock .= "        '{$toField}'\n";
                    $relationsBlock .= "    )]\n";
                    $relationsBlock .= "    public array \${$relation->table} = [];\n\n";
                }
            }

            // Build Template
            $template = "<?php\n\n";
            $template .= "declare(strict_types=1);\n\n";
            $template .= "namespace App\\Schemas;\n\n";
            $template .= "use Jengo\\Schema\\Attributes\\Field;\n";
            $template .= "use Jengo\\Schema\\Attributes\\Model;\n";
            $template .= "use Jengo\\Schema\\Attributes\\PrimaryKey;\n";
            $template .= "use Jengo\\Schema\\Attributes\\Relations\\BelongsTo;\n";
            $template .= "use Jengo\\Schema\\Attributes\\Relations\\HasMany;\n\n";

            // Check if guessed model and entity exist to clean imports
            if (class_exists($modelClass)) {
                $template .= "use {$modelClass};\n";
                $modelImportName = $singularName . 'Model';
            } else {
                $modelImportName = "'{$modelClass}'";
            }
            if (class_exists($entityClass)) {
                $template .= "use {$entityClass};\n";
                $entityImportName = $singularName;
            } else {
                $entityImportName = "'{$entityClass}'";
            }
            $template .= "\n";

            if (class_exists($modelClass) && class_exists($entityClass)) {
                $template .= "#[Model({$modelImportName}::class, {$entityImportName}::class)]\n";
            } else {
                $template .= "#[Model({$modelImportName}::class)]\n";
            }

            $template .= "final class {$schemaClassName}\n";
            $template .= "{\n";
            $template .= $fieldsBlock;
            $template .= $relationsBlock;
            $template = rtrim($template, "\n") . "\n";
            $template .= "}\n";

            file_put_contents($filePath, $template);
            CLI::write("Generated schema: [{$schemaClassName}] -> [{$filePath}]", 'green');
            $generatedCount++;
        }

        CLI::write("Successfully generated {$generatedCount} Jengo Schema files.", 'green');
    }

    private function mapPhpType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match ($dbType) {
            'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'int',
            'float', 'double', 'decimal', 'numeric', 'real'                => 'float',
            'boolean', 'bool'                                              => 'bool',
            default                                                        => 'string',
        };
    }
}
