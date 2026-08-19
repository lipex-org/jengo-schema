<?php

namespace Config;

use Jengo\Schema\Config\JengoSchema as BaseJengoSchema;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\SortOptions;

class JengoSchema extends BaseJengoSchema
{
    /**
     * Enable query logging globally
     * Can be overridden per request via QueryOptions
     */
    public bool $logger = false;

    /**
     * Default pagination mechanics
     */
    public PaginationOptions $paginationOptions;

    /**
     * Default sorting behavior
     */
    public SortOptions $sortOptions;

    /**
     * Callbacks invoked during where-clause resolution
     *
     * Signature:
     * fn(string $key, mixed $value, string $boolean, string $phase): array
     */
    public array $whereCallbacks = [];

    /**
     * Map raw database table names to default entity classes for virtual schemas.
     */
    public array $entityMap = [];

    /**
     * Generator configurations
     */
    public array $generator = [
        'namespace' => 'App\\Schemas',
        'directory' => APPPATH . 'Schemas',
        'ts' => false,
        'ts-directory' => ROOTPATH . 'resources/js/types/schemas'
    ];
    /**
     * Pagination UI / link-generation policy
     */
    public array $pagination = [
        'withNextAndPrevious' => true,
        'withMore' => true,
        'moreLabel' => '...'
    ];
}
