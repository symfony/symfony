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
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class ScrollbarLayoutTest extends TestCase
{
    public function testAutoBarsCanInduceTheOtherAxisWithoutGrowingSpecifiedDimensions()
    {
        $box = $this->box(['width' => '100px', 'height' => '40px'], new Box(null, 'block', ['width' => '95px', 'height' => '45px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertSame(100.0, $layout->bounds->width);
        self::assertSame(40.0, $layout->bounds->height);
        self::assertSame(90.0, $layout->scroll->port->width);
        self::assertSame(35.0, $layout->scroll->port->height);
        self::assertTrue($layout->scroll->horizontalBar ?? false);
        self::assertTrue($layout->scroll->verticalBar ?? false);
        self::assertSame(5.0, $layout->scroll->maxX);
        self::assertSame(10.0, $layout->scroll->maxY);
    }

    public function testAcyclicPercentageDependencyRemovesTheNowUnnecessaryHorizontalBar()
    {
        $box = $this->box(['width' => '110px', 'height' => '30px'], new Box(null, 'block', ['width' => 'calc(300% - 200px)', 'height' => '40px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertSame(100.0, $layout->scroll->port->width);
        self::assertSame(30.0, $layout->scroll->port->height);
        self::assertFalse($layout->scroll->horizontalBar);
        self::assertTrue($layout->scroll->verticalBar);
        self::assertFalse($layout->scroll->cycleResolved);
        self::assertSame(0.0, $layout->scroll->maxX);
    }

    public function testCyclicDependencyExplicitlyLocksOnlyTheVaryingAxes()
    {
        $box = $this->box(['width' => '110px', 'height' => '30px'], new Box(null, 'block', ['width' => 'calc(300% - 200px)', 'height' => '28px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertTrue($layout->scroll->cycleResolved ?? false);
        self::assertTrue($layout->scroll->horizontalBar);
        self::assertTrue($layout->scroll->verticalBar);
        self::assertSame(0.0, $layout->scroll->maxX);
        self::assertSame(3.0, $layout->scroll->maxY);
    }

    public function testStableBothEdgesReservesRtlGuttersWithoutPaintingAnUnneededBar()
    {
        $box = $this->box(['width' => '100px', 'height' => '40px', 'direction' => 'rtl', 'scrollbar-gutter' => 'stable both-edges'], new Box(null, 'block', ['width' => '50px', 'height' => '20px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertSame(10.0, $layout->scroll->port->x);
        self::assertSame(80.0, $layout->getContentBox()->width);
        self::assertFalse($layout->scroll->horizontalBar);
        self::assertFalse($layout->scroll->verticalBar);
        self::assertNotContains('scrollbar', array_column((new Painter())->paint($layout)->getOperations(), 'type'));
    }

    public function testNullDeviceAndScrollbarNoneKeepLogicalScrollingWithoutGutters()
    {
        foreach ([null, $this->metrics()] as $metrics) {
            $box = $this->box(['width' => '100px', 'height' => '40px', 'scrollbar-width' => 'none'], new Box(null, 'block', ['width' => '150px', 'height' => '80px']));
            $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $metrics))->layout($box, 200, 200);
            self::assertSame(100.0, $layout->scroll->port->width);
            self::assertSame(40.0, $layout->scroll->port->height);
            self::assertSame(50.0, $layout->scroll->maxX);
            self::assertSame(40.0, $layout->scroll->maxY);
        }
    }

    public function testGuttersReduceContentAndPositionedContainingBlockButNotBackgroundPadding()
    {
        $box = $this->box(['width' => '100.5px', 'height' => '40.5px', 'padding' => '2.25px', 'border-width' => '1.5px', 'position' => 'relative', 'overflow' => 'scroll'], new Box(null, 'block', ['position' => 'absolute', 'right' => '0', 'top' => '0', 'width' => '10px', 'height' => '10px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertSame(108.0, $layout->bounds->width);
        self::assertSame(105.0, $layout->getPaddingBox()->width);
        self::assertSame(95.0, $layout->scroll->port->width);
        self::assertSame(90.5, $layout->getContentBox()->width);
        self::assertSame(86.5, $layout->children[0]->bounds->x);
        $steps = \Symfony\Component\HtmlRenderer\Paint\PaintOrder::build($layout);
        $childBackground = array_values(array_filter($steps, static fn (array $step): bool => 'background' === $step['type'] && $step['fragment'] === $layout->children[0]))[0];
        self::assertEquals($layout->scroll->port, $childBackground['clips'][0]['rect']);
        $bar = array_values(array_filter($steps, static fn (array $step): bool => 'scrollbar' === $step['type']))[0];
        self::assertSame([], $bar['clips']);
    }

    public function testPaintsFractionalTracksAndThumbsWithResolvedCurrentColor()
    {
        $box = $this->box(['width' => '100px', 'height' => '40px', 'scrollbar-color' => 'currentcolor rgb(0, 0, 255)', 'color' => 'red', 'scrollbar-width' => 'thin', '--html-scroll-y' => '20'], new Box(null, 'block', ['width' => '50px', 'height' => '80px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        $bars = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op): bool => 'scrollbar' === $op['type']));
        self::assertCount(1, $bars);
        self::assertSame('y', $bars[0]['axis']);
        self::assertSame(['x' => 90.0, 'y' => 0.0, 'width' => 10.0, 'height' => 40.0], $bars[0]['track']);
        self::assertSame(['x' => 90.0, 'y' => 10.0, 'width' => 10.0, 'height' => 20.0], $bars[0]['thumb']);
        self::assertTrue($bars[0]['thin']);
        self::assertEquals([[1, 0, 0], 1], $bars[0]['thumb-color']);
        self::assertEquals([[0, 0, 1], 1], $bars[0]['track-color']);
    }

    public function testDefiniteFlexAndGridHeightsExcludeTheHorizontalGutter()
    {
        foreach (['flex', 'grid'] as $display) {
            $style = ['width' => '100px', 'height' => '40px', 'overflow' => 'scroll', 'flex-direction' => 'column', 'grid-template-rows' => '1fr'];
            $box = new Box(null, $display, $style, [new Box(null, 'block', ['flex-grow' => '1', 'min-height' => '0'])]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
            self::assertSame(35.0, $layout->children[0]->bounds->height, $display);
        }
    }

    public function testAnonymousPositionedScrollBoxKeepsItsStateAcrossRelayout()
    {
        $child = $this->box(['position' => 'absolute', 'width' => '100px', 'height' => '40px'], new Box(null, 'block', ['height' => '80px']));
        $box = new Box(null, 'block', ['position' => 'relative'], [$child]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertTrue($layout->children[0]->scroll->verticalBar);
        self::assertFalse($layout->children[0]->scroll->horizontalBar);
        self::assertSame(90.0, $layout->children[0]->getContentBox()->width);
    }

    public function testDeepOrdinaryScrollContainersResolveWithoutLosingContent()
    {
        $box = new Box(null, 'block', ['width' => '150px', 'height' => '100px']);
        for ($index = 23; $index >= 0; --$index) {
            $box = $this->box(['width' => (100 + $index).'px', 'height' => (40 + $index).'px'], $box);
        }
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        for ($index = 0; $index < 24; ++$index) {
            self::assertTrue($layout->scroll->horizontalBar);
            self::assertTrue($layout->scroll->verticalBar);
            self::assertSame(90.0 + $index, $layout->scroll->port->width);
            $layout = $layout->children[0];
        }
        self::assertSame(150.0, $layout->bounds->width);
    }

    public function testNestedPercentageCycleKeepsUsedBarsDistinctFromMaximumOffsets()
    {
        $middle = new Box(null, 'block', ['width' => '50%', 'overflow' => 'auto'], [new Box(null, 'block', ['width' => 'calc(300% - 100px)', 'height' => '28px'])]);
        $box = $this->box(['width' => '110px', 'height' => '30px'], $middle);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 200, 200);
        self::assertTrue($layout->scroll->cycleResolved);
        self::assertFalse($layout->scroll->horizontalBar);
        self::assertTrue($layout->scroll->verticalBar);
        self::assertTrue($layout->children[0]->scroll->cycleResolved);
        self::assertTrue($layout->children[0]->scroll->horizontalBar);
        self::assertFalse($layout->children[0]->scroll->verticalBar);
        self::assertSame(0.0, $layout->children[0]->scroll->maxX);
    }

    public function testDistinctBoxesOfTheSameNativeNodeHaveIndependentScrollbarStates()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><html><body><div id="owner"></div></body></html>');
        $node = $document->getElementById('owner');
        $small = new Box($node, 'block', ['width' => '100px', 'height' => '40px', 'overflow' => 'auto'], [new Box(null, 'block', ['height' => '80px'])]);
        $large = new Box($node, 'block', ['width' => '100px', 'height' => '100px', 'overflow' => 'auto'], [new Box(null, 'block', ['height' => '80px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout(new Box(null, 'block', [], [$small, $large]), 200, 200);
        self::assertTrue($layout->children[0]->scroll->verticalBar);
        self::assertFalse($layout->children[1]->scroll->verticalBar);
        self::assertSame(90.0, $layout->children[0]->scroll->port->width);
        self::assertSame(100.0, $layout->children[1]->scroll->port->width);
    }

    public function testTableHeightIsAMinimumAndCaptionAutoOrScrollDoesNotCreateBars()
    {
        foreach (['table', 'inline-table'] as $display) {
            foreach (['auto', 'scroll'] as $overflow) {
                $caption = new Box(null, 'table-caption', ['height' => '10px']);
                $cell = new Box(null, 'table-cell', [], [new Box(null, 'block', ['height' => '80px', 'width' => '200px'])]);
                $table = new Box(null, $display, ['width' => '100px', 'height' => '30px', 'overflow' => $overflow, 'border-width' => '1px'], [$caption, new Box(null, 'table-row', [], [$cell])]);
                $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($table, 300, 200);
                self::assertSame(92.0, $layout->bounds->height);
                self::assertSame(82.0, $layout->children[1]->bounds->height);
                self::assertSame('visible', $layout->style['overflow-x']);
                self::assertNull($layout->scroll);
                self::assertNull($layout->children[1]->scroll);
                self::assertNotContains('scrollbar', array_column((new Painter())->paint($layout)->getOperations(), 'type'));
            }
        }
    }

    public function testCaptionedHiddenTableClipsOnceAtItsWrapper()
    {
        $caption = new Box(null, 'table-caption', ['height' => '10px']);
        $cell = new Box(null, 'table-cell', [], [new Box(null, 'block', ['height' => '80px', 'width' => '200px'])]);
        $table = new Box(null, 'table', ['width' => '100px', 'height' => '30px', 'overflow' => 'hidden'], [$caption, new Box(null, 'table-row', [], [$cell])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($table, 300, 200);
        self::assertNotNull($layout->scroll);
        self::assertNull($layout->children[1]->scroll);
        $steps = \Symfony\Component\HtmlRenderer\Paint\PaintOrder::build($layout);
        self::assertSame(1, max(array_map(static fn (array $step): int => \count($step['clips']), $steps)));
        self::assertSame(0.0, $layout->children[0]->bounds->y);
    }

    public function testBlockDisplayKeepsScrollingInsteadOfUsingTableOverflowRules()
    {
        $box = $this->box(['width' => '100px', 'height' => '40px'], new Box(null, 'block', ['width' => '150px', 'height' => '80px']));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($box, 300, 200);
        self::assertTrue($layout->scroll->horizontalBar);
        self::assertTrue($layout->scroll->verticalBar);
    }

    public function testTableUsedOverflowDoesNotRewriteTheComputedStyle()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0;padding:0;border:0}table{width:100px;height:30px;overflow:scroll}caption{height:10px}td div{width:200px;height:80px}</style><table id="table"><caption>Title</caption><tr><td><div></div></td></tr></table>', new RenderingContext(300, 200, null, null, $this->metrics()));
        $table = $result->getDocument()->getElementById('table');
        self::assertSame('scroll', $result->getComputedStyle($table)['overflow-x']);
        self::assertNull($result->getScrollGeometry($table));
        self::assertNotContains('scrollbar', array_column($result->getDisplayList()->getOperations(), 'type'));
    }

    public function testTrimmedAtomicInlineCopiesKeepTheOriginalScrollbarCandidate()
    {
        $atomic = new Box(null, 'inline-block', ['width' => '50px', 'height' => '20px', 'overflow' => 'auto'], [new Box(null, 'block', ['width' => '80px', 'height' => '30px'])]);
        $inline = new Box(null, 'inline', [], [$atomic, new Box(null, 'inline', [], [], ' tail')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout(new Box(null, 'block', [], [new Box(null, 'inline', [], [], 'lead '), $inline]), 70, 200);
        $find = static function ($fragment) use (&$find) {
            if ('inline-block' === ($fragment->style['display'] ?? '')) {
                return $fragment;
            }
            foreach ($fragment->children as $child) {
                if (null !== $found = $find($child)) {
                    return $found;
                }
            }

            return null;
        };
        $fragment = $find($layout);
        self::assertNotNull($fragment);
        self::assertTrue($fragment->scroll->horizontalBar);
        self::assertTrue($fragment->scroll->verticalBar);
        self::assertSame(40.0, $fragment->scroll->port->width);
    }

    private function box(array $style, Box $child): Box
    {
        return new Box(null, 'block', array_replace(['overflow' => 'auto', 'font-size' => '10px', 'line-height' => '10px'], $style), [$child]);
    }

    private function metrics(): LayoutMetricsInterface
    {
        return new class implements LayoutMetricsInterface {
            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float
            {
                return $length;
            }

            public function outsideMarkerGutter(float $naturalGroupWidth): float
            {
                return 0.0;
            }

            public function scrollbarThickness(bool $vertical, bool $thin = false): float
            {
                return $vertical ? 10.0 : 5.0;
            }

            public function distribute(array $lengths, bool $vertical): array
            {
                return $lengths;
            }
        };
    }
}
