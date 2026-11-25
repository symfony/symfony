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
use Symfony\Component\HtmlRenderer\RenderingContext;

final class InlineMarginTest extends TestCase
{
    public function testInlineBlockMarginsReserveRoomWithoutShrinkingTextContent()
    {
        $html = '<style>ul{padding:0;margin:0;list-style:none}li{display:inline-block;margin-right:1ch}</style><ul><li>php</li><li>css</li><li>terminal</li></ul>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(300, 100));
        $items = $result->getDocument()->querySelectorAll('li');
        foreach ([[0.0, 24.0], [32.0, 24.0], [64.0, 64.0]] as $index => [$x,$width]) {
            $bounds = $result->getBounds($items[$index])[0];
            self::assertSame($x, $bounds->x);
            self::assertSame($width, $bounds->width);
        }
    }

    public function testFractionalInlineMarginPaddingAndBorderParticipateInAdvance()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline-block', ['padding' => '0 1.25px', 'border-width' => '.5px', 'margin' => '0 2.25px'], [], 'ab'), new Box(null, 'inline', [], [], 'c')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(2.25, $layout->children[0]->bounds->x);
        self::assertSame(13.5, $layout->children[0]->bounds->width);
        self::assertSame(18.0, $layout->children[1]->bounds->x);
    }

    public function testOversizedAtomicInlineStartsNewLineUsingFullContainingWidth()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline-block', ['width' => '60px', 'height' => '10px']), new Box(null, 'inline-block', [], [], 'one two three four five six seven eight')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(0.0, $layout->children[1]->bounds->x);
        self::assertSame(100.0, $layout->children[1]->bounds->width);
        self::assertSame(12.0, $layout->children[1]->bounds->y);
    }

    public function testPercentageInlineWidthAndPaddingResolveAgainstContainingBlock()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline-block', ['width' => '40px', 'height' => '10px']), new Box(null, 'inline-block', ['width' => '20%', 'padding' => '0 5%'], [], 'a')]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(40.0, $layout->children[1]->bounds->x);
        self::assertSame(30.0, $layout->children[1]->bounds->width);
        self::assertSame(20.0, $layout->children[1]->getContentBox()->width);
    }

    public function testExistingInlineFormattingContextsAreAtomicInTheOuterLine()
    {
        foreach (['inline-flex', 'inline-grid', 'inline-table'] as $display) {
            $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', [], [], 'A'), new Box(null, $display, ['width' => '20px', 'height' => '10px']), new Box(null, 'inline', [], [], 'B')]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
            self::assertSame(5.0, $layout->children[1]->bounds->x, $display);
            self::assertSame(25.0, $layout->children[2]->bounds->x, $display);
        }
    }

    public function testUndecoratedInlineAncestorFragmentsAroundAtomicChildren()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><span><i></i><i></i></span></main>');
        $span = $document->querySelector('span');
        $items = $document->querySelectorAll('i');
        $inline = new Box($span, 'inline', [], [new Box($items[0], 'inline-block', ['width' => '40px', 'height' => '10px']), new Box($items[1], 'inline-block', ['width' => '40px', 'height' => '10px'])]);
        $root = new Box($document->querySelector('main'), 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', [], [], 'Prefix '), $inline]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 100, 100);
        $result = new \Symfony\Component\HtmlRenderer\RenderingResult($document, new \SplObjectStorage(), $layout, (new \Symfony\Component\HtmlRenderer\Paint\Painter())->paint($layout));
        $bounds = $result->getBounds($span);
        self::assertCount(2, $bounds);
        self::assertSame(35.0, $bounds[0]->x);
        self::assertSame(0.0, $bounds[1]->x);
        self::assertSame(12.0, $bounds[1]->y);
    }

    public function testInapplicableVerticalInlineMarginsDoNotPreventAtomicFragmentation()
    {
        $html = '<style>main{width:50px;font-size:10px;line-height:10px}ul{display:inline;list-style:none;padding:0;margin-block:1em}li{display:inline-block;padding:0 1ch}</style><main>P <ul><li>aaa</li><li>bbb</li></ul></main>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(100, 100));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(),static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame($text[0]['y'],$text[1]['y']);
        self::assertCount(2,$result->getBounds($result->getDocument()->querySelector('ul')));
    }
}
