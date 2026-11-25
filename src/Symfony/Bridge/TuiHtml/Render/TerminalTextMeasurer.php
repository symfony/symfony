<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Internal\TerminalSpacing;
use Symfony\Bridge\TuiHtml\Internal\TextSize;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;

final readonly class TerminalTextMeasurer implements TextMeasurerInterface
{
    public function __construct(public float $cellWidth = 8.0, public float $cellHeight = 16.0, public bool $textSizing = false)
    {
        if (!is_finite($cellWidth) || !is_finite($cellHeight) || $cellWidth <= 0 || $cellHeight <= 0) {
            throw new \InvalidArgumentException('Cell dimensions must be finite and positive.');
        }
    }

    public function withTextSizing(bool $enabled = true): self
    {
        return $enabled === $this->textSizing ? $this : new self($this->cellWidth, $this->cellHeight, $enabled);
    }

    public function measure(string $text, FontStyle $font): TextMetrics
    {
        $letters = $font->letterSpacing > 0 ? TerminalSpacing::cells($font->letterSpacing, $this->cellWidth) : 0;
        $words = $font->wordSpacing > 0 ? TerminalSpacing::cells($font->wordSpacing, $this->cellWidth) : 0;
        if ('' !== $text && ($letters > 0 || $words > 0)) {
            $expanded = TerminalSpacing::expand($text, $letters, $words);
            $metrics = $this->measure($expanded['text'], new FontStyle($font->family, $font->size, $font->lineHeight, $font->weight, $font->direction, $font->style));
            $positions = $metrics->caretPositions;
            if (null === $positions) {
                $positions = [0.0];
                $advance = 0.0;
                $scale = $this->textSizing ? TextSize::scale($font->size) : 1;
                foreach (grapheme_str_split($expanded['text']) ?: [] as $grapheme) {
                    $advance += AnsiUtils::visibleWidth($grapheme) * $this->cellWidth * $scale;
                    $positions[] = $advance;
                }
            }

            return new TextMetrics($metrics->width, $metrics->ascent, $metrics->descent, array_map(static fn (int $boundary): float => $positions[$boundary], $expanded['boundaries']));
        }
        $scale = $this->textSizing ? TextSize::scale($font->size) : 1;
        $cellWidth = $this->cellWidth * $scale;
        $cellHeight = $this->cellHeight * $scale;
        $width = AnsiUtils::visibleWidth($text) * $cellWidth;
        $bidi = Bidi::isAvailable();
        $level = 'rtl' === $font->direction ? 1 : ('auto' === $font->direction && $bidi ? Bidi::direction($text) ?? 0 : 0);
        $positions = null;
        if ('' !== $text && $bidi && Bidi::needsReordering($text, $level)) {
            // These are ICU's direction enum labels, as consumed by Tui's existing bidi resolver.
            $classes = ['L', 'R', 'EN', 'ES', 'ET', 'AN', 'CS', 'B', 'S', 'WS', 'ON', 'LRE', 'LRO', 'AL', 'RLE', 'RLO', 'PDF', 'NSM', 'BN', 'FSI', 'LRI', 'RLI', 'PDI'];
            $graphemes = grapheme_str_split($text) ?: [];
            $types = array_map(static fn (string $grapheme): string => $classes[\IntlChar::charDirection(mb_ord($grapheme) ?: 0)] ?? 'L', $graphemes);
            $levels = Bidi::resolveLevels($types, $level, $graphemes);
            $positions = array_fill(0, \count($graphemes) + 1, null);
            $offset = 0.0;
            foreach (Bidi::visualOrder($levels) as $index) {
                $positions[$index] = $offset;
                $offset += AnsiUtils::visibleWidth($graphemes[$index]) * $cellWidth;
            }
            $positions[\count($graphemes)] = $width;
            // Removed directional controls carry a marker to the next logical glyph, just as Tui does.
            for ($index = \count($graphemes) - 1; $index >= 0; --$index) {
                $positions[$index] ??= $positions[$index + 1];
            }
        }

        return new TextMetrics($width, $cellHeight * 0.8, $cellHeight * 0.2, $positions);
    }
}
