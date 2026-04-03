<?php

declare(strict_types=1);

namespace Tests\Unit\Query;

use CodeIgniter\I18n\Time;
use Jengo\Schema\Query\FluentQueryAPI;
use Jengo\Schema\Query\Query;
use Jengo\Schema\Query\DTO\QueryResult;
use Jengo\Schema\Query\Enums\QueryMode;
use Jengo\Schema\Query\Enums\SortOrder;
use function Jengo\Schema\dump_query;
use function Jengo\Schema\query;
use Tests\Support\Entity\User;
use Tests\Support\Schemas\UserSchema;

final class FluentQueryAPITest extends QueryTestCase
{
    public function setUp(): void
    {
        $this->fill = false;
        parent::setUp();
    }

    private function seedUser(array $data): int
    {
        $this->db->table('users')->insert($data);
        return (int) $this->db->insertID();
    }

    public function testWhereAndFirstReturnsSingleEntity(): void
    {
        $id = $this->seedUser([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'created_at' => Time::now()->toDateTimeString(),
            'updated_at' => Time::now()->toDateTimeString(),
        ]);

        $entity = Query::run(UserSchema::class, (new \Jengo\Schema\Query\DTO\QueryOptions(
            params: new \Jengo\Schema\Query\DTO\ParamOptions(['first_name' => 'Alice']),
            first: true
        )));

        $this->assertInstanceOf(QueryResult::class, $entity);
        $this->assertInstanceOf(User::class, $entity->data);
        $this->assertSame('Alice', $entity->data->first_name);
        $this->assertSame(1, $entity->count);

        // exercise fluent where + first
        $user = query(UserSchema::class)
            ->where('first_name', 'Alice')
            ->first()->data;

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Alice', $user->first_name);
    }

    public function testOrWhereAndWhereNot(): void
    {
        $this->seedUser(['first_name' => 'Bob', 'last_name' => 'Billy', 'email' => 'bob@example.com']);
        $this->seedUser(['first_name' => 'Carol', 'last_name' => 'Jones', 'email' => 'carol@example.com']);


        $rows = query(UserSchema::class)
            ->select('first_name', 'last_name')
            ->where('first_name', 'Bob')
            ->orWhere('first_name', 'Carol')
            ->sort('created_at', SortOrder::DESC)
            ->get();

        $this->assertInstanceOf(QueryResult::class, $rows);
        $this->assertGreaterThanOrEqual(2, $rows->count);

        $notBob = query(UserSchema::class)
            ->where('first_name', 'Bob')
            ->whereNot('first_name', 'Bob')
            ->get();

        $this->assertSame(0, $notBob->count);
    }

    public function testWhereInAndWhereNotIn(): void
    {
        $id1 = $this->seedUser(['first_name' => 'A1', 'last_name' => 'L1', 'email' => 'a1@example.com']);
        $id2 = $this->seedUser(['first_name' => 'A2', 'last_name' => 'L2', 'email' => 'a2@example.com']);
        $id3 = $this->seedUser(['first_name' => 'A3', 'last_name' => 'L3', 'email' => 'a3@example.com']);

        $fromIn = query(UserSchema::class)->whereIn('id', [$id1, $id2])->get();
        $this->assertCount(2, $fromIn->data);

        $fromNotIn = query(UserSchema::class)->whereNotIn('id', [$id1, $id2])->get();
        $this->assertCount(1, $fromNotIn->data);

        $orNotIn = query(UserSchema::class)->orWhereNotIn('id', [$id1, $id2])->get();
        $this->assertCount(1, $orNotIn->data);
    }

    public function testPaginationAndSorting(): void
    {
        $this->seedUser(['first_name' => 'aaa', 'last_name' => 'z', 'email' => 'a@example.com', 'created_at' => Time::now()->toDateTimeString(), 'updated_at' => Time::now()->toDateTimeString()]);
        $this->seedUser(['first_name' => 'bbb', 'last_name' => 'y', 'email' => 'b@example.com', 'created_at' => Time::now()->toDateTimeString(), 'updated_at' => Time::now()->toDateTimeString()]);
        $this->seedUser(['first_name' => 'ccc', 'last_name' => 'x', 'email' => 'c@example.com', 'created_at' => Time::now()->toDateTimeString(), 'updated_at' => Time::now()->toDateTimeString()]);

        $page1 = query(UserSchema::class)->paginate(1, 2)->sort('first_name', SortOrder::DESC)->get();
        $this->assertCount(2, $page1->data);
        $this->assertSame('ccc', $page1->data[0]->first_name);

        $page2 = query(UserSchema::class)->paginate(2, 2)->get();
        $this->assertCount(1, $page2->data);
    }

    public function testSearch(): void
    {
        $this->seedUser(['first_name' => 'Searchable', 'last_name' => 'Thing', 'email' => 'search-term@example.com']);
        $this->seedUser(['first_name' => 'Other', 'last_name' => 'Thing', 'email' => 'other@example.com']);

        $result = query(UserSchema::class)->search('search-term')->get();

        $this->assertGreaterThanOrEqual(1, $result->count);
        $this->assertSame('Searchable', $result->data[0]->first_name);
    }

    public function testWhereSinceTodayLtGtNullMethods(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $idToday = $this->seedUser(['first_name' => 'Today', 'last_name' => 'Test', 'email' => 'today@example.com', 'created_at' => $today, 'updated_at' => $today]);
        $idYesterday = $this->seedUser(['first_name' => 'Yesterday', 'last_name' => 'Test', 'email' => 'yesterday@example.com', 'created_at' => $yesterday, 'updated_at' => $yesterday]);

        $todayRows = query(UserSchema::class)->whereToday()->get();
        $this->assertInstanceOf(QueryResult::class, $todayRows);
        $this->assertSame(1, $todayRows->count);

        $sinceRows = query(UserSchema::class)->whereSince('-2 day')->get();
        $this->assertInstanceOf(QueryResult::class, $sinceRows);
        $this->assertSame(2, $sinceRows->count);

        $lteRows = query(UserSchema::class)->whereLTe('id', $idToday)->get();
        $this->assertGreaterThanOrEqual(1, $lteRows->count);

        $gteRows = query(UserSchema::class)->whereGt('id', 0)->get();
        $this->assertGreaterThanOrEqual(2, $gteRows->count);

        $ltRows = query(UserSchema::class)->whereLt('id', $idToday + 1)->get();
        $this->assertGreaterThanOrEqual(1, $ltRows->count);

        $nullRows = query(UserSchema::class)->whereNull('email')->get();
        $this->assertSame(0, $nullRows->count);

        $notNullRows = query(UserSchema::class)->whereNotNull('email')->get();
        $this->assertGreaterThanOrEqual(2, $notNullRows->count);
    }

    public function testWhenAndSelectAndWithAndCallbackAndModeAliases(): void
    {
        $id = $this->seedUser(['first_name' => 'Alias', 'last_name' => 'Test', 'email' => 'alias@example.com']);

        $base = query(UserSchema::class)
            ->select('id', 'first_name')
            ->with('profile')
            ->whereCallback('dummy', fn($key, $value, $boolean, $phase) => [$key, $value]);

        $result = $base->when(true, fn($q) => $q->where('id', $id))->first();

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertInstanceOf(User::class, $result->data);
        $this->assertSame('Alias', $result->data->first_name);

        $clone = $base->clone()->where('first_name', 'NoMatch')->first();
        $this->assertNull($clone->data);
        $exists = query(UserSchema::class)->where('first_name', 'Alias')->exists();
        $this->assertTrue($exists);

        $count = query(UserSchema::class)->where('first_name', 'Alias')->count();
        $this->assertSame(1, $count);

        // mode shorthands
        $this->assertSame($base->inline(), $base->inlineMode());
        $this->assertSame($base->open(), $base->openMode());
        $this->assertSame($base->withQueryMode(QueryMode::INLINE), $base);
        $this->assertSame($base->log(true), $base); // debug alias
    }
}

