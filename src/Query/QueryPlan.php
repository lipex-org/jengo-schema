<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use Jengo\Schema\Graph\Node;
use Jengo\Schema\Graph\RelationshipGraph;
use Jengo\Schema\Metadata\FieldMetadata;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Support\AliasGenerator;
use Jengo\Schema\Support\ArrayUtils;
use Jengo\Schema\Support\QueryUtils;
use RuntimeException;

final class QueryPlan
{
    public Node $root;

    public QueryOptions $options;

    /**
     * @var array<string,string> mapping node path => alias
     */
    public array $aliases = [];

    /**
     * @var array<string,array> select fields by alias
     */
    public array $selects = [];

    /**
     * @var array<string,array> select fields by alias as row db columns
     */
    public array $selectsRaw = [];

    /**
     * @var array joins
     */
    public array $joins = [];

    /**
     * Where params
     * @var array
     */
    public array $where = [];

    /**
     * Where params
     * @var array
     */
    public string $whereMode = 'and';

    /**
     * Sorting info
     * @var SortOptions
     */
    public SortOptions $sort;

    /**
     * Limit of elements
     * @var int
     */
    public int $limit;

    /**
     * Offset to start from
     * @var int
     */
    public int $offset;

    /**
     * Current page
     * @var int
     */
    public int $page;

    public function __construct(Node $root, QueryOptions $options)
    {
        $this->root = $root;
        $this->options = $options;

        $this->init();
        $this->generatePlan($root, [], true);
    }

    private function init(): void
    {
        $this->page = $this->options->pagination->page;
        $this->limit = $this->options->pagination->limit;
        $this->offset = ($this->page - 1) * $this->limit;
        $this->sort = $this->options->sort;
    }

    public static function fromGraph(RelationshipGraph $graph, QueryOptions $options)
    {
        return new self($graph->root, $options);
    }

    private function generatePlan(Node $node, array $path = [], bool $isRoot = false): void
    {
        $path[] = $node->edge?->relation->name ?? 'root';

        $alias = AliasGenerator::for($node);
        $this->aliases[implode('.', $path)] = $alias;

        if ($isRoot) {
            $this->attachRootSelects($alias);
        } else {
            $this->attachSelects($node, $alias);
        }

        $this->attachJoins($node, $alias);

        if ($isRoot) {
            $this->attachWhere();
        }
        // Recurse into children
        foreach ($node->children as $child) {
            $this->generatePlan($child, $path, false);
        }
    }

    private function attachRootSelects(string $alias): void
    {
        $selects = $this->options->select->select;

        $availableFields = array_column([
            ...ArrayUtils::toArray($this->root->schema->fields),
            ArrayUtils::toArray($this->root->schema->primaryKey)
        ], 'name');

        if ($selects) {
            foreach ($selects as $field) {
                if (!in_array($field, $availableFields)) {
                    throw new RuntimeException("Select field $field must be present in {$this->root->schema->schemaClass}");
                }

                $this->selects[$alias][] = self::getSelectStr($field, $alias);
                $this->selectsRaw[$alias][] = $field;
            }
        } else {
            $this->attachSelects($this->root, $alias);
        }
    }

    private function attachSelects(Node $node, string $alias): void
    {
        $fields = [];
        $rawFields = [];

        // check if selects for the relation exists
        $relationSelects = $node->edge?->relation->select;
        $pk = $node->schema->primaryKey;

        if (!empty($relationSelects)) {
            $primaryKeyAttached = false;
            $rawFieldNames = [
                ...array_column(ArrayUtils::toArray($node->schema->fields), 'name'),
                $pk->name
            ];

            foreach ($relationSelects as $select) {
                // check if valid schema field
                if (!in_array($select, $rawFieldNames)) {
                    throw new RuntimeException("Select field $select must be present in {$node->schema->schemaClass}");
                }

                $fields[] = self::getSelectStr($select, $alias);
                $rawFields[] = $select;

                if ($select === $pk->name) {
                    $primaryKeyAttached = true;
                }
            }

            if (!$primaryKeyAttached) {
                self::attachPK($pk, $alias, $fields, $rawFields);
            }

            // attach and return
            $this->selects[$alias] = $fields;
            $this->selectsRaw[$alias] = $rawFields;
            return;
        }

        foreach ($node->schema->fields as $field) {
            if ($field->derived)
                continue;

            $fields[] = self::getSelectStr($field->name, $alias);
            $rawFields[] = $field->name;
        }

        // include primary key
        self::attachPK($pk, $alias, $fields, $rawFields);

        $this->selects[$alias] = $fields;
        $this->selectsRaw[$alias] = $rawFields;
    }

    private function attachWhere(): void
    {
        $this->where = $this->options->params->params;
        $this->whereMode = $this->options->params->isOr ? 'or' : 'and';
    }

    private static function getSelectStr(string $field, string $alias): string
    {
        return "{$alias}.{$field} AS {$alias}__{$field}";
    }

    private static function attachPK(FieldMetadata $pk, string $alias, array &$fields, array &$rawFields): void
    {
        $fields[] = self::getSelectStr($pk->name, $alias);
        $rawFields[] = $pk->name;
    }

    private function attachJoins(Node $node, string $alias): void
    {
        if (!$node->parent) { // checks for root node
            return;
        }

        $fk = $node->edge->relation->fromField;
        $pk = $node->edge->relation->toField ?? $node->schema->primaryKey->name;

        $parentAlias = AliasGenerator::for($node->parent);

        $this->joins[$alias] = [
            'table' => QueryUtils::resolveTableFromSchema($node->schema) . " $alias",
            'cond' => "{$parentAlias}.{$fk} = {$alias}.{$pk}",
            'type' => 'left'
        ];
    }
}
