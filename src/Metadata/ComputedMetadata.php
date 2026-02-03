<?php

declare(strict_types=1);

namespace Jengo\Schema\Metadata;

use Jengo\Schema\Hydration\Enums\Cast;

final class ComputedMetadata
{
    public function __construct(
        public string $name,
        public string $method,
        /**
         * Dependant fields
         * @var string[]
         */
        public array $dependants = [],
        public ?Cast $cast = null,
    ) {
    }
}
