<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\RenderCache;
use Symfony\Bridge\TuiHtml\Render\ArrayLineBuffer;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;

final class RenderCacheTest extends TestCase
{
    // The render cache itself is tested with Tui: only what TuiHtml adds to it here
    public function testAMeasureOfATextAsIfNoFloatWereBesideItKeepsItsRenderBesideThem()
    {
        $widget = new TextWidget('a');
        $widget->setLineInsets([[2, 0]]);
        $beside = new ArrayLineBuffer(['  a']);
        RenderCache::set($widget, $beside, 40, 10, '');
        $measured = $widget->measureWithoutFloats(function () use ($widget): array {
            $this->assertNull(RenderCache::get($widget, 40, 10, ''), 'Not taken for the measure.');
            RenderCache::set($widget, new ArrayLineBuffer(['a']), 40, 10, '');

            return $widget->getLineInsets();
        });
        $this->assertSame([], $measured);
        $this->assertSame($beside, RenderCache::get($widget, 40, 10, ''), 'Neither dropped nor taken from the measure.');
        $this->assertSame([[2, 0]], $widget->getLineInsets());
    }
}
