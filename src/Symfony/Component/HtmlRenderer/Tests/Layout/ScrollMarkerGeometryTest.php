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
use Symfony\Component\HtmlRenderer\Layout\ScrollMarkerGeometry;

class ScrollMarkerGeometryTest extends TestCase
{
    public function testCurrentMarkerUsesStartsAndTheLastVisibleMarkerAtTheEnd(): void
    {
        self::assertNull(ScrollMarkerGeometry::current([], 0, 100, 25));
        self::assertSame(0, ScrollMarkerGeometry::current([null, null], 0, 100, 25));
        self::assertSame(0, ScrollMarkerGeometry::current([10.5, 30.5, 60.5], 0, 100, 25));
        self::assertSame(1, ScrollMarkerGeometry::current([10.5, 30.5, 60.5], 30.5, 100, 25));
        self::assertSame(1, ScrollMarkerGeometry::current([10.5, 30.5, 60.5], 50, 100, 25));
        self::assertSame(2, ScrollMarkerGeometry::current([0, 70, 90], 75, 100, 25));
        self::assertSame(1, ScrollMarkerGeometry::current([0, 70, 100], 75, 100, 25));
        self::assertSame(0, ScrollMarkerGeometry::current([0, 20], 0, 25, 25));
        self::assertSame(2, ScrollMarkerGeometry::current([0, null, 40.25], 40.25, 100, 25));
    }
}
