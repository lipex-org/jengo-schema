<?php

declare(strict_types=1);

namespace Jengo\Schema\Config;

use CodeIgniter\Config\BaseConfig;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\SortOptions;

class JengoSchema extends BaseConfig
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

    public function __construct()
    {
        parent::__construct();

        $this->paginationOptions = new PaginationOptions();
        $this->sortOptions = new SortOptions();
    }

    public function getGeneratorNamespace(): string
    {
        return $this->generator['namespace'] ?? 'App\\Schemas';
    }

    public function getGeneratorDirectory(): string
    {
        return $this->generator['directory'] ?? APPPATH . 'Schemas';
    }

    public function shouldGenerateTypeScript(): bool
    {
        return (bool)($this->generator['ts'] ?? false);
    }

    public function getTypeScriptDirectory(): string
    {
        return $this->generator['ts-directory'] ?? ROOTPATH . 'resources/js/types/schemas';
    }

    public function shouldIncludeNextAndPrevious(): bool
    {
        return (bool)($this->pagination['withNextAndPrevious'] ?? true);
    }

    public function shouldIncludePaginationMore(): bool
    {
        return (bool)($this->pagination['withMore'] ?? true);
    }

    public function getDefaultMoreLabel(): string
    {
        return $this->pagination['moreLabel'] ?? '...';
    }
}
