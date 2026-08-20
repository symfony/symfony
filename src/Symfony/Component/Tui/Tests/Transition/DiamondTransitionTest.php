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
use Symfony\Component\Tui\Transition\DiamondTransition;
use Symfony\Component\Tui\Transition\Direction\RadialDirection;

class DiamondTransitionTest extends AbstractTransitionTestCase
{
    /**
     * @return iterable<string, array{RadialDirection, list<array{int, int, string}>}>
     */
    public static function directionProvider(): iterable
    {
        // @0.5 the reveal region is complementary between directions, not identical: Outward's
        // diamond (center) holds "to" with "from" corners; Inward's diamond holds "from" with "to"
        // corners: the mirror image, since Inward is meant to shrink the "from" screen away.
        yield 'outward' => [RadialDirection::Outward, [[5, 2, 'B'], [0, 0, 'A']]];
        yield 'inward' => [RadialDirection::Inward, [[5, 2, 'A'], [0, 0, 'B']]];
    }

    #[DataProvider('directionProvider')]
    public function testBoundariesReturnExactScreens(RadialDirection $direction, array $cells)
    {
        $transition = new DiamondTransition($direction);

        $this->assertSame($this->fromLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.0));
        $this->assertSame($this->toLines, $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 1.0));
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testControlPointsAtHalf(RadialDirection $direction, array $cells)
    {
        $transition = new DiamondTransition($direction);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, 0.5);

        foreach ($cells as [$x, $y, $char]) {
            $this->assertCharAt($x, $y, $char, $res);
        }
    }

    /**
     * @return iterable<string, array{RadialDirection, float, string, string}>
     */
    public static function cornerFallbackProvider(): iterable
    {
        // On the 10x5 screen these progress points make rows 0 and 4 satisfy d<0, reaching the
        // corner-fallback branch, while the center row stays inside the diamond's active height
        // range. Outward and Inward use symmetric progress values (0.2 / 1 - 0.2) so both land on the
        // same diamond size: Outward's small growing diamond still holds "to" at the center; Inward's
        // small remaining diamond (same size, mirrored) still holds "from" at the center.
        yield 'outward at 0.2 keeps from-corner' => [RadialDirection::Outward, 0.2, 'A', 'B'];
        yield 'inward at 0.8 shows to-corner' => [RadialDirection::Inward, 0.8, 'B', 'A'];
    }

    #[DataProvider('cornerFallbackProvider')]
    public function testCornerFallbackDiscriminatesDirection(RadialDirection $direction, float $progress, string $corner, string $center)
    {
        $transition = new DiamondTransition($direction);
        $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, $progress);

        // The d<0 corner fallback: 'A' for Outward@0.2 vs 'B' for Inward@0.8 is the discrimination.
        $this->assertCharAt(0, 0, $corner, $res);
        // The diamond itself still holds the direction-appropriate screen at this progress.
        $this->assertCharAt(5, 2, $center, $res);
    }

    /**
     * @return iterable<string, array{RadialDirection}>
     */
    public static function monotonicDirectionProvider(): iterable
    {
        yield 'outward' => [RadialDirection::Outward];
        yield 'inward' => [RadialDirection::Inward];
    }

    /**
     * A true mirror of a monotonically growing reveal (Outward) must itself be monotonic: the
     * amount of "to" on screen never regresses as progress advances. This is what broke before
     * the Inward fix (only the fallback and effectiveProgress were swapped, not the diamond's own
     * interior), so the "to" coverage rose, fell, then rose again instead of climbing steadily.
     */
    #[DataProvider('monotonicDirectionProvider')]
    public function testToCoverageNeverRegresses(RadialDirection $direction)
    {
        $transition = new DiamondTransition($direction);
        $previousCount = -1;
        $counts = [];

        foreach ([0.02, 0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9, 0.98] as $progress) {
            $res = $transition->blend($this->fromLines, $this->toLines, $this->width, $this->height, $progress);
            $count = array_sum(array_map(static fn (string $line) => substr_count($line, 'B'), $res));

            $this->assertGreaterThanOrEqual($previousCount, $count, \sprintf('"to" coverage regressed at progress %.2f.', $progress));
            $previousCount = $count;
            $counts[] = $count;
        }

        // Monotonicity alone is satisfied by a blend that never changes at all, so pin the
        // climb itself: nearly nothing of the "to" screen at the start, nearly all of it at
        // the end.
        $total = $this->width * $this->height;
        $this->assertLessThan($total / 4, $counts[0], 'The reveal should barely have started at progress 0.02.');
        $this->assertGreaterThan(3 * $total / 4, end($counts), 'The reveal should be nearly complete at progress 0.98.');
    }

    /**
     * @param list<array{int, int, string}> $cells
     */
    #[DataProvider('directionProvider')]
    public function testCellsAreRevealedInPlace(RadialDirection $direction, array $cells)
    {
        $transition = new DiamondTransition($direction);

        foreach ([0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9] as $progress) {
            $lines = $transition->blend($this->fromCells, $this->toCells, $this->width, $this->height, $progress);

            $this->assertCellsKeepTheirCoordinates($lines, \sprintf('Progress %.2f moved a cell away from its own coordinates.', $progress));
        }
    }

    #[DataProvider('directionProvider')]
    public function testWideGraphemesKeepTheLineWidth(RadialDirection $direction, array $cells)
    {
        $this->assertWideGraphemesKeepTheLineWidth(new DiamondTransition($direction));
    }
}
