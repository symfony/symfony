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

final class MarginCollapseTest extends TestCase
{
    public function testSiblingMarginsCollapseWithSignedFractionalExtrema()
    {
        foreach ([[10.5, 20.25, 20.25], [-10.5, -20.25, -20.25], [10.5, -4.25, 6.25]] as [$bottom,$top,$gap]) {
            $root = new Box(null, 'block', [], [new Box(null, 'block', ['height' => '30px', 'margin-bottom' => $bottom.'px']), new Box(null, 'block', ['height' => '10px', 'margin-top' => $top.'px'])]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
            self::assertSame(30.0 + $gap, $layout->children[1]->bounds->y);
        }
    }

    public function testEmptySiblingCarriesOneMarginStrutAcrossThreeBoxes()
    {
        $root = new Box(null, 'block', [], [new Box(null, 'block', ['height' => '10px', 'margin-bottom' => '15px']), new Box(null, 'block', ['margin-top' => '20px', 'margin-bottom' => '-5px']), new Box(null, 'block', ['height' => '10px', 'margin-top' => '12px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(25.0, $layout->children[2]->bounds->y);
        self::assertSame(35.0, $layout->bounds->height);
    }

    public function testFirstAndLastChildMarginsAdjoinParentEdges()
    {
        $parent = new Box(null, 'block', ['margin-top' => '5px', 'margin-bottom' => '7px'], [new Box(null, 'block', ['height' => '10px', 'margin-top' => '20px', 'margin-bottom' => '15px'])]);
        $root = new Box(null, 'block', [], [$parent, new Box(null, 'block', ['height' => '10px', 'margin-top' => '12px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        self::assertSame(20.0, $layout->children[0]->bounds->y);
        self::assertSame(10.0, $layout->children[0]->bounds->height);
        self::assertSame(20.0, $layout->children[0]->children[0]->bounds->y);
        self::assertSame(45.0, $layout->children[1]->bounds->y);
    }

    public function testPaddingAndFlowRootKeepChildMarginsInside()
    {
        foreach ([['padding-top' => '1.25px'], ['display' => 'flow-root'], ['overflow' => 'auto']] as $barrier) {
            $display = $barrier['display'] ?? 'block';
            $parent = new Box(null, $display, $barrier, [new Box(null, 'block', ['height' => '10px', 'margin-top' => '20px'])]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$parent]), 100, 100);
            self::assertSame(0.0, $layout->children[0]->bounds->y);
            self::assertSame(20.0 + (isset($barrier['padding-top']) ? 1.25 : 0.0), $layout->children[0]->children[0]->bounds->y);
        }
    }

    public function testFlexItemsDoNotCollapseMargins()
    {
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'flex', ['flex-direction' => 'column'], [new Box(null, 'block', ['height' => '10px', 'margin-bottom' => '10px']), new Box(null, 'block', ['height' => '10px', 'margin-top' => '20px'])]), 100, 100);
        self::assertSame(40.0, $layout->children[1]->bounds->y);
    }

    public function testActualClearanceSeparatesMarginsButClearWithoutFloatsDoesNot()
    {
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $root = new Box(null, 'block', [], [new Box(null, 'block', ['height' => '10px', 'margin-bottom' => '5px']), new Box(null, 'block', ['float' => 'left', 'width' => '10px', 'height' => '40px']), new Box(null, 'block', ['height' => '10px', 'margin-top' => '20px', 'clear' => 'both'])]);
        $layout = $engine->layout($root, 100, 100);
        self::assertSame(75.0, $layout->children[2]->bounds->y);
        $parent = new Box(null, 'block', [], [new Box(null, 'block', ['height' => '10px', 'margin-top' => '20px', 'clear' => 'both'])]);
        $layout = $engine->layout(new Box(null, 'block', [], [$parent]), 100, 100);
        self::assertSame(20.0, $layout->children[0]->bounds->y);
        self::assertSame(10.0, $layout->children[0]->bounds->height);
    }

    public function testConstrainedParentUsesItsContentWidthForCollapsedPercentageMargins()
    {
        $parent = new Box(null, 'block', ['max-width' => '100px'], [new Box(null, 'block', ['height' => '10px', 'margin-top' => '10%'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, 'block', [], [$parent]), 500, 100);
        self::assertSame(10.0, $layout->children[0]->bounds->y);
        self::assertSame(10.0, $layout->children[0]->children[0]->bounds->y);
    }

    public function testFlexAndGridItemChildMarginsDoNotCollapseOutsideItem()
    {
        foreach (['flex', 'grid'] as $display) {
            $item = new Box(null, 'block', [], [new Box(null, 'block', ['height' => '10px', 'margin-top' => '20px'])]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box(null, $display, [], [$item]), 100, 100);
            self::assertSame(0.0, $layout->children[0]->bounds->y);
            self::assertSame(20.0, $layout->children[0]->children[0]->bounds->y);
        }
    }

    public function testForcedInlineBreakSeparatesBlockMarginStruts()
    {
        $root = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null,'block',['height' => '10px', 'margin-bottom' => '5px']), new Box(null,'line-break'), new Box(null,'block',['height' => '10px', 'margin-top' => '10px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root,100,100);
        self::assertSame(35.0,$layout->children[1]->bounds->y);
    }
}
