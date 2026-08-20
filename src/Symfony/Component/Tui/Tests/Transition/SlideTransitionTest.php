<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Transition;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Tui\Transition\Direction\HorizontalDirection;
use Symfony\Component\Tui\Transition\Direction\VerticalDirection;
use Symfony\Component\Tui\Transition\SlideTransition;

class SlideTransitionTest extends AbstractTransitionTestCase
{
    /**
     * @return iterable<string, array{HorizontalDirection|VerticalDirection, list<array{int, int, string}>}>
     */
    public static function directionProvider(): iterable
    {
        // At 0.5, offset = (int) (10 * 0.5) = 5 horizontally, (int) (5 * 0.5) = 2 vertically.
        yield 'from left' => [HorizontalDirection::Left, [[0, 0, 'B'], [9, 0, 'A']]];
        yield 'from right' => [HorizontalDirection::Right, [[0, 0, 'A'], [9, 0, 'B']]];
        yield 'from top' => [VerticalDirection::Top, [[0, 0, 'B'], [0, 4, 'A']]];
        yield 'from bottom' => [VerticalDirection::Bottom, [[0, 0, 'A'], [0, 4, 'B']]];
    }

    #[DataProvider('directionProvider')]
    public function testBoundariesReturnExactScreens(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new SlideTransition($direction);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.0));
        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 1.0));
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testControlPointsAtHalf(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new SlideTransition($direction);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5);

        foreach ($cells as [$x, $y, $char]) {
            $this->assertCharAt($x, $y, $char, $res);
        }
    }

    /**
     * @return iterable<string, array{HorizontalDirection|VerticalDirection, list<string>}>
     */
    public static function slidAtHalfProvider(): iterable
    {
        // A slide translates content instead of revealing it in place, so the whole line has to
        // be pinned: sampling two columns cannot tell a correct offset from one off by a column.
        yield 'from left' => [HorizontalDirection::Left, [
            'FGHIJabcde',
            'GHIJKbcdef',
            'HIJKLcdefg',
            'IJKLMdefgh',
            'JKLMNefghi',
        ]];
        yield 'from right' => [HorizontalDirection::Right, [
            'fghijABCDE',
            'ghijkBCDEF',
            'hijklCDEFG',
            'ijklmDEFGH',
            'jklmnEFGHI',
        ]];
        yield 'from top' => [VerticalDirection::Top, [
            'DEFGHIJKLM',
            'EFGHIJKLMN',
            'abcdefghij',
            'bcdefghijk',
            'cdefghijkl',
        ]];
        yield 'from bottom' => [VerticalDirection::Bottom, [
            'cdefghijkl',
            'defghijklm',
            'efghijklmn',
            'ABCDEFGHIJ',
            'BCDEFGHIJK',
        ]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('slidAtHalfProvider')]
    public function testEveryColumnIsSlidByTheExactOffset(HorizontalDirection|VerticalDirection $direction, array $expected)
    {
        $transition = new SlideTransition($direction);

        $this->assertSame($expected, $transition->blend($this->fromCells, $this->toCells, $this->width, $this->height, 0.5));
    }

    #[DataProvider('directionProvider')]
    public function testWideGraphemesKeepTheLineWidth(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $this->assertWideGraphemesKeepTheLineWidth(new SlideTransition($direction));
    }

    public function testEasingAboveOneClampsToDestination()
    {
        $transition = (new SlideTransition(HorizontalDirection::Right))->setEasing(static fn (float $p) => 1.5);

        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5));
    }

    public function testEasingBelowZeroClampsToSource()
    {
        $transition = (new SlideTransition(HorizontalDirection::Right))->setEasing(static fn (float $p) => -0.5);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5));
    }

    public function testNormalEasingStillBlends()
    {
        $transition = (new SlideTransition(HorizontalDirection::Right))->setEasing(static fn (float $p) => $p * $p);

        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5);

        $this->assertNotSame($this->fromLines, $res);
        $this->assertNotSame($this->toLines, $res);
        $this->assertCharAt(0, 0, 'A', $res);
        $this->assertCharAt(7, 0, 'A', $res);
        $this->assertCharAt(8, 0, 'B', $res);
        $this->assertCharAt(9, 0, 'B', $res);
    }
}
