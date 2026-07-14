<?php

declare(strict_types=1);

namespace Jengo\Schema\Support;

use CodeIgniter\HTTP\URI;
use Jengo\Schema\Query\DTO\PaginationLink;
use Jengo\Schema\Query\DTO\PaginationLinksData;

final class PaginationUtils
{
    /**
     * Generates pagination links based on the pagination params provided
     * @param PaginationLinksData $data
     * @param int $number
     * @param string $group
     * @param bool $withQuery
     * @return PaginationLink[]
     */
    public static function generateLinks(
        PaginationLinksData $data,
        int $number = 5,
        string $group = "default",
        bool $withQuery = false
    ): array {
        $links = [];
        $uri = request()->getUri();

        // provide a test uri in testing mode if not present
        if (ENVIRONMENT === "testing" && !$uri) {
            $uri = new URI(base_url('?g=1'));
        }

        if (!$uri) {
            return $links;
        }

        $currentPage = $data->page;
        $total = $data->total;
        $perPage = $data->limit;

        if ($perPage === null || $perPage === 0) {
            return [];
        }

        $totalPages = (int) ceil($total / $perPage);

        // Ensure previous and next URLs
        $prevPage = $currentPage > 1 ? $currentPage - 1 : null;
        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : null;

        $prevUrl = $prevPage
            ? base_url($uri->getPath()) . ($withQuery && $uri->getQuery()
                ? sprintf('?%s&', $uri->getQuery()) : "?") . "page" . ($group === 'default' ? '=' . $prevPage : sprintf('_%s=%s', $group, $prevPage))
            : null;

        $nextUrl = $nextPage
            ? base_url($uri->getPath()) . ($withQuery && $uri->getQuery()
                ? sprintf('?%s&', $uri->getQuery()) : "?") . "page" . ($group === 'default' ? '=' . $nextPage : sprintf('_%s=%s', $group, $nextPage))
            : null;

        // Previous
        $links[] = new PaginationLink(
            label: "Previous",
            url: $prevUrl,
            active: false,
            page: $prevPage
        );

        // Determine range of page numbers to show
        $half = floor($number / 2);
        $start = max(1, $currentPage - $half);
        $end = min($totalPages, $currentPage + $half);

        // Adjust if we're near the start or end
        if ($end - $start + 1 < $number) {
            if ($start === 1) {
                $end = min($totalPages, $start + $number - 1);
            } elseif ($end === $totalPages) {
                $start = max(1, $end - $number + 1);
            }
        }

        // Add "..." before if needed
        if ($start > 1) {
            $links[] = new PaginationLink(
                url: null,
                label: '...',
                page: null,
                active: false,
            );
        }

        // Add numbered pages
        for ($page = $start; $page <= $end; ++$page) {
            $paginationQuery = "page" . ($group === 'default' ? '=' . $page : sprintf('_%s=%s', $group, $page));

            $links[] = new PaginationLink(
                url: base_url($uri->getPath()) . ($withQuery && $uri->getQuery()
                    ? sprintf('?%s&', $uri->getQuery()) : "?") . $paginationQuery,
                label: (string) $page,
                active: $currentPage == $page,
                page: (int) $page
            );
        }

        // Add "..." after if needed
        if ($end < $totalPages) {
            $links[] = new PaginationLink(
                url: null,
                label: '...',
                page: null,
                active: false,
            );
        }

        // Next
        $links[] = new PaginationLink(
            label: "Next",
            url: $nextUrl,
            active: false,
            page: $nextPage
        );

        // If there are only Previous and Next, return empty
        return count($links) === 2 ? [] : $links;
    }
}
