<?php

namespace Jenssegers\Mongodb\Tests\Query;

use DateTime;
use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Query\Builder;
use Jenssegers\Mongodb\Tests\TestCase;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

class BuilderTest extends TestCase
{
    /**
     * @var Connection
     */
    private $db;

    public function setUp()
    {
        parent::setUp();

        $this->db = $this->app->get('db');
        $this->db->collection('users')->truncate();
    }

    public function testBuilderInstance()
    {
        $this->assertInstanceOf(Builder::class, $this->db->collection('users'));
    }

    public function testInsert()
    {
        $this->db->collection('users')->insert([
            'name' => 'John Doe',
        ]);

        $this->assertEquals(1, $this->db->collection('users')->count());
    }

    public function testGet()
    {
        $results = $this->db->collection('users')->get();
        $this->assertCount(0, $results);

        $this->db->collection('users')->insert([
            [
                'name' => 'John Doe',
                'tags' => ['tag1', 'tag2'],
            ],
        ]);

        $results = $this->db->collection('users')->get();
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results[0]->name);
        $this->assertEquals(['tag1', 'tag2'], $results[0]->tags);

        $results = $this->db->collection('users')->where('foo', 'bar')->get();
        $this->assertCount(0, $results);
    }

    public function testInsertGetId()
    {
        $id = $this->db->collection('users')->insertGetId(['name' => 'Jane Doe']);
        $this->assertInstanceOf(ObjectId::class, $id);
    }

    public function testBatchInsert()
    {
        $this->db->collection('users')->insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
        ]);

        $this->assertEquals(2, $this->db->collection('users')->count());
    }

    public function testWhere()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 21],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $result = $this->db->collection('users')->where('age', 20)->get();
        $this->assertCount(2, $result);

        $result = $this->db->collection('users')->get(['name']);
        $this->assertCount(3, $result);

        $result = $this->db->collection('users')->where('age', 22)->get();
        $this->assertCount(0, $result);
    }

    public function testFind()
    {
        $id = $this->db->collection('users')->insertGetId(['name' => 'John Doe']);

        $result = $this->db->collection('users')->find($id);
        $this->assertEquals('John Doe', $result->name);
    }

    public function testFindNull()
    {
        $result = $this->db->collection('users')->find(null);
        $this->assertNull($result);
    }

    public function testCount()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => null],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $this->assertEquals(2, $this->db->collection('users')->count('age'));
    }

    public function testUpdate()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 21],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $this->db->collection('users')->where('name', 'Jane Doe')->update(['age' => 21]);

        $result = $this->db->collection('users')->where('name', 'Jane Doe')->first();
        $this->assertEquals(21, $result->age);

        $result = $this->db->collection('users')->where('name', 'Mark Moe')->first();
        $this->assertEquals(20, $result->age);
    }

    public function testFirst()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 21],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $result = $this->db->collection('users')->first();
        $this->assertEquals('Jane Doe', $result->name);

        $result = $this->db->collection('users')->where('foo', 'bar')->first();
        $this->assertNull($result);
    }

    public function testDelete()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe'],
            ['name' => 'John Doe'],
            ['name' => 'Mark Moe'],
            ['name' => 'Larry Loe'],
        ]);

        $count = $this->db->collection('users')->where('name', 'Foo Bar')->delete();
        $this->assertEquals(0, $count);
        $this->assertEquals(4, $this->db->collection('users')->count());

        $count = $this->db->collection('users')->where('name', 'John Doe')->delete();
        $this->assertEquals(1, $count);
        $this->assertEquals(3, $this->db->collection('users')->count());

        $result = $this->db->collection('users')->where('name', 'John Doe')->first();
        $this->assertNull($result);

        $first = $this->db->collection('users')->first();
        $count = $this->db->collection('users')->delete($first->_id);
        $this->assertEquals(1, $count);
        $this->assertEquals(2, $this->db->collection('users')->count());

        $count = $this->db->collection('users')->delete('abcd');
        $this->assertEquals(0, $count);
        $this->assertEquals(2, $this->db->collection('users')->count());

        $count = $this->db->collection('users')->delete();
        $this->assertEquals(2, $count);
        $this->assertEquals(0, $this->db->collection('users')->count());
    }

    public function testWhereRegex()
    {
        $this->db->collection('users')->insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
            ['name' => 'Robert Roe'],
        ]);

        $regex = new Regex('.*doe', 'i');
        $results = $this->db->collection('users')->where('name', 'regex', $regex)->get();
        $this->assertCount(2, $results);

        $regex = new Regex('.*doe', 'i');
        $results = $this->db->collection('users')->where('name', 'regexp', $regex)->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->where('name', 'regexp', '/.*doe/i')->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->where('name', 'not regexp', '/.*doe/i')->get();
        $this->assertCount(1, $results);
    }

    public function testWhereLike()
    {
        $this->db->collection('users')->insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
            ['name' => 'Robert Roe'],
        ]);

        $results = $this->db->collection('users')->where('name', 'like', '%doe%')->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->where('name', 'like', '%oe')->get();
        $this->assertCount(3, $results);

        $results = $this->db->collection('users')->where('name', 'like', 'j%')->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->where('name', 'like', 'x')->get();
        $this->assertCount(0, $results);
    }

    public function testCustomOperators()
    {
        $this->db->collection('users')->insert([
            [
                'name' => 'John Doe',
                'age' => 30,
                'addresses' => [
                    ['city' => 'Ghent'],
                    ['city' => 'Paris'],
                ],
                'tags' => ['one', 'two'],
            ],
            [
                'name' => 'Jane Doe',
                'addresses' => [
                    ['city' => 'Brussels'],
                    ['city' => 'Paris'],
                ],
                'tags' => ['one', 'two', 'three', 'four'],
            ],
            [
                'name' => 'Robert Roe',
                'age' => 'thirty-one',
                'tags' => ['three', 'four'],
            ],
        ]);

        $results = $this->db->collection('users')->where('age', 'exists', true)->get();
        $this->assertCount(2, $results);

        $resultsNames = [$results[0]->name, $results[1]->name];
        $this->assertContains('John Doe', $resultsNames);
        $this->assertContains('Robert Roe', $resultsNames);

        $results = $this->db->collection('users')->where('age', 'exists', false)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);

        $results = $this->db->collection('users')->where('age', 'type', 2)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Robert Roe', $results->first()->name);

        $results = $this->db->collection('users')->where('age', 'mod', [15, 0])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);

        $results = $this->db->collection('users')->where('age', 'mod', [29, 1])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);

        $results = $this->db->collection('users')->where('age', 'mod', [14, 0])->get();
        $this->assertCount(0, $results);

        $results = $this->db->collection('users')->where('tags', 'all', ['one', 'two'])->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->where('tags', 'all', ['one', 'three'])->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->where('tags', 'size', 2)->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->where('tags', 'size', 3)->get();
        $this->assertCount(0, $results);

        $results = $this->db->collection('users')->where('tags', 'size', 4)->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->where('addresses', 'elemMatch', ['city' => 'Brussels'])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);
    }

    public function testWhereIn()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 21],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $results = $this->db->collection('users')->whereIn('age', [20, 21])->get();
        $this->assertCount(3, $results);

        $results = $this->db->collection('users')->whereIn('age', [20])->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->whereIn('age', [21])->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereIn('age', [])->get();
        $this->assertCount(0, $results);
    }

    public function testWhereNotIn()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 21],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $results = $this->db->collection('users')->whereNotIn('age', [20, 21])->get();
        $this->assertCount(0, $results);

        $results = $this->db->collection('users')->whereNotIn('age', [20])->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereNotIn('age', [21])->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->whereNotIn('age', [])->get();
        $this->assertCount(3, $results);
    }

    public function testWhereNull()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => null],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $results = $this->db->collection('users')->whereNull('age')->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereNull('foo')->get();
        $this->assertCount(3, $results);

        $results = $this->db->collection('users')->whereNull('name')->get();
        $this->assertCount(0, $results);
    }

    public function testWhereNotNull()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => null],
            ['name' => 'Mark Moe', 'age' => 20],
        ]);

        $results = $this->db->collection('users')->whereNotNull('age')->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->whereNotNull('foo')->get();
        $this->assertCount(0, $results);

        $results = $this->db->collection('users')->whereNotNull('name')->get();
        $this->assertCount(3, $results);
    }

    public function testWhereBetween()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Mark Moe', 'age' => 25],
            ['name' => 'Larry Loe', 'age' => 40],
        ]);

        $results = $this->db->collection('users')->whereBetween('age', [19, 21])->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereBetween('age', [20, 20])->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereBetween('age', [20, 30])->get();
        $this->assertCount(3, $results);

        $results = $this->db->collection('users')->whereBetween('age', [5, 10])->get();
        $this->assertCount(0, $results);
    }

    public function testWhereNotBetween()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Mark Moe', 'age' => 25],
            ['name' => 'Larry Loe', 'age' => 40],
        ]);

        $results = $this->db->collection('users')->whereNotBetween('age', [19, 21])->get();
        $this->assertCount(3, $results);

        $results = $this->db->collection('users')->whereNotBetween('age', [20, 20])->get();
        $this->assertCount(3, $results);

        $results = $this->db->collection('users')->whereNotBetween('age', [20, 30])->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereNotBetween('age', [5, 10])->get();
        $this->assertCount(4, $results);
    }

    public function testWhereDate()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'birthday' => new UTCDateTime(new DateTime('1990/01/01 10:00:00'))],
            ['name' => 'John Doe', 'birthday' => new UTCDateTime(new DateTime('1980/03/01 11:00:00'))],
            ['name' => 'Mark Moe', 'birthday' => new UTCDateTime(new DateTime('1970/03/01 12:00:00'))],
            ['name' => 'Larry Loe', 'birthday' => new UTCDateTime(new DateTime('1960/04/01 13:00:00'))],
        ]);

        $results = $this->db->collection('users')->whereYear('birthday', 1990)->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereDay('birthday', 1)->get();
        $this->assertCount(4, $results);

        $results = $this->db->collection('users')->whereMonth('birthday', 3)->get();
        $this->assertCount(2, $results);

        $results = $this->db->collection('users')->whereDate('birthday', '1970-03-01')->get();
        $this->assertCount(1, $results);

        $results = $this->db->collection('users')->whereTime('birthday', '12:00:00')->get();
        $this->assertCount(1, $results);
    }

    public function testWhereRaw()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Mark Moe', 'age' => 25],
            ['name' => 'Larry Loe', 'age' => 40],
        ]);

        $results = $this->db->collection('users')->whereRaw([
            'age' => ['$in' => [20, 30]],
        ])->get();
        $this->assertCount(2, $results);
    }

    public function testAddFields()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'foo' => 1, 'bar' => 5],
            ['name' => 'John Doe', 'foo' => 2, 'bar' => 6],
            ['name' => 'Mark Moe', 'foo' => 3, 'bar' => 7],
            ['name' => 'Larry Loe', 'foo' => 4, 'bar' => 8],
        ]);

        $results = $this->db->collection('users')
            ->addField('sum', ['$add' => ['$foo', '$bar']])
            ->addField('max', ['$max' => ['$foo', '$bar']])
            ->get();

        $this->assertEquals(6, $results->first()->sum);
        $this->assertEquals(5, $results->first()->max);
    }

    public function testLimit()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Mark Moe', 'age' => 25],
            ['name' => 'Larry Loe', 'age' => 40],
        ]);

        $results = $this->db->collection('users')->limit(1)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Jane Doe', $results->first()->name);

        $results = $this->db->collection('users')->limit(2)->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results->last()->name);

        $results = $this->db->collection('users')
            ->whereBetween('age', [21, 100])->limit(2)->get();
        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    public function testOffset()
    {
        $this->db->collection('users')->insert([
            ['name' => 'Jane Doe', 'age' => 20],
            ['name' => 'John Doe', 'age' => 30],
            ['name' => 'Mark Moe', 'age' => 25],
            ['name' => 'Larry Loe', 'age' => 40],
        ]);

        $results = $this->db->collection('users')->offset(1)->get();
        $this->assertCount(3, $results);
        $this->assertEquals('John Doe', $results->first()->name);

        $results = $this->db->collection('users')->offset(2)->get();
        $this->assertCount(2, $results);
        $this->assertEquals('Mark Moe', $results->first()->name);

        $results = $this->db->collection('users')->offset(2)->limit(1)->get();
        $this->assertCount(1, $results);
        $this->assertEquals('Mark Moe', $results->first()->name);
    }
}
