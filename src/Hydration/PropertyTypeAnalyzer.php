<?php 

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use Jengo\Schema\Hydration\DTO\PropertyType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use ReflectionIntersectionType;

class PropertyTypeAnalyzer
{
    public static function analyze(ReflectionProperty $property): PropertyType
    {
        $reflectionType = $property->getType();

        if ($reflectionType === null) {
            return new PropertyType(['mixed'], true);
        }

        $types = [];
        $allowsNull = $reflectionType->allowsNull();

        if ($reflectionType instanceof ReflectionNamedType) {
            $types[] = $reflectionType->getName();
        } else {
            // Handles Union and Intersection types
            /** @var ReflectionUnionType|ReflectionIntersectionType $reflectionType */
            foreach ($reflectionType->getTypes() as $subType) {
                $types[] = $subType->__tostring();
            }
        }

        return new PropertyType($types, $allowsNull);
    }
}