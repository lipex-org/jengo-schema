<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

final class Clamp
{
    /**
     * Returns true if the current request is NOT an AJAX request,
     * and false if it IS an AJAX request.
     *
     * This is useful to enable clamping for normal web requests
     * but disable it for AJAX/infinite scroll endpoints so the client-side
     * script can receive empty results to know when to stop querying.
     */
    public static function ajax(): bool
    {
        return !request()->isAJAX();
    }

    /**
     * Returns true if the current request is NOT an Inertia request,
     * and false if it IS an Inertia request.
     *
     * This is useful to enable clamping for standard requests
     * but disable it for Inertia-driven requests to allow frontend page handling
     * or infinite scroll components to receive empty bounds natively.
     */
    public static function inertia(): bool
    {
        return !request()->hasHeader('X-Inertia');
    }
}
