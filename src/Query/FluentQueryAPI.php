<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

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
    private array $callbacks = [];
    private bool $isOr = false;
    private array $select = [];
    private array $derive = [];
    private int $limit = 0;
    private int $page = 0;
    private string $sortColumn = 'created_at';
    private SortOrder $sortDirection = SortOrder::ASC;
    private ?string $search = null;
    private ?bool $logger = null;
    private QueryMode $mode = QueryMode::INLINE;

    public function __construct(
        private readonly string $schema
    ) {
    }

    /**
     * Set the query mode
     */
    public function mode(QueryMode $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function inline(): self
    {
        return $this->mode(QueryMode::INLINE);

    }
    public function open(): self
    {
        return $this->mode(QueryMode::INLINE);
    }

    /**
     * Filtering & Params
     */
    public function where(string $column, mixed $value): self
    {
        $this->params[$column] = $value;
        return $this;
    }

    public function whereCallback(string $name, callable $callback): self
    {
        $this->callbacks[$name] = $callback;
        return $this;
    }

    public function useOrLogic(bool $isOr = true): self
    {
        $this->isOr = $isOr;
        return $this;
    }

    /**
     * Selection & Projection
     */
    public function select(string ...$fields): self
    {
        $this->select = array_merge($this->select, $fields);
        return $this;
    }

    public function derive(string ...$paths): self
    {
        $this->derive = array_merge($this->derive, $paths);
        return $this;
    }

    /**
     * Pagination & Sorting
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function page(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function sort(string $column, SortOrder $direction = SortOrder::ASC): self
    {
        $this->sortColumn = $column;
        $this->sortDirection = $direction;
        return $this;
    }

    /**
     * Misc Options
     */
    public function search(?string $term): self
    {
        $this->search = $term;
        return $this;
    }

    public function debug(bool $enable = true): self
    {
        $this->logger = $enable;
        return $this;
    }

    /**
     * Execution Terminators
     */
    public function first(): mixed
    {
        $result = $this->execute(first: true);
        return $result;
    }

    public function get(): QueryResult
    {
        return $this->execute(first: false);
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