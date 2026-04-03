<?php

declare(strict_types=1);

namespace Jengo\Schema\Exceptions;

use RuntimeException;

final class InvalidRelationshipException extends RuntimeException
{
    public static function duplicateRelation(string $property, string $schemaClass): self
    {
        return new self("Property '{$property}' in schema '{$schemaClass}' cannot have both BelongsTo and HasMany attributes.");
    }

    public static function relationNotFound(string $relationName, string $schemaClass): self
    {
        return new self("Relation '{$relationName}' not found on schema '{$schemaClass}'.");
    }
}
