<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\ArrayLineBuffer;
use Symfony\Component\Tui\Widget\TextWidget;

final class RenderCacheTest extends TestCase
{
    public function testRendersInAFewSizesAreKept()
    {
        $widget = new TextWidget('a');
        $wide = new ArrayLineBuffer(['wide']);
        $narrow = new ArrayLineBuffer(['narrow']);

        $widget->setRenderCache($wide, 40, 10);
        $widget->setRenderCache($narrow, 5, 10);

        $this->assertSame($narrow, $widget->getRenderCache(5, 10));
        $this->assertSame($wide, $widget->getRenderCache(40, 10));
        $this->assertNull($widget->getRenderCache(20, 10));
    }

    public function testContainingDimensionsStayInSeparateKeysWithoutIntegerArithmetic()
    {
        $widget = new TextWidget('a');
        $height = new ArrayLineBuffer(['height']);
        $width = new ArrayLineBuffer(['width']);
        $widget->setRenderCache($height, 40, '10000:height:2147483647:width:40', ['height']);
        $widget->setRenderCache($width, 40, '10000:height:40:width:2147483647', ['width']);

        $this->assertSame($height, $widget->getRenderCache(40, '10000:height:2147483647:width:40'));
        $this->assertSame($width, $widget->getRenderCache(40, '10000:height:40:width:2147483647'));
        $this->assertSame(['height'], $widget->getRenderCacheExtra(40, '10000:height:2147483647:width:40'));
        $this->assertNull($widget->getRenderCache(40, 10000));
    }

    public function testOnlyAFewSizesAreKept()
    {
        $widget = new TextWidget('a');
        foreach (range(1, 6) as $columns) {
            $widget->setRenderCache(new ArrayLineBuffer([(string) $columns]), $columns, 1);
        }

        // The last one and the three before it
        $this->assertNull($widget->getRenderCache(2, 1));
        $this->assertNotNull($widget->getRenderCache(3, 1));
        $this->assertSame(['6'], $widget->getRenderCache(6, 1)->toArray());
    }

    public function testAChangeOrAClearDropsThemAll()
    {
        $widget = new TextWidget('a');
        $widget->setRenderCache(new ArrayLineBuffer(['wide']), 40, 10);
        $widget->setRenderCache(new ArrayLineBuffer(['narrow']), 5, 10);
        $widget->invalidate();

        $this->assertNull($widget->getRenderCache(40, 10));
        $this->assertNull($widget->getRenderCache(5, 10));

        $widget->setRenderCache(new ArrayLineBuffer(['wide']), 40, 10);
        $widget->setRenderCache(new ArrayLineBuffer(['narrow']), 5, 10);
        $widget->clearRenderCache();

        // A render after a clear records the positions of what it draws again: none is kept
        $this->assertNull($widget->getRenderCache(40, 10));
        $this->assertNull($widget->getRenderCache(5, 10));
    }

    public function testAMeasureOfATextAsIfNoFloatWereBesideItKeepsItsRenderBesideThem()
    {
        $widget = new TextWidget('a');
        $widget->setLineInsets([[2, 0]]);
        $beside = new ArrayLineBuffer(['  a']);
        $widget->setRenderCache($beside, 40, 10);

        $measured = $widget->measureWithoutFloats(function () use ($widget): array {
            $this->assertNull($widget->getRenderCache(40, 10), 'Not taken for the measure.');
            $widget->setRenderCache(new ArrayLineBuffer(['a']), 40, 10);

            return $widget->getLineInsets();
        });

        $this->assertSame([], $measured);
        $this->assertSame($beside, $widget->getRenderCache(40, 10), 'Neither dropped nor taken from the measure.');
        $this->assertSame([[2, 0]], $widget->getLineInsets());
    }
}
