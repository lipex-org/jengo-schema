<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ResultInterface;
use Config\Database;
use Jengo\Schema\Debug\QueryLogger;
use Jengo\Schema\Graph\Node;
use Jengo\Schema\Metadata\FieldMetadata;
use Jengo\Schema\Query\DTO\BuilderResult;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\DTO\WhereValue;
use Jengo\Schema\Support\AliasGenerator;
use Jengo\Schema\Support\QueryUtils;
use RuntimeException;

final class QueryBuilder
{
    private ?BaseBuilder $builder = null;
    private ?QueryOptions $options = null;
    public static function build(Node $rootNode, QueryOptions $options, QueryPlan $plan): self
    {
        $baseTable = QueryUtils::resolveTableFromSchema($rootNode->schema);
        $rootAlias = AliasGenerator::for($rootNode);
        $db = Database::connect();

        // init builder
        $builder = $db->table("$baseTable AS $rootAlias");

        // build query
        self::applyRootSelect($builder, $plan, $rootAlias);

        self::applyWhere($builder, $options->params, $rootAlias);

        self::applySort($builder, $options->sort, $rootAlias);

        self::applySearch($builder, $rootNode, $options->search);

        self::applyJoins($builder, $rootNode, $plan);

        // any other feature related to query building can be done here
        $self = new self();

        $self->builder = $builder;
        $self->options = $options;

        return $self;
    }

    public function execute(): BuilderResult
    {

        $builder = $this->builder;
        $options = $this->options;

        if (!$builder || !$options) {
            throw new RuntimeException('You need to build the query before executing');
        }

        if ($options->logger) {
            QueryLogger::enable();
        }

        $total = $builder->countAllResults(false);
        $result = self::applyPagination($builder, $options->pagination);
        $resultArray = $result->getResultArray();

        QueryLogger::record();

        return new BuilderResult(
            total: $total,
            rows: $resultArray,
        );
    }

    private static function applyRootSelect(BaseBuilder $builder, QueryPlan $plan, string $rootAlias): void
    {
        $baseSelects = ["$rootAlias.*"];

        if ($plan->selects[$rootAlias] ?? []) {
            $baseSelects = $plan->selects[$rootAlias];
        }

        $builder->select($baseSelects);
    }

    private static function applyWhere(BaseBuilder $builder, ParamOptions $paramOptions, string $rootAlias): void
    {
        foreach ($paramOptions->params as $key => $value) {
            self::applyWhereOnWhereValue($builder, $key, $value, $rootAlias, $paramOptions->isOr, false);
        }

        foreach ($paramOptions->whereNotInParams as $key => $value) {
            self::applyWhereOnWhereValue($builder, $key, $value, $rootAlias, $paramOptions->isOr, true);
        }
    }

    private static function applySearch(BaseBuilder $builder, Node $node, ?string $search, ): void
    {
        if (!$search)
            return;

        $current = $node;

        foreach ($node->children as $child) {
            $fields = array_filter(array_map(function (FieldMetadata $f) {
                if (!$f->searchable) {
                    return null;
                }

                return $f->name;
            }, $current->schema->fields));

            $alias = AliasGenerator::for($current);

            $builder->groupStart();
            foreach ($fields as $field) {
                $builder->orLike(sprintf('%s.%s', $alias, $field), $search, 'both', null, true);
            }

            $builder->groupEnd();

            $current = $child;
        }
    }

    private static function applySort(BaseBuilder $builder, SortOptions $sort, string $rootAlias): void
    {
        if ($sort->column) {
            $builder->orderBy("$rootAlias.{$sort->column}", $sort->direction->value);
        }
    }

    private static function applyJoins(BaseBuilder $builder, Node $node, QueryPlan $plan): void
    {
        foreach ($node->children as $child) {
            $childAlias = AliasGenerator::for($child);

            $joins = $plan->joins[$childAlias] ?? null;

            if (!$joins) {
                continue;
            }

            $builder->join(...$joins);
            $builder->select($plan->selects[$childAlias]);

            self::applyJoins(builder: $builder, node: $child, plan: $plan);
        }
    }

    private static function applyPagination(BaseBuilder $builder, PaginationOptions $pagination): ResultInterface
    {
        $limit = $pagination->limit;
        $page = $pagination->page;

        if ($limit === null || $limit === 0) {
            return $builder->get();
        }

        $offset = ($page - 1) * $limit;

        return $builder->get($limit, $offset);
    }

    private static function validateWhereValue(array $value)
    {
        $undefined = '&&&&&&&____UNDEFINED___&&&&&&&&___VALUE:NOT:DEFINED___AND:CANNOT:BE:CONFUSED:WITH:ANTOHER:VALUE_';
        $or = array_key_exists('or', $value) ? $value['or'] : $undefined;
        $val = array_key_exists('value', $value) ? $value['value'] : $undefined;

        if ($or === $undefined || $val === $undefined || !is_bool($or)) {
            throw new RuntimeException('Invalid where clause value. Expected keys: "value" and "or". Provided value ' . json_encode($value) . ' Ensure that the value is an associative array with these keys or else provide a normal array/string value for the where clause.');
        }

        return new WhereValue(
            value: $val,
            or: $or
        );
    }

    private static function applyWhereOnWhereValue(BaseBuilder $builder, mixed $key, mixed $arr, string $rootAlias, bool $globalIsOr, bool $isWhereIn = false): void
    {
        if ($isWhereIn) {
            if (!is_array($arr)) {

                return;
            }

            foreach ($arr as $value) {
                if (self::isAssociative($value)) {
                    $whereVal = self::validateWhereValue($value);
                    $val = $whereVal->value;
                    $isOr = $whereVal->or;

                    if (is_array($val)) {
                        if (self::isAssociative($val)) {
                            self::applyWhereOnWhereValue($builder, $key, $val, $rootAlias, $isOr, $isWhereIn);
                            continue;
                        }

                        if (!$isOr) {
                            $builder->whereNotIn("$rootAlias.$key", $val);
                        } else {
                            $builder->orWhereNotIn("$rootAlias.$key", $val);
                        }
                        continue;
                    }

                    if (!$isOr) {
                        $builder->where("$rootAlias.$key !=", $val);
                    } else {
                        $builder->orWhere("$rootAlias.$key !=", $val);
                    }
                    continue;
                }

                if (!$globalIsOr) {
                    $builder->whereNotIn("$rootAlias.$key", $value);
                } else {
                    $builder->orWhereNotIn("$rootAlias.$key", $value);
                }
            }
            return;
        }

        if (is_array($arr)) {
            foreach ($arr as $value) {
                if (self::isAssociative($value)) {
                    $whereVal = self::validateWhereValue($value);
                    $val = $whereVal->value;
                    $isOr = $whereVal->or;

                    if (is_array($val)) {
                        if (self::isAssociative($val)) {
                            self::applyWhereOnWhereValue($builder, $key, $val, $rootAlias, $isOr, $isWhereIn);
                            continue;
                        }

                        if (!$isOr) {
                            $builder->whereIn("$rootAlias.$key", $val);
                        } else {
                            $builder->orWhereIn("$rootAlias.$key", $val);
                        }
                        continue;
                    }

                    if (!$isOr) {
                        $builder->where("$rootAlias.$key", $val);
                    } else {
                        $builder->orWhere("$rootAlias.$key", $val);
                    }
                } else {
                    // non-associative array means it's a whereIn clause                    
                    if (!$globalIsOr) {
                        $builder->whereIn("$rootAlias.$key", $value);
                    } else {
                        $builder->orWhereIn("$rootAlias.$key", $value);
                    }
                }
            }
            return;
        }

        if (!$globalIsOr) {
            $builder->where("$rootAlias.$key", $arr);
        } else {
            $builder->orWhere("$rootAlias.$key", $arr);
        }
    }

    private static function isAssociative(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
