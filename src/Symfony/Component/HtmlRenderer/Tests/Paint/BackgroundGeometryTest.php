<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry;

final class BackgroundGeometryTest extends TestCase
{
    public function testImageSizingPreservesFractionalRatiosAndContainCover()
    {
        self::assertSame([10.5, 5.25], BackgroundGeometry::imageSize(40, 20, 100, 100, 10.5, null));
        self::assertSame([100.0, 50.0], BackgroundGeometry::imageSize(40, 20, 100, 100, null, null, 'contain'));
        self::assertSame([200.0, 100.0], BackgroundGeometry::imageSize(40, 20, 100, 100, null, null, 'cover'));
        self::assertSame([40.0, 20.0], BackgroundGeometry::imageSize(40, 20, 100, 100, null, null));
    }

    public function testRepeatSupportsNegativeCoordinatesWithoutEnumeratingTiles()
    {
        $axis = BackgroundGeometry::axis(1.5, 1000000.0, 2.5, ['percent' => 0.0, 'offset' => 0.0, 'fromEnd' => false], 'repeat');
        self::assertEqualsWithDelta(1.0, BackgroundGeometry::sampleAxis($axis, 0.0), 1e-10);
        self::assertEqualsWithDelta(1.0, BackgroundGeometry::sampleAxis($axis, 1000000.0), 1e-10);
        self::assertCount(4, $axis);
    }

    public function testSpaceUsesWholeTilesAndRoundPreservesTheCompleteArea()
    {
        $start = ['percent' => 0.0, 'offset' => 0.0, 'fromEnd' => false];
        $space = BackgroundGeometry::axis(0, 10, 3, $start, 'space');
        self::assertSame(3, $space['count']);
        self::assertNull(BackgroundGeometry::sampleAxis($space, 3.25));
        self::assertEqualsWithDelta(0.5, BackgroundGeometry::sampleAxis($space, 4), 1e-10);
        self::assertSame(0.0, BackgroundGeometry::sampleAxis($space, 10));
        $round = BackgroundGeometry::axis(0, 10, 3, $start, 'round');
        self::assertEqualsWithDelta(10 / 3, $round['size'], 1e-10);
        self::assertEqualsWithDelta(0.0, BackgroundGeometry::sampleAxis($round, 10), 1e-10);
        $single = BackgroundGeometry::axis(0, 5, 4, ['percent' => 100.0, 'offset' => 0.0, 'fromEnd' => false], 'space');
        self::assertNull(BackgroundGeometry::sampleAxis($single, 0));
        self::assertSame(0.0, BackgroundGeometry::sampleAxis($single, 1));
    }

    public function testSpacePatternRepeatsOutsideItsPositioningArea()
    {
        $axis = BackgroundGeometry::axis(0, 10, 3, ['percent' => 0.0, 'offset' => 0.0, 'fromEnd' => false], 'space');
        self::assertSame(2.0, BackgroundGeometry::sampleAxis($axis, -1));
        self::assertSame(0.0, BackgroundGeometry::sampleAxis($axis, 10));
        self::assertNull(BackgroundGeometry::sampleAxis($axis, 13.25));
    }
}
