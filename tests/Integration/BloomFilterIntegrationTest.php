<?php
/* @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Palicao\PhpRebloom\Tests\Integration;

use Palicao\PhpRebloom\BloomFilter;
use Palicao\PhpRebloom\Exception\ErrorRateOutOfBoundsException;
use Palicao\PhpRebloom\Exception\KeyNotFoundException;

class BloomFilterIntegrationTest extends IntegrationTestCase
{
    /**
     * @var BloomFilter
     */
    private $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = new BloomFilter($this->redisClient);
    }

    public function testReserveCreatesKey(): void
    {
        $this->sut->reserve('reserveTest', .0001, 100);
        $result = (bool)$this->redis->exists('reserveTest');
        $this->assertTrue($result);
    }

    public function testInsert(): void
    {
        $this->sut->insert('insertTest', 'foo', .0001, 100);
        $this->assertTrue($this->sut->exists('insertTest', 'foo'));
        $this->assertFalse($this->sut->exists('insertTest', 'bar'));
    }

    public function testInsertWithoutErrorAndCapacity(): void
    {
        $this->sut->insert('insert2Test', 'foo');
        $this->assertTrue($this->sut->exists('insert2Test', 'foo'));
        $this->assertFalse($this->sut->exists('insert2Test', 'bar'));
    }

    public function testErrorOutOfBounds(): void
    {
        $this->expectException(ErrorRateOutOfBoundsException::class);
        $this->sut->insert('outOfBoundsTest', 'foo', 2.0, 10);
    }

    public function testInsertMany(): void
    {
        $this->sut->insertMany('insertManyTest', ['pear', 'orange', 'banana'], .0001, 100);
        $this->assertTrue($this->sut->exists('insertManyTest', 'orange'));
        $this->assertFalse($this->sut->exists('insertManyTest', 'pineapple'));
    }

    public function testInsertManyEmpty(): void
    {
        $this->assertEquals([], $this->sut->insertMany('insertManyTest', []));
    }

    public function testInsertManyWithoutErrorAndCapacity(): void
    {
        $this->sut->insertMany('insertMany2Test', ['pear', 'orange', 'banana']);
        $this->assertTrue($this->sut->exists('insertMany2Test', 'orange'));
        $this->assertFalse($this->sut->exists('insertMany2Test', 'pineapple'));
    }

    public function testInsertIfKeyExists(): void
    {
        $key = 'insertIfKeyExistsTest';
        $this->sut->reserve($key, .0001, 100);
        $this->sut->insertIfKeyExists($key, 'kiwi');
        $this->assertTrue($this->sut->exists($key, 'kiwi'));
    }

    public function testInsertManyIfKeyExists(): void
    {
        $key = 'insertManyIfKeyExistsTest';
        $this->sut->reserve($key, .0001, 100);
        $this->sut->insertManyIfKeyExists($key, ['pear', 'orange', 'banana']);
        $this->assertTrue($this->sut->exists($key, 'orange'));
        $this->assertFalse($this->sut->exists($key, 'pineapple'));
    }

    public function testInsertIfKeyExistsOnNonExistingKey(): void
    {
        $this->expectException(KeyNotFoundException::class);
        $this->sut->insertIfKeyExists('missingKey', 'foo');
    }

    public function testInsertManyIfKeyExistsOnNonExistingKey(): void
    {
        $this->expectException(KeyNotFoundException::class);
        $this->sut->insertManyIfKeyExists('missingKeyMany', ['foo', 'bar', 'baz']);
    }

    public function testManyExists(): void
    {
        $key = 'manyExistsTest';
        $this->sut->insertMany($key, ['pear', 'orange', 'banana']);
        $this->assertEquals([true, true], $this->sut->manyExist($key, ['orange', 'banana']));
        $this->assertEquals([false, false], $this->sut->manyExist($key, ['pineapple', 'strawberry']));
        $this->assertEquals([false, true], $this->sut->manyExist($key, ['watermelon', 'orange']));
    }

    public function testCopy(): void
    {
        $this->sut->insertMany('copyFrom', ['pear', 'orange', 'banana']);
        $this->sut->copy('copyFrom', 'copyTo');
        $expected = [true, true, true, false, false, false];
        $returned = $this->sut->manyExist(
            'copyTo',
            ['pear', 'orange', 'banana', 'pineapple', 'strawberry', 'watermelon']
        );
        $this->assertEquals($expected, $returned);
    }
}
