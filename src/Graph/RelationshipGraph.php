<?php

declare(strict_types=1);

namespace Jengo\Schema\Graph;

use Jengo\Schema\Metadata\SchemaMetadata;
use Jengo\Schema\Reflection\SchemaReflector;
use RuntimeException;

final class RelationshipGraph
{
    public function __construct(
        public Node $root,
    ) {
    }

    public static function build(
        SchemaMetadata $rootSchema,
        array $derivePaths,
    ): self {
        $root = new Node(schema: $rootSchema);

        foreach ($derivePaths as $path) {
            self::attachPath($root, $path);
        }

        return new self($root);
    }

    private static function attachPath(Node $root, string $path): void
    {
        $segments = explode('.', $path);
        $current  = $root;

        foreach ($segments as $segment) {
            $relation = self::findRelation($current->schema, $segment);

            // Check if this node already exists
            $existing = self::findChild($current, $segment);

            if ($existing) {
                $current = $existing;

                continue;
            }

            $childSchema = self::resolveSchema($relation->schemaClass);

            $edge = new Edge(
                relation: $relation,
                many: $relation->many,
            );

            $node = new Node(
                schema: $childSchema,
                parent: $current,
                edge: $edge,
            );

            $current->addChild($node);
            $current = $node;
        }
    }

    private static function findRelation(
        SchemaMetadata $schema,
        string $name,
    ) {
        foreach ($schema->relations as $relation) {
            if ($relation->name === $name) {
                return $relation;
            }
        }

        throw new RuntimeException(
            "Relation '{$name}' not found on schema {$schema->schemaClass}",
        );
    }

    private static function findChild(Node $node, string $name): ?Node
    {
        foreach ($node->children as $child) {
            if ($child->edge?->relation->name === $name) {
                return $child;
            }
        }

        return null;
    }

    private static function resolveSchema(string $schemaClass): SchemaMetadata
    {
        return SchemaReflector::reflect($schemaClass);
    }

    public function describe(): array
    {
        // TODO: implement
        return [];
    }
}
