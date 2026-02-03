<?php

declare(strict_types=1);

namespace Jengo\Schema\Metadata;

use Jengo\Schema\Hydration\DTO\PropertyType;
use Jengo\Schema\Hydration\Enums\Cast;

final class FieldMetadata
{
    public function __construct(
        public string $name,
        public PropertyType $type,
        public bool $searchable = false,
        public bool $derived = false,
        public ?Cast $cast = null,
    ) {
    }
}
