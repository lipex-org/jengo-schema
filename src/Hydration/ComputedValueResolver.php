<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use Jengo\Schema\Debug\QueryLogger;
use Jengo\Schema\Graph\Node;
use Jengo\Schema\Metadata\ComputedMetadata;
use Jengo\Schema\Metadata\SchemaMetadata;
use Jengo\Schema\Query\Query;
use Jengo\Schema\Query\QueryPlan;
use Jengo\Schema\Support\AliasGenerator;
use Jengo\Schema\Support\ArrayUtils;
use RuntimeException;

final class ComputedValueResolver
{
    public static function resolve(Node $node, array &$record): void
    {
        /**
         * @var QUeryPlan $plan
         */
        $plan = Query::get(QueryPlan::class);
        $schema = $node->schema;
        $schemaClass = new $schema->schemaClass();
        $alias = AliasGenerator::for($node);
        $allViableDependecies = array_merge(
            $plan->selectsRaw[$alias] ?? [],
            self::resolveColumnsFromAliases(),
            array_column(ArrayUtils::toArray($schema->computed), 'name'),
        );

        // this shuold be in form of schema, field, computed method, dependencies, cast type and resons for skipping if any
        $skippedComputations = [];

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

            // Apply cast if defined
            if ($field->cast !== null) {
                $value = ValueCaster::cast($value, $field->cast->value);
            }

            // Validate assignability
            if (!$field->type->canAccept($value)) {
                throw new RuntimeException(
                    "Type mismatch on field '{$name}' for schema '{$schema->schemaClass}'"
                );
            }

            $schemaClass->{$name} = $value;
        }

        foreach ($schema->relations as $relation) {
            $name = $relation->name;

            if (!array_key_exists($name, $record)) {
                continue;
            }

            $value = $record[$name];

            try {
                $schemaClass->{$name} = $value;
            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Failed assigning relation '{$name}' on schema '{$schema->schemaClass}'",
                    previous: $e
                );
            }
        }

        /*
         * STEP 2: Resolve computed fields
         */
        $current = null;
        foreach ($schema->computed as $computed) {
            try {
                $current = $computed;
                // check if all dependencies for the computed field have all been selected or derived
                $dependencyCheck = self::checkMissingDependencies($computed, $allViableDependecies, $schema);

                if ($dependencyCheck->status) {
                    $skippedComputations[] = [
                        'field' => $computed->name,
                        'reason' => 'Missing dependencies: ' . implode(', ', $dependencyCheck->missingDeps),
                    ];
                    continue;
                }

                // Inject dependant fields
                foreach ($computed->dependants as $dep) {
                    $fieldDep = $schema->getField($dep);
                    $computedDep = $schema->getComputed($dep);
                    $relationDep = $schema->getRelation($dep);

                    if (!\array_key_exists($dep, $record) || (!$fieldDep && !$relationDep && !$computedDep)) {
                        continue;
                    }

                    if ($computedDep) {
                        // If dependant is a computed field, resolve it first
                        self::computeValue($schemaClass, $record, $computedDep);
                    }

                    $value = $record[$dep];

                    if ($fieldDep && $fieldDep->cast !== null) {
                        $value = ValueCaster::cast($value, $fieldDep->cast->value);
                    }

                    if ($computedDep && $computedDep->cast !== null) {
                        $value = ValueCaster::cast($value, $computedDep->cast->value);
                    }

                    $schemaClass->{$dep} = $value;
                }

                // Now compute the value itself
                self::computeValue($schemaClass, $record, $computed);

            } catch (\Throwable $e) {
                throw new RuntimeException(
                    "Failed computing '{$computed->name}' on schema '{$schema->schemaClass}'",
                    previous: $e
                );
            }
        }

        QueryLogger::append('computed_value_resolution', [
            'schema' => $schema->schemaClass,
            'record' => $record,
            'skipped_computations' => $skippedComputations,
        ]);
    }

    private static function computeValue(object &$schemaClass, array &$record, ComputedMetadata $computed): void
    {
        $computedValue = $schemaClass->{$computed->method}();

        // Apply computed cast if defined
        if ($computed->cast !== null) {
            $computedValue = ValueCaster::cast(
                $computedValue,
                $computed->cast->value
            );
        }

        $record[$computed->name] = $computedValue;
    }

    private static function resolveColumnsFromAliases(): array
    {
        /**
         * @var QueryPlan $plan
         */
        $plan = Query::get(QueryPlan::class);
        $aliases = array_keys($plan->aliases);

        $columns = [];
        foreach ($aliases as $alias) {
            $value = str_replace(['.', 'root'], ['', ''], $alias);

            if ($value) {
                $columns[] = $value;
            }
        }
        return $columns;
    }

    private static function checkMissingDependencies(ComputedMetadata $field, array $allViableDependecies, SchemaMetadata $schema): object
    {
        $dependencies = $field->dependants;

        $missingDeps = array_filter($dependencies, function ($dep) use ($allViableDependecies, &$checkedDep) {
            return !in_array($dep, $allViableDependecies, true);
        });

        foreach ($dependencies as $dep) {
            if ($schema->getComputed($dep)) {
                // check for mising dependecies for the dependant computed field as well
                $depComputed = $schema->getComputed($dep);
                $depCheck = self::checkMissingDependencies($depComputed, $allViableDependecies, $schema);
                if ($depCheck->status) {
                    $missingDeps = array_merge($missingDeps, $depCheck->missingDeps);
                }
            }
        }

        return (object) [
            'status' => !empty($missingDeps),
            'missingDeps' => $missingDeps,
        ];
    }
}
