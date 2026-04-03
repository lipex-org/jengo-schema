<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use DateTimeImmutable;

final class ValueCaster
{
    public static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array' => (array) $value,
            'datetime' => new DateTimeImmutable($value ?? 'now'),
            default => $value,
        };
    }
}
