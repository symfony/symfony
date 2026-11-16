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
    public function testOnlyTheLastRenderIsKept()
    {
        $widget = new TextWidget('a');
        $widget->setRenderCache(new ArrayLineBuffer(['wide']), 40, 10);
        $widget->setRenderCache($narrow = new ArrayLineBuffer(['narrow']), 5, 10);
        $this->assertSame($narrow, $widget->getRenderCache(5, 10));
        $this->assertNull($widget->getRenderCache(40, 10));
    }

    public function testTheKeyIsPartOfTheCacheKey()
    {
        $widget = new TextWidget('a');
        $widget->setRenderCache($keyed = new ArrayLineBuffer(['keyed']), 40, 1, 'height:2147483647:width:40');
        $this->assertSame($keyed, $widget->getRenderCache(40, 1, 'height:2147483647:width:40'));
        $this->assertNull($widget->getRenderCache(40, 1));
        $this->assertNull($widget->getRenderCache(40, 1, 'height:40:width:2147483647'));
    }

    public function testAChangeOrAClearDropsTheRender()
    {
        $widget = new TextWidget('a');
        $widget->setRenderCache(new ArrayLineBuffer(['a']), 40, 10);
        $widget->invalidate();
        $this->assertNull($widget->getRenderCache(40, 10));
        $widget->setRenderCache(new ArrayLineBuffer(['a']), 40, 10);
        $widget->clearRenderCache();
        $this->assertNull($widget->getRenderCache(40, 10));
    }
}
