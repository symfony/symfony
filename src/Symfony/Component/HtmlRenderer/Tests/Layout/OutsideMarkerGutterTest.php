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
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Paint\Painter;

final class OutsideMarkerGutterTest extends TestCase
{
    public function testStandaloneOutsideMarkersKeepUnreservedGeometry()
    {
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($this->list(), 100, 100);
        self::assertSame(2.0, $layout->children[0]->getContentBox()->x);
        self::assertSame(-13.0, $layout->children[0]->children[0]->bounds->x);
    }

    public function testGroupMaximumAddsToAuthorPaddingAndUsedContentGeometry()
    {
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($this->list(), 100, 100);
        self::assertSame(22.0, $layout->children[0]->getContentBox()->x);
        self::assertSame(22.0, $layout->children[1]->getContentBox()->x);
        self::assertSame(7.0, $layout->children[0]->children[0]->bounds->x);
        self::assertSame(2.0, $layout->children[1]->children[0]->bounds->x);
        self::assertSame(78.0, $layout->children[0]->getContentBox()->width);
    }

    public function testInsideAndSuppressedMarkersDoNotContributeAndFontsAffectGroupMaximum()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><ul><li></li><li></li><li></li></ul>');
        $items = iterator_to_array($document->querySelectorAll('li'));
        $root = new Box($document->querySelector('ul'), 'block', ['font-size' => '10px', 'line-height' => '10px'], [
            new Box($items[0], 'list-item', [], [new Box($items[0], 'inline', ['font-size' => '20px'], [], 'xx ', null, null, 'marker')]),
            new Box($items[1], 'none', [], [new Box($items[1], 'inline', [], [], 'VERY LONG ', null, null, 'marker')]),
            new Box($items[2], 'list-item', ['list-style-position' => 'inside'], [new Box($items[2], 'inline', ['list-style-position' => 'inside'], [], 'LONG ', null, null, 'marker')]),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($root, 100, 100);
        self::assertSame(30.0, $layout->children[0]->getContentBox()->x);
        self::assertSame(0.0, $layout->children[1]->getContentBox()->x);
    }

    public function testRtlUsesRightPaddingAndPlacesMarkerAtInlineStart()
    {
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($this->list(true), 100, 100);
        self::assertSame(0.0, $layout->children[0]->getContentBox()->x);
        self::assertSame(78.0, $layout->children[0]->getContentBox()->width);
        self::assertSame(78.0, $layout->children[0]->children[0]->bounds->x);
        self::assertSame('22px', $layout->children[0]->style['padding-right']);
    }

    public function testFormattingContextsUseGutterOnceInIntrinsicWidths()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><ul><li></li><li></li></ul>');
        $items = iterator_to_array($document->querySelectorAll('li'));
        $children = [];
        foreach ($items as $item) {
            $children[] = new Box($item, 'list-item', ['padding-left' => '2px'], [new Box($item, 'inline', [], [], 'LONG ', null, null, 'marker'), new Box(null, 'inline', [], [], 'a')]);
        }
        $root = new Box($document->querySelector('ul'), 'flex', ['width' => '100px', 'font-size' => '10px', 'line-height' => '10px'], $children);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($root, 100, 100);
        self::assertSame(32.0, $layout->children[0]->bounds->width);
        self::assertSame(27.0, $layout->children[0]->getContentBox()->x);
        self::assertSame(59.0, $layout->children[1]->getContentBox()->x);
    }

    public function testGridAndTableFormattingPreserveUsedGutterAndContentBox()
    {
        foreach (['grid', 'table'] as $display) {
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><ol><li></li></ol>');
            $item = $document->querySelector('li');
            $child = new Box($item, 'list-item', ['padding-left' => '2px'], [new Box($item, 'inline', [], [], 'LONG ', null, null, 'marker'), new Box(null, 'inline', [], [], 'a')]);
            $root = new Box($document->querySelector('ol'), $display, ['width' => '100px', 'font-size' => '10px', 'line-height' => '10px'], [$child]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($root, 100, 100);
            $find = static function ($fragment) use (&$find, $item) {
                if ($fragment->node === $item && null === $fragment->pseudoElement) {
                    return $fragment;
                }
                foreach ($fragment->children as $nested) {
                    if (null !== $found = $find($nested)) {
                        return $found;
                    }
                }

                return null;
            };
            $fragment = $find($layout);
            self::assertSame('27px', $fragment->style['padding-left']);
            self::assertSame(27.0, $fragment->getContentBox()->x - $fragment->bounds->x);
        }
    }

    public function testNestedNativeParentGroupsReserveTheirOwnMaximum()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><ul id="outer"><li id="wide"></li><li id="holder"><ul id="inner"><li id="inneritem"></li></ul></li></ul>');
        $wide = $document->getElementById('wide');
        $holder = $document->getElementById('holder');
        $inner = $document->getElementById('inner');
        $item = $document->getElementById('inneritem');
        $nested = new Box($inner, 'block', [], [new Box($item, 'list-item', [], [new Box($item, 'inline', [], [], 'x ', null, null, 'marker'), new Box(null, 'inline', [], [], 'inner')])]);
        $root = new Box($document->getElementById('outer'), 'block', ['width' => '100px', 'font-size' => '10px', 'line-height' => '10px'], [new Box($wide, 'list-item', [], [new Box($wide, 'inline', [], [], 'LONG ', null, null, 'marker')]), new Box($holder, 'list-item', [], [new Box($holder, 'inline', [], [], 'x ', null, null, 'marker'), $nested])]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($root, 100, 100);
        self::assertSame(25.0, $layout->children[0]->getContentBox()->x);
        $find = static function ($fragment) use (&$find, $item) {
            if ($fragment->node === $item && null === $fragment->pseudoElement) {
                return $fragment;
            }
            foreach ($fragment->children as $child) {
                if (null !== $found = $find($child)) {
                    return $found;
                }
            }

            return null;
        };
        self::assertSame(35.0, $find($layout)->getContentBox()->x);
    }

    public function testContentBackgroundClipUsesReservedLogicalPadding()
    {
        foreach ([false, true] as $rtl) {
            $list = $this->list($rtl);
            $children = array_map(static fn (Box $item): Box => new Box($item->node, $item->display, array_replace($item->style, ['background-color' => 'red', 'background-clip' => 'content-box']), $item->children), $list->children);
            $root = new Box($list->node, $list->display, $list->style, $children);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $this->metrics()))->layout($root, 100, 100);
            $rectangles = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op) => 'rectangle' === $op['type']));
            self::assertSame($rtl ? 0.0 : 22.0, $rectangles[0]['x']);
            self::assertSame(78.0, $rectangles[0]['width']);
        }
    }

    private function list(bool $rtl = false): Box
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><ol><li></li><li></li></ol>');
        $items = iterator_to_array($document->querySelectorAll('li'));
        $children = [];
        foreach (['9. ', '10. '] as $index => $label) {
            $children[] = new Box($items[$index], 'list-item', [$rtl ? 'padding-right' : 'padding-left' => '2px'], [new Box($items[$index], 'inline', [], [], $label, null, null, 'marker'), new Box(null, 'inline', [], [], 'item')]);
        }

        return new Box($document->querySelector('ol'), 'block', ['width' => '100px', 'font-size' => '10px', 'line-height' => '10px', 'direction' => $rtl ? 'rtl' : 'ltr'], $children);
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
                return $naturalGroupWidth;
            }

            public function scrollbarThickness(bool $vertical, bool $thin = false): float
            {
                return 0.0;
            }

            public function distribute(array $lengths, bool $vertical): array
            {
                return $lengths;
            }
        };
    }
}
