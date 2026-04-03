<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class PaginationLink
{
    public function __construct(
        public string $label,
        public ?string $url,
        public ?int $page,
        public bool $active,
    ) {
    }
}