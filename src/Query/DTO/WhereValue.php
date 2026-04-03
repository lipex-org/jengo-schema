<?php

namespace Jengo\Schema\Query\DTO;

final class WhereValue
{
    public function __construct(
        public readonly mixed $value,
        public readonly bool $or = false,
    ) {
    }
}