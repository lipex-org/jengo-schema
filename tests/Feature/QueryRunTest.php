<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jengo\Schema\Query\Query;
use Jengo\Schema\Query\DTO\QueryOptions;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\DTO\ParamOptions;
use Jengo\Schema\Query\DTO\PaginationOptions;
use Jengo\Schema\Query\Enums\QueryMode;
use Tests\Support\Entity\UserFile;
use Tests\Support\Schemas\UserFileSchema;
use Tests\Support\Schemas\UserSchema;
use Tests\TestCase;

final class QueryRunTest extends TestCase
{
    public function setUp(): void
    {
        $this->fill = false;

        parent::setUp();
    }

    /**
     * Test single result retrieval (first = true)
     */
    public function testRunReturnsSingleObjectWhenFirstIsTrue(): void
    {
        // Seed 1 user with 1 file
        $userId = $this->db->table('users')->insert([
            'first_name' => 'Carleton',
            'last_name' => 'Krajcik',
            'email' => 'emmerich.rory@yahoo.com'
        ]);

        $fileId = $this->db->table('user_files')->insert([
            'name' => 'Et.',
            'size' => 5.6733,
            'path' => 'Qui optio.',
            'user_id' => $userId
        ]);

        $options = new QueryOptions(
            params: new ParamOptions(['id' => $fileId]),
            derive: ['user.files'],
            first: true
        );

        $result = Query::run(UserFileSchema::class, $options, QueryMode::INLINE);

        $this->assertInstanceOf(QueryResult::class, $result);

        // Assert Data Structure for 'first'
        $data = $result->data;
        $this->assertInstanceOf(UserFile::class, $data);
        $this->assertSame('Et.', $data->name);

        // Assert Nested Derived Data
        $this->assertSame('Carleton', $data->user->first_name);
        $this->assertCount(1, $data->user->files);

        // Assert Pagination for single result
        $this->assertSame(1, $result->count);
        //$this->assertSame(1, $result->pagination->limit);
    }

    /**
     * Test collection retrieval (first = false)
     */
    public function testRunReturnsArrayOfArraysWhenFirstIsFalse(): void
    {
        $this->db->table('users')->insert([
            'first_name' => 'Carleton',
            'last_name' => 'Krajcik',
            'email' => 'emmerich.rory@yahoo.com'
        ]);

        // Seed multiple files
        $this->db->table('user_files')->insertBatch([
            ['name' => 'File A', 'size' => 1.2, 'path' => '/a', 'user_id' => 1],
            ['name' => 'File B', 'size' => 2.2, 'path' => '/b', 'user_id' => 1],
        ]);

        $options = new QueryOptions(
            pagination: new PaginationOptions(limit: 10),
            first: false
        );

        $result = Query::run(UserFileSchema::class, $options, QueryMode::INLINE);

        $this->assertIsArray($result->data);
        $this->assertCount(2, $result->data);

        // Assert that the first element is an array (the record)
        $this->assertIsObject($result->data[0]);
        $this->assertSame('File A', $result->data[0]->name);

        // Assert Pagination totals
        $this->assertGreaterThanOrEqual(2, $result->pagination->total);
    }

    /**
     * Test Computed fields inclusion during hydration
     */
    public function testRunIncludesComputedFieldsInResult(): void
    {
        $this->db->table('users')->insert([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);

        $options = new QueryOptions(first: true, logger: true);
        $result = Query::run(UserSchema::class, $options);

        // 'full_name' is a #[Computed] field in UserSchema
        $this->assertSame('John Doe', $result->data->full_name);
    }
}