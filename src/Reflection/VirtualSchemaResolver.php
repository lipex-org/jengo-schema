<?php

declare(strict_types=1);

namespace Jengo\Schema\Reflection;

use CodeIgniter\Model;
use Jengo\Schema\Hydration\DTO\PropertyType;
use Jengo\Schema\Metadata\FieldMetadata;
use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Metadata\SchemaMetadata;
use ReflectionProperty;
use stdClass;
use Tatter\Schemas\Drafter\Handlers\DatabaseHandler;
use Throwable;

final class VirtualSchemaResolver
{
    /**
     * Resolves a table name, model class, or virtual schema class into SchemaMetadata dynamically.
     */
    public static function resolve(string $schemaClassOrTable): ?SchemaMetadata
    {
        // 1. Try to check cache
        $cacheKey = 'jengo_schema_meta_' . md5($schemaClassOrTable);

        try {
            if ($cached = cache($cacheKey)) {
                $metadata = unserialize($cached);
                if ($metadata && str_starts_with($metadata->modelClass, 'Jengo\\Schema\\Dynamic\\')) {
                    $parts     = explode('\\', $metadata->modelClass);
                    $className = end($parts);
                    if (! class_exists($metadata->modelClass)) {
                        eval("
                            namespace Jengo\\Schema\\Dynamic;
                            class {$className} extends \\CodeIgniter\\Model {
                                protected \$table = '{$schemaClassOrTable}';
                                protected \$returnType = 'object';
                            }
                        ");
                    }
                }

                return $metadata;
            }
        } catch (Throwable $e) {
            // Ignore cache exceptions in case cache driver is not ready
        }

        // 2. Map database schema using Tatter\Schemas
        try {
            $db      = (defined('ENVIRONMENT') && ENVIRONMENT === 'testing') ? 'tests' : 'default';
            $handler = new DatabaseHandler(null, $db);
            $schemas = service('schemas');
            $schema  = $schemas->draft($handler)->get();
        } catch (Throwable $e) {
            return null;
        }

        // 3. Resolve table name
        $tableName   = $schemaClassOrTable;
        $modelClass  = null;
        $entityClass = null;

        // If the class exists and is a model
        if (class_exists($schemaClassOrTable) && is_subclass_of($schemaClassOrTable, Model::class)) {
            $modelClass = $schemaClassOrTable;

            try {
                $modelInstance = new $modelClass();
                $tableName     = $modelInstance->table;
                $entityClass   = $modelInstance->returnType;
                if ($entityClass === 'object' || $entityClass === null) {
                    $entityClass = stdClass::class;
                } elseif ($entityClass === 'array') {
                    $entityClass = null;
                }
            } catch (Throwable $e) {
                // Ignore instantiation issues
            }
        }

        // Find table in mapped schema
        $table = $schema->tables->{$tableName} ?? null;
        if ($table === null) {
            // Case-insensitive check
            foreach ($schema->tables as $tName => $tObj) {
                if (strtolower($tName) === strtolower($tableName)) {
                    $table     = $tObj;
                    $tableName = $tName;
                    break;
                }
            }
        }

        if ($table === null) {
            return null;
        }

        // 4. Construct dynamic model/entity classes if not present
        if ($modelClass === null) {
            $safeName   = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            $safeName   = ucfirst($safeName);
            $modelClass = "Jengo\\Schema\\Dynamic\\{$safeName}Model";
            if (! class_exists($modelClass)) {
                $fieldsList = [];
                foreach ($table->fields as $f) {
                    $fieldsList[] = $f->name;
                }
                $allowedStr = "['" . implode("', '", $fieldsList) . "']";
                eval("
                    namespace Jengo\\Schema\\Dynamic;
                    class {$safeName}Model extends \\CodeIgniter\\Model {
                        protected \$table = '{$tableName}';
                        protected \$returnType = 'object';
                        protected \$allowedFields = {$allowedStr};
                    }
                ");
            }
        }

        // 5. Map fields
        $fields  = [];
        $primary = null;

        foreach ($table->fields as $field) {
            $dbType = strtolower($field->type ?? 'string');
            $type   = match ($dbType) {
                'int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'int',
                'float', 'double', 'decimal', 'numeric', 'real'                => 'float',
                'boolean', 'bool'                                              => 'bool',
                default                                                        => 'string',
            };
            $propType = new PropertyType([$type], true);
            if ($field->primary_key) {
                $primary = new FieldMetadata(
                    name: $field->name,
                    type: $propType,
                    searchable: false,
                    derived: false,
                );
            } else {
                $fields[] = new FieldMetadata(
                    name: $field->name,
                    type: $propType,
                    searchable: true,
                    derived: false,
                );
            }
        }

        if (! $primary) {
            $primary = new FieldMetadata(
                name: 'id',
                type: new PropertyType(['int'], true),
                searchable: false,
                derived: false,
            );
        }

        // Update dynamic model's primary key
        if (str_starts_with($modelClass, 'Jengo\\Schema\\Dynamic\\')) {
            try {
                $modelInstance = new $modelClass();
                $ref           = new ReflectionProperty($modelInstance, 'primaryKey');
                $ref->setValue($modelInstance, $primary->name);
            } catch (Throwable $e) {
                // Ignore reflection adjustments on failures
            }
        }

        // 6. Map relations
        $relations = [];
        helper('inflector');

        foreach ($table->relations as $relation) {
            // Find relation table object
            $relTable = $schema->tables->{$relation->table} ?? null;
            if ($relTable === null) {
                continue;
            }

            // Determine local key and foreign key from pivots or fallback
            $fromField = '';
            $toField   = '';

            if (! empty($relation->pivots)) {
                $pivot     = $relation->pivots[0];
                $fromField = $pivot[1];
                $toField   = $pivot[3];
            } else {
                if ($relation->type === 'belongsTo') {
                    $fromField = singular($relation->table) . '_id';
                    $toField   = 'id';
                } else {
                    $fromField = 'id';
                    $toField   = singular($tableName) . '_id';
                }
            }

            // Determine schema class
            $relSchemaClass = $relation->table;

            $relations[] = new RelationMetadata(
                name: $relation->table,
                type: $relation->type === 'belongsTo'
                ? RelationMetadata::BELONGS_TO
                : RelationMetadata::HAS_MANY,
                schemaClass: $relSchemaClass,
                fromField: $fromField,
                toField: $toField,
                select: [],
                many: ! $relation->singleton,
            );

            // Add implicit derived fields
            $fields[] = new FieldMetadata(
                name: $relation->table,
                type: new PropertyType(['object'], true),
                searchable: false,
                derived: true,
            );
        }

        if ($entityClass === null) {
            $config      = config('Schema');
            $entityClass = $config->entityMap[$tableName] ?? stdClass::class;
        }

        $metadata = new SchemaMetadata(
            schemaClass: $schemaClassOrTable,
            modelClass: $modelClass,
            entityClass: $entityClass,
            primaryKey: $primary,
            fields: $fields,
            relations: $relations,
            computed: [],
        );

        // Cache the metadata
        try {
            cache()->save($cacheKey, serialize($metadata), 3600);
        } catch (Throwable $e) {
            // Ignore cache storage errors
        }

        return $metadata;
    }
}
