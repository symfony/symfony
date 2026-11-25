<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class TextSelectionPaintingTest extends TestCase
{
    public function testSelectionAndCaretUseGraphemeBoundariesAndMeasuredPrefixes()
    {
        $selection = new TextSelection(1, 3, true, '#ffffff', '#0066cc');
        $box = new Box(null, 'inline-block', ['font-size' => '10px', 'line-height' => '12px', 'width' => '100px', 'padding' => '2px', 'border-width' => '1px'], [], "A\u{0301}bc", null, $selection);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        $operations = (new Painter(new MonospaceTextMeasurer()))->paint($fragment)->getOperations();
        $selected = array_values(array_filter($operations, static fn (array $operation): bool => 'rectangle' === $operation['type']))[0];
        self::assertSame(8.0, $selected['x']);
        self::assertSame(10.0, $selected['width']);
        self::assertSame('#0066cc', $selected['color']);
        self::assertSame(["A\u{0301}", 'bc'], array_column($operations, 'text'));
        $caret = array_values(array_filter($operations, static fn (array $operation): bool => 'caret' === $operation['type']))[0];
        self::assertSame(18.0, $caret['x']);
        self::assertSame(12.0, $caret['height']);
    }

    public function testEmptyFocusedControlStillHasACaret()
    {
        $box = new Box(null, 'inline-block', ['font-size' => '10px', 'line-height' => '12px', 'width' => '100px'], [], '', null, new TextSelection(0, 0));
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 200, 100);
        $operations = (new Painter())->paint($fragment)->getOperations();
        self::assertSame(['caret'], array_column($operations, 'type'));
        self::assertSame(0.0, $operations[0]['x']);
    }

    public function testWrappedAndExplicitLinesRetainControlValueRanges()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px', 'white-space' => 'pre-wrap', 'width' => '15px'], [], "one two\nx", null, new TextSelection(2, 9));
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame([[0, 3], [4, 7], [8, 9]], array_map(static fn ($line): array => [$line->run->start, $line->run->end], $fragment->children));
        $operations = (new Painter())->paint($fragment)->getOperations();
        $carets = array_values(array_filter($operations, static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertCount(1, $carets);
        self::assertSame(24.0, $carets[0]['y']);
    }

    public function testMaskedTextNeverExposesASecretThroughSelectionOperations()
    {
        $box = new Box(null, 'inline-block', ['font-size' => '10px', 'line-height' => '12px'], [], '•••', null, new TextSelection(0, 3));
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 100, 100);
        self::assertSame(['•••'], array_column((new Painter())->paint($fragment)->getOperations(), 'text'));
    }

    public function testCaretUsesBackendShapingMapInsteadOfLogicalPrefix()
    {
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics(\strlen($text) * 5.0, 8, 2, 'abc' === $text && 'rtl' === $font->direction ? [10.0, 5.0, 0.0, 15.0] : null);
            }
        };
        $box = new Box(null, 'inline-block', ['font-size' => '10px', 'line-height' => '12px', 'direction' => 'rtl'], [], 'abc', null, new TextSelection(0, 0));
        $fragment = (new LayoutEngine($measurer))->layout($box, 100, 100);
        $operations = (new Painter($measurer))->paint($fragment)->getOperations();
        $carets = array_values(array_filter($operations, static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertSame(10.0, $carets[0]['x']);
    }
}
