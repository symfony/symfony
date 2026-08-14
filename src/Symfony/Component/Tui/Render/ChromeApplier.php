<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Applies chrome (padding, border, background) around widget content.
 *
 * Chrome is the visual frame around a widget's rendered lines:
 * padding adds space inside, borders draw a box, and background colors
 * fill the area. The result is cached for performance.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ChromeApplier
{
    public function __construct(
        private readonly WidgetRendererInterface $widgetRenderer,
    ) {
    }

    /**
     * Apply chrome (padding, border, background) to rendered lines.
     */
    public function apply(LineBufferInterface $lines, int $width, Style $style, AbstractWidget $widget): LineBufferInterface
    {
        if ($this->isIdentity($style)) {
            return $lines;
        }

        $border = $style->getBorder();
        $padding = $style->getPadding();

        $borderLeft = $border?->left ?? 0;
        $borderRight = $border?->right ?? 0;
        $borderTop = $border?->top ?? 0;
        $borderBottom = $border?->bottom ?? 0;
        $paddingLeft = $padding?->left ?? 0;
        $paddingRight = $padding?->right ?? 0;
        $paddingTop = $padding?->top ?? 0;
        $paddingBottom = $padding?->bottom ?? 0;

        $hasVerticalPadding = $paddingTop || $paddingBottom;

        if (0 === \count($lines) && !$hasVerticalPadding && !$borderTop && !$borderBottom) {
            return new ArrayLineBuffer([]);
        }

        $outerStyle = $this->resolveOuterStyle($widget);

        // Clamp the border the same way, before the padding: a border alone can be
        // wider than the box (e.g. 1 column each side in a 2-column container, which
        // a horizontal layout hands out as soon as there are enough children).
        $maxHorizontalBorder = max(0, $width - 1);
        if ($borderLeft + $borderRight > $maxHorizontalBorder) {
            $borderLeft = min($borderLeft, $maxHorizontalBorder);
            $borderRight = min($borderRight, max(0, $maxHorizontalBorder - $borderLeft));
            $border = new Border($borderTop, $borderRight, $borderBottom, $borderLeft, $border->pattern, $border->color);
        }

        $innerWidth = max(1, $width - $borderLeft - $borderRight);

        // Clamp padding so it fits within the inner width, preserving
        // at least 1 column for content. Without this, excessive padding
        // (e.g. padding=50 in a 10-column container) would overflow.
        $maxHorizontalPadding = max(0, $innerWidth - 1);
        if ($paddingLeft + $paddingRight > $maxHorizontalPadding) {
            $paddingLeft = min($paddingLeft, $maxHorizontalPadding);
            $paddingRight = min($paddingRight, max(0, $maxHorizontalPadding - $paddingLeft));
        }

        $contentWidth = max(1, $innerWidth - $paddingLeft - $paddingRight);

        $processedLines = [];
        $processedWidths = [];
        foreach ($lines as $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            if ($lineWidth > $contentWidth) {
                $line = AnsiUtils::truncateToWidth($line, $contentWidth);
                $lineWidth = AnsiUtils::visibleWidth($line);
            }
            $processedLines[] = $line;
            $processedWidths[] = $lineWidth;
        }

        // If no content and no padding/border, return empty
        if (!$processedLines && !$paddingTop && !$paddingBottom
            && !$borderTop && !$borderBottom) {
            return new ArrayLineBuffer([]);
        }

        $textAlign = $style->getTextAlign() ?? TextAlign::Left;

        // For center/right alignment, compute offset from the widest line
        // so all lines shift uniformly (preserving internal alignment of
        // multi-line content like FIGlet).
        $alignPadLeft = 0;
        if (TextAlign::Left !== $textAlign) {
            $maxContentWidth = $processedWidths ? max($processedWidths) : 0;
            $availableSpace = max(0, $contentWidth - $maxContentWidth);
            $alignPadLeft = match ($textAlign) {
                TextAlign::Center => (int) floor($availableSpace / 2),
                TextAlign::Right => $availableSpace,
            };
        }

        $gradient = $style->getGradient();
        $padLen = $paddingLeft + $alignPadLeft;
        $leftPad = str_repeat(' ', $padLen);

        if (null !== $gradient) {
            $totalRows = $paddingTop + \count($processedLines) + $paddingBottom;
            $fullHeight = $borderTop + $totalRows + $borderBottom;
            // Resolve over innerWidth so the gradient spans the content area only.
            // Borders echo the endpoint colors; content always starts at offset=0 and ends at offset=1.
            $fullCodes = $gradient->resolve($innerWidth, $fullHeight);
            $codes = [];
            for ($r = 0; $r < $totalRows; ++$r) {
                $codes[$r] = $fullCodes[$borderTop + $r];
            }

            $topPadding = [];
            for ($r = 0; $r < $paddingTop; ++$r) {
                $topPadding[] = $this->buildGradientLine('', $codes[$r], $innerWidth);
            }

            $contentLines = [];
            foreach ($processedLines as $i => $line) {
                $lineWithPad = $leftPad.$line;
                $rightPad = str_repeat(' ', max(0, $innerWidth - $padLen - $processedWidths[$i]));
                $contentLines[] = $this->buildGradientLine($lineWithPad.$rightPad, $codes[$paddingTop + $i], $innerWidth);
            }

            $bottomPadding = [];
            for ($r = 0; $r < $paddingBottom; ++$r) {
                $row = $paddingTop + \count($processedLines) + $r;
                $bottomPadding[] = $this->buildGradientLine('', $codes[$row], $innerWidth);
            }
        } else {
            $styledEmptyLine = $style->apply(str_repeat(' ', $innerWidth));
            $topPadding = $paddingTop > 0 ? array_fill(0, $paddingTop, $styledEmptyLine) : [];
            $bottomPadding = $paddingBottom > 0 ? array_fill(0, $paddingBottom, $styledEmptyLine) : [];

            $contentLines = [];
            foreach ($processedLines as $i => $line) {
                $lineWithPad = $leftPad.$line;
                $rightPad = str_repeat(' ', max(0, $innerWidth - $padLen - $processedWidths[$i]));
                $contentLines[] = $style->apply($lineWithPad.$rightPad);
            }
        }

        $innerLines = [...$topPadding, ...$contentLines, ...$bottomPadding];

        return new ArrayLineBuffer($border?->wrapLines(
            $innerLines,
            $innerWidth,
            $style,
            $outerStyle,
        ) ?? $innerLines);
    }

    private function isIdentity(Style $style): bool
    {
        $border = $style->getBorder();
        $padding = $style->getPadding();

        return !($border?->top ?? 0)
            && !($border?->right ?? 0)
            && !($border?->bottom ?? 0)
            && !($border?->left ?? 0)
            && !($padding?->top ?? 0)
            && !($padding?->right ?? 0)
            && !($padding?->bottom ?? 0)
            && !($padding?->left ?? 0)
            && null === $style->getTextAlign()
            && $style->isPlain();
    }

    /**
     * Compute inner dimensions (content area after border/padding).
     *
     * @return array{int, int} [innerColumns, innerRows]
     */
    public function computeInnerDimensions(int $columns, int $rows, Style $style): array
    {
        $border = $style->getBorder();
        $padding = $style->getPadding();

        $hChrome = ($border?->left ?? 0) + ($border?->right ?? 0)
            + ($padding?->left ?? 0) + ($padding?->right ?? 0);
        $vChrome = ($border?->top ?? 0) + ($border?->bottom ?? 0)
            + ($padding?->top ?? 0) + ($padding?->bottom ?? 0);

        return [
            max(1, $columns - $hChrome),
            max(1, $rows - $vChrome),
        ];
    }

    /**
     * Compute the top-left chrome offset (border + padding) for a style.
     *
     * @return array{int, int} [topOffset, leftOffset]
     */
    public function computeChromeOffset(Style $style): array
    {
        $border = $style->getBorder();
        $padding = $style->getPadding();

        $top = ($border?->top ?? 0) + ($padding?->top ?? 0);
        $left = ($border?->left ?? 0) + ($padding?->left ?? 0);

        return [$top, $left];
    }

    /**
     * Compute a RenderContext with inner dimensions (content area after border/padding).
     *
     * Widgets receive this context so they render into the content area without
     * needing to account for their own chrome.
     */
    public function computeInnerContext(RenderContext $context, Style $style): RenderContext
    {
        [$innerColumns, $innerRows] = $this->computeInnerDimensions($context->getColumns(), $context->getRows(), $style);

        // Strip layout properties from the style so leaf widgets only see
        // visual formatting (color, bold, etc.). The Renderer owns layout
        // (padding, border, gap, direction, hidden, cursorShape, textAlign, align, verticalAlign); widgets own content.
        return new RenderContext($innerColumns, $innerRows, $context->getStyle()->withoutLayoutProperties(), $context->getFontRegistry());
    }

    /**
     * Build a line applying per-column gradient background codes.
     *
     * Walks the ANSI string in a single O(n) pass, injecting a gradient bg code
     * before each visible character only when no child widget has set an explicit
     * background color. This implements CSS-style cascade: child bg takes
     * precedence over the parent gradient.
     *
     * @param array<int, string> $rowCodes Per-column ANSI bg codes indexed by column
     */
    private function buildGradientLine(string $content, array $rowCodes, int $width): string
    {
        $result = '';
        $col = 0;
        $i = 0;
        $len = \strlen($content);
        $childBgActive = false;
        // Last gradient code written to the line, so a run of cells sharing a color
        // states it once. Reset to null whenever a sequence of the child goes
        // through, since it may have changed the background behind our back.
        $lastCode = null;

        while ($i < $len && $col < $width) {
            $ord = \ord($content[$i]);

            // ANSI escape sequence (CSI: ESC [): pass through, track bg state
            if (0x1B === $ord && '[' === ($content[$i + 1] ?? '')) {
                $j = self::endOfCsi($content, $i, $len);
                $seq = substr($content, $i, $j - $i);
                $childBgActive = $this->parseBgState($seq, $childBgActive);
                $result .= $seq;
                $lastCode = null;
                $i = $j;
                continue;
            }

            // Printable ASCII: inject gradient code only when child has no active bg
            if ($ord >= 0x20 && $ord <= 0x7E) {
                if (!$childBgActive && ($rowCodes[$col] ?? '') !== $lastCode) {
                    $result .= $lastCode = $rowCodes[$col] ?? '';
                }
                $result .= $content[$i];
                ++$col;
                ++$i;
                continue;
            }

            // Multi-byte UTF-8: same logic
            if ($ord >= 0x80) {
                $seqLen = match (true) {
                    ($ord & 0xE0) === 0xC0 => 2,
                    ($ord & 0xF0) === 0xE0 => 3,
                    ($ord & 0xF8) === 0xF0 => 4,
                    default => 1,
                };
                $char = substr($content, $i, $seqLen);
                $charWidth = AnsiUtils::graphemeWidth($char);
                if (!$childBgActive && ($rowCodes[$col] ?? '') !== $lastCode) {
                    $result .= $lastCode = $rowCodes[$col] ?? '';
                }
                $result .= $char;
                $col += $charWidth;
                $i += $seqLen;
                continue;
            }

            ++$i;
        }

        // Fill remaining width with gradient-colored spaces
        while ($col < $width) {
            if (($rowCodes[$col] ?? '') !== $lastCode) {
                $result .= $lastCode = $rowCodes[$col] ?? '';
            }
            $result .= ' ';
            ++$col;
        }

        // Re-emit the escape sequences left after the last visible cell. When the
        // content fills the line exactly, the loop above stops on $col and would
        // otherwise drop the resets a child appended past its last character
        // (ESC[22m, ESC[39m, ...), leaking its style onto the next line.
        while ($i < $len) {
            if (0x1B !== \ord($content[$i]) || '[' !== ($content[$i + 1] ?? '')) {
                ++$i;
                continue;
            }

            $j = self::endOfCsi($content, $i, $len);
            $result .= substr($content, $i, $j - $i);
            $i = $j;
        }

        return $result."\x1b[49m";
    }

    /**
     * Return the offset just past the CSI sequence starting at $start.
     */
    private static function endOfCsi(string $content, int $start, int $len): int
    {
        $j = $start + 2;
        // Parameter bytes (0x30–0x3F)
        while ($j < $len && \ord($content[$j]) >= 0x30 && \ord($content[$j]) <= 0x3F) {
            ++$j;
        }
        // Intermediate bytes (0x20–0x2F)
        while ($j < $len && \ord($content[$j]) >= 0x20 && \ord($content[$j]) <= 0x2F) {
            ++$j;
        }
        // Final byte (0x40–0x7E)
        if ($j < $len && \ord($content[$j]) >= 0x40 && \ord($content[$j]) <= 0x7E) {
            ++$j;
        }

        return $j;
    }

    /**
     * Parse a CSI escape sequence to determine the new bg-active state.
     *
     * Returns true if the sequence sets a background color (child bg takes
     * precedence over parent gradient), false if it resets the background.
     * Returns $current unchanged if the sequence doesn't affect bg.
     */
    private function parseBgState(string $seq, bool $current): bool
    {
        // Only process CSI sequences (ESC [)
        if (\strlen($seq) < 3 || "\x1b[" !== substr($seq, 0, 2)) {
            return $current;
        }

        // Parameter string is between [ and the final byte
        if ('' === $params = substr($seq, 2, -1)) {
            return false; // bare ESC [ m = SGR 0 = full reset
        }

        $state = $current;
        $parts = explode(';', $params);
        $count = \count($parts);
        $idx = 0;

        while ($idx < $count) {
            $n = (int) $parts[$idx];

            if (38 === $n || 48 === $n || 58 === $n) {
                // Extended color: N;5;I (256-color) or N;2;R;G;B (RGB). Only 48
                // sets a background, but the payload of 38 (foreground) and 58
                // (underline) has to be skipped just the same: a zero channel
                // would otherwise be read as SGR 0 and clear the child bg.
                if (48 === $n) {
                    $state = true;
                }
                if ($idx + 1 < $count) {
                    if ('5' === $parts[$idx + 1]) {
                        $idx += 2; // skip N + 5; ++$idx at end advances past I
                    } elseif ('2' === $parts[$idx + 1]) {
                        $idx += 4; // skip N + 2 + R + G; ++$idx at end advances past B
                    }
                }
            } elseif (0 === $n || 49 === $n) {
                // SGR 0 (full reset) or SGR 49 (bg-default): clear child bg
                $state = false;
            } elseif (($n >= 40 && $n <= 47) || ($n >= 100 && $n <= 107)) {
                // Standard (40-47) and bright (100-107) bg colors
                $state = true;
            }
            ++$idx;
        }

        return $state;
    }

    /**
     * Resolve the outer style for a widget by accumulating resolved
     * ancestor styles from root to immediate parent.
     *
     * This ensures that visual properties (color, background) set on
     * a grandparent propagate through intermediate containers that
     * don't override them.
     */
    private function resolveOuterStyle(AbstractWidget $widget): ?Style
    {
        // Collect ancestors from immediate parent to root
        $ancestors = [];
        $parent = $widget;
        while (null !== $parent = $parent->getParent()) {
            $ancestors[] = $parent;
        }

        if (!$ancestors) {
            return null;
        }

        // Resolve each ancestor's style from root (last) to immediate
        // parent (first) so closer ancestors override more distant ones
        $resolvedStyles = [];
        for ($i = \count($ancestors) - 1; $i >= 0; --$i) {
            $resolvedStyles[] = $this->widgetRenderer->resolveStyle($ancestors[$i]);
        }

        return Style::mergeAll($resolvedStyles);
    }
}
