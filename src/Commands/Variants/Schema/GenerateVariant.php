<?php

declare(strict_types=1);

namespace Jengo\Schema\Commands\Variants\Schema;

use CodeIgniter\Autoloader\FileLocator;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;
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
            '--table'       => 'Generate schema for a specific table only',
            '--force'       => 'Force overwrite of existing schema files',
            '--dbgroup'     => 'Specify the database group to connect to (Defaults to tests in testing, default otherwise)',
            '--namespace'   => 'Specify custom namespace for the generated schema classes',
            '--dir'         => 'Specify custom directory where schema classes should be saved',
            '--dry-run'     => 'Simulate the generation without creating/modifying files on disk',
            '--with-vendor' => 'Generate schemas for vendor/system models as well (defaults to false)',
            '--ts'          => 'Generate TypeScript interfaces alongside Jengo Schema classes',
            '--ts-dir'      => 'Specify custom directory for TypeScript types (defaults to resources/js/types/schemas)',
        ];
    }

    public function run(array $params): void
    {
        CLI::write('Drafting database schema...', 'cyan');

        // Resolve dbgroup
        $dbGroup = $params['dbgroup'] ?? null;
        if ($dbGroup === null) {
            foreach ($params as $k => $v) {
                if (strpos((string) $k, 'dbgroup=') === 0) {
                    $dbGroup = substr((string) $k, strlen('dbgroup='));
                    break;
                }
            }
        }
        if ($dbGroup === null) {
            $dbGroup = CLI::getOption('dbgroup');
        }

        try {
            if (empty($dbGroup)) {
                $db = (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') ? 'tests' : 'default';
            } else {
                $db = $dbGroup;
            }
            $handler = new DatabaseHandler(null, $db);
            $schemas = service('schemas');
            $schema = $schemas->draft($handler)->get();
        } catch (Throwable $e) {
            CLI::error('Failed to map database: ' . $e->getMessage());

            return;
        }

        $targetTable = $params['table'] ?? CLI::getOption('table');
        if ($targetTable === null) {
            foreach ($params as $k => $v) {
                if (strpos((string) $k, 'table=') === 0) {
                    $targetTable = substr((string) $k, strlen('table='));
                    break;
                }
            }
        }

        $force = isset($params['force']) || (CLI::getOption('force') !== null);
        $dryRun = isset($params['dry-run']) || (CLI::getOption('dry-run') !== null);

        // Resolve with-vendor option
        $withVendorVal = $params['with-vendor'] ?? null;
        if ($withVendorVal === null) {
            foreach ($params as $k => $v) {
                if (strpos((string) $k, 'with-vendor=') === 0) {
                    $withVendorVal = substr((string) $k, strlen('with-vendor='));
                    break;
                }
            }
        }
        if ($withVendorVal === null) {
            $withVendorVal = CLI::getOption('with-vendor');
        }
        $withVendor = array_key_exists('with-vendor', $params) || $withVendorVal !== null;
        if ($withVendorVal === 'false' || $withVendorVal === false) {
            $withVendor = false;
        }

        // Resolve Config overrides or option overrides
        $config = config('Schema');
        $nsOption = $params['namespace'] ?? null;
        if ($nsOption === null) {
            foreach ($params as $k => $v) {
                if (strpos((string) $k, 'namespace=') === 0) {
                    $nsOption = substr((string) $k, strlen('namespace='));
                    break;
                }
            }
        }
        if ($nsOption === null) {
            $nsOption = CLI::getOption('namespace');
        }
        $namespace = $nsOption ?: ($config->generatorNamespace ?? 'App\\Schemas');
        $namespace = rtrim($namespace, '\\');

        $dirOption = $params['dir'] ?? null;
        if ($dirOption === null) {
            foreach ($params as $k => $v) {
                if (strpos((string) $k, 'dir=') === 0) {
                    $dirOption = substr((string) $k, strlen('dir='));
                    break;
                }
            }
        }
        if ($dirOption === null) {
            $dirOption = CLI::getOption('dir');
        }
        $outputDir = $dirOption ?: ($config->generatorDirectory ?? APPPATH . 'Schemas');
        $outputDir = rtrim($outputDir, '/');

        if (!$dryRun && !is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Resolve TypeScript options
        $tsOption = $params['ts'] ?? null;
        if ($tsOption === null) {
            foreach ($params as $k => $v) {
                if (strpos((string)$k, 'ts=') === 0) {
                    $tsOption = substr((string)$k, strlen('ts='));
                    break;
                }
            }
        }
        if ($tsOption === null) {
            $tsOption = CLI::getOption('ts');
        }
        $generateTs = array_key_exists('ts', $params) || $tsOption !== null;
        if ($tsOption === 'false' || $tsOption === false) {
            $generateTs = false;
        }
        if ($tsOption === null && !array_key_exists('ts', $params)) {
            $generateTs = $config->generateTypeScript ?? false;
        }

        $tsDirOption = $params['ts-dir'] ?? null;
        if ($tsDirOption === null) {
            foreach ($params as $k => $v) {
                if (strpos((string)$k, 'ts-dir=') === 0) {
                    $tsDirOption = substr((string)$k, strlen('ts-dir='));
                    break;
                }
            }
        }
        if ($tsDirOption === null) {
            $tsDirOption = CLI::getOption('ts-dir');
        }
        $tsOutputDir = $tsDirOption ?: ($config->typeScriptDirectory ?? ROOTPATH . 'resources/js/types/schemas');
        $tsOutputDir = rtrim($tsOutputDir, '/');

        if ($generateTs && !$dryRun && !is_dir($tsOutputDir)) {
            mkdir($tsOutputDir, 0755, true);
        }

        $tsExports = [];

        helper('inflector');

        // Locate all models and entities in the application & Jengo modules dynamically
        $locator = service('locator');
        $modelMap = [];
        $entityMap = [];

        try {
            $modelFiles = $locator->listFiles('Models/');
            foreach ($modelFiles as $file) {
                $className = $locator->findQualifiedNameFromPath($file);
                if ($className && class_exists($className)) {
                    if (is_subclass_of($className, 'CodeIgniter\Model')) {
                        try {
                            $modelInstance = new $className();
                            $builder = $modelInstance->builder();
                            $table = $builder->getTable();
                            $getReturnType = \Closure::bind(function ($model) {
                                return $model->returnType;
                            }, null, $modelInstance);

                            $getPrimaryKey = \Closure::bind(function ($model) {
                                return $model->primaryKey;
                            }, null, $modelInstance);

                            $entity = $getReturnType($modelInstance);
                            $primaryKey = $getPrimaryKey($modelInstance);
                            if ($entity === 'object' || $entity === 'array') {
                                $entity = null;
                            }
                            if ($table) {
                                $modelMap[strtolower($table)] = [
                                    'model' => $className,
                                    'entity' => $entity,
                                    'primary_key' => $primaryKey,
                                ];
                            }
                        } catch (Throwable $e) {
                        }
                    }
                }
            }

            $entityFiles = $locator->listFiles('Entities/');
            foreach ($entityFiles as $file) {
                $className = $locator->findQualifiedNameFromPath($file);
                if ($className && class_exists($className)) {
                    $parts = explode('\\', $className);
                    $shortName = end($parts);
                    $entityMap[strtolower($shortName)] = $className;
                }
            }
        } catch (Throwable $e) {
            // Fallback gracefully
        }

        $generatedCount = 0;

        foreach ($schema->tables as $tableName => $table) {
            if ($targetTable && strtolower($tableName) !== strtolower((string) $targetTable)) {
                continue;
            }

            if ($tableName === 'migrations') {
                continue;
            }

            $singularName = pascalize(singular($tableName));
            $schemaClassName = $singularName . 'Schema';
            $filePath = $outputDir . '/' . $schemaClassName . '.php';

            if (file_exists($filePath) && !$force) {
                if ($dryRun) {
                    CLI::write("  [dry-run] Would prompt to modify/overwrite existing schema file: [{$schemaClassName}] at [{$filePath}]", 'yellow');
                    continue;
                }

                if (ENVIRONMENT === 'testing') {
                    CLI::write("Skipping existing schema file for [{$tableName}] in testing environment.", 'yellow');
                    continue;
                }

                $choice = CLI::prompt("Schema file for [{$tableName}] already exists at [{$filePath}]. Overwrite?", ['y', 'n', 'd'], 'n');
                if (strtolower($choice) === 'n') {
                    CLI::write("Skipping existing schema file for [{$tableName}].", 'yellow');
                    continue;
                }
                if (strtolower($choice) === 'd') {
                    $oldContent = file_get_contents($filePath);
                    CLI::write("--- Current File ---\n" . substr($oldContent, 0, 300) . "...\n", 'yellow');
                    $confirm = CLI::prompt("Do you still want to overwrite?", ['y', 'n'], 'n');
                    if (strtolower($confirm) === 'n') {
                        continue;
                    }
                }
            }

            // Resolve Model and Entity Class names from discovery maps
            $mapped = $modelMap[strtolower($tableName)] ?? null;

            // By default (with-vendor is false), verify that a project-level model exists and is not inside vendor directory
            if (!$withVendor) {
                if (!$mapped) {
                    continue;
                }
                try {
                    $ref = new \ReflectionClass($mapped['model']);
                    $fileName = $ref->getFileName();
                    if ($fileName !== false && strpos($fileName, '/vendor/') !== false) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if ($mapped) {
                $modelClass = $mapped['model'];
                $entityClass = $mapped['entity'] ?? ($entityMap[strtolower($singularName)] ?? "App\\Entities\\{$singularName}");
            } else {
                $modelClass = "App\\Models\\{$singularName}Model";
                $entityClass = $entityMap[strtolower($singularName)] ?? "App\\Entities\\{$singularName}";
            }

            // Map Primary Key & Fields
            $fieldsBlock = '';
            $primaryKey = null;

            foreach ($table->fields as $field) {
                $type = $this->mapPhpType($field->type ?? 'string');
                $castAttribute = '';
                $dbType = strtolower($field->type ?? '');

                if (in_array($dbType, ['json', 'jsonb'], true)) {
                    $castAttribute = "(cast: \\Jengo\\Schema\\Hydration\\Enums\\Cast::JSON)";
                }

                $commentBlock = '';
                if (!empty($field->comment)) {
                    $commentBlock = "    /**\n     * " . str_replace("\n", "\n     * ", trim($field->comment)) . "\n     */\n";
                }

                $isPrimaryKey = $field->primary_key || ($mapped && isset($mapped['primary_key']) && $mapped['primary_key'] === $field->name);

                if ($isPrimaryKey) {
                    $fieldsBlock .= $commentBlock;
                    $fieldsBlock .= "    #[PrimaryKey()]\n";
                    $fieldsBlock .= "    public {$type} \${$field->name};\n\n";
                    $primaryKey = $field->name;
                } else {
                    $fieldsBlock .= $commentBlock;
                    if ($castAttribute) {
                        $fieldsBlock .= "    #[Field{$castAttribute}]\n";
                    } else {
                        $fieldsBlock .= "    #[Field()]\n";
                    }
                    $fieldsBlock .= "    public {$type} \${$field->name};\n\n";
                }
            }

            // Map Relations
            $relationsBlock = '';

            foreach ($table->relations as $relation) {
                $relSingular = pascalize(singular($relation->table));
                $relSchemaClass = $relSingular . 'Schema';

                $fromField = '';
                $toField = '';

                if (!empty($relation->pivots)) {
                    $pivot = $relation->pivots[0];
                    $fromField = $pivot[1];
                    $toField = $pivot[3];

                    if (is_array($fromField) && isset($fromField)) {
                        $fromField = $fromField[0];
                    }

                    if (is_array($toField) && isset($toField)) {
                        $toField = $toField[0];
                    }
                } else {
                    if ($relation->type === 'belongsTo') {
                        $fromField = singular($relation->table) . '_id';
                        $toField = $primaryKey ?? 'id';
                    } else {
                        $fromField = $primaryKey ?? 'id';
                        $toField = singular($tableName) . '_id';
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
            $template .= "namespace {$namespace};\n\n";
            $template .= "use Jengo\\Schema\\Attributes\\Field;\n";
            $template .= "use Jengo\\Schema\\Attributes\\Model;\n";
            $template .= "use Jengo\\Schema\\Attributes\\PrimaryKey;\n";
            $template .= "use Jengo\\Schema\\Attributes\\Relations\\BelongsTo;\n";
            $template .= "use Jengo\\Schema\\Attributes\\Relations\\HasMany;\n\n";

            // Check if guessed model and entity exist to clean imports
            if (class_exists($modelClass)) {
                $template .= "use {$modelClass};\n";
                $parts = explode('\\', $modelClass);
                $modelImportName = end($parts);
            } else {
                $modelImportName = "'{$modelClass}'";
            }
            if (class_exists($entityClass)) {
                $template .= "use {$entityClass};\n";
                $parts = explode('\\', $entityClass);
                $entityImportName = end($parts);
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

            if ($generateTs) {
                $tsFieldsBlock = '';
                $tsImports = [];

                foreach ($table->fields as $field) {
                    $tsType = $this->mapTsType($field->type ?? 'string');
                    $isPrimaryKey = $field->primary_key || ($mapped && isset($mapped['primary_key']) && $mapped['primary_key'] === $field->name);
                    $isNullable = !$isPrimaryKey && (!empty($field->nullable) || (isset($field->null) && $field->null === true));
                    $tsFieldsBlock .= "  {$field->name}: {$tsType}" . ($isNullable ? ' | null' : '') . ";\n";
                }

                foreach ($table->relations as $relation) {
                    $relSingular = pascalize(singular($relation->table));
                    $relSchemaClass = $relSingular . 'Schema';
                    $tsImports[] = "import { {$relSchemaClass} } from './{$relSchemaClass}';";
                    if ($relation->type === 'belongsTo') {
                        $tsFieldsBlock .= "  {$relation->table}?: {$relSchemaClass};\n";
                    } else {
                        $tsFieldsBlock .= "  {$relation->table}?: {$relSchemaClass}[];\n";
                    }
                }

                $tsImports = array_unique($tsImports);
                $tsContent = "";
                if (!empty($tsImports)) {
                    $tsContent .= implode("\n", $tsImports) . "\n\n";
                }
                $tsContent .= "export interface {$schemaClassName} {\n";
                $tsContent .= $tsFieldsBlock;
                $tsContent .= "}\n";

                $tsFilePath = $tsOutputDir . '/' . $schemaClassName . '.ts';

                if ($dryRun) {
                    CLI::write("  [dry-run] Would generate TS interface: [{$schemaClassName}] -> [{$tsFilePath}]", 'yellow');
                } else {
                    file_put_contents($tsFilePath, $tsContent);
                    CLI::write("Generated TS interface: [{$schemaClassName}] -> [{$tsFilePath}]", 'green');
                }
                $tsExports[] = $schemaClassName;
            }

            if ($dryRun) {
                CLI::write("  [dry-run] Would generate schema: [{$schemaClassName}] -> [{$filePath}]", 'yellow');
                $generatedCount++;
                continue;
            }

            file_put_contents($filePath, $template);
            CLI::write("Generated schema: [{$schemaClassName}] -> [{$filePath}]", 'green');
            $generatedCount++;
        }

        if ($generateTs && !empty($tsExports)) {
            $indexContent = '';
            foreach ($tsExports as $exportName) {
                $indexContent .= "export * from './{$exportName}';\n";
            }
            $indexFilePath = $tsOutputDir . '/index.ts';

            if ($dryRun) {
                CLI::write("  [dry-run] Would generate TS index: [{$indexFilePath}]", 'yellow');
            } else {
                file_put_contents($indexFilePath, $indexContent);
                CLI::write("Generated TS index: [{$indexFilePath}]", 'green');
            }
        }

        if ($dryRun) {
            CLI::write("Successfully simulated generation of {$generatedCount} Jengo Schema files.", 'green');
        } else {
            CLI::write("Successfully generated {$generatedCount} Jengo Schema files.", 'green');
        }
    }

    private function mapPhpType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match ($dbType) {
            'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'int',
            'float', 'double', 'decimal', 'numeric', 'real' => 'float',
            'boolean', 'bool' => 'bool',
            default => 'string',
        };
    }

    private function mapTsType(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match ($dbType) {
            'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'number',
            'float', 'double', 'decimal', 'numeric', 'real' => 'number',
            'boolean', 'bool' => 'boolean',
            'json', 'jsonb' => 'any',
            default => 'string',
        };
    }

    private function getOptionFromParams(array $params, string $name)
    {
        foreach ($params as $i => $param) {
            if (!is_string($param)) {
                continue;
            }
            if ($param === "--{$name}" || $param === "-{$name}") {
                $next = $params[$i + 1] ?? null;
                if ($next !== null && is_string($next) && strpos($next, '-') !== 0) {
                    return $next;
                }
                return true;
            }
            if (strpos($param, "--{$name}=") === 0) {
                return substr($param, strlen("--{$name}="));
            }
            if (strpos($param, "-{$name}=") === 0) {
                return substr($param, strlen("-{$name}="));
            }
        }
        return null;
    }
}
