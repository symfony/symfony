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
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class CollapsibleWhitespaceLineTest extends TestCase
{
    public function testOverflowingCollapsibleSpaceDoesNotCreateAnEmptyLineBetweenAtomicItems()
    {
        $space = new Box(null, 'inline', [], [], " \n ");
        $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top']), $space, new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 100);
        self::assertSame(32.0, $fragment->bounds->height);
        self::assertSame([0.0, 16.0], array_map(static fn ($child): float => $child->bounds->y, $fragment->children));
        self::assertSame(" \n ", $space->text);
    }

    public function testPreservedAndDecoratedSpacesStillParticipateInLineLayout()
    {
        foreach ([['white-space' => 'pre'], ['white-space' => 'pre-wrap'], ['white-space' => 'break-spaces'], ['background-color' => 'red']] as $spaceStyle) {
            $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [
                new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top']),
                new Box(null, 'inline', $spaceStyle, [], ' '),
                new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top']),
            ]);
            $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 100);
            self::assertSame(48.0, $fragment->bounds->height);
            self::assertCount(3, $fragment->children);
        }
    }

    public function testPreLineCarriageReturnsRemainSegmentBreaks()
    {
        foreach (["\r", "\r\n", "\n"] as $segmentBreak) {
            $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [
                new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top']),
                new Box(null, 'inline', ['white-space' => 'pre-line'], [], $segmentBreak),
                new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top']),
            ]);
            $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 100);
            self::assertSame(48.0, $fragment->bounds->height);
            self::assertCount(3, $fragment->children);
        }
    }

    public function testNativeTextMutationPreservesPreLineSegmentBreaksAndSourceIdentity()
    {
        foreach (["\r", "\r\n", "\n"] as $segmentBreak) {
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>main{width:24px;font-size:16px;line-height:16px}i{display:inline-block;width:24px;height:16px;vertical-align:top}span{white-space:pre-line}</style><main><i></i><span></span><i></i></main>');
            $span = $document->querySelector('span');
            $text = $document->createTextNode($segmentBreak);
            $span->appendChild($text);
            $result = (new HtmlRenderer())->render($document, new RenderingContext(24, 100));
            self::assertSame(48.0, $result->getLayout()->bounds->height);
            self::assertSame($text, $span->firstChild);
            self::assertSame($segmentBreak, $text->data);
        }
    }

    public function testSpaceAfterMultilineAtomicContentCollapsesAtTheNewLine()
    {
        $provider = new class implements ReplacedContentInterface {
            public function getIntrinsicSize(): IntrinsicSize
            {
                return new IntrinsicSize(8, 64, 16);
            }

            public function layout(float $width, float $availableHeight): ContentLayout
            {
                return new ContentLayout(ceil(64 / $width) * 16, new DisplayList());
            }
        };
        foreach (['normal', 'nowrap', 'pre-line'] as $whiteSpace) {
            foreach ([' ', "\t", "\f", "\r", "\r\n", "\n"] as $separator) {
                $text = new Box(null, 'inline', ['white-space' => $whiteSpace], [], $separator.'Z');
                $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [
                    new Box(null, 'inline', [], [], 'A'),
                    new Box(null, 'inline', ['max-width' => '100%', 'vertical-align' => 'top'], [], null, null, null, null, null, null, null, $provider),
                    $text,
                ]);
                $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 200);
                $operations = array_values(array_filter((new Painter())->paint($fragment)->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
                self::assertSame(['A', 'Z'], array_column($operations, 'text'));
                self::assertSame([0.0, 0.0], array_column($operations, 'x'));
                $segmentBreak = 'pre-line' === $whiteSpace && strpbrk($separator, "\r\n");
                self::assertSame([0.0, $segmentBreak ? 80.0 : 64.0], array_column($operations, 'y'));
                self::assertSame($separator.'Z', $text->text);
            }
        }
    }

    public function testGeneratedTabCollapsesAtTheBeginningOfItsLine()
    {
        $html = '<style>p{width:24px;font-size:16px;line-height:16px}i{display:inline-block;width:24px;height:48px;vertical-align:top}span::before{content:"\\9 Z"}</style><p><i></i><span></span></p>';
        $result = (new HtmlRenderer())->render($html, new RenderingContext(24, 200));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['Z'], array_column($text, 'text'));
        self::assertSame([0.0], array_column($text, 'x'));
        self::assertSame([48.0], array_column($text, 'y'));
    }

    public function testDescendantPreservedWhitespaceAndNonBreakingSpacesAreNotTrimmed()
    {
        foreach (['pre', 'pre-wrap', 'break-spaces'] as $whiteSpace) {
            $leaf = new Box(null, 'inline', ['white-space' => $whiteSpace], [], ' Z');
            $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [
                new Box(null, 'inline-block', ['width' => '24px', 'height' => '16px', 'vertical-align' => 'top']),
                new Box(null, 'inline', [], [$leaf]),
            ]);
            $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 100);
            $text = array_values(array_filter((new Painter())->paint($fragment)->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
            self::assertSame([' Z'], array_column($text, 'text'));
            self::assertSame(' Z', $leaf->text);
        }
        $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [new Box(null, 'inline', [], [], "\u{00A0}Z")]);
        $text = array_values(array_filter((new Painter())->paint((new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 24, 100))->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(["\u{00A0}Z"], array_column($text, 'text'));
    }

    public function testNestedInlineContinuationPreservesItsSameLineSeparator()
    {
        $leaf = new Box(null, 'inline', [], [], ' C');
        $box = new Box(null, 'block', ['font-size' => '16px', 'line-height' => '16px'], [
            new Box(null, 'inline', [], [], 'A '),
            new Box(null, 'inline-block', ['width' => '40px', 'height' => '16px', 'vertical-align' => 'top']),
            new Box(null, 'inline', [], [$leaf]),
        ]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 80, 100);
        $text = array_values(array_filter((new Painter())->paint($fragment)->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['A ', ' C'], array_column($text, 'text'));
        self::assertSame([0.0, 56.0], array_column($text, 'x'));
        self::assertSame([0.0, 0.0], array_column($text, 'y'));
        self::assertSame(' C', $leaf->text);
    }

    public function testNestedWhitespaceKeepsItsSourceBoundsAndPointerTarget()
    {
        foreach (['span', 'a href="#x"', 'span style="text-decoration:underline"'] as $tag) {
            $name = strtok($tag, ' ');
            $result = (new HtmlRenderer())->render('<style>p{margin:0;font-size:16px;line-height:16px}</style><p>A<'.$tag.' id="gap"> </'.$name.'>B</p>', new RenderingContext(100, 100));
            $element = $result->getDocument()->querySelector('#gap');
            $bounds = $result->getBounds($element);
            self::assertCount(1, $bounds);
            self::assertSame([8.0, 0.0, 8.0, 16.0], [$bounds[0]->x, $bounds[0]->y, $bounds[0]->width, $bounds[0]->height]);
            self::assertSame($element, $result->hitTest(12, 8));
            self::assertSame(' ', $element->firstChild->textContent);
            self::assertContains(' ', array_column($result->getDisplayList()->getOperations(), 'text'));
        }
    }
}
