<?php

declare(strict_types=1);

namespace Jengo\Schema\Metadata;

final class RelationMetadata
{
    public const string BELONGS_TO = 'belongs_to';
    public const string HAS_MANY = 'has_many';

    public function __construct(
        public string $name,
        public string $type,
        public string $schemaClass,
        public string $fromField,
        public ?string $toField,
        /**
         * @var array<string>
         */
        public array $select,
        public bool $many,
    ) {
    }
}
