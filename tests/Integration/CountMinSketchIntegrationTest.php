<?php
/* @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Palicao\PhpRebloom\Tests\Integration;

use Palicao\PhpRebloom\CountMinSketch;
use Palicao\PhpRebloom\CountMinSketchInfo;
use Palicao\PhpRebloom\Pair;

class CountMinSketchIntegrationTest extends IntegrationTestCase
{

    /**
     * @var CountMinSketch
     */
    private $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = new CountMinSketch($this->redisClient);
    }

    public function testInitByDimensions(): void
    {
        $this->sut->initByDimensions('initByDim', 3000, 40);
    }

    public function testInitByProbability(): void
    {
        $this->sut->initByProbability('initByProb', .001, .01);
    }

    public function testIncrementBy(): void
    {
        $key = 'incrementByTest';
        $this->sut->initByDimensions($key, 3000, 40);
        $this->sut->incrementBy($key, new Pair('a', 100), new Pair('b', 200));
        $this->sut->incrementBy($key, new Pair('a', 20), new Pair('b', 10));

        $expected = [new Pair('a', 120), new Pair('b', 210)];
        $this->assertEquals($expected, $this->sut->query($key, 'a', 'b'));
    }

    public function testMerge(): void
    {
        $this->sut->initByDimensions('source1', 3000, 40);
        $this->sut->incrementBy('source1', new Pair('a', 10), new Pair('b', 20));

        $this->sut->initByDimensions('source2', 3000, 40);
        $this->sut->incrementBy('source2', new Pair('a', 20), new Pair('c', 30));

        $this->sut->initByDimensions('destination', 3000, 40);
        $result = $this->sut->merge('destination', ['source1' => 3, 'source2' => 5]);

        $this->assertTrue($result);

        $expected = [new Pair('a', 130), new Pair('b', 60), new Pair('c', 150)];
        $this->assertEquals($expected, $this->sut->query('destination', 'a', 'b', 'c'));
    }

    public function testInfo(): void
    {
        $key = 'infoTest';
        $this->sut->initByDimensions($key, 3000, 40);
        $this->sut->incrementBy($key, new Pair('a', 10), new Pair('b', 20));

        $expected = new CountMinSketchInfo($key, 3000, 40, 30);
        $result = $this->sut->info($key);

        $this->assertEquals($expected, $result);
    }

}