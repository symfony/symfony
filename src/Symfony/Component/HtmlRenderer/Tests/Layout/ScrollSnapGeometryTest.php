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
use Symfony\Component\HtmlRenderer\Layout\ScrollSnapGeometry;

class ScrollSnapGeometryTest extends TestCase
{
    public function testPositionsUseTheBorderBoxAndResolvedScrollPaddingAndMargins()
    {
        self::assertSame(15.25, ScrollSnapGeometry::position('start', 20.5, 10.25, 2.0, 3.0, 3.25, 4.0, 12.0, 80.0));
        self::assertSame(25.75, ScrollSnapGeometry::position('end', 20.5, 10.25, 2.0, 3.0, 3.25, 4.0, 12.0, 80.0));
        self::assertSame(19.625, ScrollSnapGeometry::position('center', 20.5, 10.25, 2.0, 3.0, 3.25, 4.0, 12.0, 80.0));
        self::assertSame(0.0, ScrollSnapGeometry::position('start', 1.0, 10.0, 2.0, 0.0, 3.0, 0.0, 12.0, 80.0));
        self::assertSame(80.0, ScrollSnapGeometry::position('end', 100.0, 10.0, 0.0, 0.0, 0.0, 0.0, 12.0, 80.0));
    }

    public function testSelectionPreservesDirectionStopsProximityAndFractionalCoordinates()
    {
        $points = [[0.0, false], [30.25, false], [60.5, false], [90.75, false]];
        self::assertSame(1.0, ScrollSnapGeometry::select([], 1.0, null));
        self::assertSame(0.0, ScrollSnapGeometry::select($points, 1.0, null));
        self::assertSame(30.25, ScrollSnapGeometry::select($points, 1.0, 0.0));
        self::assertSame(90.75, ScrollSnapGeometry::select($points, 85.0, 30.25));
        self::assertSame(60.5, ScrollSnapGeometry::select($points, 89.0, 90.75));
        self::assertSame(89.0, ScrollSnapGeometry::select($points, 89.0, 90.75, 10.0));
        self::assertSame(30.25, ScrollSnapGeometry::select([[0.0, false], [30.25, true], [60.5, false]], 60.5, 0.0));
        self::assertSame(30.25, ScrollSnapGeometry::select([[0.0, false], [30.25, true], [60.5, false]], 0.0, 60.5));
    }

    public function testModesAndAlignmentRetainTheExistingBlockAndInlineAxisContract()
    {
        self::assertSame('mandatory', ScrollSnapGeometry::mode('y mandatory', true));
        self::assertSame('', ScrollSnapGeometry::mode('y mandatory', false));
        self::assertSame('proximity', ScrollSnapGeometry::mode('inline', false));
        self::assertSame('mandatory', ScrollSnapGeometry::mode('both mandatory', false));
        self::assertSame('', ScrollSnapGeometry::mode('none', true));
        self::assertNull(ScrollSnapGeometry::mode('y unknown', true));
        self::assertSame('start', ScrollSnapGeometry::alignment('start', false));
        self::assertNull(ScrollSnapGeometry::alignment('none start', true));
        self::assertSame('start', ScrollSnapGeometry::alignment('none start', false));
        self::assertSame('center', ScrollSnapGeometry::alignment('center end', true));
    }
}
