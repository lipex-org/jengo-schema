<?php

declare(strict_types=1);

namespace Jengo\Schema\Metadata;

final class SchemaMetadata
{
    public function __construct(
        public string $schemaClass,
        public string $modelClass,
        public ?string $entityClass,
        public FieldMetadata $primaryKey,
        /**
         * @var list<FieldMetadata>
         */
        public array $fields,
        /**
         * @var list<RelationMetadata>
         */
        public array $relations,
        /**
         * @var list<ComputedMetadata>
         */
        public array $computed,
    ) {
    }

    public function getField(string $name): ?FieldMetadata
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public function getRelation(string $name): ?RelationMetadata
    {
        foreach ($this->relations as $relation) {
            if ($relation->name === $name) {
                return $relation;
            }
        }

        return null;
    }

    public function getComputed(string $name): ?ComputedMetadata
    {
        foreach ($this->computed as $computed) {
            if ($computed->name === $name) {
                return $computed;
            }
        }

        return null;
    }
}
