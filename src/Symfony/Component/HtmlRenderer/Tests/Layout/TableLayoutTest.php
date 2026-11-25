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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Layout\TableGeometry;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class TableLayoutTest extends TestCase
{
    public function testSpanningFloorSharesKeepFractionalSlackAndColumnKeys()
    {
        self::assertSame([1 => 1.25, 2 => 3.75], TableGeometry::floorShares([2.0, 1.0, 3.0], [2.0, 3.5, 10.5], 1, 2, 9.0));
        self::assertSame([1 => 2.5, 2 => 7.5], TableGeometry::floorShares([2.0, 1.0, 3.0], [2.0, 3.5, 10.5], 1, 2, 100.0));
        self::assertSame([], TableGeometry::floorShares([2.0, 3.0], [2.0, 3.0], 0, 2, 10.0));
        self::assertSame([], TableGeometry::floorShares([2.0, 3.0], [8.0, 9.0], 0, 2, 4.0));
    }

    public function testSpanningColumnsDistributeFractionalDeficit()
    {
        $widths = TableGeometry::columns([['column' => 0, 'span' => 1, 'width' => 10.25, 'minimum' => 5.0], ['column' => 1, 'span' => 1, 'width' => 20.0, 'minimum' => 5.0], ['column' => 0, 'span' => 2, 'width' => 40.75, 'minimum' => 10.0]], 2, 100.0, 0.5, false);
        self::assertSame([15.25, 25.0], $widths);
    }

    public function testColumnsShrinkInProportionToSlackAboveContentFloors()
    {
        $widths = TableGeometry::columns([['column' => 0, 'span' => 1, 'width' => 30.0, 'minimum' => 10.0], ['column' => 1, 'span' => 1, 'width' => 20.0, 'minimum' => 10.0]], 2, 35.0, 0.0, false);
        self::assertSame([20.0, 15.0], $widths);
    }

    public function testSpanningRowsAddDeficitToLastRow()
    {
        self::assertSame([10.25, 19.75], TableGeometry::rows([['row' => 0, 'span' => 1, 'height' => 10.25], ['row' => 1, 'span' => 1, 'height' => 5.0], ['row' => 0, 'span' => 2, 'height' => 30.5]], 2, 0.5));
    }

    public function testOverlappingRowSpansKeepStableMeasurementOrderAndFractionalGaps()
    {
        $spans = [10 => ['row' => 0, 'span' => 3, 'height' => 30.0], 20 => ['row' => 1, 'span' => 2, 'height' => 18.0], 30 => ['row' => 1, 'span' => 2, 'height' => 20.0]];
        $ordered = TableGeometry::orderRowSpans($spans);
        self::assertSame([20, 30, 10], array_keys($ordered));
        self::assertSame([10.5, 4.25, 15.25], TableGeometry::extendRows([10.5, 4.25, 3.75], $ordered, 0.5));
    }

    public function testOccupiedRowMinimumDoesNotPrematurelyRaiseEmptyRows()
    {
        $span = [['row' => 0, 'span' => 2, 'height' => 1.5]];
        self::assertSame([0.0, 0.0], TableGeometry::extendRows([0.0, 0.0], $span, 0.0, 1.0));
        self::assertSame([0.0, 1.5], TableGeometry::extendRows([0.0, 0.0], $span));
        $span[0]['height'] = 3.5;
        self::assertSame([0.0, 2.5], TableGeometry::extendRows([0.0, 0.0], $span, 0.0, 1.0));
        self::assertSame([0.0, 3.5], TableGeometry::extendRows([0.0, 0.0], $span));
    }

    public function testTableCellsShareColumnsAcrossRowsAndHonorRowSpans()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><table><tbody><tr><td rowspan="2">A</td><td>B</td></tr><tr><td>C</td></tr></tbody></table>');
        $cells = iterator_to_array($document->querySelectorAll('td'));
        $rows = iterator_to_array($document->querySelectorAll('tr'));
        $box = new Box($document->querySelector('table'), 'table', ['font-size' => '10px', 'line-height' => '12px', 'border-spacing' => '0.5px'], [new Box($document->querySelector('tbody'), 'table-row-group', [], [new Box($rows[0], 'table-row', [], [new Box($cells[0], 'table-cell', ['height' => '30.5px'], [], 'A'), new Box($cells[1], 'table-cell', [], [], 'B')]), new Box($rows[1], 'table-row', [], [new Box($cells[2], 'table-cell', [], [], 'C')])])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100.0, 80.0);
        $first = $layout->children[0]->children[0];
        $second = $layout->children[0]->children[1];
        self::assertSame(5.5, $first->children[1]->bounds->x);
        self::assertSame(5.5, $second->children[0]->bounds->x);
        self::assertSame(30.5, $first->children[0]->bounds->height);
        self::assertSame(30.5, $layout->bounds->height);
        self::assertSame(10.5, $layout->bounds->width);
    }

    public function testNativeDomColspanSharesTrackGeometry()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><table style="border-spacing:.5px"><tr><td colspan="2" id="wide">ABCD</td></tr><tr><td>A</td><td id="right">B</td></tr></table>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        self::assertSame(32.0, $result->getBounds($document->querySelector('#wide'))[0]->width);
        self::assertSame(16.25, $result->getBounds($document->querySelector('#right'))[0]->x);
    }

    public function testNativeDomRowspanStopsAtGroupBoundary()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><table><tbody><tr><td rowspan="0" style="height:40px">A</td><td>B</td></tr><tr><td>C</td></tr></tbody><tbody><tr><td id="next">D</td></tr></tbody></table>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        $next = $result->getBounds($document->querySelector('#next'))[0];
        self::assertSame(0.0, $next->x);
        self::assertSame(40.0, $next->y);
    }

    public function testFirstHeaderAndFooterGroupsFollowTablePaintOrder()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><table><tfoot><tr><td id="foot">F</td></tr></tfoot><tbody><tr><td id="body">B</td></tr></tbody><thead><tr><td id="head">H</td></tr></thead></table>');
        $result = (new HtmlRenderer())->render($document, new RenderingContext(100, 100));
        self::assertSame(0.0, $result->getBounds($document->querySelector('#head'))[0]->y);
        self::assertSame(19.2, $result->getBounds($document->querySelector('#body'))[0]->y);
        self::assertSame(38.4, $result->getBounds($document->querySelector('#foot'))[0]->y);
    }
}
