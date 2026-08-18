<?php

declare(strict_types=1);

namespace Jengo\Schema\Config;

use CodeIgniter\Config\BaseConfig;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\SortOptions;

class Schema extends BaseConfig
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
    public string $generatorNamespace = 'App\\Schemas';
    public string $generatorDirectory = APPPATH . 'Schemas';
    public bool $generateTypeScript    = false;
    public string $typeScriptDirectory = ROOTPATH . 'resources/js/types/schemas';

    /**
     * Pagination UI / link-generation policy
     */
    public bool $includeNextAndPrevious = true;

    public bool $includePaginationMore = true;
    public string $defaultMoreLabel    = '...';

    public function __construct()
    {
        parent::__construct();

        $this->paginationOptions = new PaginationOptions();
        $this->sortOptions       = new SortOptions();
    }
}
