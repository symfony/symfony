<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\Splitter;
use Symfony\Component\JsonEncoder\Exception\UnexpectedValueException;
use Symfony\Component\JsonEncoder\Stream\BufferedStream;

class SplitterTest extends TestCase
{
    public function testSplitNull()
    {
        $this->assertListBoundaries(null, 'null');
        $this->assertDictBoundaries(null, 'null');
    }

    public function testSplitList()
    {
        $this->assertListBoundaries([], '[]');
        $this->assertListBoundaries([[1, 3]], '[100]');
        $this->assertListBoundaries([[1, 3], [5, 3]], '[100,200]');
        $this->assertListBoundaries([[1, 1], [3, 5]], '[1,[2,3]]');
        $this->assertListBoundaries([[1, 1], [3, 7]], '[1,{"2":3}]');
    }

    public function testSplitDict()
    {
        $this->assertDictBoundaries([], '{}');
        $this->assertDictBoundaries(['k' => [5, 2]], '{"k":10}');
        $this->assertDictBoundaries(['k' => [5, 4]], '{"k":[10]}');
    }

    /**
     * @dataProvider splitDictInvalidDataProvider
     */
    public function testSplitDictInvalidThrowException(string $content)
    {
        $this->expectException(UnexpectedValueException::class);

        $stream = new BufferedStream();
        $stream->write($content);
        $stream->rewind();

        iterator_to_array((new Splitter())->splitDict($stream));
    }

    /**
     * @return iterable<array{0: list<array{0: string, 1: int}>}>
     */
    public static function splitDictInvalidDataProvider(): iterable
    {
        yield ['{100'];
        yield ['{{}'];
        yield ['{{}]'];
    }

    /**
     * @dataProvider splitListInvalidDataProvider
     */
    public function testSplitListInvalidThrowException(string $content)
    {
        $this->expectException(UnexpectedValueException::class);

        $stream = new BufferedStream();
        $stream->write($content);
        $stream->rewind();

        iterator_to_array((new Splitter())->splitList($stream));
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function splitListInvalidDataProvider(): iterable
    {
        yield ['[100'];
        yield ['[[]'];
        yield ['[[]}'];
    }

    private function assertListBoundaries(?array $expectedBoundaries, string $content, int $offset = 0, ?int $length = null): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitList($resource, $offset, $length);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);

        $stream = new BufferedStream();
        $stream->write($content);
        $stream->rewind();

        $boundaries = (new Splitter())->splitList($stream, $offset, $length);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);
    }

    private function assertDictBoundaries(?array $expectedBoundaries, string $content, int $offset = 0, ?int $length = null): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitDict($resource, $offset, $length);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);

        $stream = new BufferedStream();
        $stream->write($content);
        $stream->rewind();

        $boundaries = (new Splitter())->splitDict($stream, $offset, $length);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);
    }
}
