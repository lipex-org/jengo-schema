<?php


declare(strict_types=1);

namespace Tests\Unit\Reflector;

use Jengo\Schema\Hydration\DTO\PropertyType;
use Jengo\Schema\Hydration\Enums\Cast;
use Jengo\Schema\Metadata\FieldMetadata;
use Jengo\Schema\Metadata\RelationMetadata;
use Tests\TestCase;

class ReflectorTestCase extends TestCase
{
    protected function getField(string $name, array $fields): FieldMetadata
    {
        $f = array_values(array_filter(
            $fields,
            fn($field) => $field['name'] === $name
        ))[0];

        $f['type'] = new PropertyType(...$f['type']);
        $f['cast'] = $this->cast($f['cast']);

        return new FieldMetadata(...$f);
    }

    protected function getRelation(string $name, array $fields): RelationMetadata
    {
        return new RelationMetadata(...array_values(array_filter(
            $fields,
            fn($field) => $field['name'] === $name
        ))[0]);
    }

    private function cast(?string $cast): ?Cast
    {
        foreach (Cast::cases() as $case) {
            if ($case->value === $cast) {
                return $case;
            }
        }

        return null;
    }
}
