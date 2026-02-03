<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use Jengo\Schema\Graph\Node;
use RuntimeException;

final class ComputedValueResolver
{
    public static function resolve(Node $node, array &$record): void
    {
        $schema = $node->schema;
        $schemaClass = new $schema->schemaClass();

        /*
         * STEP 1: Assign base (non-derived) fields safely
         */
        foreach ($schema->fields as $field) {
            if ($field->derived) {
                continue;
            }

            $name = $field->name;

            if (!array_key_exists($name, $record)) {
                continue;
            }

            $value = $record[$name];

            // Validate assignability
            if (!$field->type->canAccept($value)) {
                throw new RuntimeException(
                    "Type mismatch on field '{$name}' for schema '{$schema->schemaClass}'"
                );
            }

            // Apply cast if defined
            if ($field->cast !== null) {
                $value = ValueCaster::cast($value, $field->cast->value);
            }

            $schemaClass->{$name} = $value;
        }

        /*
         * STEP 2: Resolve computed fields
         */
        foreach ($schema->computed as $computed) {
            try {
                // Inject dependant fields
                foreach ($computed->dependants as $dep) {
                    $field = $schema->getField($dep);

                    if (!array_key_exists($dep, $record) || !$field) {
                        continue;
                    }

                    $value = $record[$dep];

                    if ($field->cast !== null) {
                        $value = ValueCaster::cast($value, $field->cast->value);
                    }

                    $schemaClass->{$dep} = $value;
                }

                // Call computed method
                $computedValue = $schemaClass->{$computed->method}();

                // Apply computed cast if defined
                if ($computed->cast !== null) {
                    $computedValue = ValueCaster::cast(
                        $computedValue,
                        $computed->cast->value
                    );
                }

                $record[$computed->name] = $computedValue;
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Failed computing '{$computed->name}' on schema '{$schema->schemaClass}'",
                    previous: $e
                );
            }
        }
    }
}
