<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class PaginationOptions
{
    public function __construct(
        public readonly int $limit = 0,
        public readonly int $page = 1,
        public readonly int $linksMax = 5,
        public readonly bool $withQuery = true,
        public readonly string $group = 'default',
    ) {
    }
}
