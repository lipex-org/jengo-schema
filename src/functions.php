<?php

declare(strict_types=1);

namespace Jengo\Schema;

use Jengo\Schema\Query\FluentQueryAPI;

function query(string $schema): FluentQueryAPI
{
    return new FluentQueryAPI($schema);
}
