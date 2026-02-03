<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration;

use Jengo\Schema\Graph\Node;

final class EntityFactory
{
    /**
     * Convert a hydrated array into entity objects
     */
    public static function make(Node $node, array $data): mixed
    {
        $entityClass = $node->schema->entityClass;

        if ($entityClass && class_exists($entityClass)) {
            $entity = new $entityClass();
            $fields = [
                $node->schema->primaryKey,
                ...$node->schema->fields,
                ...$node->schema->computed
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field->name, $data)) {
                    $entity->{$field->name} = $data[$field->name];
                }
            }

            // recurse into children
            foreach ($node->children as $child) {
                $childData = $data[$child->edge->relation->name] ?? null;

                if ($childData) {
                    if ($child->isMany()) {
                        $entity->{$child->edge->relation->name} = array_map(
                            static fn($d) => self::make($child, $d),
                            $childData,
                        );
                    } else {
                        $entity->{$child->edge->relation->name} = self::make($child, $childData);
                    }
                }
            }

            return $entity;
        }

        // fallback: return plain array
        return $data;
    }
}
