<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class SelectOptions
{
    public function __construct(
        /**
         * @var list<string>
         */
        public readonly array $select = [],
    ) {
    }
}
