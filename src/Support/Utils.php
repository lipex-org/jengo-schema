<?php

declare(strict_types=1);

namespace Jengo\Schema\Support;

use Jengo\Schema\Config\JengoSchema;

final class Utils
{
    public static function config(): JengoSchema
    {
        return config('JengoSchema');
    }
}
