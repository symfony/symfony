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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Transition\Direction\HorizontalDirection;
use Symfony\Component\Tui\Transition\Direction\VerticalDirection;
use Symfony\Component\Tui\Transition\SliceTransition;

class SliceTransitionTest extends AbstractTransitionTestCase
{
    /**
     * @return iterable<string, array{HorizontalDirection|VerticalDirection, list<array{int, int, string}>}>
     */
    public static function directionProvider(): iterable
    {
        // Horizontal slices alternate their entrance edge from one row to the next.
        yield 'from left' => [HorizontalDirection::Left, [[0, 0, 'B'], [9, 0, 'A'], [0, 1, 'A'], [9, 1, 'B']]];
        yield 'from right' => [HorizontalDirection::Right, [[0, 0, 'A'], [9, 0, 'B'], [0, 1, 'B'], [9, 1, 'A']]];
        // Vertical slices are four columns wide and alternate their entrance edge by band.
        yield 'from top' => [VerticalDirection::Top, [[0, 0, 'B'], [4, 0, 'A'], [0, 4, 'A'], [4, 4, 'B']]];
        yield 'from bottom' => [VerticalDirection::Bottom, [[0, 0, 'A'], [4, 0, 'B'], [0, 4, 'B'], [4, 4, 'A']]];
    }

    #[DataProvider('directionProvider')]
    public function testBoundariesReturnExactScreens(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new SliceTransition($direction);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.0));
        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 1.0));
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testControlPointsAtHalf(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new SliceTransition($direction);
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
        $transition = new SliceTransition($direction);

        foreach ([0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9] as $progress) {
            $lines = $transition->blend($this->fromCells, $this->toCells, $this->width, $this->height, $progress);

            $this->assertCellsKeepTheirCoordinates($lines, \sprintf('Progress %.2f moved a cell away from its own coordinates.', $progress));
        }
    }

    #[DataProvider('directionProvider')]
    public function testWideGraphemesKeepTheLineWidth(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $this->assertWideGraphemesKeepTheLineWidth(new SliceTransition($direction, 3));
    }

    public function testZeroSizeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Size must be greater than 0, got 0.');

        new SliceTransition(size: 0);
    }

    public function testNegativeSizeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Size must be greater than 0, got -1.');

        new SliceTransition(size: -1);
    }
}
