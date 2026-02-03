<?php

declare(strict_types=1);

namespace Jengo\Schema\Attributes;

use Attribute;
use Jengo\Schema\Hydration\Enums\Cast;

#[Attribute(Attribute::TARGET_METHOD)]
final class Computed
{
    public function __construct(
        public string $name,
        /**
         * Fields this computed field depend on
         * @var string[]
         */
        public array $dependants = [],
        public ?Cast $cast = null,
    ) {
    }
}
