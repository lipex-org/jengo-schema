<?php

declare(strict_types=1);

namespace Tests\Unit\Resolver;

use CodeIgniter\Config\Factories;
use Jengo\Schema\Config\Schema as SchemaConfig;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\SelectOptions;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Query\Enums\SortOrder;
use Jengo\Schema\Query\OptionsResolver;
use Jengo\Schema\Support\Utils;
use PHPUnit\Framework\TestCase;
use Mockery;

final class OptionsResolverTest extends TestCase
{
    private $configMock;

    public function setUp(): void
    {
        parent::setUp();
        
        // Mock the SchemaConfig DTO
        $this->configMock = new SchemaConfig();
        $this->configMock->paginationOptions = new PaginationOptions(limit: 20, page: 1, linksMax: 5);
        $this->configMock->sortOptions = new SortOptions(column: 'id', direction: SortOrder::ASC);
        $this->configMock->whereCallbacks = ['default_cb' => fn() => null];
        $this->configMock->logger = true;

       Factories::injectMock('config', 'Schema', $this->configMock);
    }

    /**
     * Test that resolution in STRICT mode ignores request data and applies config defaults.
     */
    public function testResolveInStrictModeAppliesConfigDefaults(): void
    {
        $inputOptions = new QueryOptions(
            pagination: new PaginationOptions(limit: 0), // Trigger default
            sort: new SortOptions(column: '')           // Trigger default
        );

        $resolved = OptionsResolver::resolve(QueryMode::INLINE, $inputOptions);

        $this->assertSame(20, $resolved->pagination->limit);
        $this->assertSame('id', $resolved->sort->column);
        $this->assertTrue($resolved->logger);
    }

    /**
     * Test that provided options override config defaults.
     */
    public function testProvidedOptionsOverrideDefaults(): void
    {
        $inputOptions = new QueryOptions(
            pagination: new PaginationOptions(limit: 50, page: 2),
            sort: new SortOptions(column: 'created_at', direction: SortOrder::DESC),
            logger: false
        );

        $resolved = OptionsResolver::resolve(QueryMode::INLINE, $inputOptions);

        $this->assertSame(50, $resolved->pagination->limit);
        $this->assertSame(2, $resolved->pagination->page);
        $this->assertSame('created_at', $resolved->sort->column);
        $this->assertSame(SortOrder::DESC, $resolved->sort->direction);
        $this->assertFalse($resolved->logger);
    }

    /**
     * Test ParamOptions callback merging logic.
     */
    public function testResolveParamsMergesCallbacks(): void
    {
        $customCb = fn() => 'custom';
        $params = new ParamOptions(callbacks: ['custom_cb' => $customCb]);
        
        $inputOptions = new QueryOptions(params: $params);
        $resolved = OptionsResolver::resolve(QueryMode::INLINE, $inputOptions);

        $this->assertCount(2, $resolved->params->callbacks);
        $this->assertArrayHasKey('default_cb', $resolved->params->callbacks);
        $this->assertArrayHasKey('custom_cb', $resolved->params->callbacks);
    }

    /**
     * Test that SELECT options pass through untouched.
     */
    public function testResolveSelectPassesThrough(): void
    {
        $select = new SelectOptions(select: ['id', 'name']);
        $inputOptions = new QueryOptions(select: $select);
        
        $resolved = OptionsResolver::resolve(QueryMode::INLINE, $inputOptions);

        $this->assertSame(['id', 'name'], $resolved->select->select);
    }
}