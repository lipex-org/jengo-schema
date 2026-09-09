<?php

declare(strict_types=1);

namespace Jengo\Schema\Query;

final class Clamp
{
    /**
     * Resolve whether clamping should be enabled based on context or value.
     */
    public static function resolve(mixed $clampValue = 'auto'): bool
    {
        if ($clampValue === null) {
            $config = config('JengoSchema');
            $clampValue = $config->clamp ?? 'auto';
        }

        if (is_callable($clampValue)) {
            return (bool) $clampValue();
        }

        if (is_bool($clampValue)) {
            return $clampValue;
        }

        if (is_string($clampValue) && strtolower($clampValue) === 'auto') {
            return self::auto();
        }

        return (bool) $clampValue;
    }

    /**
     * Context-aware detection:
     * Returns true for UI / Web / Inertia requests.
     * Returns false for Pure API, JSON, or non-Inertia AJAX requests.
     */
    public static function auto(): bool
    {
        $request = function_exists('request') ? request() : (function_exists('service') ? service('request') : null);
        if (! $request instanceof \CodeIgniter\HTTP\RequestInterface) {
            return true;
        }

        // 1. Inertia requests are UI requests -> enable clamping by default
        if ($request->hasHeader('X-Inertia')) {
            return true;
        }

        // 2. Pure API route prefix (/api/*) -> disable clamping by default
        $uri = $request->getUri();
        if ($uri !== null) {
            $path = trim($uri->getPath(), '/');
            if (str_starts_with($path, 'api/') || $path === 'api') {
                return false;
            }
        }

        // 3. Explicit JSON accept header without Inertia -> disable clamping by default
        if ($request->hasHeader('Accept')) {
            $accept = $request->getHeaderLine('Accept');
            if (str_contains($accept, 'application/json') || str_contains($accept, 'text/json')) {
                return false;
            }
        }

        // 4. Raw AJAX request without Inertia (background fetch / infinite scroll) -> disable clamping
        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return false;
        }

        // 5. Traditional Web requests (Accept text/html or standard browser page load) -> enable clamping
        return true;
    }

    /**
     * Returns true if the current request is a UI or Inertia request.
     */
    public static function ui(): bool
    {
        return self::auto();
    }

    /**
     * Returns true if the current request is a pure API request.
     */
    public static function api(): bool
    {
        return ! self::auto();
    }

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
