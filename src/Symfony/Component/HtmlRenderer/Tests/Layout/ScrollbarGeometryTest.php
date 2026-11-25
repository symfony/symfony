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
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\ScrollbarGeometry;
use Symfony\Component\HtmlRenderer\Layout\ScrollGeometry;

final class ScrollbarGeometryTest extends TestCase
{
    public function testFractionalThumbLengthAndOffsetUseActualTravel()
    {
        self::assertSame(25.0, ScrollbarGeometry::thumbLength(100, 100, 400));
        self::assertSame(37.5, ScrollbarGeometry::thumbOffset(100, 25, 100, 400, 150));
        self::assertSame(75.0, ScrollbarGeometry::thumbOffset(100, 25, 100, 400, 1000));
        self::assertSame(0.0, ScrollbarGeometry::thumbOffset(100, 25, 100, 400, -1));
    }

    public function testEmptyAndNonOverflowingContentCannotDivideByZero()
    {
        self::assertSame(100.0, ScrollbarGeometry::thumbLength(100, 100, 80));
        self::assertSame(0.0, ScrollbarGeometry::thumbOffset(100, 100, 100, 80, 1));
        self::assertSame(0.0, ScrollbarGeometry::thumbLength(0, 0, 0));
        self::assertSame(0.0, ScrollbarGeometry::thumbOffset(0, 0, 0, 0, 0));
    }

    public function testRegionsSharePaintedTracksAndExcludeUnusedStableGutters()
    {
        $port = new Rect(0, 0, 72, 16);
        $fragment = new Fragment(null, new Rect(0, 0, 80, 32), [], null, [], null, null, [], null, null, null, new ScrollGeometry($port, $port, 0, 0, true, true, [0, 8, 16, 0], true, true));
        self::assertEquals(['x' => new Rect(0, 16, 72, 16), 'y' => new Rect(72, 0, 8, 16), 'corner' => new Rect(72, 16, 8, 16)], ScrollbarGeometry::regions($fragment));
        $stable = new Fragment(null, new Rect(0, 0, 80, 32), [], null, [], null, null, [], null, null, null, new ScrollGeometry($port, $port, 0, 0, true, true, [0, 8, 16, 0]));
        self::assertSame([], ScrollbarGeometry::regions($stable));
    }

    public function testRtlBothEdgesHasOnePaintedVerticalTrackAndItsCorner()
    {
        $port = new Rect(8, 0, 64, 16);
        $fragment = new Fragment(null, new Rect(0, 0, 80, 32), ['direction' => 'rtl'], null, [], null, null, [], null, null, null, new ScrollGeometry($port, $port, 0, 0, true, true, [0, 8, 16, 8], true, true));
        $regions = ScrollbarGeometry::regions($fragment);
        self::assertEquals(new Rect(0, 0, 8, 16), $regions['y']);
        self::assertEquals(new Rect(0, 16, 8, 16), $regions['corner']);
        self::assertEquals(new Rect(8, 16, 64, 16), $regions['x']);
    }
}
