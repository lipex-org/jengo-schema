<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use Jengo\Schema\Graph\RelationshipGraph;
use Jengo\Schema\Hydration\Hydrator;
use Jengo\Schema\Metadata\SchemaMetadata;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Reflection\SchemaReflector;

final class Query
{
    public const QueryMode INLINE_MODE = QueryMode::INLINE;
    public const QueryMode OPEN_MODE = QueryMode::OPEN;

    private static $container;

    public static function run(string $schema, QueryOptions $options = new QueryOptions(), QueryMode $mode = Query::INLINE_MODE): QueryResult
    {
        $options = OptionsResolver::resolve($mode, $options);

        self::set(QueryOptions::class, $options);

        // reflect schema
        // TODO: we can later improve this by caching reflected clases and removing this overhead
        $schemaMeta = SchemaReflector::reflect($schema);

        self::set(SchemaMetadata::class, $schemaMeta);

        // build relationship graph from schema
        $graph = RelationshipGraph::build(rootSchema: $schemaMeta, derivePaths: $options?->derive ?? []);

        self::set(RelationshipGraph::class, $graph);

        // generate plan
        $plan = QueryPlan::fromGraph($graph, $options);

        self::set(QueryPlan::class, $plan);

        // build and exceute query
        $builderResult = QueryBuilder::build($graph->root, $options, $plan)->execute();

        // Check if clamping is requested and page/cursor is out of range
        if ($options->pagination->clamp && $options->pagination->limit > 0 && $builderResult->total > 0 && empty($builderResult->rows)) {
            $lastPage = (int) ceil($builderResult->total / $options->pagination->limit);
            $shouldClamp = false;

            if ($options->pagination->page > 1 && $options->pagination->page > $lastPage) {
                $shouldClamp = true;
            } elseif ($options->pagination->after !== null) {
                $shouldClamp = true;
            }

            if ($shouldClamp) {
                $newPagination = new PaginationOptions(
                    limit: $options->pagination->limit,
                    page: $lastPage,
                    linksMax: $options->pagination->linksMax,
                    withQuery: $options->pagination->withQuery,
                    group: $options->pagination->group,
                    after: null,
                    clamp: $options->pagination->clamp,
                );
                $options = new QueryOptions(
                    params: $options->params,
                    select: $options->select,
                    pagination: $newPagination,
                    derive: $options->derive,
                    sort: $options->sort,
                    search: $options->search,
                    logger: $options->logger,
                    first: $options->first,
                    allowedCapabilities: $options->allowedCapabilities,
                    entityClass: $options->entityClass,
                );
                self::set(QueryOptions::class, $options);

                // Regenerate plan and rebuild query
                $plan = QueryPlan::fromGraph($graph, $options);
                self::set(QueryPlan::class, $plan);

                $builderResult = QueryBuilder::build($graph->root, $options, $plan)->execute();
            }
        }

        // hydrate data to get QueryResult
        return Hydrator::hydrate($graph->root, $builderResult, $options, $plan);
    }

    public static function set(string $class, mixed $value): void
    {
        self::$container[$class] = $value;
    }

    public static function get(string $class): mixed
    {
        return self::$container[$class] ?? null;
    }
}
