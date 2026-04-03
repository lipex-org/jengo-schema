<?php

declare(strict_types=1);

namespace Jengo\Schema\Reflection;

use Jengo\Schema\Attributes\Computed;
use Jengo\Schema\Attributes\Derived;
use Jengo\Schema\Attributes\Field;
use Jengo\Schema\Attributes\Model;
use Jengo\Schema\Attributes\PrimaryKey;
use Jengo\Schema\Attributes\Relations\BelongsTo;
use Jengo\Schema\Attributes\Relations\HasMany;
use Jengo\Schema\Exceptions\InvalidRelationshipException;
use Jengo\Schema\Exceptions\InvalidSchemaException;
use Jengo\Schema\Hydration\PropertyTypeAnalyzer;
use Jengo\Schema\Metadata\ComputedMetadata;
use Jengo\Schema\Metadata\FieldMetadata;
use Jengo\Schema\Metadata\RelationMetadata;
use Jengo\Schema\Metadata\SchemaMetadata;
use Jengo\Schema\Validation\SchemaValidator;
use ReflectionClass;
use RuntimeException;

final class SchemaReflector
{
    public static function reflect(string $schemaClass): SchemaMetadata
    {
        if (!class_exists($schemaClass)) {
            throw new RuntimeException("Schema class {$schemaClass} does not exist.");
        }

        $reflection = new ReflectionClass($schemaClass);

        /** --------------------
         *  Class-level Model
         *  -------------------- */
        $modelAttr = AttributeReflector::class($reflection, Model::class);

        if (!$modelAttr) {
            throw InvalidSchemaException::missingModelAttribute($schemaClass);
        }

        /** --------------------
         *  Properties
         * -------------------- */
        $fields = [];
        $relations = [];
        $primary = null;
        $parsedProperties = [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();
            $type = PropertyTypeAnalyzer::analyze($property);

            // Primary Key
            if (AttributeReflector::property($property, PrimaryKey::class)) {
                if ($primary) {
                    throw new RuntimeException(
                        "Schema {$schemaClass} has multiple primary keys.",
                    );
                }

                $primary = new FieldMetadata(
                    name: $name,
                    searchable: false,
                    derived: false,
                    type: $type
                );

                $parsedProperties[] = $name;
                continue;
            }

            // Relations
            $belongsTo = AttributeReflector::property($property, BelongsTo::class);
            $hasMany = AttributeReflector::property($property, HasMany::class);

            if ($belongsTo && $hasMany) {
                throw InvalidRelationshipException::duplicateRelation($name, $schemaClass);
            }

            if ($belongsTo || $hasMany) {
                $rel = $belongsTo ?? $hasMany;

                $relations[] = new RelationMetadata(
                    name: $name,
                    type: $belongsTo
                    ? RelationMetadata::BELONGS_TO
                    : RelationMetadata::HAS_MANY,
                    schemaClass: $rel->schema,
                    fromField: $rel->from,
                    toField: $rel->to,
                    select: $rel->select,
                    many: (bool) $hasMany,
                );

                // Implicitly derived
                $fields[] = new FieldMetadata(
                    name: $name,
                    searchable: false,
                    derived: true,
                    type: $type
                );

                $parsedProperties[] = $name;
                continue;
            }

            // Fields
            $fieldAttr = AttributeReflector::property($property, Field::class);
            $derivedAttr = AttributeReflector::property($property, Derived::class);

            if ($fieldAttr || $derivedAttr) {
                $fields[] = new FieldMetadata(
                    name: $name,
                    searchable: $fieldAttr?->searchable ?? false,
                    derived: (bool) $derivedAttr,
                    type: $type,
                    cast: $fieldAttr?->cast ?? null
                );

                $parsedProperties[] = $name;
                continue;
            }

            if (!in_array($name, $parsedProperties)) {
                // add as a normal field
                $fields[] = new FieldMetadata(
                    name: $name,
                    searchable: false,
                    derived: false,
                    type: $type
                );
            }

            $parsedProperties[] = $name;
        }

        if (!$primary) {
            throw InvalidSchemaException::missingPrimaryKey($schemaClass);
        }

        /** --------------------
         *  Computed methods
         *  -------------------- */
        $computed = [];

        foreach ($reflection->getMethods() as $method) {
            $computedAttr = AttributeReflector::method($method, Computed::class);

            if ($computedAttr) {
                $computed[] = new ComputedMetadata(
                    name: $computedAttr->name,
                    method: $method->getName(),
                    dependants: $computedAttr->dependants,
                    cast: $computedAttr->cast
                );
            }
        }

        $metadata = new SchemaMetadata(
            schemaClass: $schemaClass,
            modelClass: $modelAttr->model,
            entityClass: $modelAttr->entity,
            primaryKey: $primary,
            fields: $fields,
            relations: $relations,
            computed: $computed,
        );

        SchemaValidator::validate($metadata);

        return $metadata;
    }
}
