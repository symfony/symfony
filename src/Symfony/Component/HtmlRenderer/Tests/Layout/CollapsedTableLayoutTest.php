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
use Symfony\Component\HtmlRenderer\Paint\PaintOrder;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class CollapsedTableLayoutTest extends TestCase
{
    public function testIndependentRowGroupAndColumnCandidatesWinTheirOwnEdges()
    {
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0}table{border-collapse:collapse;border:1px solid black}col{border-right:4px solid #ff00ff}tbody{border-bottom:5px solid #00ff00}tr{color:blue;border-top:3px solid currentColor}td{width:10px;padding:0;border:2px solid red}</style><table><col><tbody><tr><td>A</td></tr></tbody></table>', new RenderingContext(100, 100));
        $lines = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type']));
        self::assertSame([3.0, 5.0, 2.0, 4.0], array_column($lines, 'width'));
        self::assertSame([[[0.0, 0.0, 1.0], 1.0], [[0.0, 1.0, 0.0], 1.0], [[1.0, 0.0, 0.0], 1.0], [[1.0, 0.0, 1.0], 1.0]], array_column($lines, 'color'));
    }

    public function testRowSpanningCellHasNoRuleThroughItsInterior()
    {
        $result = (new HtmlRenderer())->render('<style>table{border-collapse:collapse}td{width:10px;padding:0;border:1px solid}</style><table><tr><td rowspan="2">A</td><td>B</td></tr><tr><td>C</td></tr></table>', new RenderingContext(100, 100));
        $lines = array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type']);
        self::assertCount(11, $lines);
    }

    public function testManyNarrowRowsDoNotUseTheSumOfAllSpansAsAWidthBound()
    {
        $result = (new HtmlRenderer())->render('<style>table{border-collapse:collapse}td{border:1px solid}</style><table>'.str_repeat('<tr><td></td></tr>', 100).'</table>', new RenderingContext(100, 100));
        self::assertCount(301, array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type']));
    }

    public function testOwnedBorderSkipsItsOwnClipAndScrollButKeepsAncestorConstraints()
    {
        $session = (new HtmlRenderer())->createSession('<style>html,body{margin:0}#clip{width:15px;height:80px;overflow:hidden}table{border-collapse:collapse;overflow:hidden}td{width:10px;padding:0;border:3px solid}span{position:relative;left:50px}</style><div id="clip"><table><tr><td><span>A</span></td><td>B</td></tr></table></div>', new RenderingContext(100, 100));
        $document = $session->getDocument();
        $table = $document->querySelector('table');
        $clip = $document->getElementById('clip');
        $before = $session->render();
        $lines = static fn ($result): array => array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type']));
        foreach (PaintOrder::build($before->getLayout()) as $step) {
            if ('table-border' === $step['fragment']->pseudoElement) {
                self::assertSame([$clip], array_map(static fn (array $entry) => $entry['owner']->node, $step['clips']));
            }
        }
        self::assertGreaterThan(0.0, $before->getScrollGeometry($table)->maxX);
        $session->scroll($table, 5, 0);
        self::assertSame($lines($before), $lines($session->render()));
        $session->scroll($clip, 5, 0);
        self::assertSame($lines($before)[0]['x1'] - 5.0, $lines($session->render())[0]['x1']);
    }

    public function testCollapsedGridBudgetRejectsLargeSpanBeforeOwnershipExpansion()
    {
        $html = '<style>table{border-collapse:collapse}td{border:1px solid}</style><table>'.str_repeat('<tr><td colspan="1000" rowspan="6"></td></tr>', 6).'</table>';
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('border grid position limit');
        (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
    }

    public function testSparseCellsAtCollapsedGridBudgetKeepEveryBorderSegment()
    {
        $html = '<style>table{border-collapse:collapse}td{border:1px solid}</style><table>'.str_repeat('<tr><td colspan="500"></td></tr>', 10).'</table>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        $lines = array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type']);
        self::assertCount(5520, $lines);
    }

    public function testCollapsedPaintContainsPortableLinesAndExplicitJunctions()
    {
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0}table{border-collapse:collapse}td{width:10px;padding:0;border:3px solid red}</style><table><tr><td>A</td><td>B</td></tr></table>', new RenderingContext(100, 100));
        $operations = $result->getDisplayList()->getOperations();
        self::assertSame([], array_values(array_filter($operations, static fn (array $op): bool => 'border' === $op['type'])));
        $lines = array_values(array_filter($operations, static fn (array $op): bool => 'line' === $op['type']));
        self::assertCount(7, $lines);
        self::assertSame([false, true, true, false], $lines[0]['start']);
        self::assertSame([false, true, true, true], $lines[0]['end']);
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $lines[0]['color']);
        self::assertSame(3.0, $lines[0]['width']);
        self::assertSame([1.5, 1.5, 14.5, 1.5], [$lines[0]['x1'], $lines[0]['y1'], $lines[0]['x2'], $lines[0]['y2']]);
    }

    public function testHiddenAndWiderCellEdgesResolveBeforeGeometryAndPainting()
    {
        $renderer = new HtmlRenderer();
        $html = '<style>html,body{margin:0}table{border-collapse:collapse}td{width:10px;padding:0;border:3px solid blue}#a{border-right:5px dashed red}</style><table><tr><td id="a">A</td><td id="b">B</td></tr></table>';
        $result = $renderer->render($html, new RenderingContext(100, 100));
        $lines = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type'] && $op['x1'] === $op['x2']));
        self::assertSame('dashed', $lines[1]['style']);
        self::assertSame(5.0, $lines[1]['width']);
        self::assertSame([[1.0, 0.0, 0.0], 1.0], $lines[1]['color']);
        $result->getDocument()->getElementById('b')->setAttribute('style', 'border-left-style:hidden');
        $result = $renderer->render($result->getDocument(), new RenderingContext(100, 100));
        $lines = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'line' === $op['type'] && $op['x1'] === $op['x2']));
        self::assertCount(2, $lines);
        self::assertSame(26.0, $result->getBounds($result->getDocument()->querySelector('table'))[0]->width);
    }

    public function testSharedFractionalBordersUseHalvesInCellAndTableGeometry()
    {
        // CSS 2.2 section 17.6.2; also measured in Chrome with these exact CSS pixels.
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0}table{border-collapse:collapse}td{width:10px;padding:0;border:3px solid;font:10px/10px monospace}</style><table><tr><td id="a">A</td><td id="b">B</td></tr></table>', new RenderingContext(100, 100));
        $document = $result->getDocument();
        self::assertSame(29.0, $result->getBounds($document->querySelector('table'))[0]->width);
        self::assertSame(16.0, $result->getBounds($document->querySelector('table'))[0]->height);
        self::assertSame(1.5, $result->getBounds($document->getElementById('a'))[0]->x);
        self::assertSame(13.0, $result->getBounds($document->getElementById('a'))[0]->width);
        self::assertSame(14.5, $result->getBounds($document->getElementById('b'))[0]->x);
        $texts = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(3.0, $texts[0]['x']);
        self::assertSame(16.0, $texts[1]['x']);
    }
}
