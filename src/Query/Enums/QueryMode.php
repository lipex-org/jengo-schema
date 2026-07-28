<?php

declare(strict_types=1);

namespace Jengo\Schema\Query\Enums;

enum QueryMode: string
{
    case INLINE = 'inline'; // pull query params from options
    case OPEN   = 'open';   // pull query params from request
}
