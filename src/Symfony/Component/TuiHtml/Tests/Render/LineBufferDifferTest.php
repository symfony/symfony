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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Render\ArrayLineBuffer;
use Symfony\Component\TuiHtml\Render\ConcatenatedLineBuffer;
use Symfony\Component\TuiHtml\Render\LineBufferDiffer;

class LineBufferDifferTest extends TestCase
{
    public function testSharedTranscriptIsNotReadWhenFooterChanges()
    {
        $transcript = new CountingLineBuffer(100000);
        $previous = new ConcatenatedLineBuffer([$transcript, new ArrayLineBuffer(['footer 1'])]);
        $current = new ConcatenatedLineBuffer([$transcript, new ArrayLineBuffer(['footer 2'])]);
        $this->assertSame(['first' => 100000, 'last' => 100000], (new LineBufferDiffer())->findChangedRange($current, $previous));
        $this->assertSame(0, $transcript->getReadCount());
    }

    public static function changedRangeProvider(): iterable
    {
        yield 'identical content' => [['A', 'B'], ['A', 'B'], null];
        yield 'middle lines' => [['A', 'X', 'C', 'Y', 'E'], ['A', 'B', 'C', 'D', 'E'], ['first' => 1, 'last' => 3]];
        yield 'appended lines' => [['A', 'B', 'C', 'D'], ['A', 'B'], ['first' => 2, 'last' => 3]];
        yield 'removed middle line' => [['A', 'C', 'D'], ['A', 'B', 'C', 'D'], ['first' => 1, 'last' => 3]];
    }

    #[DataProvider('changedRangeProvider')]
    public function testFindChangedRange(array $current, array $previous, ?array $expected)
    {
        $this->assertSame($expected, (new LineBufferDiffer())->findChangedRange(new ArrayLineBuffer($current), new ArrayLineBuffer($previous)));
    }
}
