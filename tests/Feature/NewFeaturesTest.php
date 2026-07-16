<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jengo\Schema\Query\DTO\QueryResult;
use Tests\Support\Schemas\UserSchema;
use Tests\Support\Schemas\UserFileSchema;
use Tests\Support\Entity\User;
use function Jengo\Schema\query;
use Tests\TestCase;

final class NewFeaturesTest extends TestCase
{
    public function setUp(): void
    {
        $this->fill = false;
        parent::setUp();
    }

    public function testEmptyHasManyRelationReturnsEmptyArray()
    {
        // Seed 1 user, but 0 files
        $this->db->table('users')->insert([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com'
        ]);
        $userId = (int) $this->db->insertID();

        $result = query(UserSchema::class)
            ->where('id', $userId)
            ->derive('files')
            ->first();

        $this->assertInstanceOf(User::class, $result->data);
        // files should be an empty array instead of array with null properties
        $this->assertSame([], $result->data->files);
    }

    public function testDefaultResponseIsNotPaginated()
    {
        // Seed 5 users
        for ($i = 1; $i <= 5; $i++) {
            $this->db->table('users')->insert([
                'first_name' => "User {$i}",
                'last_name' => 'Test',
                'email' => "user{$i}@example.com"
            ]);
        }

        // Run query without limit
        $result = query(UserSchema::class)->get();

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(5, $result->data);
        // Since limit is 0 (non-paginated), pagination links should be empty
        $this->assertSame([], $result->pagination->links);
        $this->assertSame(0, $result->pagination->limit);
    }

    public function testFindMethodFindsByPrimaryKey()
    {
        // Seed 2 users
        $this->db->table('users')->insert([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com'
        ]);
        $id1 = (int) $this->db->insertID();

        $this->db->table('users')->insert([
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@example.com'
        ]);
        $id2 = (int) $this->db->insertID();

        $result = query(UserSchema::class)->find($id2);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertInstanceOf(User::class, $result->data);
        $this->assertSame('Bob', $result->data->first_name);
        $this->assertSame($id2, $result->data->id);
    }

    public function testDeeplyNestedHasManyRelationWithEmptyChild()
    {
        // Seed 1 user
        $this->db->table('users')->insert([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com'
        ]);
        $userId = (int) $this->db->insertID();

        // Seed 1 file for user
        $this->db->table('user_files')->insert([
            'name' => 'document.txt',
            'size' => 1024.0,
            'path' => '/docs/document.txt',
            'user_id' => $userId
        ]);

        // No file comments are seeded (0 comments)

        $result = query(UserSchema::class)
            ->where('id', $userId)
            ->derive('files.comments')
            ->first();

        $this->assertInstanceOf(User::class, $result->data);
        $this->assertCount(1, $result->data->files);
        
        $file = $result->data->files[0];
        $this->assertSame('document.txt', $file->name);
        
        // Deeply nested empty hasMany relation must be an empty array
        $this->assertSame([], $file->comments);
    }

    public function testHydratorPreservesStringAndFloatPrimaryKeys()
    {
        $ref = new \ReflectionClass(\Jengo\Schema\Hydration\Hydrator::class);
        $method = $ref->getMethod('hydrateNode');
        $method->setAccessible(true);

        $schema = \Jengo\Schema\Reflection\SchemaReflector::reflect(UserSchema::class);
        $node = new \Jengo\Schema\Graph\Node($schema);
        $options = new \Jengo\Schema\Query\DTO\QueryOptions();
        $plan = new \Jengo\Schema\Query\QueryPlan($node, $options);
        \Jengo\Schema\Query\Query::set(\Jengo\Schema\Query\QueryPlan::class, $plan);

        $rows = [
            [
                't_0_root__id' => '0123',
                't_0_root__first_name' => 'Alice',
                't_0_root__last_name' => 'Smith',
                't_0_root__email' => 'alice@example.com',
            ],
            [
                't_0_root__id' => '1.5',
                't_0_root__first_name' => 'Bob',
                't_0_root__last_name' => 'Jones',
                't_0_root__email' => 'bob@example.com',
            ]
        ];

        $hydrator = new \Jengo\Schema\Hydration\Hydrator($plan, $rows);
        $selfProp = $ref->getProperty('self');
        $selfProp->setAccessible(true);
        $selfProp->setValue(null, $hydrator);

        $result = $method->invoke(null, $node, $plan, $rows);

        $this->assertCount(2, $result);
        $this->assertSame('0123', $result[0]['id']);
        $this->assertSame('1.5', $result[1]['id']);
    }
}
