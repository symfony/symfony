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
use Symfony\Component\JsonEncoder\Exception\InvalidStreamException;

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
    public function testSplitDictInvalidThrowException(string $expectedMessage, string $content)
    {
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage($expectedMessage);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        iterator_to_array((new Splitter())->splitDict($resource));
    }

    /**
     * @return iterable<array{0: list<array{0: string, 1: string}>}>
     */
    public static function splitDictInvalidDataProvider(): iterable
    {
        yield ['Unterminated JSON.', '{"foo":1'];
        yield ['Unexpected "{" token.', '{{}'];
        yield ['Unexpected "}" token.', '}'];
        yield ['Unexpected "}" token.', '{}}'];
        yield ['Unexpected "," token.', ','];
        yield ['Unexpected "," token.', '{"foo",}'];
        yield ['Unexpected ":" token.', ':'];
        yield ['Unexpected ":" token.', '{:'];
        yield ['Unexpected "0" token.', '{"foo" 0}'];
        yield ['Expected scalar value, but got "_".', '{"foo":_'];
        yield ['Expected dict key, but got "100".', '{100'];
        yield ['Got "foo" dict key twice.', '{"foo":1,"foo"'];
        yield ['Expected end, but got ""x"".', '{"a": true} "x"'];
    }

    /**
     * @dataProvider splitListInvalidDataProvider
     */
    public function testSplitListInvalidThrowException(string $expectedMessage, string $content)
    {
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage($expectedMessage);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        iterator_to_array((new Splitter())->splitList($resource));
    }

    /**
     * @return iterable<array{0: string, 1: string}>
     */
    public static function splitListInvalidDataProvider(): iterable
    {
        yield ['Unterminated JSON.', '[100'];
        yield ['Unexpected "[" token.', '[]['];
        yield ['Unexpected "]" token.', ']'];
        yield ['Unexpected "]" token.', '[]]'];
        yield ['Unexpected "," token.', ','];
        yield ['Unexpected "," token.', '[100,,]'];
        yield ['Unexpected ":" token.', ':'];
        yield ['Unexpected ":" token.', '[100:'];
        yield ['Unexpected "0" token.', '[1 0]'];
        yield ['Expected scalar value, but got "_".', '[_'];
        yield ['Expected end, but got "100".', '{"a": true} 100'];
    }

    private function assertListBoundaries(?array $expectedBoundaries, string $content, int $offset = 0, ?int $length = null): void
    {
        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitList($resource, $offset, $length);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitList($resource, $offset, $length);
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

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $content);
        rewind($resource);

        $boundaries = (new Splitter())->splitDict($resource, $offset, $length);
        $boundaries = null !== $boundaries ? iterator_to_array($boundaries) : null;

        $this->assertSame($expectedBoundaries, $boundaries);
    }
}
