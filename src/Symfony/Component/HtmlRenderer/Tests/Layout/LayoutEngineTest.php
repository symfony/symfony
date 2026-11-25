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

final class LayoutEngineTest extends TestCase
{
    public function testFractionalWidthsSurviveNestedLayout()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'block', ['width' => '33.333%'], [new Box(null, 'block', ['width' => '50%'])])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.5, 80.0);
        self::assertEqualsWithDelta(33.499665, $layout->children[0]->bounds->width, 0.000001);
        self::assertEqualsWithDelta(16.7498325, $layout->children[0]->children[0]->bounds->width, 0.000001);
    }

    public function testTextFragmentsUseInjectedMetricsAndWrapAtWords()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px'], [new Box(null, 'inline', [], [], 'one two three')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 36.5, 100.0);
        $lines = $layout->children[0]->children;
        self::assertSame(['one two', 'three'], array_column($lines, 'text'));
        self::assertSame(35.0, $lines[0]->bounds->width);
        self::assertSame(12.0, $lines[1]->bounds->y);
        self::assertSame(24.0, $layout->bounds->height);
    }

    public function testFlexDistributesFractionalRoomWithoutRounding()
    {
        $box = new Box(null, 'flex', ['gap' => '0.5px'], [new Box(null, 'block', ['flex-grow' => '1']), new Box(null, 'block', ['flex-grow' => '2'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 101.0, 80.0);
        self::assertSame(33.5, $layout->children[0]->bounds->width);
        self::assertSame(67.0, $layout->children[1]->bounds->width);
        self::assertSame(34.0, $layout->children[1]->bounds->x);
    }

    public function testGridTracksKeepFractionalGaps()
    {
        $box = new Box(null, 'grid', ['grid-template-columns' => '1fr 2fr', 'gap' => '0.5px'], [new Box(null, 'block', ['height' => '10.25px']), new Box(null, 'block', ['height' => '8px']), new Box(null, 'block', ['height' => '3px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 101.0, 80.0);
        self::assertSame(33.5, $layout->children[0]->bounds->width);
        self::assertSame(67.0, $layout->children[1]->bounds->width);
        self::assertSame(10.75, $layout->children[2]->bounds->y);
        self::assertSame(13.75, $layout->bounds->height);
    }

    public function testHiddenBoxesHaveNoFragments()
    {
        $box = new Box(null, 'block', [], [new Box(null, 'none', [], [], 'hidden'), new Box(null, 'block', ['height' => '1.5px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 101.0, 80.0);
        self::assertCount(1, $layout->children);
        self::assertSame(1.5, $layout->bounds->height);
    }

    public function testPositionedChildrenDoNotConsumeNormalFlow()
    {
        $box = new Box(null, 'block', ['height' => '40px'], [new Box(null, 'block', ['position' => 'absolute', 'left' => '2.25px', 'top' => '3.5px', 'width' => '5px', 'height' => '6px']), new Box(null, 'block', ['height' => '10px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.0, 80.0);
        self::assertSame(2.25, $layout->children[1]->bounds->x);
        self::assertSame(3.5, $layout->children[1]->bounds->y);
        self::assertSame(0.0, $layout->children[0]->bounds->y);
        self::assertSame(40.0, $layout->bounds->height);
    }

    public function testScrollingMovesContentAndKeepsContainerBounds()
    {
        $box = new Box(null, 'block', ['height' => '20px', 'overflow' => 'hidden', '--html-scroll-y' => '1.25'], [new Box(null, 'block', ['height' => '30px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.0, 80.0);
        self::assertSame(-1.25, $layout->children[0]->bounds->y);
        self::assertSame(0.0, $layout->bounds->y);
        self::assertSame(20.0, $layout->bounds->height);
    }

    public function testAdjacentInlineBoxesShareALine()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px'], [new Box(null, 'inline', [], [], 'one'), new Box(null, 'inline', [], [], 'two')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.0, 80.0);
        self::assertSame(15.0, $layout->children[0]->bounds->width);
        self::assertSame(15.0, $layout->children[1]->bounds->x);
        self::assertSame(0.0, $layout->children[1]->bounds->y);
        self::assertSame(12.0, $layout->bounds->height);
    }

    public function testViewportUnitsUseExplicitViewportRatherThanContainingBlock()
    {
        $box = new Box(null, 'block', ['width' => '50px'], [new Box(null, 'block', ['width' => '25vw', 'height' => '12.5vh'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 201.0, 100.0);
        self::assertEqualsWithDelta(50.25, $layout->children[0]->bounds->width, 0.0000001);
        self::assertSame(12.5, $layout->children[0]->bounds->height);
    }

    public function testFlexAutomaticBasesUseTextMetricsAndShrinkToFit()
    {
        $box = new Box(null, 'flex', ['font-size' => '10px', 'line-height' => '12px'], [new Box(null, 'block', ['min-width' => '0'], [], 'one'), new Box(null, 'block', ['min-width' => '0'], [], 'two')]);
        $engine = new LayoutEngine(new MonospaceTextMeasurer());
        $layout = $engine->layout($box, 100.0, 80.0);
        self::assertSame(15.0, $layout->children[0]->bounds->width);
        self::assertSame(15.0, $layout->children[1]->bounds->width);
        $layout = $engine->layout($box, 25.0, 80.0);
        self::assertSame(12.5, $layout->children[0]->bounds->width);
        self::assertSame(12.5, $layout->children[1]->bounds->width);
    }

    public function testCssMathUsesSharedParserAndContextMetricsWithoutRounding()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'width' => 'calc(50% - 1ch)', 'height' => 'clamp(1.25px, calc(2.5vh + 1px), 10px)']);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 101.5, 100.0);
        self::assertEqualsWithDelta(45.75, $layout->bounds->width, 0.0000001);
        self::assertSame(3.5, $layout->bounds->height);
    }

    public function testContentBoxConstraintsIncludeChromeAndMinimumWins()
    {
        $box = new Box(null, 'block', ['width' => '20px', 'height' => '20px', 'padding' => '2px', 'border-width' => '1px', 'min-width' => '30px', 'max-width' => '15px', 'min-height' => '30px', 'max-height' => '15px']);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(36.0, $layout->bounds->width);
        self::assertSame(36.0, $layout->bounds->height);
    }

    public function testBorderBoxConstraintsAlreadyIncludeChrome()
    {
        $box = new Box(null, 'block', ['box-sizing' => 'border-box', 'width' => '20px', 'height' => '20px', 'padding' => '2px', 'border-width' => '1px', 'max-width' => '15px', 'min-height' => '25.5px']);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(15.0, $layout->bounds->width);
        self::assertSame(25.5, $layout->bounds->height);
    }

    public function testAutoHeightIsClampedAfterMeasuringContent()
    {
        $box = new Box(null, 'block', ['max-height' => '20px', 'padding' => '2px', 'border-width' => '1px'], [new Box(null, 'block', ['height' => '40px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(26.0, $layout->bounds->height);
    }

    public function testFlexWrappingAlignsEachLineInFractionalRoom()
    {
        $box = new Box(null, 'flex', ['flex-wrap' => 'wrap', 'gap' => '.5px', 'justify-content' => 'center', 'align-items' => 'flex-end'], [new Box(null, 'block', ['width' => '40.25px', 'height' => '10.5px']), new Box(null, 'block', ['width' => '40.25px', 'height' => '20.25px']), new Box(null, 'block', ['width' => '40.25px', 'height' => '5.25px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.0, 100.0);
        self::assertSame(9.5, $layout->children[0]->bounds->x);
        self::assertSame(9.75, $layout->children[0]->bounds->y);
        self::assertSame(29.875, $layout->children[2]->bounds->x);
        self::assertSame(20.75, $layout->children[2]->bounds->y);
        self::assertSame(26.0, $layout->bounds->height);
    }

    public function testFlexColumnSharesDefiniteHeight()
    {
        $box = new Box(null, 'flex', ['flex-direction' => 'column', 'height' => '60px', 'gap' => '.5px'], [new Box(null, 'block', ['height' => '10px', 'flex-grow' => '1']), new Box(null, 'block', ['height' => '10px', 'flex-grow' => '2'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.0, 100.0);
        self::assertEqualsWithDelta(23.1666666667, $layout->children[0]->bounds->height, 0.000001);
        self::assertEqualsWithDelta(36.3333333333, $layout->children[1]->bounds->height, 0.000001);
    }

    public function testGridRepeatPlacesSpanningItemsAndImplicitRows()
    {
        $box = new Box(null, 'grid', ['grid-template-columns' => 'repeat(2,minmax(0,1fr))', 'gap' => '.5px'], [new Box(null, 'block', ['grid-column' => 'span 2', 'height' => '10.25px']), new Box(null, 'block', ['height' => '5.5px']), new Box(null, 'block', ['height' => '3px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.5, 100.0);
        self::assertSame(100.5, $layout->children[0]->bounds->width);
        self::assertSame(50.0, $layout->children[1]->bounds->width);
        self::assertSame(50.5, $layout->children[2]->bounds->x);
        self::assertSame(10.75, $layout->children[1]->bounds->y);
        self::assertSame(16.25, $layout->bounds->height);
    }

    public function testGridDefiniteRowsAndItemAlignmentUseFractionalGeometry()
    {
        $box = new Box(null, 'grid', ['grid-template-columns' => '1fr 1fr', 'grid-template-rows' => '20.25px 1fr', 'height' => '60.5px', 'gap' => '.5px', 'justify-items' => 'center', 'align-items' => 'center'], [new Box(null, 'block', ['width' => '5.5px', 'height' => '5.25px']), new Box(null, 'block', ['grid-row' => '2', 'height' => '5.25px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.5, 100.0);
        self::assertSame(22.25, $layout->children[0]->bounds->x);
        self::assertSame(7.5, $layout->children[0]->bounds->y);
        self::assertSame(38.0, $layout->children[1]->bounds->y);
    }

    public function testBordersResolveEachSideWithoutCellRounding()
    {
        $box = new Box(null, 'block', ['width' => '20px', 'height' => '10px', 'border-top-width' => '1.25px', 'border-right-width' => '2px', 'border-bottom-width' => '3.5px', 'border-left-width' => '4px'], [new Box(null, 'block', ['height' => '1px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(26.0, $fragment->bounds->width);
        self::assertSame(14.75, $fragment->bounds->height);
        self::assertSame(4.0, $fragment->children[0]->bounds->x);
        self::assertSame(1.25, $fragment->children[0]->bounds->y);
    }

    public function testNamedGridAreasPlaceItemsIndependentlyOfSourceOrder()
    {
        $box = new Box(null, 'grid', ['grid-template-columns' => '1fr 2fr', 'grid-template-areas' => '"head head" "side main"', 'gap' => '.5px'], [new Box(null, 'block', ['grid-area' => 'main', 'height' => '5px']), new Box(null, 'block', ['grid-area' => 'head', 'height' => '10.25px']), new Box(null, 'block', ['grid-area' => 'side', 'height' => '3px'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 101, 100);
        self::assertSame(34.0, $fragment->children[0]->bounds->x);
        self::assertSame(10.75, $fragment->children[0]->bounds->y);
        self::assertSame(101.0, $fragment->children[1]->bounds->width);
        self::assertSame(0.0, $fragment->children[1]->bounds->y);
    }

    public function testNormalFlexContentAlignmentStretchesWrappedLines()
    {
        $item = static fn (): Box => new Box(null, 'block', ['width' => '15px', 'height' => 'auto'], [], 'a');
        $box = new Box(null, 'flex', ['width' => '20px', 'height' => '60px', 'font-size' => '10px', 'line-height' => '10px', 'flex-wrap' => 'wrap', 'align-content' => 'normal'], [$item(), $item(), $item()]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(20.0, $fragment->children[0]->bounds->height);
        self::assertSame(20.0, $fragment->children[1]->bounds->y);
        self::assertSame(40.0, $fragment->children[2]->bounds->y);
    }

    public function testSingleWrappedFlexLineUsesContentAlignment()
    {
        $item = new Box(null, 'block', ['width' => '15px', 'height' => '10px']);
        $box = new Box(null, 'flex', ['width' => '20px', 'height' => '60px', 'flex-wrap' => 'wrap', 'align-content' => 'center'], [$item]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(25.0, $fragment->children[0]->bounds->y);
    }
}
