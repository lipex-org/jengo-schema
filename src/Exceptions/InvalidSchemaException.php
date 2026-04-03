<?php

declare(strict_types=1);

namespace Jengo\Schema\Exceptions;

use RuntimeException;

final class InvalidSchemaException extends RuntimeException
{
    public static function missingPrimaryKey(string $schemaClass): self
    {
        return new self("Schema '{$schemaClass}' must define a primary key.");
    }

    public static function multiplePrimaryKeys(string $schemaClass): self
    {
        return new self("Schema '{$schemaClass}' cannot have multiple primary keys.");
    }

    public static function missingModelAttribute(string $schemaClass): self
    {
        return new self("Schema '{$schemaClass}' must declare a #[Model] attribute.");
    }
}
