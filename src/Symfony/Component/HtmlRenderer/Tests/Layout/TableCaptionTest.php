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
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class TableCaptionTest extends TestCase
{
    public function testNativeCaptionIsRenderedOutsideTableBorderWithoutDroppingContent()
    {
        $html = '<style>table{font-size:10px;line-height:10px;border:1px solid}caption{margin:0}td{padding:0;border:0}</style><table><caption>Quarterly results</caption><tr><td>A</td></tr></table>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(200, 100));
        $caption = $result->getDocument()->querySelector('caption');
        $table = $result->getDocument()->querySelector('table');
        $captionBounds = $result->getBounds($caption);
        self::assertNotEmpty($captionBounds);
        $grid = $result->getBounds($table, 'table-grid')[0];
        self::assertSame($captionBounds[0]->y + $captionBounds[0]->height, $grid->y);
        $text = implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
        self::assertStringContainsString('Quarterly', $text);
        self::assertStringContainsString('results', $text);
        self::assertStringEndsWith('A', $text);
        $borders = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']));
        self::assertSame($grid->y, $borders[0]['y']);
        self::assertSame($caption, $result->hitTest($captionBounds[0]->x + 1, $captionBounds[0]->y + 1));
    }

    public function testBottomCaptionFractionalMarginsAreOutsideGridAndCountOnce()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><table><caption>Bottom</caption><tbody><tr><td>X</td></tr></tbody></table><div></div></main>');
        $table = $document->querySelector('table');
        $caption = $document->querySelector('caption');
        $cells = new Box($document->querySelector('tr'), 'table-row', [], [new Box($document->querySelector('td'), 'table-cell', [], [], 'X')]);
        $captionBox = new Box($caption, 'table-caption', ['caption-side' => 'bottom', 'padding' => '1.25px', 'margin-top' => '2.25px', 'margin-bottom' => '3.5px', 'margin-left' => '1.25px', 'margin-right' => '2.5px'], [], 'Bottom');
        $root = new Box($document->querySelector('main'), 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box($table, 'table', ['border-width' => '1px'], [$captionBox, $cells]), new Box($document->querySelector('div'), 'block', ['height' => '1px'])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(36.25, $result->getBounds($table)[0]->width);
        self::assertSame(14.25, $result->getBounds($caption)[0]->y);
        self::assertSame(30.25, $result->getBounds($table)[0]->height);
        self::assertSame(30.25, $result->getBounds($document->querySelector('div'))[0]->y);
    }

    public function testCaptionOnlyTableStillPreservesItsText()
    {
        $result = (new HtmlRenderer())->render('<table style="border:1px solid"><caption>Only caption</caption></table>', new RenderingContext(200, 100));
        $text = implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
        self::assertStringContainsString('caption', $text);
    }

    public function testDisplayOverridesControlCaptionBoxGeneration()
    {
        foreach (['block', 'contents'] as $display) {
            $html = '<style>caption{display:'.$display.'}table{border:1px solid}</style><table><caption>Kept text</caption><tr><td>A</td></tr></table>';
            $result = (new HtmlRenderer())->render($html, new RenderingContext(200, 100));
            $table = $result->getDocument()->querySelector('table');
            self::assertSame([], $result->getBounds($table, 'table-grid'));
            $text = implode('', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
            self::assertStringContainsString('Kept text', $text);
            if ('contents' === $display) {
                self::assertSame([], $result->getBounds($result->getDocument()->querySelector('caption')));
            }
        }
        $result = (new HtmlRenderer())->render('<table><caption style="display:none">Hidden</caption><tr><td>A</td></tr></table>', new RenderingContext(200, 100));
        self::assertSame([], $result->getBounds($result->getDocument()->querySelector('caption')));
    }

    public function testGenericCssCaptionHasSameOutsideGridPlacement()
    {
        $html = '<main style="display:table;border:1px solid"><div id="cap" style="display:table-caption">Generic</div><div style="display:table-row"><div style="display:table-cell">Cell</div></div></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(200, 100));
        $caption = $result->getBounds($result->getDocument()->querySelector('#cap'))[0];
        $grid = $result->getBounds($result->getDocument()->querySelector('main'), 'table-grid')[0];
        self::assertSame($caption->y + $caption->height, $grid->y);
    }
}
