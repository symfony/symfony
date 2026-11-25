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

class ArrayLineBufferTest extends TestCase
{
    public function testGetLineThrowsOnOutOfBoundsIndex()
    {
        $this->expectException(OutOfBoundsException::class);
        (new ArrayLineBuffer(['A']))->getLine(1);
    }

    public function testSliceThrowsOnNegativeOffset()
    {
        $this->expectException(OutOfBoundsException::class);
        (new ArrayLineBuffer(['A']))->slice(-1, 1);
    }

    public function testTheWidthOfLinesAllAsWideIsKnown()
    {
        $this->assertNull(ArrayLineBuffer::uniformWidthOf(new ArrayLineBuffer(['ab', 'ab'])), 'Not known: not measured.');
        $this->assertNull(ArrayLineBuffer::uniformWidthOf(new ArrayLineBuffer(['ab', 'a'], 2)), 'The widest only.');
        $this->assertSame(2, ArrayLineBuffer::uniformWidthOf($lines = new ArrayLineBuffer(['ab', 'cd'], 2, true)));
        $this->assertSame(2, ArrayLineBuffer::uniformWidthOf(ArrayLineBuffer::takenFrom($lines, ['cd'])), 'Some of them are as wide.');
        $this->assertNull(ArrayLineBuffer::uniformWidthOf(ArrayLineBuffer::takenFrom(new ArrayLineBuffer(['ab', 'a']), ['ab'])));
        $this->assertSame(2, ArrayLineBuffer::uniformWidthOf(new ConcatenatedLineBuffer([$lines, new ArrayLineBuffer([]), new ArrayLineBuffer(['ef'], 2, true)])));
        $this->assertNull(ArrayLineBuffer::uniformWidthOf(new ConcatenatedLineBuffer([$lines, new ArrayLineBuffer(['efg'], 3, true)])), 'Of another width.');
        $this->assertNull(ArrayLineBuffer::uniformWidthOf(new ConcatenatedLineBuffer([$lines, new ArrayLineBuffer(['ef'])])), 'Not known for some.');
        $this->assertNull(ArrayLineBuffer::uniformWidthOf(new ConcatenatedLineBuffer([new ArrayLineBuffer(['ef']), $lines])), 'Not known for the first ones.');
    }

    public function testTheWidestLineIsFoundAmongRepeatedOnes()
    {
        $this->assertSame(5, (new ArrayLineBuffer(['ab', 'ab', '', 'abcde', 'ab']))->getMaxVisibleWidth());
        $this->assertSame(5, (new ArrayLineBuffer(['abcde', 'ab'], 5))->getMaxVisibleWidth(), 'A width given is the one of the lines, not measured again.');
    }
}
