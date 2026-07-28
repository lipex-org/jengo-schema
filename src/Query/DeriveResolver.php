<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

use Jengo\Schema\Graph\Node;

final class DeriveResolver
{
    /**
     * Map derived fields into the final hydrated array
     *
     * @param array $rows raw db rows
     */
    public static function resolve(Node $node, array $rows): array
    {
        $alias = $node->edge ? $node->edge->relation->name : 'root';

        $results = [];

        foreach ($rows as $row) {
            $record = [];

            foreach ($node->schema->fields as $field) {
                $col                  = "{$alias}__{$field->name}";
                $record[$field->name] = $row[$col] ?? null;
            }

            // recurse children
            foreach ($node->children as $child) {
                $record[$child->edge->relation->name] = self::resolve($child, $rows);
            }

            $results[] = $record;
        }

        return $results;
    }
}
