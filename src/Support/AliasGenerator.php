<?php

declare(strict_types=1);

namespace Jengo\Schema\Support;

use Jengo\Schema\Graph\Node;

final class AliasGenerator
{
    /**
     * Generate alias for a node based on its path
     */
    public static function for(Node $node): string
    {
        if ($node->isRoot()) {
            return 't_0_root';
        }

        $path  = self::path($node);
        $depth = count($path) - 1;

        return sprintf(
            't_%d_%s',
            $depth,
            self::hash($path),
        );
    }

    /**
     * Return array of path segments
     *
     * Example: ['round', 'members', 'user']
     */
    private static function path(Node $node): array
    {
        $segments = [];

        while ($node) {
            if ($node->edge) {
                $segments[] = $node->edge->relation->name;
            } else {
                $segments[] = 'root';
            }

            $node = $node->parent;
        }

        return array_reverse($segments);
    }

    /**
     * Short, deterministic hash of path
     */
    private static function hash(array $path): string
    {
        return substr(
            sha1(implode('.', $path)),
            0,
            6,
        );
    }
}
