<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class QueryOptions
{
    public function __construct(
        public readonly ParamOptions $params = new ParamOptions(),
        public readonly SelectOptions $select = new SelectOptions(),
        public readonly PaginationOptions $pagination = new PaginationOptions(),
        public readonly array $derive = [],
        public readonly SortOptions $sort = new SortOptions(),
        public readonly ?string $search = null,
        public readonly ?bool $logger = null,
        public readonly bool $first = false,
    ) {
    }
}
