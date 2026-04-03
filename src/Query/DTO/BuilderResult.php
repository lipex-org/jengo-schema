<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class BuilderResult
{
    public function __construct(
        public readonly array $rows,
        public readonly int $total,
    ) {
    }
}
