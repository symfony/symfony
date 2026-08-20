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
use Symfony\Component\Tui\Transition\Direction\RadialDirection;
use Symfony\Component\Tui\Transition\SpiralTransition;

class SpiralTransitionTest extends AbstractTransitionTestCase
{
    /**
     * @return iterable<string, array{RadialDirection, list<array{int, int, string}>}>
     */
    public static function directionProvider(): iterable
    {
        // size 1, @0.5 half the blocks are revealed following the spiral path.
        // Inward starts from the outer edge: top row is "to", the center stays "from".
        yield 'inward' => [RadialDirection::Inward, [[0, 0, 'B'], [5, 2, 'A']]];
        // Outward starts from the center: center is "to", the corners stay "from".
        yield 'outward' => [RadialDirection::Outward, [[5, 2, 'B'], [0, 0, 'A']]];
    }

    #[DataProvider('directionProvider')]
    public function testBoundariesReturnExactScreens(RadialDirection $direction, array $cells)
    {
        $transition = new SpiralTransition($direction, 1);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.0));
        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 1.0));
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testControlPointsAtHalf(RadialDirection $direction, array $cells)
    {
        $transition = new SpiralTransition($direction, 1);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5);

        foreach ($cells as [$x, $y, $char]) {
            $this->assertCharAt($x, $y, $char, $res);
        }
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testCellsAreRevealedInPlace(RadialDirection $direction, array $cells)
    {
        $transition = new SpiralTransition($direction);

        foreach ([0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9] as $progress) {
            $lines = $transition->blend($this->fromCells, $this->toCells, $this->width, $this->height, $progress);

            $this->assertCellsKeepTheirCoordinates($lines, \sprintf('Progress %.2f moved a cell away from its own coordinates.', $progress));
        }
    }

    #[DataProvider('directionProvider')]
    public function testWideGraphemesKeepTheLineWidth(RadialDirection $direction, array $cells)
    {
        $this->assertWideGraphemesKeepTheLineWidth(new SpiralTransition($direction, 3));
    }

    public function testZeroSizeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Size must be greater than 0, got 0.');

        new SpiralTransition(size: 0);
    }

    public function testNegativeSizeThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Size must be greater than 0, got -1.');

        new SpiralTransition(size: -1);
    }
}
