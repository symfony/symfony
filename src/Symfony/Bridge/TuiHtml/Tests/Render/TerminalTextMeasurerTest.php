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
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;

final class TerminalTextMeasurerTest extends TestCase
{
    public function testTextSizingScalesGlyphMetricsOnlyWhenEnabled()
    {
        $base = new TerminalTextMeasurer();
        $font = new FontStyle('monospace', 32, 32);
        $this->assertSame(24.0, $base->measure('A中́', $font)->width);
        $scaled = $base->withTextSizing();
        $metrics = $scaled->measure('A中́', $font);
        $this->assertSame(48.0, $metrics->width);
        $this->assertSame(25.6, $metrics->ascent);
        $this->assertSame(6.4, $metrics->descent);
        $this->assertSame(32.0, $scaled->measure('A', new FontStyle('monospace', 160, 160))->width);
        $this->assertSame(8.0, $scaled->measure('A', new FontStyle('monospace', 8, 16))->width);
        $this->assertSame($scaled, $scaled->withTextSizing());
        $this->assertFalse($scaled->withTextSizing(false)->textSizing);
        $this->assertSame(8.0, $scaled->cellWidth);
        $this->assertSame(16.0, $scaled->cellHeight);
    }

    public function testLatinUsesTheMeasurementFastPath()
    {
        $metrics = (new TerminalTextMeasurer())->measure('A中é', new FontStyle());
        $this->assertSame(32.0, $metrics->width);
        $this->assertNull($metrics->caretPositions);
    }

    public function testEmptyRtlTextDoesNotRequireBidiResolution()
    {
        $metrics = (new TerminalTextMeasurer())->measure('', new FontStyle('monospace', 16, 16, 400, 'rtl'));
        $this->assertSame(0.0, $metrics->width);
        $this->assertNull($metrics->caretPositions);
    }

    public function testVisualCaretPositionsMatchExistingTerminalBidiMarkers()
    {
        if (!Bidi::isAvailable()) {
            $this->markTestSkipped('The Intl extension is needed for terminal bidi.');
        }
        $measurer = new TerminalTextMeasurer();
        foreach (['אבג', 'Aאב中B', 'مرحبا', "A\u{202e}BC\u{202c}D", 'א́ב中'] as $text) {
            foreach (['ltr', 'rtl', 'auto'] as $direction) {
                $level = 'rtl' === $direction ? 1 : ('auto' === $direction ? Bidi::direction($text) ?? 0 : 0);
                $metrics = $measurer->measure($text, new FontStyle('monospace', 16, 16, 400, $direction));
                $characters = grapheme_str_split($text);
                $expected = [];
                for ($index = 0; $index <= \count($characters); ++$index) {
                    $marked = implode('', \array_slice($characters, 0, $index)).AnsiUtils::cursorMarker().implode('', \array_slice($characters, $index));
                    $visual = Bidi::reorderLine($marked, $level);
                    $expected[] = AnsiUtils::visibleWidth(substr($visual, 0, strpos($visual, AnsiUtils::cursorMarker()))) * 8.0;
                }
                $this->assertSame($expected, $metrics->caretPositions, $text.' '.$direction);
                $this->assertSame(array_map(static fn (float $position): float => $position * 2, $expected), $measurer->withTextSizing()->measure($text, new FontStyle('monospace', 32, 32, 400, $direction))->caretPositions);
            }
        }
        $this->assertSame([16.0, 8.0, 0.0, 24.0], $measurer->measure('אבג', new FontStyle('monospace', 16, 16, 400, 'rtl'))->caretPositions);
    }
}
