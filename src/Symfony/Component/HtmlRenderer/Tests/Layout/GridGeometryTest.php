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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\GridGeometry;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class GridGeometryTest extends TestCase
{
    public function testAutomaticTracksGrowFromIntrinsicMinimumWithinAvailableRoom()
    {
        $length = static fn (string $value): ?float => str_ends_with($value, 'px') ? (float) $value : null;
        $tracks = GridGeometry::tracks('auto auto', 60.5, 0.5, $length);
        self::assertSame([25.25, 35.25], GridGeometry::resolve($tracks, 61.0, 0.5, [80.0, 90.0], [10.0, 20.0], false));
        self::assertSame([80.0, 20.0], GridGeometry::resolve($tracks, 60.5, 0.5, [80.0, 20.0], [80.0, 20.0], false));
        $tracks = GridGeometry::tracks('20px max-content', 30.0, 0.0, $length);
        self::assertSame([20.0, 80.0], GridGeometry::resolve($tracks, 30.0, 0.0, [0.0, 80.0], [0.0, 10.0], false));
        $tracks = GridGeometry::tracks('fit-content(20px) auto', 60.0, 0.0, $length);
        self::assertSame([20.0, 40.0], GridGeometry::resolve($tracks, 60.0, 0.0, [80.0, 80.0], [10.0, 10.0], false));
        $tracks = GridGeometry::tracks('auto 1fr', 100.0, 0.0, $length);
        self::assertSame([20.0, 80.0], GridGeometry::resolve($tracks, 100.0, 0.0, [20.0, 0.0], [10.0, 10.0], false));
    }

    public function testAutoTrackWrapsAtAvailableSizeButKeepsUnbreakableAndNowrapMinima()
    {
        $result = (new HtmlRenderer())->render('<style>html,body,p{margin:0;font-size:10px;line-height:10px}.grid{display:grid;width:20px}.nowrap{white-space:nowrap}</style><div class="grid"><p id="wrap">long long long</p></div><div class="grid"><p id="word">unbroken</p></div><div class="grid"><p class="nowrap" id="nowrap">a b c d</p></div>', new RenderingContext(100, 100));
        $document = $result->getDocument();
        self::assertSame(20.0, $result->getBounds($document->getElementById('wrap'))[0]->width);
        self::assertSame(30.0, $result->getBounds($document->getElementById('wrap'))[0]->height);
        self::assertSame(40.0, $result->getBounds($document->getElementById('word'))[0]->width);
        self::assertSame(35.0, $result->getBounds($document->getElementById('nowrap'))[0]->width);
    }

    public function testNumberedLineResolutionKeepsSignedCoordinates()
    {
        self::assertSame([-2, 3], GridGeometry::resolveLines(-6, 2, 1, 3));
        self::assertSame([-2, 3], GridGeometry::resolveLines(2, -6, 1, 3));
        self::assertSame([0, 2], GridGeometry::resolveLines(null, 3, 2, 3));
        self::assertSame([null, 2], GridGeometry::resolveLines(null, null, 2, 3));
        self::assertSame([2, 1], GridGeometry::resolveLines(-2, -1, 1, 3));
    }

    public function testNegativeLinesCreateTracksBeforeExplicitGrid()
    {
        $placed = GridGeometry::place([['grid-column' => '-6 / 2'], ['grid-row' => '2', 'grid-column' => '1'], ['grid-row' => '2']], 3, false, false, 1, $columnLead, $rowLead);
        self::assertSame(2, $columnLead);
        self::assertSame(0, $rowLead);
        self::assertSame([[0, 0, 1, 3], [1, 2, 1, 1], [1, 0, 1, 1]], $placed);
        GridGeometry::place([['grid-row' => '-4 / -1', 'grid-column' => '1']], 2, false, false, 2, $columnLead, $rowLead);
        self::assertSame(1, $rowLead);
        $placed = GridGeometry::place([['grid-row' => '-4 / -1', 'grid-column' => '1'], ['grid-row' => '1', 'grid-column' => '2']], 2, false, true, 2, $columnLead, $rowLead);
        self::assertSame(1, $rowLead);
        self::assertSame([[0, 0, 3, 1], [1, 1, 1, 1]], $placed);
    }

    public function testLeadingImplicitTracksKeepBackwardCycleAndFractionalGeometry()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>html,body{margin:0}.g{display:grid;grid-template-columns:10.5px 10.5px 10.5px;grid-auto-columns:3.25px 4.5px}.wide{grid-column:-6 / 2}.a{grid-column:1;grid-row:2}</style><div class="g"><div class="wide">w</div><div class="a">a</div><div class="b" style="grid-row:2">b</div></div>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        self::assertSame(7.75, $result->getBounds($document->querySelector('.a'))[0]->x);
        self::assertSame(18.25, $result->getBounds($document->querySelector('.wide'))[0]->width);
        self::assertSame(0.0, $result->getBounds($document->querySelector('.b'))[0]->x);
    }

    public function testLeadingRowsUseImplicitSizesWithoutChangingExplicitLines()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>html,body{margin:0}.g{display:grid;grid-template-columns:20px 20px;grid-template-rows:10.5px 12.25px;grid-auto-rows:3.25px 4.5px}.t{grid-row:-5 / -1;grid-column:1}.a{grid-row:1;grid-column:2}</style><div class="g"><div class="t"></div><div class="a"></div></div>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        self::assertSame(7.75, $result->getBounds($document->querySelector('.a'))[0]->y);
        self::assertSame(30.5, $result->getBounds($document->querySelector('.t'))[0]->height);
    }

    public function testFlexibleTracksFreezeMinimaWithoutRounding()
    {
        self::assertSame([0 => 10.0 / 3.0, 1 => 20.0 / 3.0], GridGeometry::flexibleTracks([0.0, 0.0], [1.0, 2.0], 10.0));
        self::assertSame([1 => 8.0, 2 => 4.0], GridGeometry::flexibleTracks([8.0, 0.0, 3.0], [1.0, 2.0, 1.0], 20.0));
        self::assertSame([2 => 2.25], GridGeometry::flexibleTracks([2.5, 8.0, 0.0], [1 => 1.0, 2 => 1.0], 12.75));
        self::assertSame([], GridGeometry::flexibleTracks([4.0, 2.0], [1.0, 1.0], 3.0));
        self::assertSame([], GridGeometry::flexibleTracks([2.0], [], 10.0));
    }

    public function testRepeatAndMinmaxKeepFractionalTrackSizes()
    {
        $tracks = GridGeometry::tracks('repeat(3, minmax(10.25px, 1fr))', 101.5, 0.5, static fn (string $value): ?float => str_ends_with($value, 'px') || is_numeric($value) ? (float) $value : null);
        $widths = GridGeometry::resolve($tracks, 101.5, 0.5, [], []);
        self::assertCount(3, $widths);
        foreach ($widths as $width) {
            self::assertSame(33.5, $width);
        }
    }

    public function testFractionTracksFreezeAtMinContent()
    {
        $tracks = GridGeometry::tracks('1fr 2fr', 100.0, 0.0, static fn (string $value): ?float => is_numeric($value) ? (float) $value : null);
        self::assertSame([60.0, 40.0], GridGeometry::resolve($tracks, 100.0, 0.0, [60.0, 10.0], [60.0, 10.0]));
    }

    public function testAutoRepeatUsesTrackMinimumAndGap()
    {
        $tracks = GridGeometry::tracks('repeat(auto-fill,minmax(30px,1fr))', 100.0, 1.0, static fn (string $value): ?float => (float) $value);
        self::assertCount(3, $tracks);
    }

    public function testPlacementReservesExplicitCellsBeforeAutomaticItems()
    {
        $placed = GridGeometry::place([[], ['grid-column' => '1', 'grid-row' => '1'], ['grid-column' => 'span 2']], 3);
        self::assertSame([0, 1, 1, 1], $placed[0]);
        self::assertSame([0, 0, 1, 1], $placed[1]);
        self::assertSame([1, 0, 1, 2], $placed[2]);
    }

    public function testUntrustedGridPlacementCannotAllocateUnboundedImplicitTracks()
    {
        $this->expectException(\LengthException::class);
        GridGeometry::place([['grid-row' => '999999999', 'grid-column' => '1']], 1);
    }

    public function testNamedAreasMustFillRectangles()
    {
        self::assertSame(['head' => [0, 0, 0, 1], 'side' => [1, 0, 1, 0], 'main' => [1, 1, 1, 1]], GridGeometry::areas('"head head" "side main"'));
        self::assertNull(GridGeometry::areas('"a a" "a b"'));
    }

    public function testNamedAreaShorthandSpansBothColumnsAfterCascadeExpansion()
    {
        $html = '<style>.page{display:grid;width:480px;grid-template-columns:96px 1fr;grid-template-areas:"head head" "side main"}header{grid-area:head;height:16px}aside{grid-area:side;height:16px}main{grid-area:main;height:16px}</style><div class="page"><header></header><aside></aside><main></main></div>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(800, 640));
        self::assertSame(480.0, $result->getBounds($result->getDocument()->querySelector('header'))[0]->width);
        self::assertSame(96.0, $result->getBounds($result->getDocument()->querySelector('main'))[0]->x);
    }

    public function testNamedLinesAndRepeatedOccurrencesResolveBeforePlacement()
    {
        $html = '<style>main{display:grid;width:100px;grid-template-columns:[full-start]10px [content-start]1fr [content-end]10px [full-end]}p{margin:0;height:5px}.bleed{grid-column:full-start/full-end}.body{grid-column:content}</style><main><p class="bleed"></p><p class="body"></p></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        self::assertSame(100.0, $result->getBounds($result->getDocument()->querySelector('.bleed'))[0]->width);
        $body = $result->getBounds($result->getDocument()->querySelector('.body'))[0];
        self::assertSame(10.0, $body->x);
        self::assertSame(80.0, $body->width);
        self::assertSame(5.0, $body->y);
    }

    public function testNamedRepeatedLinesCountFromEitherEndAndResolveSpans()
    {
        $html = '<style>main{display:grid;width:100px;grid-template-columns:repeat(3,[col]10px) [last]1fr}p{margin:0;height:5px}.a{grid-column:col 2/last}.b{grid-column:col -1}.c{grid-column:span col/last}.d{grid-column:col 1/span last}</style><main><p class="a"></p><p class="b"></p><p class="c"></p><p class="d"></p></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        foreach (['.a' => [10.0, 20.0], '.b' => [20.0, 10.0], '.c' => [20.0, 10.0], '.d' => [0.0, 30.0]] as $selector => [$x,$width]) {
            $bounds = $result->getBounds($result->getDocument()->querySelector($selector))[0];
            self::assertSame($x, $bounds->x, $selector);
            self::assertSame($width, $bounds->width, $selector);
        }
    }

    public function testAutoRepeatReservesSuffixAndKeepsItsNamedLine()
    {
        $html = '<style>main{display:grid;width:100px;grid-template-columns:[edge]10px repeat(auto-fill,[cell]20px) [tail]10px;gap:0 5px}p{margin:0;height:5px}.tail{grid-column:tail}.cells{grid-column:cell 2/cell 3}</style><main><p class="tail"></p><p class="cells"></p></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        $tail = $result->getBounds($result->getDocument()->querySelector('.tail'))[0];
        self::assertSame(90.0, $tail->x);
        self::assertSame(10.0, $tail->width);
        $cells = $result->getBounds($result->getDocument()->querySelector('.cells'))[0];
        self::assertSame(40.0, $cells->x);
        self::assertSame(20.0, $cells->width);
    }
}
