<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use CodeIgniter\Entity\Entity;
use Jengo\Schema\Debug\QueryLogger;
use Jengo\Schema\Graph\Node;
use Jengo\Schema\Query\DTO\BuilderResult;
use Jengo\Schema\Query\DTO\PaginationData;
use Jengo\Schema\Query\DTO\PaginationLink;
use Jengo\Schema\Query\DTO\PaginationLinksData;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\QueryPlan;
use Jengo\Schema\Support\AliasGenerator;
use Jengo\Schema\Support\PaginationUtils;
use RuntimeException;

final class Hydrator
{
    private array $data = [];
    private null|array|Entity $resolvedData = null;
    private ?int $total = null;

    private QueryOptions $options;
    private QueryPlan $plan;
    private Node $node;

    private static Hydrator $self;

    private $grouped = [];

    private array $rows = [];
    /**
     * Hydrate flat DB rows into nested structure
     */
    public static function hydrate(Node $rootNode, BuilderResult $builderResult, QueryOptions $options, QueryPlan $plan): QueryResult
    {
        $self = new self();

        $self->total = $builderResult->total;
        $self->options = $options ?? new QueryOptions();
        $self->plan = $plan;
        $self->node = $rootNode;
        $self->rows = $builderResult->rows;

        self::$self = $self;

        $self->data = self::hydrateNode($rootNode, $plan);

        $pl = [
            'where' => $plan->where,
            'aliases' => $plan->aliases,
        ];


        QueryLogger::add('plan', $pl);
        QueryLogger::add('builderResult', $builderResult);
        QueryLogger::add('hydratedData', $self->data);

        return $self->finish();
    }

    /**
     * Returns the query result from the request
     * @return QueryResult
     */
    private function finish(): QueryResult
    {
        return new QueryResult(
            data: $this->resolveData(),
            count: $this->resolveCount(),
            pagination: $this->resolvePagination(),
        );
    }

    /**
     * Resolves total count for the query(this is for all elements in db being paginated)
     * @return int
     */
    private function resolveTotal(): int
    {
        if ($this->total !== null) {
            return $this->total;
        }

        return count($this->data);
    }

    /**
     * Resolves data based on whether request is for first element
     * @return array|object|null
     */
    private function resolveData(): array|object|null
    {
        if ($this->resolvedData) {
            return $this->resolvedData;
        }

        $result = [];
        foreach ($this->data as $d) {
            if (!is_array($d)) {
                continue;
            }
            $result[] = EntityFactory::make($this->node, $d);
        }

        $data = $this->options->first ? $result[0] ?? null : $result;
        $this->resolvedData = $data;

        return $data;
    }

    /**
     * Resolves count for the elements returned
     * @return int
     */
    private function resolveCount(): int
    {
        $data = $this->resolveData();

        if (!$data) {
            return 0;
        }

        if (is_array($data) && !$this->options->first) {
            return count($data);
        }

        return 1;
    }

    /**
     * Resolves pagination for the query
     * @return PaginationData
     */
    private function resolvePagination(): PaginationData
    {
        $page = $this->options->pagination->page;
        $limit = $this->options->pagination->limit;
        $total = $this->resolveTotal();

        $links = PaginationUtils::generateLinks(
            data: new PaginationLinksData(
                page: $page,
                limit: $limit,
                total: $total
            ),
            number: $this->options->pagination->linksMax,
            group: $this->options->pagination->group,
            withQuery: $this->options->pagination->withQuery
        );

        return new PaginationData(
            page: $page,
            limit: $limit,
            total: $total,
            links: $links,
        );
    }

    /**
     * Recursive hydration per node
     */
    private static function hydrateNode(Node $node, QueryPlan $plan, ?array $rows = null): array
    {
        $alias = AliasGenerator::for($node);
        $pk = $node->schema->primaryKey;
        $pkCol = "{$alias}__{$pk->name}";
        $rows = $rows ?? self::$self->rows;

        $grouped = [];

        foreach ($rows as $row) {
            $key = $row[$pkCol] ?? null;

            if ($key === null) {
                continue;
            }

            $grouped[$key][] = $row;
        }

        $result = [];

        foreach ($grouped as $groupRows) {
            $record = [];

            $record[$pk->name] = $groupRows[0][$pkCol];

            foreach ($node->schema->fields as $field) {
                $selects = $plan->selectsRaw[$alias] ?? [];

                if (!in_array($field->name, $selects, true)) {
                    continue;
                }

                $col = "{$alias}__{$field->name}";
                $record[$field->name] = $groupRows[0][$col] ?? null;
            }

            foreach ($node->children as $child) {
                $childData = self::hydrateNode($child, $plan, $groupRows);

                $record[$child->edge->relation->name] = $child->isMany()
                    ? array_values($childData)
                    : ($childData[0] ?? null);
            }

            ComputedValueResolver::resolve($node, $record);

            $result[] = $record;
        }

        return $result;
    }
}
