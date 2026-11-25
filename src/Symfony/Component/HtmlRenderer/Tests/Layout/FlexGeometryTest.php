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
use Symfony\Component\HtmlRenderer\Layout\FlexGeometry;

final class FlexGeometryTest extends TestCase
{
    public function testGrowthRedistributesAfterAnItemReachesItsMaximum()
    {
        $items = [['basis' => 10.0, 'minimum' => 0.0, 'maximum' => 20.0, 'grow' => 1.0, 'shrink' => 1.0], ['basis' => 10.0, 'minimum' => 0.0, 'maximum' => null, 'grow' => 1.0, 'shrink' => 1.0]];
        self::assertSame([20.0, 80.5], FlexGeometry::resolve($items, 101.0, 0.5));
    }

    public function testShrinkFreezesAtMinimumAndRetainsFractionalRemainder()
    {
        $items = [['basis' => 40.0, 'minimum' => 35.0, 'maximum' => null, 'grow' => 0.0, 'shrink' => 1.0], ['basis' => 40.0, 'minimum' => 0.0, 'maximum' => null, 'grow' => 0.0, 'shrink' => 1.0]];
        self::assertSame([35.0, 14.5], FlexGeometry::resolve($items, 50.0, 0.5));
    }

    public function testFractionalGrowthRetainsUnusedRoomAfterFreezing()
    {
        $items = [['basis' => 10.0, 'minimum' => 0.0, 'maximum' => null, 'grow' => 0.25, 'shrink' => 1.0], ['basis' => 10.0, 'minimum' => 0.0, 'maximum' => null, 'grow' => 0.25, 'shrink' => 1.0]];
        self::assertSame([30.0, 30.0], FlexGeometry::resolve($items, 100.0, 0.0));
        $items[0]['maximum'] = 15.0;
        self::assertSame([15.0, 30.0], FlexGeometry::resolve($items, 100.0, 0.0));
    }

    public function testInflexibleLimitsRemainFrozenWhenOtherItemsGrow()
    {
        $items = [['basis' => 10.0, 'minimum' => 20.0, 'maximum' => null, 'grow' => 0.0, 'shrink' => 0.0], ['basis' => 10.0, 'minimum' => 0.0, 'maximum' => null, 'grow' => 1.0, 'shrink' => 1.0]];
        self::assertSame([20.0, 80.0], FlexGeometry::resolve($items, 100.0, 0.0));
        $items[0]['grow'] = $items[0]['shrink'] = 1.0;
        $items[1]['basis'] = 30.0;
        $items[1]['maximum'] = 20.0;
        self::assertSame([20.0, 20.0], FlexGeometry::resolve($items, 40.0, 0.0));
    }

    public function testDistributionKeepsFractionalSparseAutoMarginsAndEmptyBoundaries()
    {
        self::assertSame([0.0], FlexGeometry::spaces(0, 7.5, 0.25, 'space-around'));
        self::assertSame([0.0, 0.0], FlexGeometry::spaces(1, 5.5, 0.25, 'space-between'));
        self::assertSame([2.75, 2.75], FlexGeometry::spaces(1, 5.5, 0.25, 'space-around'));
        self::assertSame([0.0, 0.25, 7.75, 0.0], FlexGeometry::spaces(3, 7.5, 0.25, 'space-evenly', [2 => [true, false]]));
    }

    public function testFreeSpaceWeightsMatchCssDistribution()
    {
        self::assertSame([2.5, 5.5, 2.5], FlexGeometry::spaces(2, 10.0, 0.5, 'space-around'));
        self::assertSame([0.0, 10.5, 0.0], FlexGeometry::spaces(2, 10.0, 0.5, 'space-between'));
        self::assertSame([10.0, 0.5, 0.0], FlexGeometry::spaces(2, 10.0, 0.5, 'space-between', [[true, false], [false, false]]));
    }
}
