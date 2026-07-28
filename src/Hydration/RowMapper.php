<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use Jengo\Schema\Graph\Node;
use Jengo\Schema\Support\AliasGenerator;

final class RowMapper
{
    /**
     * Map a flat row to a per-node structure based on aliases
     *
     * Example:
     *  [
     *      't_0_root__id' => 1,
     *      't_1_91e4ab__id' => 10,
     *  ]
     *  ->
     *  [
     *      't_0_root' => ['id'=>1],
     *      't_1_91e4ab' => ['id'=>10]
     *  ]
     */
    public static function map(Node $rootNode, array $row): array
    {
        $mapped = [];

        self::mapNode($rootNode, $row, $mapped);

        return $mapped;
    }

    private static function mapNode(Node $node, array $row, array &$mapped): void
    {
        $alias = AliasGenerator::for($node);

        $mapped[$alias] = [];

        foreach ($node->schema->fields as $field) {
            $col                          = "{$alias}__{$field->name}";
            $mapped[$alias][$field->name] = $row[$col] ?? null;
        }

        foreach ($node->children as $child) {
            self::mapNode($child, $row, $mapped);
        }
    }
}
