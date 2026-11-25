<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Layout\CollapsedBorderGrid;

final class CollapsedBorderGridTest extends TestCase
{
    public function testConflictResolutionUsesWidthStyleOriginAndDirectionalTies()
    {
        $cell = ['width' => 2.5, 'style' => 'solid', 'color' => 'red', 'origin' => 5, 'row' => 0, 'column' => 0];
        $row = array_replace($cell, ['color' => 'blue', 'origin' => 4]);
        self::assertSame($cell, CollapsedBorderGrid::winner([$row, $cell]));
        $wide = array_replace($row, ['width' => 3.0]);
        self::assertSame($wide, CollapsedBorderGrid::winner([$cell, $wide]));
        $double = array_replace($row, ['style' => 'double']);
        self::assertSame($double, CollapsedBorderGrid::winner([$cell, $double]));
        $right = array_replace($cell, ['column' => 1, 'color' => 'green']);
        self::assertSame($cell, CollapsedBorderGrid::winner([$right, $cell]));
        self::assertSame($right, CollapsedBorderGrid::winner([$cell, $right], true));
        $below = array_replace($cell, ['row' => 1, 'color' => 'blue']);
        self::assertSame($cell, CollapsedBorderGrid::winner([$below, $cell]));
    }

    public function testHiddenBorderSuppressesEvenWiderBordersAndNoneNeverWins()
    {
        $solid = ['width' => 10.0, 'style' => 'solid', 'color' => 'red', 'origin' => 5, 'row' => 0, 'column' => 0];
        $hidden = array_replace($solid, ['width' => 1.0, 'style' => 'hidden', 'origin' => 0]);
        self::assertSame(array_replace($hidden, ['width' => 0.0]), CollapsedBorderGrid::winner([$solid, $hidden]));
        self::assertNull(CollapsedBorderGrid::winner([array_replace($solid, ['style' => 'none'])]));
        self::assertSame($solid, CollapsedBorderGrid::winner([array_replace($solid, ['style' => 'none', 'width' => 100.0]), $solid]));
        self::assertSame([1.625, 1.625], CollapsedBorderGrid::halves(3.25, false));
    }

    public function testSpanningCellSuppressesInternalEdgesButKeepsItsOuterSides()
    {
        $grid = new CollapsedBorderGrid([[0, 0], [1, 2]], [[0, 0, 1, 2], [1, 0, 1, 1], [1, 1, 1, 1]], array_fill(0, 3, [true, true, true, true]), array_fill(0, 2, [false, false, false, false]), [false, false, false, false], 2);
        self::assertFalse($grid->vertical(0, 1));
        self::assertTrue($grid->vertical(1, 1));
        self::assertTrue($grid->vertical(0, 0));
        self::assertTrue($grid->vertical(0, 2));
        self::assertTrue($grid->horizontal(0, 1, 0));
        self::assertTrue($grid->horizontal(null, 0, 1));
        self::assertTrue($grid->horizontal(1, null, 1));
    }

    public function testRowSpanSuppressesRowRuleInsideItsCell()
    {
        $grid = new CollapsedBorderGrid([[0, 1], [0, 2]], [[0, 0, 2, 1], [0, 1, 1, 1], [1, 1, 1, 1]], array_fill(0, 3, [true, true, true, true]), array_fill(0, 2, [true, true, true, true]), [true, true, true, true], 2);
        self::assertFalse($grid->horizontal(0, 1, 0));
        self::assertTrue($grid->horizontal(0, 1, 1));
        self::assertTrue($grid->horizontal(null, 0, 0));
        self::assertTrue($grid->horizontal(1, null, 0));
    }

    public function testResolvedRowGroupAndTableSidesApplyAtTheirBoundaries()
    {
        $grid = new CollapsedBorderGrid([[0], [1]], [[0, 0, 1, 1], [1, 0, 1, 1]], array_fill(0, 2, [false, false, false, false]), [[false, true, true, false], [false, true, false, false]], [true, false, false, false], 1);
        self::assertTrue($grid->horizontal(null, 0, 0));
        self::assertTrue($grid->horizontal(0, 1, 0));
        self::assertFalse($grid->horizontal(1, null, 0));
        self::assertTrue($grid->vertical(0, 1));
        self::assertFalse($grid->vertical(0, 0));
        self::assertFalse($grid->vertical(null, 0));
        self::assertFalse($grid->horizontal(null, null, 0));
        self::assertFalse($grid->horizontal(0, 1, 1));
    }
}
