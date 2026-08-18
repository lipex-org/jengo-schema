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
}
