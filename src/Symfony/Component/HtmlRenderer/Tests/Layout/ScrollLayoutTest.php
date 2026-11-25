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
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class ScrollLayoutTest extends TestCase
{
    public function testScrollPortExcludesBorderAndOffsetsClampToActualExtent()
    {
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'border-width' => '1px', 'overflow' => 'auto', '--html-scroll-x' => '999', '--html-scroll-y' => '999'], [new Box(null, 'block', ['width' => '80.5px', 'height' => '60.25px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(40.0, $layout->scroll->port->width);
        self::assertSame(40.5, $layout->scroll->maxX);
        self::assertSame(40.25, $layout->scroll->maxY);
        self::assertSame(40.5, $layout->scroll->offsetX);
        self::assertSame(-39.5, $layout->children[0]->bounds->x);
        self::assertSame(-39.25, $layout->children[0]->bounds->y);
    }

    public function testAbsoluteContentScrollsButFixedAndTopLayerDoNotContributeOrMove()
    {
        $box = new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'position' => 'relative', 'overflow' => 'auto', '--html-scroll-y' => '999'], [
            new Box(null, 'block', ['position' => 'absolute', 'left' => '0', 'top' => '50px', 'width' => '10px', 'height' => '10px']),
            new Box(null, 'block', ['position' => 'fixed', 'left' => '0', 'top' => '100px', 'width' => '10px', 'height' => '10px']),
            new Box(null, 'block', ['top' => '150px', 'width' => '10px', 'height' => '10px'], [], null, null, null, null, 1),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 200);
        self::assertSame(40.0, $layout->scroll->maxY);
        self::assertSame(10.0, $layout->children[0]->bounds->y);
        self::assertSame(100.0, $layout->children[1]->bounds->y);
        self::assertSame(150.0, $layout->children[2]->bounds->y);
    }

    public function testNestedScrollOverflowDoesNotIncreaseAncestorRange()
    {
        $box = new Box(null, 'block', ['width' => '50px', 'height' => '30px', 'overflow' => 'auto'], [
            new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'overflow' => 'auto', '--html-scroll-y' => '10'], [new Box(null, 'block', ['height' => '80px'])]),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(0.0, $layout->scroll->maxY);
        self::assertSame(60.0, $layout->children[0]->scroll->maxY);
        self::assertSame(-10.0, $layout->children[0]->children[0]->bounds->y);
    }

    public function testTransformOverflowContributesAndRecomputationClampsAfterContentShrink()
    {
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $style = ['width' => '40px', 'height' => '20px', 'overflow' => 'auto', '--html-scroll-y' => '50'];
        $layout = $engine->layout(new Box(null, 'block', $style, [new Box(null, 'block', ['width' => '10px', 'height' => '10px', 'transform' => 'translateY(70.5px)'])]), 100, 100);
        self::assertSame(60.5, $layout->scroll->maxY);
        self::assertSame(50.0, $layout->scroll->offsetY);
        $layout = $engine->layout(new Box(null, 'block', $style, [new Box(null, 'block', ['height' => '5px'])]), 100, 100);
        self::assertSame(0.0, $layout->scroll->offsetY);
        self::assertSame(0.0, $layout->children[0]->bounds->y);
    }

    public function testClippedAxisRejectsScrollingAndViewportChangesClampRange()
    {
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $box = new Box(null, 'block', ['height' => '20px', 'overflow-x' => 'clip', 'overflow-y' => 'auto', '--html-scroll-x' => '50', '--html-scroll-y' => '-10'], [new Box(null, 'block', ['width' => '80px', 'height' => '60px'])]);
        $layout = $engine->layout($box, 40, 100);
        self::assertFalse($layout->scroll->horizontal);
        self::assertSame(0.0, $layout->scroll->maxX);
        self::assertSame(0.0, $layout->scroll->offsetX);
        self::assertSame(0.0, $layout->scroll->offsetY);
        self::assertSame(40.0, $layout->scroll->maxY);
        $layout = $engine->layout(new Box(null, 'block', ['height' => '100%', 'overflow' => 'auto', '--html-scroll-y' => '100'], [new Box(null, 'block', ['height' => '60px'])]), 100, 50);
        self::assertSame(10.0, $layout->scroll->offsetY);
        $layout = $engine->layout(new Box(null, 'block', ['height' => '100%', 'overflow' => 'auto', '--html-scroll-y' => '100'], [new Box(null, 'block', ['height' => '60px'])]), 100, 80);
        self::assertSame(0.0, $layout->scroll->offsetY);
    }

    public function testFixedContentUsesItsTransformedOrContainedScrollableContext()
    {
        foreach ([['transform' => 'translateX(10px)'], ['container-type' => 'inline-size'], ['contain' => 'layout']] as $containment) {
            $box = new Box(null, 'block', array_replace(['width' => '40px', 'height' => '20px', 'overflow' => 'auto', '--html-scroll-y' => '999'], $containment), [
                new Box(null, 'block', ['position' => 'fixed', 'top' => '50px', 'left' => '0', 'width' => '10px', 'height' => '10px']),
            ]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 200);
            self::assertSame(40.0, $layout->scroll->maxY);
            self::assertSame(10.0, $layout->children[0]->bounds->y);
        }
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', ['width' => '40px', 'height' => '20px', 'overflow' => 'auto', '--html-scroll-y' => '999'], [
            new Box(null, 'block', ['position' => 'fixed', 'top' => '50px', 'left' => '0', 'width' => '10px', 'height' => '10px']),
        ]), 200, 200);
        self::assertSame(0.0, $layout->scroll->maxY);
        self::assertSame(50.0, $layout->children[0]->bounds->y);
    }
}
