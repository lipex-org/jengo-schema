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
         * @var PaginationLink[] $links
         */
        public array $links = [],
    ) {
    }
}