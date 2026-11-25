<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\Fragment;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingResult;
use Symfony\Component\HtmlRenderer\Text\TextRun;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class TextPositionTest extends TestCase
{
    public function testPointerPositionUsesGraphemesAndProportionalPrefixMetrics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input>');
        $element = $document->querySelector('input');
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                preg_match_all('/\X/u', $text, $matches);
                $width = array_sum(array_map(static fn (string $grapheme): float => match ($grapheme) {
                    "A\u{0301}" => 2.25, 'b' => 3.0, default => 12.5,
                }, $matches[0]));

                return new TextMetrics($width, 8, 2);
            }
        };
        $box = new Box($element, 'inline-block', ['font-size' => '10px', 'line-height' => '12px', 'width' => '100px'], [], "A\u{0301}bW", null, new TextSelection(0, 0));
        $layout = (new LayoutEngine($measurer))->layout($box, 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter($measurer))->paint($layout), $measurer);
        self::assertSame(0, $result->getTextPosition($element, 0, 2));
        self::assertSame(1, $result->getTextPosition($element, 2.3, 2));
        self::assertSame(2, $result->getTextPosition($element, 5.3, 2));
        self::assertSame(3, $result->getTextPosition($element, 50, 2));
    }

    public function testPointerPositionUsesInverseTransformsAndOriginalAncestorClip()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><input></main>');
        $element = $document->querySelector('input');
        $run = new Fragment($element, new Rect(0, 0, 15, 12), ['font-size' => '10px', 'transform' => 'translateX(5.5px)'], 'abc', [], null, [1.0, 0.0, 0.0, 1.0, 5.5, 0.0], [], new TextRun(0, 3, new TextSelection(0, 0)));
        $layout = new Fragment($document->querySelector('main'), new Rect(0, 0, 10, 12), ['overflow' => 'hidden'], null, [$run]);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(0, $result->getTextPosition($element, 6, 2));
        self::assertSame(1, $result->getTextPosition($element, 9, 2));
        self::assertNull($result->getTextPosition($element, 12, 2));
    }

    public function testWrappedLinePositionReturnsGlobalControlValueOffset()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><textarea></textarea>');
        $element = $document->querySelector('textarea');
        $box = new Box($element, 'block', ['font-size' => '10px', 'line-height' => '12px', 'white-space' => 'pre-wrap', 'width' => '15px'], [], "one two\nx", null, new TextSelection(0, 0));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(7, $result->getTextPosition($element, 14, 13));
        self::assertSame(8, $result->getTextPosition($element, 0, 25));
    }

    public function testMaskedRunPositionNeverReadsTheOriginalPassword()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input type="password" value="secret">');
        $element = $document->querySelector('input');
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout(new Box($element, 'inline-block', ['font-size' => '10px', 'line-height' => '12px'], [], '••••••', null, new TextSelection(0, 0)), 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(2, $result->getTextPosition($element, 10, 2));
        self::assertStringNotContainsString('secret', json_encode($result->getDisplayList()->getOperations(), \JSON_THROW_ON_ERROR));
    }

    public function testPointerUsesNearestNonMonotonicBackendCaretPosition()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input>');
        $element = $document->querySelector('input');
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics(\strlen($text) * 5.0, 8, 2, 'abc' === $text && 'rtl' === $font->direction ? [10.0, 5.0, 0.0, 15.0] : null);
            }
        };
        $box = new Box($element, 'inline-block', ['font-size' => '10px', 'line-height' => '12px', 'direction' => 'rtl'], [], 'abc', null, new TextSelection(0, 0));
        $layout = (new LayoutEngine($measurer))->layout($box, 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter($measurer))->paint($layout), $measurer);
        self::assertSame(2, $result->getTextPosition($element, .1, 2));
        self::assertSame(0, $result->getTextPosition($element, 9.9, 2));
        self::assertSame(1, $result->getTextPosition($element, 5, 2));
    }

    public function testGeneratedPrefixDoesNotCaptureUnfocusedControlValuePositions()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><input value="abc">');
        $element = $document->querySelector('input');
        $style = ['font-size' => '10px', 'line-height' => '12px', 'white-space' => 'pre'];
        $box = new Box($element, 'inline-block', $style, [
            new Box($element, 'inline', $style, [], '> ', null, null, 'before'),
            new Box($element, 'inline', $style, [], 'abc'),
            new Box($element, 'inline', $style, [], ' suffix', null, null, 'after'),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(0, $result->getTextPosition($element, 1, 2));
        self::assertSame(1, $result->getTextPosition($element, 14, 2));
        self::assertSame(3, $result->getTextPosition($element, 40, 2));
    }
}
