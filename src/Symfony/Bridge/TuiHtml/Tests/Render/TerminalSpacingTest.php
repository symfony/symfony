<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Internal\TerminalSpacing;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\FontStyle;

final class TerminalSpacingTest extends TestCase
{
    public function testLegacySpacingThresholdAndInsertedGapWordExpansion()
    {
        self::assertSame(0, TerminalSpacing::cells(5.99, 8));
        self::assertSame(1, TerminalSpacing::cells(6, 8));
        self::assertSame(0, TerminalSpacing::cells(-8, 8));
        self::assertSame(10000, TerminalSpacing::cells(1e9, 8));
        self::assertSame(['text' => 'A        B', 'boundaries' => [0, 9, 10]], TerminalSpacing::expand('AB', 2, 3));
        self::assertSame(['text' => '  A  B', 'boundaries' => [2, 5, 6]], TerminalSpacing::expand('AB', 1, 1, true));
        self::assertSame(['text' => 'A', 'boundaries' => [0, 1]], TerminalSpacing::expand('A', 10000, 10000));
        self::assertSame("\u{100000}\u{100001}  A", TerminalSpacing::expand("\u{100000}\u{100001}A", 2, 0)['text']);
        $metrics = new \Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics(new TerminalTextMeasurer());
        self::assertSame(2.0, $metrics->constrainLength(2.0, 'letter-spacing', false));
        self::assertSame(-2.0, $metrics->constrainLength(-2.0, 'word-spacing', false));
    }

    public function testMeasuredGraphemePositionsUseTheSameExpandedDeviceText()
    {
        $measurer = new TerminalTextMeasurer();
        $metrics = $measurer->measure('A中é', new FontStyle('monospace', 16, 16, 400, 'ltr', 'normal', 8));
        self::assertSame(48.0, $metrics->width);
        self::assertSame([0.0, 16.0, 40.0, 48.0], $metrics->caretPositions);
        self::assertSame(32.0, $measurer->measure('A中é', new FontStyle('monospace', 16, 16, 400, 'ltr', 'normal', -8))->width);
    }

    public function testExpandedByteBudgetIsCheckedBeforeAllocatingGaps()
    {
        $this->expectException(\LengthException::class);
        TerminalSpacing::expand('ABC', 10000, 10000);
    }

    public function testBackendPaintsTheSameWideAndCombiningGlyphAdvances()
    {
        $operation = ['type' => 'text', 'x' => 0.0, 'y' => 0.0, 'width' => 48.0, 'height' => 16.0, 'text' => 'A中é', 'color' => 'red', 'letter-spacing' => 8.0];
        $lines = (new \Symfony\Bridge\TuiHtml\Render\DisplayListRenderer())->render(new \Symfony\Component\HtmlRenderer\Paint\DisplayList([$operation]), 10, 1);
        self::assertSame('A 中 é', rtrim(\Symfony\Component\Tui\Ansi\AnsiUtils::stripAnsiCodes($lines[0])));
        $operation['letter-spacing'] = 5.99;
        $lines = (new \Symfony\Bridge\TuiHtml\Render\DisplayListRenderer())->render(new \Symfony\Component\HtmlRenderer\Paint\DisplayList([$operation]), 10, 1);
        self::assertSame('A中é', rtrim(\Symfony\Component\Tui\Ansi\AnsiUtils::stripAnsiCodes($lines[0])));
    }

    public function testSpacingCaretMapPreservesVisualBidiOrder()
    {
        if (!\Symfony\Component\Tui\Ansi\Bidi::isAvailable()) {
            self::markTestSkipped('The Intl extension is needed for terminal bidi.');
        }
        foreach (['אבג', 'Aאב 中B', 'مرحبا', "A\u{202e}BC\u{202c}D"] as $text) {
            $expanded = TerminalSpacing::expand($text, 1, 2);
            $characters = grapheme_str_split($expanded['text']);
            $expected = [];
            foreach ($expanded['boundaries'] as $boundary) {
                $marked = implode('', \array_slice($characters, 0, $boundary)).\Symfony\Component\Tui\Ansi\AnsiUtils::cursorMarker().implode('', \array_slice($characters, $boundary));
                $visual = \Symfony\Component\Tui\Ansi\Bidi::reorderLine($marked, 1);
                $expected[] = \Symfony\Component\Tui\Ansi\AnsiUtils::visibleWidth(substr($visual, 0, strpos($visual, \Symfony\Component\Tui\Ansi\AnsiUtils::cursorMarker()))) * 8.0;
            }
            self::assertSame($expected, (new TerminalTextMeasurer())->measure($text, new FontStyle('monospace', 16, 16, 400, 'rtl', 'normal', 8, 16))->caretPositions);
        }
    }
}
