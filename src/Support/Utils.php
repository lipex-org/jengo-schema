<?php

declare(strict_types=1);

namespace Jengo\Schema\Support;

use Jengo\Schema\Config\Schema;

final class Utils
{
    public static function config(): Schema
    {
        return config('Schema');
    }
}
