<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class SearchOptions
{
    public function __construct(
        public readonly ?string $value = null,
        public readonly array $fields = [],
        public readonly string $side = 'both',
        public readonly ?bool $caseInsensitive = null,
    ) {
    }
}
