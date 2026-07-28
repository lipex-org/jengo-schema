<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use Jengo\Schema\Hydration\DTO\PropertyType;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

class PropertyTypeAnalyzer
{
    public static function analyze(ReflectionProperty $property): PropertyType
    {
        $reflectionType = $property->getType();

        if ($reflectionType === null) {
            return new PropertyType(['mixed'], true);
        }

        $types      = [];
        $allowsNull = $reflectionType->allowsNull();

        if ($reflectionType instanceof ReflectionNamedType) {
            $types[] = $reflectionType->getName();
        } else {
            // Handles Union and Intersection types
            /** @var ReflectionIntersectionType|ReflectionUnionType $reflectionType */
            foreach ($reflectionType->getTypes() as $subType) {
                $types[] = $subType->__toString();
            }
        }

        return new PropertyType($types, $allowsNull);
    }
}
