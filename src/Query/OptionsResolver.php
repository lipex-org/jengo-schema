<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use Jengo\Schema\Config\Schema as SchemaConfig;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\SelectOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Query\OptionBuilder\RequestOptionsBuilder;
use Jengo\Schema\Support\Utils;

final class OptionsResolver
{
    public static function resolve(QueryMode $mode, QueryOptions $options): QueryOptions
    {
        // 1. OPEN MODE → hydrate from request
        if ($mode === QueryMode::OPEN) {
            $options = RequestOptionsBuilder::build($options);
        }

        // 2. Merge config defaults
        return self::fromConfig($options);
    }

    private static function fromConfig(QueryOptions $options): QueryOptions
    {
        /** @var SchemaConfig $config */
        $config = Utils::config();

        return new QueryOptions(
            params: self::resolveParams($options->params, $config),
            select: self::resolveSelect($options->select),
            pagination: self::resolvePagination($options->pagination, $config),
            derive: $options->derive,
            sort: self::resolveSort($options->sort, $config),
            search: $options->search,
            logger: $options->logger ?? $config->logger,
            first: $options->first
        );
    }

    // -----------------------------
    // Individual resolvers
    // -----------------------------

    private static function resolveParams(
        ParamOptions $params,
        SchemaConfig $config
    ): ParamOptions {
        return new ParamOptions(
            params: $params->params,
            callbacks: array_merge(
                $config->whereCallbacks,
                $params->callbacks
            ),
            isOr: $params->isOr
        );
    }

    private static function resolveSelect(
        SelectOptions $select
    ): SelectOptions {
        // Select has no config-level defaults (by design)
        return new SelectOptions(
            select: $select->select
        );
    }

    private static function resolvePagination(
        PaginationOptions $pagination,
        SchemaConfig $config
    ): PaginationOptions {
        $default = $config->paginationOptions;

        return new PaginationOptions(
            limit: $pagination->limit ?: $default->limit,
            page: $pagination->page ?: $default->page,
            linksMax: $default->linksMax,
            withQuery: $default->withQuery,
            group: $pagination->group ?: $default->group,
        );
    }

    private static function resolveSort(
        SortOptions $sort,
        SchemaConfig $config
    ): SortOptions {
        $default = $config->sortOptions;

        return new SortOptions(
            column: $sort->column ?: $default->column,
            direction: $sort->direction ?? $default->direction
        );
    }
}
