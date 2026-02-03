<?php

declare(strict_types=1);

namespace Jengo\Schema\Attributes\Relations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class HasMany
{
    public function __construct(
        public string $schema,
        public string $from,
        public ?string $to = null,
        public array $select = [],
    ) {
    }
}
