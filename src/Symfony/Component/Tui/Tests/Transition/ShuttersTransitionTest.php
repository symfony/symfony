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
use Symfony\Component\Tui\Transition\ShuttersTransition;

class ShuttersTransitionTest extends AbstractTransitionTestCase
{
    /**
     * @return iterable<string, array{HorizontalDirection|VerticalDirection, list<array{int, int, string}>}>
     */
    public static function directionProvider(): iterable
    {
        // Default size 4, @0.5 reveals half of each shutter from its entrance edge.
        yield 'from left' => [HorizontalDirection::Left, [[0, 0, 'B'], [2, 0, 'A'], [4, 0, 'B']]];
        yield 'from right' => [HorizontalDirection::Right, [[0, 0, 'A'], [2, 0, 'B'], [4, 0, 'A']]];
        yield 'from top' => [VerticalDirection::Top, [[0, 0, 'B'], [0, 2, 'A'], [0, 4, 'B'], [9, 4, 'A']]];
        yield 'from bottom' => [VerticalDirection::Bottom, [[0, 0, 'A'], [0, 2, 'B'], [0, 4, 'B'], [9, 4, 'A']]];
    }

    #[DataProvider('directionProvider')]
    public function testBoundariesReturnExactScreens(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new ShuttersTransition($direction);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.0));
        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 1.0));
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testControlPointsAtHalf(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new ShuttersTransition($direction);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5);

        foreach ($cells as [$x, $y, $char]) {
            $this->assertCharAt($x, $y, $char, $res);
        }
    }

    public function testVerticalEntranceWipesTheBoundaryRowInsteadOfJumping()
    {
        // Default size 4, @0.375 reveal = 1.5: row 0 fully "to", row 1 mid-wipe, rows 2-3 "from".
        // A hard row-count reveal (the old behavior) could only ever land on revealSize 0-3 here;
        // this progress falls *between* two of those steps, which is exactly the point.
        $transition = new ShuttersTransition(VerticalDirection::Top);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.375);

        $this->assertCharAt(0, 0, 'B', $res);
        $this->assertCharAt(0, 1, 'B', $res);
        $this->assertCharAt(4, 1, 'B', $res);
        $this->assertCharAt(5, 1, 'A', $res);
        $this->assertCharAt(9, 1, 'A', $res);
        $this->assertCharAt(0, 2, 'A', $res);
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testCellsAreRevealedInPlace(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $transition = new ShuttersTransition($direction);

        foreach ([0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9] as $progress) {
            $lines = $transition->blend($this->fromCells, $this->toCells, $this->width, $this->height, $progress);

            $this->assertCellsKeepTheirCoordinates($lines, \sprintf('Progress %.2f moved a cell away from its own coordinates.', $progress));
        }
    }

    #[DataProvider('directionProvider')]
    public function testWideGraphemesKeepTheLineWidth(HorizontalDirection|VerticalDirection $direction, array $cells)
    {
        $this->assertWideGraphemesKeepTheLineWidth(new ShuttersTransition($direction, 3));
    }

    public function testZeroSizeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Size must be greater than 0, got 0.');

        new ShuttersTransition(size: 0);
    }

    public function testNegativeSizeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Size must be greater than 0, got -1.');

        new ShuttersTransition(size: -1);
    }
}
