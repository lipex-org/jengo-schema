<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\Enums;

enum SortOrder: string
{
    case ASC  = 'asc';
    case DESC = 'desc';
}
