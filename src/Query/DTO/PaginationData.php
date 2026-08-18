<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class PaginationData
{
    public function __construct(
        public int $page,
        public int $limit,
        public int $total,
        /**
         * @var list<PaginationLink> $links
         */
        public array $links = [],
        public bool $hasMore = false,
        public ?int $nextPage = null,
        public ?string $nextCursor = null,
    ) {
    }
}
