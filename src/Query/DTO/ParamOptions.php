<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\DTO;

final class ParamOptions
{
    public function __construct(
        /** @var list<string>|list<array> */
        public readonly array $params = [],
        /** @var list<string>|list<array> */
        public readonly array $whereNotInParams = [],
        /** @var callable[] */
        public readonly array $callbacks = [],
        public readonly bool $isOr = false,
    ) {
    }
}
