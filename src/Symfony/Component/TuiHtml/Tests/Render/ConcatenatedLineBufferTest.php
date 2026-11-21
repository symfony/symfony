<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Exception\OutOfBoundsException;
use Symfony\Component\TuiHtml\Render\ArrayLineBuffer;
use Symfony\Component\TuiHtml\Render\ConcatenatedLineBuffer;

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

    public function testSliceThrowsOnNegativeLength()
    {
        $this->expectException(OutOfBoundsException::class);
        (new ConcatenatedLineBuffer([new ArrayLineBuffer(['A'])]))->slice(0, -1);
    }
}
