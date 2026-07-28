<?php

declare(strict_types=1);

namespace Jengo\Schema\Debug;

use Jengo\Schema\Graph\RelationshipGraph;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\QueryPlan;
use Jengo\Schema\Reflection\SchemaReflector;

final class Explain
{
    public static function run(
        string $schema,
        QueryOptions $options,
    ): array {
        // Reflect schema
        $rootSchema = SchemaReflector::reflect($schema);

        // Build relationship graph
        $graph = RelationshipGraph::build(
            rootSchema: $rootSchema,
            derivePaths: $options->derive ?? [],
        );

        // Build query plan
        $plan = QueryPlan::fromGraph(
            graph: $graph,
            options: $options,
        );

        return [
            'schema' => $schema,
            'derive' => $options->derive,
            'graph'  => self::describeGraph($graph),
            'plan'   => self::describePlan($plan),
        ];
    }

    private static function describeGraph(RelationshipGraph $graph): array
    {
        return $graph->describe();
    }

    private static function describePlan(QueryPlan $plan): array
    {
        return [
            'select'     => $plan->selects,
            'joins'      => $plan->joins,
            'where'      => $plan->where,
            'order'      => $plan->sort->direction->name,
            'sortColumn' => $plan->sort->column,
            'limit'      => $plan->limit,
            'offset'     => $plan->offset,
        ];
    }
}
