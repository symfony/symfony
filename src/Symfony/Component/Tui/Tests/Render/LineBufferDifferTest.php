<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\ArrayLineBuffer;
use Symfony\Component\Tui\Render\ConcatenatedLineBuffer;
use Symfony\Component\Tui\Render\LineBufferDiffer;

class LineBufferDifferTest extends TestCase
{
    public function testSharedTranscriptIsNotReadWhenFooterChanges()
    {
        $transcript = new CountingLineBuffer(100_000);
        $previous = new ConcatenatedLineBuffer([$transcript, new ArrayLineBuffer(['footer 1'])]);
        $current = new ConcatenatedLineBuffer([$transcript, new ArrayLineBuffer(['footer 2'])]);

        $this->assertSame(['first' => 100_000, 'last' => 100_000], new LineBufferDiffer()->findChangedRange($current, $previous));
        $this->assertSame(0, $transcript->getReadCount());
    }

    /**
     * @return iterable<string, array{list<string>, list<string>, array{first: int, last: int}|null}>
     */
    public static function changedRangeProvider(): iterable
    {
        yield 'identical content' => [['A', 'B'], ['A', 'B'], null];
        yield 'middle lines' => [['A', 'X', 'C', 'Y', 'E'], ['A', 'B', 'C', 'D', 'E'], ['first' => 1, 'last' => 3]];
        yield 'appended lines' => [['A', 'B', 'C', 'D'], ['A', 'B'], ['first' => 2, 'last' => 3]];
        yield 'removed middle line' => [['A', 'C', 'D'], ['A', 'B', 'C', 'D'], ['first' => 1, 'last' => 3]];
    }

    /**
     * @param list<string>                      $current
     * @param list<string>                      $previous
     * @param array{first: int, last: int}|null $expected
     */
    #[DataProvider('changedRangeProvider')]
    public function testFindChangedRange(array $current, array $previous, ?array $expected)
    {
        $this->assertSame($expected, new LineBufferDiffer()->findChangedRange(new ArrayLineBuffer($current), new ArrayLineBuffer($previous)));
    }
}
