<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\OptionBuilder;

use Closure;
use Jengo\Schema\Config\Schema as SchemaConfig;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\SelectOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\Enums\SortOrder;
use Jengo\Schema\Support\Utils;

final class RequestOptionsBuilder
{
    private array $reservedQueryWords = [
        'select',
        'orWhere',
        'sort',
        'page',
        'limit',
        'search',
        'derive',
        'null',
        'encrypted',
        'group',
        'withQuery',
        'links'
    ];

    private array $reservedQueryValues = [
        'null'
    ];

    private SchemaConfig $config;

    private array $paramCallbacks = [];

    public static function build(?QueryOptions $options = null): QueryOptions
    {
        $self = new self();
        $self->config = Utils::config();
        $request = request();

        $allowed = $options?->allowedCapabilities ?? ['pagination'];
        $allowAll = in_array('*', $allowed, true) || in_array('all', $allowed, true);

        $self->paramCallbacks = [
            ...$self->config->whereCallbacks,
            ...($options?->params->callbacks ?? [])
        ];

        // 1. Params / Where
        $params = $options?->params->params ?? [];
        if ($allowAll || in_array('where', $allowed, true)) {
            $orWhere = !!$request->getGet('orWhere');
            $requestParams = $self->parseWhere($request->getGet(), !$orWhere);
            $params = array_merge($params, $requestParams);
        }

        // 2. Select
        $select = $options?->select->select ?? [];
        if ($allowAll || in_array('select', $allowed, true)) {
            $requestSelect = explode(',', $request->getGet('select') ?? '') ?? [];
            $requestSelect = array_filter(array_map('trim', $requestSelect));
            if (!empty($requestSelect)) {
                $select = array_merge($select, $requestSelect);
            }
        }

        // 3. Derive
        $derive = $options?->derive ?? [];
        if ($allowAll || in_array('derive', $allowed, true)) {
            $requestDerive = explode(',', $request->getGet('derive') ?? '') ?? [];
            $requestDerive = array_filter(array_map('trim', $requestDerive));
            if (!empty($requestDerive)) {
                $derive = array_merge($derive, $requestDerive);
            }
        }

        // 4. Search
        $search = $options?->search ?? new \Jengo\Schema\Query\DTO\SearchOptions();
        if ($allowAll || in_array('search', $allowed, true)) {
            $searchValue = $request->getGet('search');
            if ($searchValue !== null && $searchValue !== '') {
                $search = new \Jengo\Schema\Query\DTO\SearchOptions(value: $searchValue);
            }
        }

        // 5. Sort (Convert request string into proper SortOptions)
        $sort = $options?->sort ?? new SortOptions();
        if ($allowAll || in_array('sort', $allowed, true)) {
            $requestSort = $request->getGet('sort');
            if (is_string($requestSort) && $requestSort !== '') {
                $direction = SortOrder::ASC;
                $column = $requestSort;
                if (str_starts_with($requestSort, '-')) {
                    $direction = SortOrder::DESC;
                    $column = substr($requestSort, 1);
                }
                $sort = new SortOptions(column: $column, direction: $direction);
            }
        }

        // 6. Coordinated Pagination
        $pagination = $options?->pagination ?? new PaginationOptions();
        if ($allowAll || in_array('pagination', $allowed, true)) {
            $group = $pagination->group; // Derived from inline code config

            // Dynamic URL parameter names based on group prefixing
            $pageKey = $group === 'default' ? 'page' : 'page_' . $group;
            $limitKey = $group === 'default' ? 'limit' : 'limit_' . $group;

            $withQuery = $request->getGet('withQuery') !== null ?: $pagination->withQuery;
            $linksMax = max((int) $request->getGet('links'), $pagination->linksMax);

            $page = max((int) $request->getGet($pageKey), 1);
            $limit = $request->getGet($limitKey) ?: $pagination->limit;

            $pagination = new PaginationOptions(
                limit: (int) $limit,
                page: (int) $page,
                linksMax: (int) $linksMax,
                withQuery: (bool) $withQuery,
                group: (string) $group
            );
        }

        return new QueryOptions(
            params: new ParamOptions(
                params: $params,
                callbacks: $self->paramCallbacks
            ),
            select: new SelectOptions(select: $select),
            pagination: $pagination,
            derive: $derive,
            sort: $sort,
            search: $search,
            logger: $options?->logger,
            first: $options?->first ?? false,
            allowedCapabilities: $allowed
        );
    }

    private function parseWhere(array $wheres, bool $isAndWhere = true): array
    {
        $result = [];
        foreach ($wheres as $key => $value) {
            if (!in_array($key, $this->reservedQueryWords)) {
                if (is_string($key) && is_string($value)) {
                    $out = $this->parseSelectValue($key, $value, $isAndWhere);
                    $result[$out['key']] = $out['value'];
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    protected function unwrapReservedWord(string $value): ?string
    {
        if (str_starts_with($value, '--') && str_ends_with($value, '--')) {
            return substr($value, 2, -2); // remove first and last two characters
        }

        return $value;
    }

    protected function parseSelectValue(string $value, string $key, bool $isAndWhere = true): array
    {
        // before callbacks
        $this->runCallbacks($key, $value, $isAndWhere, 'before');

        // unwrap any reserved words
        if (is_string($value)) {
            $value = trim((string) self::unwrapReservedWord((string) $value));

        }

        if (is_string($key)) {
            $key = trim((string) self::unwrapReservedWord((string) $key));
        }

        // parse any special characters
        if (is_string($value) && str_starts_with($value, '!')) {
            $key .= " !=";
            $value = substr($value, 1);
        }

        if (!in_array($value, $this->reservedQueryValues)) {
            $value;
        }

        if (is_string($value) && $value === 'null') {
            $value = null;
        }

        // after callbacks
        $this->runCallbacks($key, $value, $isAndWhere, 'after');

        return [
            'key' => $key,
            'value' => $value
        ];
    }

    /**
     * Runs select callbacks and adjusts values of key and value accordingly
     * @param string $key
     * @param string $value
     * @param bool $isAndSelect
     * @param string $callTime
     * @return list<string>
     */
    private function runCallbacks(string &$key, string &$value, bool $isAndSelect, string $callTime): void
    {
        $defaultOutput = [$key, $value];
        $output = $defaultOutput;

        foreach ($this->paramCallbacks as $fn) {
            try {
                if ($fn instanceof Closure) {
                    $output = $fn($key, $value, $isAndSelect ? 'and' : 'or', $callTime);
                }
            } catch (\Throwable $e) {
            }

            // check if array is of key - value pair
            if (!is_array($output) || !isset($output[0]) || !isset($output[1])) {
                $output = $defaultOutput;
            }
        }

        $key = $output[0];
        $value = $output[1];
    }
}
