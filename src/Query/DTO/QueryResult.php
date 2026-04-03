<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class QueryResult
{
    public function __construct(
        public array|object|null $data,
        public int $count,
        public PaginationData|null $pagination = null,
    ) {
        # code...
    }
}
