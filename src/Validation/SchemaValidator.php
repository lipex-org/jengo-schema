<?php

declare(strict_types=1);

namespace Jengo\Schema\Validation;

use Jengo\Schema\Exceptions\InvalidSchemaException;
use Jengo\Schema\Metadata\SchemaMetadata;

final class SchemaValidator
{
    public static function validate(SchemaMetadata $metadata): void
    {
        // Primary key check
        if (! $metadata->primaryKey) {
            throw InvalidSchemaException::missingPrimaryKey($metadata->schemaClass);
        }

        // Relations
        foreach ($metadata->relations as $relation) {
            if ($relation->type === 'both') {
                throw InvalidSchemaException::multiplePrimaryKeys($metadata->schemaClass);
            }
        }

        // Ensure field names are unique
        $fieldNames = array_map(static fn ($f) => $f->name, $metadata->fields);
        if (count($fieldNames) !== count(array_unique($fieldNames))) {
            throw InvalidSchemaException::multiplePrimaryKeys($metadata->schemaClass);
        }

        // Could add more rules here:
        // - ensure derived fields are valid
        // - computed methods exist
    }
}
