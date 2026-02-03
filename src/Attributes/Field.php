<?php

declare(strict_types=1);

namespace Jengo\Schema\Attributes;

use Attribute;
use Jengo\Schema\Hydration\Enums\Cast;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Field
{
    public function __construct(
        public bool $searchable = false,
        public ?Cast $cast = null,
    ) {
    }
}
