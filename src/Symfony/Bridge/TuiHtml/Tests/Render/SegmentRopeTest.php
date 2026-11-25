<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\ArrayLineBuffer;
use Symfony\Bridge\TuiHtml\Render\SegmentRope;
use Symfony\Component\Tui\Render\LineBufferInterface;

class SegmentRopeTest extends TestCase
{
    public static function provideSizes(): iterable
    {
        foreach ([1, 2, 3, 4, 5, 16, 17, 23, 64, 70] as $size) {
            yield "$size parts" => [$size];
        }
    }

    public function testPartsReplacedInTurnBeforeTheRootIsReadAgain()
    {
        $rope = new SegmentRope(array_map(static fn (int $i): array => [new ArrayLineBuffer(["$i a", "$i b"])], range(0, 9)));
        $rope->getRoot()->toArray();
        $rope->replace(2, [[new ArrayLineBuffer(['two'])], [new ArrayLineBuffer([])]]);
        $rope->replace(8, [[new ArrayLineBuffer(['eight', 'more', 'lines'])]]);
        $rope->getRoot()->slice(0, 2);
        $rope->replace(0, [[new ArrayLineBuffer(['zero'])]]);

        $this->assertSame(['zero', '1 a', '1 b', 'two', '4 a', '4 b', '5 a', '5 b', '6 a', '6 b', '7 a', '7 b', 'eight', 'more', 'lines', '9 a', '9 b'], $rope->getRoot()->toArray());
    }

    public function testMostOfTheLinesOfARootAreThoseAsked()
    {
        $rope = new SegmentRope(array_map(static fn (int $i): array => [new ArrayLineBuffer(["$i a", "$i b"])], range(0, 4)));
        $this->assertSame(['0 b', '1 a', '1 b', '2 a', '2 b', '3 a', '3 b', '4 a'], $rope->getRoot()->slice(1, 8));
        $rope->replace(2, [[new ArrayLineBuffer(['two'])]]);

        $this->assertSame(['0 b', '1 a', '1 b', 'two', '3 a', '3 b'], $rope->getRoot()->slice(1, 6));
        $this->assertSame(['1 b', 'two', '3 a', '3 b', '4 a'], $rope->getRoot()->slice(3, 5));
    }

    public function testTheLinesOfARootAreMadeOnlyWhenMostOfThemAreRead()
    {
        $rope = new SegmentRope(array_map(static fn (int $i): array => [new ArrayLineBuffer(array_fill(0, 2000, "line $i"))], range(1, 64)));
        $rope->getRoot()->toArray();
        $before = memory_get_usage();
        for ($i = 0; $i < 5; ++$i) {
            $rope->replace($i, [[new ArrayLineBuffer(["part $i"])]]);
        }
        $this->assertSame(['part 0', 'part 1', 'part 2', 'part 3', 'part 4', 'line 6'], $rope->getRoot()->slice(0, 6));
        $root = $rope->getRoot();
        $this->assertSame(['line 64', 'line 64'], $root->slice(\count($root) - 2, \PHP_INT_MAX));

        // Not the 118,000 lines of the root after each part replaced, nor for a few lines read
        $this->assertFalse($root->hasKeptLines());
        $this->assertLessThan(200000, memory_get_usage() - $before);
        $this->assertCount(118005, $root->toArray());
        $this->assertTrue($root->hasKeptLines());
    }

    public function testRopesInEachOtherKeepABoundedNumberOfCopiesOfTheirLines()
    {
        $build = static function (int $depth) use (&$build): LineBufferInterface {
            return (new SegmentRope([[new ArrayLineBuffer(['a'])], [0 === $depth ? new ArrayLineBuffer(array_fill(0, 20000, 'l')) : $build($depth - 1)], [new ArrayLineBuffer(['b'])]]))->getRoot();
        };
        $root = $build(16);
        $before = memory_get_usage();
        $root->toArray();

        // Two copies, in arrays of up to twice as many slots, not one for each of the 16 ropes
        $this->assertLessThan(2 * 2 * 16 * 20032, memory_get_usage() - $before);
    }

    public function testAFewLinesOfARopeKeepNoMoreLines()
    {
        $rope = new SegmentRope(array_map(static fn (int $i): array => [new ArrayLineBuffer(array_fill(0, 2000, "line $i"))], range(1, 64)));
        $rope->replace(0, [[new ArrayLineBuffer(array_fill(0, 2000, 'changed'))]]);
        $before = memory_get_usage();
        $lines = $rope->getRoot()->slice(0, 24);

        $this->assertSame(array_fill(0, 24, 'changed'), $lines);
        // Not the 128,000 lines of the nodes holding them, about 2 MB
        $this->assertLessThan(100000, memory_get_usage() - $before);
    }

    public function testTheRootOfARopeReadKeepsItsLinesNotThoseOfItsNodes()
    {
        $inner = (new SegmentRope(array_map(static fn (int $i): array => [new ArrayLineBuffer(array_fill(0, 1000, "line $i"))], range(1, 64))))->getRoot();
        $before = memory_get_usage();
        foreach ($inner as $line) {
        }
        $kept = memory_get_usage() - $before;
        $outer = new SegmentRope([[new ArrayLineBuffer(['before'])], [$inner], [new ArrayLineBuffer(['after'])]]);
        $this->assertCount(64002, $outer->getRoot()->toArray());

        // Its lines, about 1 MB, not those of its nodes too, 3 MB
        $this->assertLessThan(1.5 * 16 * 64000, $kept);
        $this->assertLessThan(2.5 * 16 * 64000, memory_get_usage() - $before);
    }

    public function testTheRopesInThePartsOfAnotherDoNotKeepTheirLinesToo()
    {
        $build = static function (int $depth) use (&$build): LineBufferInterface {
            $leaves = [];
            for ($i = 0; $i < 8; ++$i) {
                $leaves[] = [0 === $depth ? new ArrayLineBuffer(array_map(static fn (int $row): string => "line $i $row", range(1, 9))) : $build($depth - 1)];
            }

            return (new SegmentRope($leaves))->getRoot();
        };
        $root = $build(3);
        $before = memory_get_usage();
        $lines = $root->toArray();

        $this->assertCount(36864, $lines);
        // The lines of the root of each of the 4 ropes in each other, in arrays of up to twice as many slots: not those of
        // every node of each rope too
        $this->assertLessThan(2.5 * 4 * 16 * \count($lines), memory_get_usage() - $before);
    }

    public function testThePartsLeftAreNotReadAgainOnceAPartIsReplaced()
    {
        $reads = 0;
        $leaves = [];
        for ($i = 0; $i < 16; ++$i) {
            $leaves[] = [new class(["line $i a", "line $i b"], $reads) implements LineBufferInterface {
                private ArrayLineBuffer $lines;

                public function __construct(array $lines, private int &$reads)
                {
                    $this->lines = new ArrayLineBuffer($lines);
                }

                public function count(): int
                {
                    return \count($this->lines);
                }

                public function getIterator(): \Traversable
                {
                    ++$this->reads;

                    return $this->lines->getIterator();
                }

                public function getLine(int $index): string
                {
                    ++$this->reads;

                    return $this->lines->getLine($index);
                }

                public function slice(int $offset, int $length): array
                {
                    ++$this->reads;

                    return $this->lines->slice($offset, $length);
                }

                public function toArray(): array
                {
                    ++$this->reads;

                    return $this->lines->toArray();
                }

                public function getMaxVisibleWidth(): int
                {
                    return $this->lines->getMaxVisibleWidth();
                }
            }];
        }
        $rope = new SegmentRope($leaves);
        $this->assertCount(32, $rope->getRoot()->toArray());
        $this->assertSame(16, $reads);

        // Read after a part is replaced too
        $rope->replace(12, [[new ArrayLineBuffer(['first'])]]);
        $this->assertSame('first', $rope->getRoot()->toArray()[24]);

        $reads = 0;
        $rope->replace(5, [[new ArrayLineBuffer(['changed'])]]);

        $this->assertSame('changed', $rope->getRoot()->toArray()[10]);
        // The lines of the others taken from those of the root before
        $this->assertSame(0, $reads, 'The lines of the parts left are kept by the root.');
    }

    #[DataProvider('provideSizes')]
    public function testLinesMatchThePartsAfterReplacements(int $size)
    {
        $leaves = [];
        for ($i = 0; $i < $size; ++$i) {
            $leaves[] = self::part($i, 0);
        }
        $rope = new SegmentRope($leaves);
        $this->assertRope($leaves, $rope);

        // A part, the first, the last, a run across groups, a part made empty
        foreach ([[intdiv($size, 2), 1], [0, 1], [$size - 1, 1], [max(0, $size - 6), min($size, 6)], [0, 0]] as $round => [$from, $count]) {
            $parts = [];
            for ($i = $from; $i < $from + $count; ++$i) {
                $parts[] = 0 === $count % 2 && $i === $from ? [] : self::part($i, $round + 1);
            }
            $rope->replace($from, $parts);
            array_splice($leaves, $from, \count($parts), $parts);
            $this->assertRope($leaves, $rope);
        }
    }

    /**
     * @param list<list<ArrayLineBuffer>> $leaves
     */
    private function assertRope(array $leaves, SegmentRope $rope)
    {
        $lines = [];
        foreach ($leaves as $i => $buffers) {
            $this->assertSame(\count($lines), $rope->linesBefore($i), "lines before $i");
            $count = 0;
            foreach ($buffers as $buffer) {
                array_push($lines, ...$buffer->toArray());
                $count += \count($buffer);
            }
            $this->assertSame($count, $rope->linesOf($i), "lines of $i");
        }
        $this->assertSame(\count($leaves), $rope->count());
        $this->assertSame($lines, $rope->getRoot()->toArray());
    }

    /**
     * @return list<ArrayLineBuffer>
     */
    private static function part(int $i, int $version): array
    {
        // One buffer, or several, of a line or two
        return array_map(static fn (int $j): ArrayLineBuffer => new ArrayLineBuffer(array_fill(0, 1 + ($i + $j) % 2, "p$i.$j v$version")), range(0, $i % 3));
    }
}
