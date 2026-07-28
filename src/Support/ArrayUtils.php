<?php

declare(strict_types=1);

namespace Jengo\Schema\Support;

use Throwable;

final class ArrayUtils
{
    /**
     * Get a value from a nested array using dot notation.
     *
     * Example: get(['a' => ['b' => 1]], 'a.b') => 1
     */
    public static function get(array $array, string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Set a value in a nested array using dot notation.
     *
     * Example: set($arr, 'a.b', 1) => ['a' => ['b' => 1]]
     */
    public static function set(array &$array, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $ref  = &$array;

        foreach ($keys as $key) {
            if (! isset($ref[$key]) || ! is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
    }

    /**
     * Check if a nested key exists in a dot-notated array.
     */
    public static function has(array $array, string $path): bool
    {
        $keys = explode('.', $path);

        foreach ($keys as $key) {
            if (is_array($array) && array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Flatten a multi-dimensional array using dot notation keys
     *
     * Example: ['a' => ['b' => 1]] => ['a.b' => 1]
     */
    public static function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flatten($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    public static function toArray(mixed $val): array
    {
        try {
            return json_decode(json_encode($val), true);
        } catch (Throwable $e) {
            return [];
        }
    }
}
