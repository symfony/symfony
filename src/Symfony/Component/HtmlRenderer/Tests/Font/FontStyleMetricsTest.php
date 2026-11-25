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
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderingResult;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class FontStyleMetricsTest extends TestCase
{
    public function testFontStyleChangesWrappingAndChWidthsWithinOneLayout()
    {
        $measure = $this->measurer();
        $root = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'block', ['width' => '30px'], [], 'aa aa'), new Box(null, 'block', ['width' => '30px', 'font-style' => 'italic'], [], 'aa aa'), new Box(null, 'block', ['width' => '2ch'], [], 'x'), new Box(null, 'block', ['width' => '2ch', 'font-style' => 'oblique'], [], 'x')]);
        $layout = (new LayoutEngine($measure))->layout($root, 100, 100);
        self::assertSame(10.0, $layout->children[0]->bounds->height);
        self::assertSame(20.0, $layout->children[1]->bounds->height);
        self::assertSame(10.0, $layout->children[2]->bounds->width);
        self::assertSame(12.0, $layout->children[3]->bounds->width);
    }

    public function testSelectionCaretAndPointerUseItalicMetrics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><input>');
        $input = $document->querySelector('input');
        $measure = $this->measurer();
        $box = new Box($input, 'inline-block', ['width' => '100px', 'font-size' => '10px', 'line-height' => '10px', 'font-style' => 'italic'], [], 'abcd', null, new TextSelection(1, 3));
        $layout = (new LayoutEngine($measure))->layout($box, 100, 100);
        $list = (new Painter($measure))->paint($layout);
        $operations = $list->getOperations();
        $selection = array_values(array_filter($operations, static fn (array $op) => 'rectangle' === $op['type']))[0];
        $caret = array_values(array_filter($operations, static fn (array $op) => 'caret' === $op['type']))[0];
        self::assertSame(8.0, $selection['x']);
        self::assertSame(16.0, $selection['width']);
        self::assertSame(24.0, $caret['x']);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, $list, $measure);
        self::assertSame(2, $result->getTextPosition($input, 17, 1));
    }

    public function testDefaultFontStyleSeedsCssAndExplicitInitialResetsToNormal()
    {
        $context = new RenderingContext(200, 100, $this->measurer(), null, null, new FontStyle('Example', 10, 10, 400, 'ltr', 'italic'));
        $result = new HtmlRenderer()->render('<!doctype html><main>x<span style="font-style:initial">x</span><b style="all:initial">x</b></main>', $context);
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame(['italic', 'normal', 'normal'], array_column($text, 'font-style'));
        self::assertSame([8.0, 5.0, 5.0], array_column($text, 'width'));
    }

    public function testFontRelativeDecorationLengthsUseComputedStyleMetrics()
    {
        $result = new HtmlRenderer()->render('<!doctype html><p style="font-style:italic;text-decoration:underline;text-decoration-thickness:1ch">x</p><p style="font-style:normal;text-decoration:underline;text-decoration-thickness:1ch">x</p>', new RenderingContext(200, 100, $this->measurer()));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op) => 'text' === $op['type']));
        self::assertSame([8.0, 5.0], array_column($text, 'text-decoration-thickness'));
    }

    private function measurer(): TextMeasurerInterface
    {
        return new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $advance = match ($font->style ?? 'normal') {
                    'italic' => 8.0,
                    'oblique' => 6.0,
                    default => 5.0,
                };

                return new TextMetrics(\strlen($text) * $advance, 8, 2);
            }
        };
    }
}
