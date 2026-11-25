<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\RenderCache;
use Symfony\Component\Tui\Render\ArrayLineBuffer;
use Symfony\Component\Tui\Widget\TextWidget;

final class RenderCacheTest extends TestCase
{
    public function testRendersInAFewSizesAreKept()
    {
        $widget = new TextWidget('a');
        $wide = new ArrayLineBuffer(['wide']);
        $narrow = new ArrayLineBuffer(['narrow']);
        RenderCache::set($widget, $wide, 40, 10, '', [], 3);
        RenderCache::set($widget, $narrow, 5, 10, '', [], 3);
        $this->assertSame($narrow, RenderCache::get($widget, 5, 10, ''));
        $this->assertSame($narrow, $widget->getRenderCache(5, 10), 'The last render is the one of Tui.');
        $this->assertSame($wide, RenderCache::get($widget, 40, 10, ''));
        $this->assertNull(RenderCache::get($widget, 20, 10, ''));
    }

    public function testOnlyTheLastRenderIsKeptByDefault()
    {
        $widget = new TextWidget('a');
        RenderCache::set($widget, new ArrayLineBuffer(['wide']), 40, 10, '', [], 3);
        RenderCache::set($widget, $narrow = new ArrayLineBuffer(['narrow']), 5, 10, '');
        $this->assertSame($narrow, RenderCache::get($widget, 5, 10, ''));
        $this->assertNull(RenderCache::get($widget, 40, 10, ''), 'Dropped by a render that keeps none.');
    }

    public function testContainingDimensionsStayInSeparateKeys()
    {
        $widget = new TextWidget('a');
        $height = new ArrayLineBuffer(['height']);
        $width = new ArrayLineBuffer(['width']);
        RenderCache::set($widget, $height, 40, 10000, 'height:2147483647:width:40', ['height'], 3);
        RenderCache::set($widget, $width, 40, 10000, 'height:40:width:2147483647', ['width'], 3);
        $this->assertSame($height, RenderCache::get($widget, 40, 10000, 'height:2147483647:width:40'));
        $this->assertSame($width, RenderCache::get($widget, 40, 10000, 'height:40:width:2147483647'));
        $this->assertSame(['height'], RenderCache::getExtra($widget, 40, 10000, 'height:2147483647:width:40'));
        $this->assertSame(['width'], RenderCache::getExtra($widget, 40, 10000, 'height:40:width:2147483647'));
        $this->assertNull(RenderCache::get($widget, 40, 10000, ''));
    }

    public function testTheKeyDoesNotMixWithTheRows()
    {
        $widget = new TextWidget('a');
        $keyed = new ArrayLineBuffer(['keyed']);
        RenderCache::set($widget, $keyed, 40, 1, '0', [], 3);
        RenderCache::set($widget, new ArrayLineBuffer(['plain']), 40, 10, '', [], 3);
        $this->assertSame($keyed, RenderCache::get($widget, 40, 1, '0'));
        $this->assertNull(RenderCache::get($widget, 40, 1, ''));
        $this->assertNull(RenderCache::get($widget, 40, 10, '0'));
    }

    public function testOnlyAFewSizesAreKept()
    {
        $widget = new TextWidget('a');
        foreach (range(1, 6) as $columns) {
            RenderCache::set($widget, new ArrayLineBuffer([(string) $columns]), $columns, 1, '', [], 3);
        }
        // The last one and the three before it
        $this->assertNull(RenderCache::get($widget, 2, 1, ''));
        $this->assertNotNull(RenderCache::get($widget, 3, 1, ''));
        $this->assertSame(['6'], RenderCache::get($widget, 6, 1, '')->toArray());
    }

    public function testAChangeOrAClearDropsThemAll()
    {
        $widget = new TextWidget('a');
        RenderCache::set($widget, new ArrayLineBuffer(['wide']), 40, 10, '', ['floats'], 3);
        RenderCache::set($widget, new ArrayLineBuffer(['narrow']), 5, 10, '', [], 3);
        $widget->invalidate();
        $this->assertNull(RenderCache::get($widget, 40, 10, ''));
        $this->assertNull(RenderCache::get($widget, 5, 10, ''));
        $this->assertSame([], RenderCache::getExtra($widget, 40, 10, ''));
        RenderCache::set($widget, new ArrayLineBuffer(['wide']), 40, 10, '', ['floats'], 3);
        RenderCache::set($widget, new ArrayLineBuffer(['narrow']), 5, 10, '', [], 3);
        // Like Tui when a style sheet is added, which knows nothing of the renders kept here
        $widget->clearRenderCache();
        $this->assertNull(RenderCache::get($widget, 40, 10, ''));
        $this->assertNull(RenderCache::get($widget, 5, 10, ''));
        $this->assertSame([], RenderCache::getExtra($widget, 40, 10, ''));
    }

    public function testARenderKeptByTuiAloneDropsTheOthers()
    {
        $widget = new TextWidget('a');
        RenderCache::set($widget, new ArrayLineBuffer(['wide']), 40, 10, '', [], 3);
        RenderCache::set($widget, new ArrayLineBuffer(['narrow']), 5, 10, '', ['floats'], 3);
        // Like the native renderer, drawing the widget after another renderer was selected
        $widget->setRenderCache(new ArrayLineBuffer(['native']), 5, 10);
        $this->assertNull(RenderCache::get($widget, 40, 10, ''));
        $this->assertSame([], RenderCache::getExtra($widget, 5, 10, ''));
    }

    public function testARenderWithoutTheCacheKeepsTheRendersBefore()
    {
        $widget = new TextWidget('a');
        RenderCache::set($widget, $wide = new ArrayLineBuffer(['wide']), 40, 10, '', ['floats'], 3);
        RenderCache::set($widget, $narrow = new ArrayLineBuffer(['narrow']), 5, 10, '', ['narrow floats'], 3);

        $measured = RenderCache::without($widget, function () use ($widget) {
            $this->assertNull(RenderCache::get($widget, 5, 10, ''), 'Not seen by the measure.');
            $this->assertNull(RenderCache::get($widget, 40, 10, ''), 'Not seen by the measure.');
            RenderCache::set($widget, $lines = new ArrayLineBuffer(['measure']), 7, 10, '', [], 3);

            return $lines;
        });

        $this->assertSame(['measure'], $measured->toArray());
        $this->assertSame($narrow, RenderCache::get($widget, 5, 10, ''));
        $this->assertSame($narrow, $widget->getRenderCache(5, 10));
        $this->assertSame(['narrow floats'], RenderCache::getExtra($widget, 5, 10, ''));
        $this->assertSame($wide, RenderCache::get($widget, 40, 10, ''));
        $this->assertNull(RenderCache::get($widget, 7, 10, ''), 'The measure is not kept.');
    }

    public function testARenderWithoutTheCacheThatChangesTheWidgetKeepsNothing()
    {
        $widget = new TextWidget('a');
        RenderCache::set($widget, new ArrayLineBuffer(['narrow']), 5, 10, '', [], 3);

        RenderCache::without($widget, static function () use ($widget) {
            RenderCache::set($widget, new ArrayLineBuffer(['measure']), 7, 10, '');
            $widget->invalidate();
        });

        $this->assertNull(RenderCache::get($widget, 5, 10, ''));
        $this->assertNull(RenderCache::get($widget, 7, 10, ''));
    }
}
