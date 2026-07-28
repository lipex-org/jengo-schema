<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use Jengo\Schema\Debug\QueryLogger;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\DTO\SearchOptions;
use Jengo\Schema\Query\DTO\SelectOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Query\Enums\SortOrder;
use Jengo\Schema\Reflection\SchemaReflector;

final class FluentQueryAPI
{
    // Internal state tracking
    private array $params                = [];
    private array $whereConflicts        = [];
    private array $whereNotInConflicts   = [];
    private array $whereNotInParams      = [];
    private array $callbacks             = [];
    private bool $isOr                   = false;
    private array $select                = [];
    private array $derive                = [];
    private int $limit                   = 0;
    private int $page                    = 0;
    private ?string $sortColumn          = null;
    private SortOrder $sortDirection     = SortOrder::ASC;
    private ?string $searchTerm          = null;
    private array $searchFields          = [];
    private string $searchSide           = 'both';
    private ?bool $searchCaseInsensitive = null;
    private ?bool $logger                = null;
    private QueryMode $mode              = QueryMode::INLINE;
    private string $paginationGroup      = 'default';
    private array $allowedCapabilities   = ['pagination'];
    private ?string $entityClass         = null;

    public function __construct(
        private readonly string $schema,
    ) {
    }

    /**
     * Set the querymode to operate in
     */
    public function mode(QueryMode $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Specify the target entity class for hydration at runtime.
     */
    public function as(string $entityClass): self
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    /**
     * Apply the inline mode
     */
    public function inline(): self
    {
        return $this->mode(QueryMode::INLINE);
    }

    /**
     * Apply the open mode.
     * This mode hydrates options from the request which can be useful for building dynamic queries based on user
     * input without having to manually extract those and apply them to the query.
     * By default the FluentQueryAPI operates in INLINE mode where you have to manually
     * apply options using the provided methods.
     */
    public function open(array $allowedCapabilities = ['pagination']): self
    {
        $this->allowedCapabilities = $allowedCapabilities;

        $allowAll = in_array('*', $allowedCapabilities, true) || in_array('all', $allowedCapabilities, true);
        if ($allowAll || in_array('pagination', $allowedCapabilities, true)) {
            if ($this->limit === 0) {
                $this->limit = 15;
            }
        }

        return $this->mode(QueryMode::OPEN);
    }

    /**
     * Apply where clauses
     */
    public function where(string $column, mixed $value, bool $isOr = false): self
    {
        if (\array_key_exists($column, $this->params)) {
            $this->params[$column][] = [
                'value' => $value,
                'or'    => $isOr,
            ];
        } else {
            $this->params[$column] = [
                [
                    'value' => $value,
                    'or'    => $isOr,
                ],
            ];
        }

        return $this;
    }

    /**
     * Applies aan operation on the current where operation
     */
    public function orWhere(string $column, mixed $value): self
    {
        return $this->where($column, $value, true);
    }

    /**
     * Applies ow logic to an whenreNotIn operation
     */
    public function orWhereNotIn(string $column, array $value): self
    {
        return $this->whereNotIn($column, $value, true);
    }

    /**
     * Applies an or logic on an a whereNot operation
     */
    public function orWhereNot(string $column, mixed $value): self
    {
        return $this->whereNot($column, $value, true);
    }

    /**
     * Adds a where callback that can be used to apply custom where logic based on the name of the callback.
     */
    public function whereCallback(string $name, callable $callback): self
    {
        $this->callbacks[$name] = $callback;

        return $this;
    }

    /**
     * Applies or logic to all where operations.
     * Note that this is applied globally for all where clauses and cannot be applied to specific ones at the moment.
     */
    public function useOrLogic(bool $isOr = true): self
    {
        $this->isOr = $isOr;

        return $this;
    }

    /**
     * Select specific fields/columns only. Note that this only applies to the root schema by design.
     * You can use the schema definition for derived relationships to specify select fields for those.
     * Note: a feature for runtime selection of derived relationship fields is on the roadmap.
     *
     * @param list<array>|string $fields
     */
    public function select(array|string ...$fields): self
    {
        foreach ($fields as $field) {
            if (is_array($field)) {
                $this->select = [...$this->select, ...$field];
            } else {
                $this->select[] = $field;
            }
        }

        return $this;
    }

    /**
     * Derive relationsships attached to the schema.
     * Use dot syntax (for example - 'user.profile') for nested relatioships
     *
     * @param list<string> $paths
     */
    public function derive(string ...$paths): self
    {
        $this->derive = [...$this->derive, ...$paths];

        return $this;
    }

    /**
     * Add a limit to the pagination logic
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Add a page to the pagination logic.
     * Note that you must also set a limit for this to work or use the paginate() method which sets both automatically
     */
    public function page(int $page): self
    {
        $this->page = $page;

        return $this;
    }

    /**
     * Applies an order by statement
     */
    public function sort(string $column, SortOrder $direction = SortOrder::DESC): self
    {
        $this->sortColumn    = $column;
        $this->sortDirection = $direction;

        return $this;
    }

    /**
     * Perform a search operation based on the schema's searchable fields
     *
     * @param mixed $term
     */
    public function search(?string $term, array $fields = [], string $side = 'both', ?bool $caseInsensitive = null): self
    {
        $this->searchTerm            = $term;
        $this->searchFields          = $fields;
        $this->searchSide            = $side;
        $this->searchCaseInsensitive = $caseInsensitive;

        return $this;
    }

    /**
     * Turns on debug mode which can be used to get access to aliases used in the system
     */
    public function debug(bool $enable = true): self
    {
        $this->logger = $enable;

        return $this;
    }

    /**
     * Adds a where clause with today's date
     */
    public function whereToday(string $column = 'created_at'): self
    {
        return $this->where($column, date('Y-m-d'));
    }

    /**
     * Adds a where caluse with time past since the provided $timestring
     */
    public function whereSince(string $timeString, string $column = 'created_at'): self
    {
        return $this->where("{$column} >=", date('Y-m-d H:i:s', strtotime($timeString)));
    }

    /**
     * Apply where Less THan or Equal to comparison
     */
    public function whereLTe(string $column, mixed $value): self
    {
        return $this->where("{$column} <=", $value);
    }

    /**
     * Apply a where not claues
     */
    public function whereNot(string $column, mixed $value, bool $isOr = false): self
    {
        return $this->where("{$column} !=", $value, $isOr);
    }

    /**
     * Apply callbacks only if $condition is true
     */
    public function when(mixed $condition, callable $callback): self
    {
        if ($condition) {
            $callback($this, $condition);
        }

        return $this;
    }

    /**
     * Applies a Greater Than comparision
     */
    public function whereGt(string $column, mixed $value): self
    {
        return $this->where("{$column} >", $value);
    }

    /**
     * Applies a Less Than comparison
     */
    public function whereLt(string $column, mixed $value): self
    {
        return $this->where("{$column} <", $value);
    }

    /**
     * Alias for where with stirct array type required
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->where($column, $values);
    }

    /**
     * Alias for where with stirct array type required and or flag raised
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->where($column, $values, true);
    }

    /**
     * Applies whereNotIn logic
     */
    public function whereNotIn(string $column, array $values, bool $isOr = false): self
    {
        if (\array_key_exists($column, $this->whereNotInParams)) {
            $this->whereNotInParams[$column][] = ['value' => $values, 'or' => $isOr];
        } else {
            $this->whereNotInParams[$column] = [
                [
                    'value' => $values,
                    'or'    => $isOr,
                ],
            ];
        }

        return $this;
    }

    /**
     * Alias for debug
     */
    public function log(bool $enable = true): self
    {
        return $this->debug($enable);
    }

    /**
     * Alias for mode method
     */
    public function withQueryMode(QueryMode $mode): self
    {
        return $this->mode($mode);
    }

    /**
     * Alias for mode method with QueryMode::INLINE
     */
    public function inlineMode(): self
    {
        return $this->mode(QueryMode::INLINE);
    }

    /**
     * Alias for mode method with QueryMode::OPEN
     */
    public function openMode(array $allowedCapabilities = ['pagination']): self
    {
        return $this->open($allowedCapabilities);
    }

    /**
     * Assign a where clause for null column
     */
    public function whereNull(string $column): self
    {
        return $this->where($column, null);
    }

    /**
     * Applies 'DESC' sort order
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->sort($column, SortOrder::DESC);
    }

    /**
     * Applies 'ASC' sort order
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->sort($column, SortOrder::ASC);
    }

    /**
     * Applies page and limit automatically
     */
    public function paginate(int $page, int $perPage = 15, string $group = 'default'): self
    {
        $this->paginationGroup = $group;

        return $this->page($page)->limit($perPage);
    }

    /**
     * Configure a specific pagination group.
     */
    public function paginationGroup(string $group): self
    {
        $this->paginationGroup = $group;

        return $this;
    }

    /**
     * Assign a where clause for not null column
     */
    public function whereNotNull(string $column): self
    {
        return $this->whereNot($column, null);
    }

    /**
     * Alias for derive
     *
     * @param list<string> $paths
     */
    public function with(string ...$paths): self
    {
        return $this->derive(...$paths);
    }

    /**
     * Clone the current query state to branch off a different execution
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Returns only the count of the results
     */
    public function count(): int
    {
        // This assumes your Query::run or QueryOptions handles a 'count' flag
        // Or you can modify the execute logic to handle a count mode
        return $this->execute(first: false)->count;
    }

    /**
     * Check if any records exist matching the criteria
     */
    public function exists(): bool
    {
        return $this->limit(1)->first()->data !== null;
    }

    /**
     * Execution Terminators
     */
    public function first(bool $value = false): ?object
    {
        $result = $this->execute(first: true);

        if ($value) {
            return $result->data;
        }

        return $result;
    }

    public function get(bool $value = false): array|object|null
    {
        $result = $this->execute(first: false);

        if ($value) {
            return $result->data;
        }

        return $result;
    }

    /**
     * Find a record by its primary key.
     *
     * @param mixed $id The primary key value
     */
    public function find(mixed $id): array|object|null
    {
        $metadata       = SchemaReflector::reflect($this->schema);
        $primaryKeyName = $metadata->primaryKey->name;

        return $this->where($primaryKeyName, $id)->first(true);
    }

    public static function dd(): void
    {
        if (ENVIRONMENT === 'production') {
            return;
        }

        $logs = QueryLogger::all();
        var_dump(json_encode($logs, JSON_PRETTY_PRINT));

        exit();
    }

    /**
     * Internal: Compiles properties into QueryOptions and runs Query::run
     */
    private function execute(bool $first): QueryResult
    {
        $options = new QueryOptions(
            params: new ParamOptions(
                params: $this->params,
                callbacks: $this->callbacks,
                whereNotInParams: $this->whereNotInParams,
                isOr: $this->isOr,
            ),
            select: new SelectOptions(select: $this->select),
            pagination: new PaginationOptions(
                limit: $this->limit,
                page: $this->page,
                group: $this->paginationGroup,
            ),
            derive: $this->derive,
            sort: new SortOptions(
                column: $this->sortColumn,
                direction: $this->sortDirection,
            ),
            search: new SearchOptions(
                value: $this->searchTerm,
                fields: $this->searchFields,
                side: $this->searchSide,
                caseInsensitive: $this->searchCaseInsensitive,
            ),
            logger: $this->logger,
            first: $first,
            allowedCapabilities: $this->allowedCapabilities,
            entityClass: $this->entityClass,
        );

        return Query::run($this->schema, $options, $this->mode);
    }
}
