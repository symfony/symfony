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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Exception\OutOfBoundsException;
use Symfony\Bridge\TuiHtml\Render\ArrayLineBuffer;
use Symfony\Bridge\TuiHtml\Render\ConcatenatedLineBuffer;

class ConcatenatedLineBufferTest extends TestCase
{
    public function testGetLineThrowsOnOutOfBoundsIndex()
    {
        $this->expectException(OutOfBoundsException::class);
        (new ConcatenatedLineBuffer([new ArrayLineBuffer(['A'])]))->getLine(1);
    }

    public function testItsLinesAreThoseOfItsBuffersInOrder()
    {
        $lines = new ConcatenatedLineBuffer([new ArrayLineBuffer(['a', 'b']), new ArrayLineBuffer([]), new ConcatenatedLineBuffer([new ArrayLineBuffer(['c']), new ArrayLineBuffer(['d', 'e'])])]);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], iterator_to_array($lines));
        $this->assertSame(['b', 'c', 'd'], $lines->slice(1, 3));
        $this->assertSame(['e'], $lines->slice(4, 9));
        $this->assertSame([], $lines->slice(5, 1));
    }

    public function testTheBuffersJoinedInOneJoinedAtOnceDoNotKeepTheirLinesToo()
    {
        $lines = new ArrayLineBuffer(array_map(static fn (int $i): string => "line $i", range(1, 50000)));
        for ($depth = 0; $depth < 10; ++$depth) {
            $lines = new ConcatenatedLineBuffer([$lines, new ArrayLineBuffer(['more'])]);
        }
        $before = memory_get_usage();
        $all = $lines->toArray();

        $this->assertCount(50010, $all);
        $this->assertSame($all, $lines->toArray());
        // The lines of the outer buffer kept, about 1 MB, not those of each buffer in it too: 10 MB
        $this->assertLessThan(3000000, memory_get_usage() - $before);
    }

    public function testSliceThrowsOnNegativeLength()
    {
        $this->expectException(OutOfBoundsException::class);
        (new ConcatenatedLineBuffer([new ArrayLineBuffer(['A'])]))->slice(0, -1);
    }
}
