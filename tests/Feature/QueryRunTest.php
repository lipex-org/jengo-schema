<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\DTO\SortOptions;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Query\Query;
use Tests\Support\Entity\UserFile;
use Tests\Support\Schemas\UserFileSchema;
use Tests\Support\Schemas\UserSchema;
use Tests\TestCase;

/**
 * @internal
 */
final class QueryRunTest extends TestCase
{
    protected function setUp(): void
    {
        $this->fill = false;

        parent::setUp();
    }

    /**
     * Test single result retrieval (first = true)
     */
    public function testRunReturnsSingleObjectWhenFirstIsTrue(): void
    {
        $this->tearDown();
        // Seed 1 user with 1 file
        $userId = $this->db->table('users')->insert([
            'first_name' => 'Carleton',
            'last_name'  => 'Krajcik',
            'email'      => 'emmerich.rory@yahoo.com',
        ]);

        $fileId = $this->db->table('user_files')->insert([
            'name'    => 'Et.',
            'size'    => 5.6733,
            'path'    => 'Qui optio.',
            'user_id' => $userId,
        ]);

        $options = new QueryOptions(
            params: new ParamOptions(['id' => $fileId]),
            derive: ['user.files'],
            first: true,
            logger: true,
        );

        $result = Query::run(UserFileSchema::class, $options, QueryMode::INLINE);

        $this->assertInstanceOf(QueryResult::class, $result);

        // dump_query();

        // Assert Data Structure for 'first'
        $data = $result->data;
        $this->assertInstanceOf(UserFile::class, $data);
        $this->assertSame('Et.', $data->name);

        // Assert Nested Derived Data
        $this->assertSame('Carleton', $data->user->first_name);
        $this->assertCount(1, $data->user->files);

        // Assert Pagination for single result
        $this->assertSame(1, $result->count);
    }

    /**
     * Test collection retrieval (first = false)
     */
    public function testRunReturnsArrayOfArraysWhenFirstIsFalse(): void
    {
        $this->db->table('users')->insert([
            'first_name' => 'Carleton',
            'last_name'  => 'Krajcik',
            'email'      => 'emmerich.rory@yahoo.com',
        ]);

        // Seed multiple files
        $this->db->table('user_files')->insertBatch([
            ['name' => 'File A', 'size' => 1.2, 'path' => '/a', 'user_id' => 1],
            ['name' => 'File B', 'size' => 2.2, 'path' => '/b', 'user_id' => 1],
        ]);

        $options = new QueryOptions(
            pagination: new PaginationOptions(limit: 10),
            first: false,
        );

        $result = Query::run(UserFileSchema::class, $options, QueryMode::INLINE);

        $this->assertIsArray($result->data);
        $this->assertCount(2, $result->data);

        // Assert that the first element is an array (the record)
        $this->assertIsObject($result->data[0]);
        $this->assertSame('File A', $result->data[0]->name);

        // Assert Pagination totals
        $this->assertGreaterThanOrEqual(2, $result->pagination->total);
        $this->assertFalse($result->pagination->hasMore);
        $this->assertNull($result->pagination->nextPage);

        // Verify hasMore = true, nextPage = 2 when limit is less than total
        $optionsLimit1 = new QueryOptions(
            pagination: new PaginationOptions(page: 1, limit: 1),
            first: false,
        );
        $resultLimit1 = Query::run(UserFileSchema::class, $optionsLimit1, QueryMode::INLINE);
        $this->assertTrue($resultLimit1->pagination->hasMore);
        $this->assertSame(2, $resultLimit1->pagination->nextPage);
    }

    /**
     * Test Computed fields inclusion during hydration
     */
    public function testRunIncludesComputedFieldsInResult(): void
    {
        $this->tearDown();
        $this->db->table('users')->insert([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'john@example.com',
        ]);

        $options = new QueryOptions(first: true, logger: true);
        $result  = Query::run(UserSchema::class, $options);

        // 'full_name' is a #[Computed] field in UserSchema
        $this->assertSame('John Doe', $result->data->full_name);
    }

    public function testCursorPagination(): void
    {
        $this->tearDown();
        $this->db->table('users')->insert([
            'first_name' => 'Carleton',
            'last_name'  => 'Krajcik',
            'email'      => 'emmerich.rory@yahoo.com',
        ]);

        $this->db->table('user_files')->insertBatch([
            ['name' => 'File A', 'size' => 1.0, 'path' => '/a', 'user_id' => 1],
            ['name' => 'File B', 'size' => 2.0, 'path' => '/b', 'user_id' => 1],
            ['name' => 'File C', 'size' => 3.0, 'path' => '/c', 'user_id' => 1],
            ['name' => 'File D', 'size' => 4.0, 'path' => '/d', 'user_id' => 1],
        ]);

        // First page request
        $options = new QueryOptions(
            pagination: new PaginationOptions(page: 1, limit: 2),
            sort: new SortOptions(column: 'size', direction: \Jengo\Schema\Query\Enums\SortOrder::ASC),
            first: false,
        );

        $result1 = Query::run(UserFileSchema::class, $options, QueryMode::INLINE);
        $this->assertCount(2, $result1->data);
        $this->assertSame('File A', $result1->data[0]->name);
        $this->assertSame('File B', $result1->data[1]->name);
        $this->assertTrue($result1->pagination->hasMore);
        $this->assertNotNull($result1->pagination->nextCursor);

        // Second page request using the cursor
        $cursorOptions = new QueryOptions(
            pagination: new PaginationOptions(limit: 2, after: $result1->pagination->nextCursor),
            sort: new SortOptions(column: 'size', direction: \Jengo\Schema\Query\Enums\SortOrder::ASC),
            first: false,
        );

        $result2 = Query::run(UserFileSchema::class, $cursorOptions, QueryMode::INLINE);
        $this->assertCount(2, $result2->data);
        $this->assertSame('File C', $result2->data[0]->name);
        $this->assertSame('File D', $result2->data[1]->name);
    }

    public function testPaginationClamping(): void
    {
        $this->tearDown();
        $this->db->table('users')->insert([
            'first_name' => 'Carleton',
            'last_name'  => 'Krajcik',
            'email'      => 'emmerich.rory@yahoo.com',
        ]);

        $this->db->table('user_files')->insertBatch([
            ['name' => 'File A', 'size' => 1.0, 'path' => '/a', 'user_id' => 1],
            ['name' => 'File B', 'size' => 2.0, 'path' => '/b', 'user_id' => 1],
            ['name' => 'File C', 'size' => 3.0, 'path' => '/c', 'user_id' => 1],
            ['name' => 'File D', 'size' => 4.0, 'path' => '/d', 'user_id' => 1],
        ]);

        // 1. Without clamp, requesting page 5 (beyond last page 2) should yield 0 results
        $optionsNoClamp = new QueryOptions(
            pagination: new PaginationOptions(page: 5, limit: 2, clamp: false),
            sort: new SortOptions(column: 'size', direction: \Jengo\Schema\Query\Enums\SortOrder::ASC),
            first: false,
        );
        $resultNoClamp = Query::run(UserFileSchema::class, $optionsNoClamp, QueryMode::INLINE);
        $this->assertCount(0, $resultNoClamp->data);

        // 2. With clamp, requesting page 5 should clamp to page 2 and yield 2 results (File C and File D)
        $optionsClamp = new QueryOptions(
            pagination: new PaginationOptions(page: 5, limit: 2, clamp: true),
            sort: new SortOptions(column: 'size', direction: \Jengo\Schema\Query\Enums\SortOrder::ASC),
            first: false,
        );
        $resultClamp = Query::run(UserFileSchema::class, $optionsClamp, QueryMode::INLINE);
        $this->assertCount(2, $resultClamp->data);
        $this->assertSame('File C', $resultClamp->data[0]->name);
        $this->assertSame('File D', $resultClamp->data[1]->name);
        $this->assertSame(2, $resultClamp->pagination->page);

        // 3. With clamp and cursor yielding no data (e.g. value > 100), it should clamp to last page and yield File C and File D
        $outOfBoundsCursor = base64_encode(json_encode(['v' => 100.0, 'k' => 100]));
        $optionsCursorClamp = new QueryOptions(
            pagination: new PaginationOptions(limit: 2, after: $outOfBoundsCursor, clamp: true),
            sort: new SortOptions(column: 'size', direction: \Jengo\Schema\Query\Enums\SortOrder::ASC),
            first: false,
        );
        $resultCursorClamp = Query::run(UserFileSchema::class, $optionsCursorClamp, QueryMode::INLINE);
        $this->assertCount(2, $resultCursorClamp->data);
        $this->assertSame('File C', $resultCursorClamp->data[0]->name);
        $this->assertSame('File D', $resultCursorClamp->data[1]->name);

        // 4. Test via Fluent API
        $fluentResult = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp(true)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();

        $this->assertCount(2, $fluentResult->data);
        $this->assertSame('File C', $fluentResult->data[0]->name);

        // 5. Test with callable that returns false (should NOT clamp, yielding 0 results)
        $fluentCallableFalse = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp(fn() => false)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(0, $fluentCallableFalse->data);

        // 6. Test with callable that returns true (should clamp, yielding 2 results)
        $fluentCallableTrue = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp(fn() => true)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentCallableTrue->data);

        // 7. Test with Clamp::ajax() condition helper (in CLI test environment, not AJAX, so returns true -> clamps)
        $fluentClampAjax = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp([\Jengo\Schema\Query\Clamp::class, 'ajax'])
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentClampAjax->data);

        // 8. Test with Clamp::inertia() condition helper (by default, no X-Inertia header -> clamps)
        $fluentClampInertia = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp([\Jengo\Schema\Query\Clamp::class, 'inertia'])
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentClampInertia->data);

        // 9. Test with Clamp::inertia() under simulated Inertia request (X-Inertia: true -> does NOT clamp)
        request()->setHeader('X-Inertia', 'true');
        try {
            $fluentClampInertiaActive = \Jengo\Schema\query(UserFileSchema::class)
                ->inline()
                ->limit(2)
                ->page(5)
                ->clamp([\Jengo\Schema\Query\Clamp::class, 'inertia'])
                ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
                ->get();
            $this->assertCount(0, $fluentClampInertiaActive->data);
        } finally {
            request()->removeHeader('X-Inertia');
        }

        // 10. Test clamping in openMode (request-driven) with GET parameters
        service('request')->setGlobal('get', ['page' => '5']);
        try {
            $fluentOpenClamp = \Jengo\Schema\query(UserFileSchema::class)
                ->open()
                ->limit(2)
                ->clamp(true)
                ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
                ->get();
            $this->assertCount(2, $fluentOpenClamp->data);
            $this->assertSame(2, $fluentOpenClamp->pagination->page);
        } finally {
            service('request')->setGlobal('get', []);
        }

        // 11. Test custom clamp target page (integer: clamp to page 1)
        $fluentTargetOne = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp(true, 1)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentTargetOne->data);
        $this->assertSame('File A', $fluentTargetOne->data[0]->name);
        $this->assertSame(1, $fluentTargetOne->pagination->page);

        // 12. Test custom clamp target page (closure: clamp to page 1)
        $fluentTargetClosure = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(5)
            ->clamp(true, fn() => 1)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentTargetClosure->data);
        $this->assertSame('File A', $fluentTargetClosure->data[0]->name);
        $this->assertSame(1, $fluentTargetClosure->pagination->page);

        // 13. Test clamping in openMode with custom target page (page 1)
        service('request')->setGlobal('get', ['page' => '5']);
        try {
            $fluentOpenClampTargetOne = \Jengo\Schema\query(UserFileSchema::class)
                ->open()
                ->limit(2)
                ->clamp(true, 1)
                ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
                ->get();
            $this->assertCount(2, $fluentOpenClampTargetOne->data);
            $this->assertSame(1, $fluentOpenClampTargetOne->pagination->page);
        } finally {
            service('request')->setGlobal('get', []);
        }

        // 14. Test clampForce = true (even when page 2 has data, force reset to page 1)
        $fluentForceClamp = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(2)
            ->clamp(true, 1, true)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentForceClamp->data);
        $this->assertSame('File A', $fluentForceClamp->data[0]->name);
        $this->assertSame(1, $fluentForceClamp->pagination->page);

        // 15. Test clampForce closure returning true
        $fluentForceClosureTrue = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(2)
            ->clamp(true, 1, fn() => true)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentForceClosureTrue->data);
        $this->assertSame('File A', $fluentForceClosureTrue->data[0]->name);
        $this->assertSame(1, $fluentForceClosureTrue->pagination->page);

        // 16. Test clampForce closure returning false (should NOT clamp since it has data and force is false)
        $fluentForceClosureFalse = \Jengo\Schema\query(UserFileSchema::class)
            ->inline()
            ->limit(2)
            ->page(2)
            ->clamp(true, 1, fn() => false)
            ->sort('size', \Jengo\Schema\Query\Enums\SortOrder::ASC)
            ->get();
        $this->assertCount(2, $fluentForceClosureFalse->data);
        $this->assertSame('File C', $fluentForceClosureFalse->data[0]->name);
        $this->assertSame(2, $fluentForceClosureFalse->pagination->page);
    }
}
