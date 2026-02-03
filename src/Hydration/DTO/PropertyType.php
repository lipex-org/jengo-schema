<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration\DTO;

final class PropertyType
{
    public function __construct(
        public array $types,
        public bool $allowsNull
    ) {
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function isNullable(): bool
    {
        return $this->allowsNull;
    }

    public function isCompound(): bool
    {
        return count($this->types) > 1;
    }

    public function hasClass(): bool
    {
        foreach ($this->types as $type) {
            if ($type !== 'mixed' && !in_array($type, ['int', 'float', 'string', 'bool', 'array', 'object', 'null'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if a value can be safely cast or assigned to this type.
     */
    public function canAccept(mixed $value): bool
    {
        if ($value === null)
            return $this->allowsNull;

        $valueType = gettype($value);
        // Normalize 'double' to 'float' and 'integer' to 'int' for PHP consistency
        $valueType = match ($valueType) {
            'double' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            default => $valueType
        };

        foreach ($this->types as $type) {
            if ($type === 'mixed' || $valueType === $type)
                return true;
            if (is_object($value) && $value instanceof $type)
                return true;
        }

        return false;
    }
}