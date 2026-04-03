<?php

declare(strict_types=1);

namespace Jengo\Schema\Debug;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

final class QueryLogger
{
    private static bool $enabled = false;
    private static array $logs = [];

    public static function enable(): void
    {
        self::$enabled = true;
        self::$logs = [];
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function record(): void
    {
        if (!self::$enabled) {
            return;
        }

        $db = Database::connect();

        $query = $db->getLastQuery();

        if (!$query) {
            return;
        }

        self::$logs[] = [
            'sql' => $query->getQuery(),
            'duration' => $query->getDuration(),
        ];
    }

    public static function add(string $key, mixed $data = null): void
    {
        if (!self::$enabled) {
            return;
        }

        self::$logs[$key] = $data;
    }

    public static function append(string $key, mixed $data = null): void
    {
        if (!self::$enabled) {
            return;
        }

        if (!isset(self::$logs[$key])) {
            self::$logs[$key] = [];
        }

        self::$logs[$key][] = $data;
    }

    public static function prepend(string $key, mixed $data = null): void
    {
        if (!self::$enabled) {
            return;
        }

        if (!isset(self::$logs[$key])) {
            self::$logs[$key] = [];
        }

        array_unshift(self::$logs[$key], $data);
    }

    public static function all(): array
    {
        return self::$logs;
    }

    public static function flush(): array
    {
        $logs = self::$logs;
        self::$logs = [];

        return $logs;
    }
}
