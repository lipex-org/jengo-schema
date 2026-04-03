<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class PaginationLinksData
{
    public function __construct(
        public readonly int $page,
        public readonly int $total,
        public readonly int $limit,
    ) {
    }
}
