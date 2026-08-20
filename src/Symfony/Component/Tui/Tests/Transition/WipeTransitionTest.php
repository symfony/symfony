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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Transition\Direction\HorizontalDirection;
use Symfony\Component\Tui\Transition\Direction\VerticalDirection;
use Symfony\Component\Tui\Transition\WipeTransition;

class WipeTransitionTest extends AbstractTransitionTestCase
{
    /**
     * @return iterable<string, array{HorizontalDirection|VerticalDirection, list<array{int, int, string}>}>
     */
    public static function directionProvider(): iterable
    {
        // At 0.5, split = (int) (10 * 0.5) = 5 horizontally, (int) (5 * 0.5) = 2 vertically.
        yield 'from left' => [HorizontalDirection::Left, [[0, 0, 'B'], [9, 0, 'A']]];
        yield 'from right' => [HorizontalDirection::Right, [[0, 0, 'A'], [9, 0, 'B']]];
        yield 'from top' => [VerticalDirection::Top, [[0, 0, 'B'], [0, 4, 'A']]];
        yield 'from bottom' => [VerticalDirection::Bottom, [[0, 0, 'A'], [0, 4, 'B']]];
    }

    #[DataProvider('directionProvider')]
    public function testBoundariesReturnExactScreens(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new WipeTransition($direction);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.0));
        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 1.0));
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testControlPointsAtHalf(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new WipeTransition($direction);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5);

        foreach ($cells as [$x, $y, $char]) {
            $this->assertCharAt($x, $y, $char, $res);
        }
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testCellsAreRevealedInPlace(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new WipeTransition($direction);

        foreach ([0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9] as $progress) {
            $lines = $transition->blend($this->fromCells, $this->toCells, $this->width, $this->height, $progress);

            $this->assertCellsKeepTheirCoordinates($lines, \sprintf('Progress %.2f moved a cell away from its own coordinates.', $progress));
        }
    }

    #[DataProvider('directionProvider')]
    public function testWideGraphemesKeepTheLineWidth(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $this->assertWideGraphemesKeepTheLineWidth(new WipeTransition($direction));
    }

    public function testWideGraphemesAreNotCutInHalf()
    {
        $emoji = "\u{1F600}";
        $from = ['AAAAAA'.$emoji.'AA'];
        $to = ['BB'.$emoji.'BBBBBB'];

        $res = (new WipeTransition(HorizontalDirection::Left))->blend($from, $to, 10, 1, 0.5);

        $this->assertSame(2, substr_count($res[0], $emoji), 'Both wide graphemes must survive intact, none half-cut.');
    }
}
