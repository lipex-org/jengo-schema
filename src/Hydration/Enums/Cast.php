<?php

declare(strict_types=1);

namespace Jengo\Schema\Hydration\Enums;

enum Cast: string
{
    case INT = 'int';
    case BOOL = 'bool';
    case FLOAT = 'float';
    case STRING = 'string';
    case ARRAY = 'array';
    case DATETIME = 'datetime';
}

