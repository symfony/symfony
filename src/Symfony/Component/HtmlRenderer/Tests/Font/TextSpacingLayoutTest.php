<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Font;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderingResult;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class TextSpacingLayoutTest extends TestCase
{
    public function testSpacingIsMeasuredWithoutSplittingTheDisplayText()
    {
        $result = (new HtmlRenderer())->render('<p style="margin:0;font-size:16px;line-height:16px;letter-spacing:.25px;word-spacing:1.5px">a b</p>', new RenderingContext(200, 100));
        $operations = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertCount(1, $operations);
        self::assertSame('a b', $operations[0]['text']);
        self::assertSame(26.25, $operations[0]['width']);
        self::assertSame(.25, $operations[0]['letter-spacing']);
        self::assertSame(1.5, $operations[0]['word-spacing']);
    }

    public function testWrappingAndIntrinsicWidthUseTheSameSpacing()
    {
        $result = (new HtmlRenderer())->render('<div style="font-size:16px;line-height:16px;letter-spacing:2px"><p style="margin:0;width:45px">ab cd</p><span id="atomic" style="display:inline-block;word-spacing:3px">a b</span><p id="ch" style="margin:0;width:2ch">a</p></div>', new RenderingContext(200, 100));
        $operations = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(['ab', 'cd', 'a b', 'a'], array_column($operations, 'text'));
        self::assertSame([20.0, 20.0, 33.0, 10.0], array_column($operations, 'width'));
        self::assertSame(33.0, $result->getBounds($result->getDocument()->getElementById('atomic'))[0]->width);
        self::assertSame(16.0, $result->getBounds($result->getDocument()->getElementById('ch'))[0]->width);
    }

    public function testAProportionalProviderReceivesResolvedTypography()
    {
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $width = 0.0;
                $positions = [0.0];
                preg_match_all('/\\X/u', $text, $matches);
                foreach ($matches[0] as $grapheme) {
                    $width += match ($grapheme) {
                        'W' => 12, 'i' => 4, ' ' => 3, default => 5
                    } + $font->letterSpacing + (' ' === $grapheme ? $font->wordSpacing : 0.0);
                    $positions[] = $width;
                }

                return new TextMetrics($width, 12, 4, $positions);
            }
        };
        $result = (new HtmlRenderer())->render('<p style="margin:0;font-size:20px;line-height:20px;letter-spacing:.1em;word-spacing:3px">W i</p>', new RenderingContext(200, 100, $measurer));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type']));
        self::assertSame(28.0, $text[0]['width']);
        self::assertSame(2.0, $text[0]['letter-spacing']);
        self::assertSame(3.0, $text[0]['word-spacing']);
    }

    public function testSelectionCaretAndHitTestingUseSpacedMetrics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><input>');
        $input = $document->querySelector('input');
        $measurer = new MonospaceTextMeasurer();
        $box = new Box($input, 'inline-block', ['width' => '100px', 'font-size' => '16px', 'line-height' => '16px', 'letter-spacing' => '2px'], [], 'abcd', null, new TextSelection(1, 3));
        $fragment = (new LayoutEngine($measurer))->layout($box, 100, 100);
        $display = (new Painter($measurer))->paint($fragment);
        $result = new RenderingResult($document, new \SplObjectStorage(), $fragment, $display, $measurer);
        $selection = array_values(array_filter($display->getOperations(), static fn (array $operation): bool => 'rectangle' === $operation['type']));
        self::assertSame(10.0, $selection[0]['x']);
        self::assertSame(20.0, $selection[0]['width']);
        $caret = array_values(array_filter($display->getOperations(), static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertSame(30.0, $caret[0]['x']);
        self::assertSame(2, $result->getTextPosition($input, 24, 8));
        self::assertSame(3, $result->getTextPosition($input, 27, 8));
    }
}
