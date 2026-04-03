<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use Jengo\Schema\Debug\QueryLogger;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\DTO\SelectOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Query\Enums\SortOrder;

final class FluentQueryAPI
{
    // Internal state tracking
    private array $params = [];
    private array $whereConflicts = [];
    private array $whereNotInConflicts = [];
    private array $whereNotInParams = [];
    private array $callbacks = [];
    private bool $isOr = false;
    private array $select = [];
    private array $derive = [];
    private int $limit = 0;
    private int $page = 0;
    private ?string $sortColumn = null;
    private SortOrder $sortDirection = SortOrder::ASC;
    private ?string $search = null;
    private ?bool $logger = null;
    private QueryMode $mode = QueryMode::INLINE;

    public function __construct(
        private readonly string $schema
    ) {
    }

    /**
     * Set the querymode to operate in
     * @param QueryMode $mode
     * @return FluentQueryAPI
     */
    public function mode(QueryMode $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Apply the inline mode
     * @return FluentQueryAPI
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
     * @return FluentQueryAPI
     */
    public function open(): self
    {
        return $this->mode(QueryMode::INLINE);
    }

    /**
     * Apply where clauses
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function where(string $column, mixed $value, bool $isOr = false): self
    {
        if (\array_key_exists($column, $this->params)) {
            $this->params[$column][] = [
                'value' => $value,
                'or' => $isOr
            ];
        } else {
            $this->params[$column] = [
                [
                    'value' => $value,
                    'or' => $isOr
                ]
            ];
        }

        return $this;
    }

    /**
     * Applies aan operation on the current where operation
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function orWhere(string $column, mixed $value): self
    {
        return $this->where($column, $value, true);
    }

    /**
     * Applies ow logic to an whenreNotIn operation
     * @param string $column
     * @param array $value
     * @return FluentQueryAPI
     */
    public function orWhereNotIn(string $column, array $value): self
    {
        return $this->whereNotIn($column, $value, true);
    }

    /**
     * Applies an or logic on an a whereNot operation
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function orWhereNot(string $column, mixed $value): self
    {
        return $this->whereNot($column, $value, true);
    }

    /**
     * Adds a where callback that can be used to apply custom where logic based on the name of the callback.
     * @param string $name
     * @param callable $callback
     * @return FluentQueryAPI
     */
    public function whereCallback(string $name, callable $callback): self
    {
        $this->callbacks[$name] = $callback;
        return $this;
    }

    /**
     * Applies or logic to all where operations. 
     * Note that this is applied globally for all where clauses and cannot be applied to specific ones at the moment.
     * @param bool $isOr
     * @return FluentQueryAPI
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
     * @param string|array[] $fields
     * @return FluentQueryAPI
     */
    public function select(string|array ...$fields): self
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
     * @param string[] $paths
     * @return FluentQueryAPI
     */
    public function derive(string ...$paths): self
    {
        $this->derive = [...$this->derive, ...$paths];
        return $this;
    }

    /**
     * Add a limit to the pagination logic
     * @param int $limit
     * @return FluentQueryAPI
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Add a page to the pagination logic. 
     * Note that you must also set a limit for this to work or use the paginate() method which sets both automatically
     * @param int $page
     * @return FluentQueryAPI
     */
    public function page(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    /**
     * Applies an order by statement
     * @param string $column
     * @param SortOrder $direction
     * @return FluentQueryAPI
     */
    public function sort(string $column, SortOrder $direction = SortOrder::DESC): self
    {
        $this->sortColumn = $column;
        $this->sortDirection = $direction;
        return $this;
    }

    /**
     * Perform a search operation based on the schema's searchable fields
     * @param mixed $term
     * @return FluentQueryAPI
     */
    public function search(?string $term): self
    {
        $this->search = $term;
        return $this;
    }

    /**
     * Turns on debug mode which can be used to get access to aliases used in the system 
     * @param bool $enable
     * @return FluentQueryAPI
     */
    public function debug(bool $enable = true): self
    {
        $this->logger = $enable;
        return $this;
    }



    /**
     * Adds a where clause with today's date
     * @param string $column
     * @return FluentQueryAPI
     */
    public function whereToday(string $column = 'created_at'): self
    {
        return $this->where($column, date('Y-m-d'));
    }

    /**
     * Adds a where caluse with time past since the provided $timestring
     * @param string $timeString
     * @param string $column
     * @return FluentQueryAPI
     */
    public function whereSince(string $timeString, string $column = 'created_at'): self
    {
        return $this->where("$column >=", date('Y-m-d H:i:s', strtotime($timeString)));
    }

    /**
     * Apply where Less THan or Equal to comparison
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function whereLTe(string $column, mixed $value): self
    {
        return $this->where("$column <=", $value);
    }

    /**
     * Apply a where not claues
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function whereNot(string $column, mixed $value, bool $isOr = false): self
    {
        return $this->where("$column !=", $value, $isOr);
    }

    /**
     * Apply callbacks only if $condition is true
     * @param mixed $condition
     * @param callable $callback
     * @return FluentQueryAPI
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
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function whereGt(string $column, mixed $value): self
    {
        return $this->where("$column >", $value);
    }

    /**
     * Applies a Less Than comparison
     * @param string $column
     * @param mixed $value
     * @return FluentQueryAPI
     */
    public function whereLt(string $column, mixed $value): self
    {
        return $this->where("$column <", $value);
    }

    /**
     * Alias for where with stirct array type required
     * @param string $column
     * @param array $values
     * @return FluentQueryAPI
     */
    public function whereIn(string $column, array $values): self
    {
        return $this->where($column, $values);
    }

    /**
     * Applies whereNotIn logic
     * @param string $column
     * @param array $values
     * @param bool $isOr
     * @return FluentQueryAPI
     */
    public function whereNotIn(string $column, array $values, bool $isOr = false): self
    {
        if (\array_key_exists($column, $this->whereNotInParams)) {
            $this->whereNotInParams[$column][] = ['value' => $values, 'or' => $isOr];
        } else {
            $this->whereNotInParams[$column] = [
                [
                    'value' => $values,
                    'or' => $isOr
                ]
            ];
        }
        return $this;
    }

    /**
     * Alias for debug
     * @param bool $enable
     * @return FluentQueryAPI
     */
    public function log(bool $enable = true): self
    {
        return $this->debug($enable);
    }

    /**
     * Alias for mode method
     * @param QueryMode $mode
     * @return FluentQueryAPI
     */
    public function withQueryMode(QueryMode $mode): self
    {
        return $this->mode($mode);
    }

    /**
     * Alias for mode method with QueryMode::INLINE
     * @return FluentQueryAPI
     */
    public function inlineMode(): self
    {
        return $this->mode(QueryMode::INLINE);
    }

    /**
     * Alias for mode method with QueryMode::OPEN
     * @return FluentQueryAPI
     */
    public function openMode(): self
    {
        return $this->mode(QueryMode::OPEN);
    }

    /**
     * Assign a where clause for null column
     * @param string $column
     * @return FluentQueryAPI
     */
    public function whereNull(string $column): self
    {
        return $this->where($column, null);
    }

    /**
     * Applies 'DESC' sort order
     * @param string $column
     * @return FluentQueryAPI
     */
    public function latest(string $column = 'created_at'): self
    {
        return $this->sort($column, SortOrder::DESC);
    }

    /**
     * Applies 'ASC' sort order
     * @param string $column
     * @return FluentQueryAPI
     */
    public function oldest(string $column = 'created_at'): self
    {
        return $this->sort($column, SortOrder::ASC);
    }

    /**
     * Applies page and limit automatically
     * @param int $page
     * @param int $perPage
     * @return FluentQueryAPI
     */
    public function paginate(int $page, int $perPage = 15): self
    {
        return $this->page($page)->limit($perPage);
    }

    /**
     * Assign a where clause for not null column
     * @param string $column
     * @return FluentQueryAPI
     */
    public function whereNotNull(string $column): self
    {
        return $this->whereNot($column, null);
    }

    /**
     * Alias for derive
     * @param string[] $paths
     * @return FluentQueryAPI
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
        return $this->limit(1)->first() !== null;
    }

    /**
     * Execution Terminators
     */
    public function first(bool $value = false): object|null
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

    public static function dd(): void
    {
        if (ENVIRONMENT === 'production')
            return;

        $logs = QueryLogger::all();
        var_dump(json_encode($logs, JSON_PRETTY_PRINT));
        die();
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
                isOr: $this->isOr
            ),
            select: new SelectOptions(select: $this->select),
            pagination: new PaginationOptions(
                limit: $this->limit,
                page: $this->page
            ),
            derive: $this->derive,
            sort: new SortOptions(
                column: $this->sortColumn,
                direction: $this->sortDirection
            ),
            search: $this->search,
            logger: $this->logger,
            first: $first
        );

        return Query::run($this->schema, $options, $this->mode);
    }
}