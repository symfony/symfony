<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Render;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\BackgroundLayout;
use Symfony\Component\TuiHtml\Style\Border;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Padding;
use Symfony\Component\TuiHtml\Style\Shadow;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\TextAlign;
use Symfony\Component\TuiHtml\Style\UnderlineStyle;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

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
    /** @var (\Closure(AbstractWidget, int): void)|null Told the rows of the content of each widget, before they are clipped */
    private ?\Closure $onContentRows = null;
    /**
     * The outer style of each widget, with the style epoch and the parent it was merged at.
     *
     * @var \WeakMap<AbstractWidget, array{int, AbstractWidget, Style}>
     */
    private \WeakMap $outerStyles;
    /** @var \WeakMap<AbstractWidget, array{int, list<string>, int, list<string>}> The rows of the content of each widget left out of its box above and below it, and the lines drawn there, see setOutsideRows() */
    private \WeakMap $outsideRows;
    /** @var \WeakMap<AbstractWidget, array{int, array{?bool, ?bool, ?UnderlineStyle, ?Color}}> The decorations the children of each container get from it and the boxes around it, with the style epoch */
    private \WeakMap $decorations;

    public function __construct(private readonly WidgetRendererInterface $widgetRenderer)
    {
        $this->outerStyles = new \WeakMap();
        $this->outsideRows = new \WeakMap();
        $this->decorations = new \WeakMap();
    }

    /**
     * Tells a listener the rows of the content of each widget drawn, before max-height clips them.
     *
     * @param (\Closure(AbstractWidget, int): void)|null $onContentRows
     *
     * @internal
     */
    public function setContentRowsListener(?\Closure $onContentRows): void
    {
        $this->onContentRows = $onContentRows;
    }

    /**
     * Apply chrome (margin, padding, border, background) to rendered lines.
     */
    public function apply(LineBufferInterface $lines, int $width, Style $style, AbstractWidget $widget): LineBufferInterface
    {
        $shadow = $style->getShadow();
        if (null !== $shadow && !$shadow->inset && [0, 0] !== $shadow->extents()) {
            return $this->applyShadow($lines, $width, $style, $widget, $shadow);
        }
        $margin = $style->getMargin();
        if (null === $margin || 0 === $margin->top + $margin->right + $margin->bottom + $margin->left) {
            return $this->applyBox($lines, $width, $style, $widget);
        }
        // The margin is outside the box: clamp it, keeping at least one column for the box
        $left = min($margin->left, max(0, $width - 1));
        $right = min($margin->right, max(0, $width - 1 - $left));
        $box = $this->applyBox($lines, $width - $left - $right, $style, $widget)->toArray();
        // Painted like the parent: a margin shows what is behind the box
        $outerStyle = $this->resolveOuterStyle($widget);
        $paint = static fn (int $columns) => 0 >= $columns ? '' : (null === $outerStyle || $outerStyle->isPlain() ? str_repeat(' ', $columns) : $outerStyle->apply(str_repeat(' ', $columns)));
        $emptyLine = $paint($width);
        // A box of no rows, like an empty spacer <div> of margin-top: 45px: its margins, collapsed through it, still keep
        // what is after it apart from what is before, like CSS
        // Only an empty block, as LayoutEngine::collapsedMargin() collapses through, like an empty <p> drawing nothing, its
        // margins collapsed with those around it; in a flex or grid container, where margins do not collapse, both
        if (!$box) {
            if (!$widget instanceof ContainerWidget || null !== $style->getMinRows() || null !== $style->getMinRowsPercent()) {
                return new ArrayLineBuffer([]);
            }
            $parent = $widget->getParent();
            $parentStyle = $parent instanceof AbstractWidget ? $this->widgetRenderer->resolveComputedStyle($parent) : null;
            $rows = null !== $parentStyle?->getDirection() || null !== $parentStyle?->getGridTemplate() ? $margin->top + $margin->bottom : max($margin->top, $margin->bottom);

            return new ArrayLineBuffer(array_fill(0, max(0, $rows), $emptyLine));
        }
        $result = array_fill(0, $margin->top, $emptyLine);
        // The same line once: the rows alike, like those of a tall margin, share their string
        $painted = [];
        foreach ($box as $line) {
            $result[] = $painted[$line] ??= $paint($left).$line.$paint($right);
        }
        array_push($result, ...array_fill(0, $margin->bottom, $emptyLine));

        return new ArrayLineBuffer($result);
    }

    /**
     * Leaves rows of the content of a widget out of its box, outside its border, padding and background, like the
     * captions of a table, for its next chrome: its first and last rows, drawn instead by the lines given, as wide as
     * the box.
     *
     * @param list<string> $topLines
     * @param list<string> $bottomLines
     *
     * @internal
     */
    public function setOutsideRows(AbstractWidget $widget, int $top, array $topLines, int $bottom, array $bottomLines): void
    {
        if (0 === $top + $bottom) {
            unset($this->outsideRows[$widget]);
        } else {
            $this->outsideRows[$widget] = [$top, $topLines, $bottom, $bottomLines];
        }
    }

    /**
     * Compute inner dimensions (content area after margin/border/padding).
     *
     * @return array{int, int} [innerColumns, innerRows]
     */
    public function computeInnerDimensions(int $columns, int $rows, Style $style): array
    {
        $border = $style->getBorder();
        $padding = $style->getPadding();
        $margin = $style->getMargin();
        $shadow = $style->getShadow();
        // The room the shadow takes on the right and below, its spread and blur counted
        [$shadowX, $shadowY] = null === $shadow ? [0, 0] : ($shadow->inset ? [$shadow->x, $shadow->y] : $shadow->extents());
        $hChrome = ($border->left ?? 0) + ($border->right ?? 0) + ($padding->left ?? 0) + ($padding->right ?? 0) + ($margin->left ?? 0) + ($margin->right ?? 0) + $shadowX;
        $vChrome = ($border->top ?? 0) + ($border->bottom ?? 0) + ($padding->top ?? 0) + ($padding->bottom ?? 0) + ($margin->top ?? 0) + ($margin->bottom ?? 0) + $shadowY;

        return [max(1, $columns - $hChrome), max(1, $rows - $vChrome)];
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
        $margin = $style->getMargin();
        $top = ($border->top ?? 0) + ($padding->top ?? 0) + ($margin->top ?? 0);
        $left = ($border->left ?? 0) + ($padding->left ?? 0) + ($margin->left ?? 0);

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
        return new RenderContext($innerColumns, $innerRows, StyleBridge::get($context)->withoutLayoutProperties(), $context->getFontRegistry());
    }

    /**
     * Resolve the outer style for a widget by accumulating resolved
     * ancestor styles from root to immediate parent.
     *
     * This ensures that visual properties (color, background) set on
     * a grandparent propagate through intermediate containers that
     * don't override them.
     */
    public function resolveOuterStyle(AbstractWidget $widget): ?Style
    {
        if (null === $parent = $widget->getParent()) {
            return null;
        }
        // The outer style of the parent with its own over it: each ancestor merged once, not once per descendant
        $epoch = $this->widgetRenderer->getStyleEpoch();
        $cached = $this->outerStyles[$widget] ?? null;
        if (null !== $cached && $cached[0] === $epoch && $cached[1] === $parent) {
            return $cached[2];
        }
        $parentStyle = $this->widgetRenderer->resolveComputedStyle($parent);
        $parentOuter = $this->resolveOuterStyle($parent);
        $outer = Style::mergeAll(null === $parentOuter ? [$parentStyle] : [$parentOuter, $parentStyle]);
        // Resolving the ancestors may have changed the epoch: keep what it is now, so that the next call checks again
        $this->outerStyles[$widget] = [$this->widgetRenderer->getStyleEpoch(), $parent, $outer];

        return $outer;
    }

    /**
     * The box with its shadow below and to the right of it, inside the margin.
     */
    private function applyShadow(LineBufferInterface $lines, int $width, Style $style, AbstractWidget $widget, Shadow $shadow): LineBufferInterface
    {
        $margin = $style->getMargin() ?? new Padding(0, 0, 0, 0);
        $left = min($margin->left, max(0, $width - 1));
        $right = min($margin->right, max(0, $width - 1 - $left));
        // The columns and rows of the shadow on the right and below, its spread counted; where it starts along the
        // box, its spread before its offset
        [$x, $y] = $shadow->extents();
        $x = min($x, max(0, $width - $left - $right - 1));
        $box = $this->applyBox($lines, $width - $left - $right - $x, $style->withShadow(null)->withMargin(new Padding(0, 0, 0, 0)), $widget)->toArray();
        if (!$box) {
            return new ArrayLineBuffer([]);
        }
        $outerStyle = $this->resolveOuterStyle($widget);
        $paint = static fn (int $columns) => 0 >= $columns ? '' : (null === $outerStyle || $outerStyle->isPlain() ? str_repeat(' ', $columns) : $outerStyle->apply(str_repeat(' ', $columns)));
        // Blurred, the outer cells of the shadow are half drawn: half blocks in its color, over what is behind
        $soft = static fn (string $block, int $columns) => 0 >= $columns ? '' : (null !== $shadow->color ? (new Style(null, null, $outerStyle?->getBackground(), $shadow->color))->apply(str_repeat($block, $columns)) : $paint($columns));
        $shade = static fn (int $columns) => 0 >= $columns ? '' : (null !== $shadow->color ? (new Style(null, null, $shadow->color))->apply(str_repeat(' ', $columns)) : (new Style(null, null, null, null, null, true))->apply(str_repeat('░', $columns)));
        // The outer column and row of a blurred shadow, a corner where they meet
        $blur = $shadow->blur > 0 && $x > 0 && $y > 0 ? 1 : 0;
        $boxWidth = $width - $left - $right - $x;
        $top = max(0, $shadow->y - $shadow->spread);
        $start = min(max(0, $shadow->x - $shadow->spread), $boxWidth);
        $result = array_fill(0, $margin->top, $paint($width));
        foreach ($box as $i => $line) {
            // As wide as the box, its shadow beside it, not beside a shorter line
            $line .= $paint(max(0, $boxWidth - AnsiUtils::visibleWidth($line)));
            $result[] = $paint($left).$line.($i < $top ? $paint($x) : $shade($x - $blur).$soft('▌', $blur)).$paint($right);
        }
        for ($i = 0; $i < $y; ++$i) {
            $result[] = $paint($left + $start).($i === $y - 1 && $blur > 0 ? $soft('▀', $boxWidth - $start + $x - $blur).$soft('▘', $blur) : $shade($boxWidth - $start + $x - $blur).$soft('▌', $blur)).$paint($right);
        }
        array_push($result, ...array_fill(0, $margin->bottom, $paint($width)));

        return new ArrayLineBuffer($result);
    }

    private function applyBox(LineBufferInterface $lines, int $width, Style $style, AbstractWidget $widget): LineBufferInterface
    {
        // Rows outside the box, like the captions of a table: as wide as it, in what is behind it, its box between them
        if (null !== ([$top, $topLines, $bottom, $bottomLines] = $this->outsideRows[$widget] ?? null) && $top + $bottom <= \count($lines)) {
            unset($this->outsideRows[$widget]);
            $all = $lines->toArray();
            $box = $this->applyBox(new ArrayLineBuffer(\array_slice($all, $top, \count($all) - $top - $bottom)), $width, $style, $widget)->toArray();
            $outerStyle = $this->resolveOuterStyle($widget);
            $paint = static fn (int $columns): string => 0 >= $columns ? '' : (null === $outerStyle || $outerStyle->isPlain() ? str_repeat(' ', $columns) : $outerStyle->apply(str_repeat(' ', $columns)));
            $outside = static fn (string $line): string => ($width < AnsiUtils::visibleWidth($line) ? AnsiUtils::truncateToWidth($line, $width) : $line).$paint($width - AnsiUtils::visibleWidth($line));

            return new ArrayLineBuffer([...array_map($outside, $topLines), ...$box, ...array_map($outside, $bottomLines)]);
        }
        unset($this->outsideRows[$widget]);
        // Text decorations, like the underline of a link around a card, are of the text, like CSS: a container passes
        // them on to the text blocks inside, which draw them under their text only, not under the room of their lines,
        // their padding or their border
        $decorations = $this->textDecorations($style, $widget);
        $style = $style->withoutTextDecorations();
        if ($this->isIdentity($style)) {
            if (null === $decorations) {
                return $lines;
            }
            $decorated = [];
            foreach ($lines as $line) {
                $decorated[] = '' === $line ? '' : $decorations->apply($line);
            }

            return new ArrayLineBuffer($decorated);
        }
        $border = $style->getBorder();
        $padding = $style->getPadding();
        $borderLeft = $border->left ?? 0;
        $borderRight = $border->right ?? 0;
        $borderTop = $border->top ?? 0;
        $borderBottom = $border->bottom ?? 0;
        $paddingLeft = $padding->left ?? 0;
        $paddingRight = $padding->right ?? 0;
        $paddingTop = $padding->top ?? 0;
        $paddingBottom = $padding->bottom ?? 0;
        $hasVerticalPadding = $paddingTop || $paddingBottom;
        if (0 === \count($lines) && !$hasVerticalPadding && !$borderTop && !$borderBottom && !$style->getMinRows()) {
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
            $border = $border->withWidths($borderTop, $borderRight, $borderBottom, $borderLeft);
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
        if (null !== $this->onContentRows) {
            ($this->onContentRows)($widget, \count($lines));
        }
        // Row bounds: clip what is beyond the maximum, pad up to the minimum
        if (null !== $maxRows = $style->getMaxRows()) {
            $lines = new ArrayLineBuffer(\array_slice($lines->toArray(), 0, $maxRows));
        }
        if (null !== ($minRows = $style->getMinRows()) && \count($lines) < $minRows) {
            $lines = new ArrayLineBuffer([...$lines->toArray(), ...array_fill(0, $minRows - \count($lines), '')]);
        }
        // Text blocks align each of their lines, like CSS; the block is then aligned as a whole below
        if ($widget instanceof RichTextWidget && null !== ($textAlign = $style->getTextAlign()) && null === $style->getFont()) {
            $lines = new ArrayLineBuffer($widget->alignLines($lines->toArray(), $textAlign, $contentWidth));
        }
        $processedLines = [];
        $processedWidths = [];
        // Measured once each, the rows alike, like the blank ones of a tall box, many
        $measured = [];
        foreach ($lines as $line) {
            $lineWidth = $measured[$line] ??= AnsiUtils::visibleWidth($line);
            if ($lineWidth > $contentWidth) {
                $line = AnsiUtils::truncateToWidth($line, $contentWidth);
                $lineWidth = AnsiUtils::visibleWidth($line);
            }
            $processedLines[] = $line;
            $processedWidths[] = $lineWidth;
        }
        // If no content and no padding/border, return empty
        if (!$processedLines && !$paddingTop && !$paddingBottom && !$borderTop && !$borderBottom) {
            return new ArrayLineBuffer([]);
        }
        // background-clip: the cells out of the clip box show what is behind the box, the text keeping its colors
        $clip = $style->getBackgroundLayout()->clip ?? BackgroundLayout::BORDER_BOX;
        $borderStyle = $paddingStyle = $contentStyle = $style;
        if (BackgroundLayout::BORDER_BOX !== $clip && null !== $style->getBackground()) {
            $unpainted = $style->withBackground($outerStyle?->getBackground());
            $borderStyle = $unpainted;
            if (BackgroundLayout::PADDING_BOX !== $clip) {
                $paddingStyle = $unpainted;
            }
            if (BackgroundLayout::TEXT === $clip) {
                $contentStyle = $unpainted;
            }
        }
        $textStyle = null === $decorations ? $contentStyle : self::decorated($contentStyle, $decorations);
        $styledEmptyLine = $paddingStyle->apply(str_repeat(' ', $innerWidth));
        $topPadding = $paddingTop > 0 ? array_fill(0, $paddingTop, $styledEmptyLine) : [];
        $bottomPadding = $paddingBottom > 0 ? array_fill(0, $paddingBottom, $styledEmptyLine) : [];
        $textAlign = $style->getTextAlign() ?? TextAlign::Left;
        // For center/right alignment, compute offset from the widest line
        // so all lines shift uniformly (preserving internal alignment of
        // multi-line content like FIGlet).
        $alignPadLeft = 0;
        // Not the items of a flex or grid container, which justify-content places, like a row of buttons centered in a
        // centered hero
        // The row of the controls in a line of text aligns each of its lines itself, see LayoutEngine::layoutWrapped()
        $laidOut = $widget instanceof ContainerWidget && (ContainerState::of($widget)->isInlineFlow() && true === $style->getWrap() || null !== $style->getDirection() || null !== $style->getGridTemplate());
        if (TextAlign::Left !== $textAlign && TextAlign::Justify !== $textAlign && !$laidOut) {
            $maxContentWidth = $processedWidths ? max($processedWidths) : 0;
            $availableSpace = max(0, $contentWidth - $maxContentWidth);
            $alignPadLeft = match ($textAlign) {
                TextAlign::Center => (int) floor($availableSpace / 2),
                TextAlign::Right => $availableSpace,
            };
        }
        $contentLines = [];
        $padLen = $paddingLeft + $alignPadLeft;
        $leftPad = str_repeat(' ', $padLen);
        // The same line padded once: the rows alike, like those of a tall margin inside, share their string
        $padded = [];
        foreach ($processedLines as $i => $line) {
            if (isset($padded[$line])) {
                $contentLines[] = $padded[$line];
                continue;
            }
            if ($textStyle !== $contentStyle) {
                // The text decorated, the room around it not: plain spaces where it paints no background
                $room = static fn (Style $style, int $columns): string => 0 >= $columns ? '' : (null === $style->getBackground() && true !== $style->getReverse() ? str_repeat(' ', $columns) : $style->apply(str_repeat(' ', $columns)));
                $contentLines[] = $padded[$line] = $room($paddingStyle, $paddingLeft).$room($contentStyle, $alignPadLeft).$textStyle->apply($line).$room($contentStyle, $contentWidth - $alignPadLeft - $processedWidths[$i]).$room($paddingStyle, $innerWidth - $paddingLeft - $contentWidth);
                continue;
            }
            if ($paddingStyle !== $contentStyle) {
                // The padding columns apart from the content ones, in their own background
                $contentLines[] = $padded[$line] = $paddingStyle->apply(str_repeat(' ', $paddingLeft)).$contentStyle->apply(str_repeat(' ', $alignPadLeft).$line.str_repeat(' ', max(0, $contentWidth - $alignPadLeft - $processedWidths[$i]))).$paddingStyle->apply(str_repeat(' ', max(0, $innerWidth - $paddingLeft - $contentWidth)));
                continue;
            }
            $lineWithPad = $leftPad.$line;
            $rightPad = str_repeat(' ', max(0, $innerWidth - $padLen - $processedWidths[$i]));
            $contentLines[] = $padded[$line] = $contentStyle->apply($lineWithPad.$rightPad);
        }
        $innerLines = [...$topPadding, ...$contentLines, ...$bottomPadding];
        if (null !== ($inset = $style->getInsetShadow()) && $innerLines) {
            $innerLines = self::insetShadow($innerLines, $innerWidth, $inset);
        }
        $boxed = $border?->wrapLines($innerLines, $innerWidth, $borderStyle, $outerStyle) ?? $innerLines;
        // Its last row pulled into its bottom border by a negative margin, like the active tab of the tabs of Bootstrap
        // over the line under them: drawn over it, their lines joined
        if ($borderBottom > 0 && 0 === $paddingBottom && $contentLines && \count($boxed) >= 2 && $widget instanceof ParentInterface && $this->widgetRenderer->getBottomPull($widget) > 0) {
            $boxed = self::pullIntoBottomBorder($boxed, $borderLeft, $innerWidth, $contentStyle->getBackground() ?? $outerStyle?->getBackground());
        }

        // Each row padded to the inner width: as wide as it, not measured again; with a border, whose characters may be
        // wide, measured
        return new ArrayLineBuffer($boxed, null === $border ? $boxed ? $innerWidth : 0 : null);
    }

    /**
     * The last row of a box drawn over its bottom border, its blank cells showing it, the lines of both joined.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function pullIntoBottomBorder(array $lines, int $left, int $innerWidth, ?Color $background): array
    {
        $last = \count($lines) - 1;
        $border = $lines[$last];
        $width = max(1, AnsiUtils::visibleWidth($border));
        // The glyphs of the border under the row, by column
        $under = [];
        $column = 0;
        foreach (grapheme_str_split(AnsiUtils::stripAnsiCodes($border)) ?: [] as $glyph) {
            if ($column >= $left && $column < $left + $innerWidth) {
                $under[$column] = $glyph;
            }
            $column += AnsiUtils::graphemeWidth($glyph);
        }
        // The row above too, whose sides the corners of the row go on with
        $buffer = new CellBuffer($width, 2);
        $buffer->writeAnsiLines([$lines[$last - 2] ?? '', $border]);
        $code = null !== $background ? substr($background->toBackgroundCode(), 2, -1) : null;
        $buffer->writeAnsiLines([AnsiUtils::sliceToWidth($lines[$last - 1], $left, $innerWidth)], 1, $left, true, $code);
        $buffer->joinBoxRow(1, $under, $code);
        $lines[$last - 1] = $buffer->toLines()[1];
        unset($lines[$last]);

        return array_values($lines);
    }

    /**
     * Shades the edges of the padding box a shadow inside it is offset from, like box-shadow: inset: the top and left
     * ones for positive offsets, the bottom and right ones for negative ones; the text stays.
     *
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function insetShadow(array $lines, int $width, Shadow $shadow): array
    {
        $rows = \count($lines);
        $buffer = new CellBuffer($width, $rows);
        $buffer->writeAnsiLines($lines);
        // Without a color, the terminal background darkened
        $background = null !== $shadow->color ? (string) preg_replace('/^\e\[|m$/', '', $shadow->color->toBackgroundCode()) : '48;5;236';
        $x = min(abs($shadow->x), $width);
        $y = min(abs($shadow->y), $rows);
        for ($row = 0; $row < $rows; ++$row) {
            if ($shadow->y > 0 ? $row < $y : $row >= $rows - $y) {
                $buffer->tint($row, 0, $width, $background);
            } elseif ($x > 0) {
                $buffer->tint($row, $shadow->x > 0 ? 0 : $width - $x, $x, $background);
            }
        }

        return $buffer->toLines();
    }

    /**
     * The text decorations a widget draws under its text, as a style of them alone: its own, and for a text block
     * those of the boxes around it too, like CSS; null for none, and for a container, which passes them on.
     */
    private function textDecorations(Style $style, AbstractWidget $widget): ?Style
    {
        if ($widget instanceof ParentInterface) {
            return null;
        }
        $underline = $style->getUnderline();
        $strikethrough = $style->getStrikethrough();
        $underlineStyle = $style->getUnderlineStyle();
        $underlineColor = $style->getUnderlineColor();
        if ($widget instanceof RichTextWidget && (null === $underline || null === $strikethrough)) {
            [$outerUnderline, $outerStrikethrough, $outerUnderlineStyle, $outerUnderlineColor] = $this->outerDecorations($widget);
            if (null === $underline && true === $outerUnderline) {
                $underline = true;
                $underlineStyle ??= $outerUnderlineStyle;
                $underlineColor ??= $outerUnderlineColor;
            }
            $strikethrough ??= $outerStrikethrough;
        }
        if (true !== $underline && true !== $strikethrough) {
            return null;
        }
        $decorations = new Style();
        if (true === $underline) {
            $decorations = $decorations->withUnderline()->withUnderlineStyle($underlineStyle, $underlineColor);
        }

        return true === $strikethrough ? $decorations->withStrikethrough() : $decorations;
    }

    /**
     * The text decorations of the boxes around a widget, the closest one that says each: like resolveOuterStyle(), its
     * decorations only, for each text block drawn, without merging the styles around it.
     *
     * @return array{?bool, ?bool, ?UnderlineStyle, ?Color} The underline, the line through, the shape and the color of the underline
     */
    private function outerDecorations(AbstractWidget $widget): array
    {
        if (null === $parent = $widget->getParent()) {
            return [null, null, null, null];
        }
        $epoch = $this->widgetRenderer->getStyleEpoch();
        $cached = $this->decorations[$parent] ?? null;
        if (null !== $cached && $cached[0] === $epoch) {
            return $cached[1];
        }
        $style = $this->widgetRenderer->resolveComputedStyle($parent);
        $outer = $this->outerDecorations($parent);
        $decorations = [$style->getUnderline() ?? $outer[0], $style->getStrikethrough() ?? $outer[1], $style->getUnderlineStyle() ?? $outer[2], $style->getUnderlineColor() ?? $outer[3]];
        $this->decorations[$parent] = [$this->widgetRenderer->getStyleEpoch(), $decorations];

        return $decorations;
    }

    /**
     * A style with text decorations over it.
     */
    private static function decorated(Style $style, Style $decorations): Style
    {
        if (true === $decorations->getUnderline()) {
            $style = $style->withUnderline()->withUnderlineStyle($decorations->getUnderlineStyle(), $decorations->getUnderlineColor());
        }

        return true === $decorations->getStrikethrough() ? $style->withStrikethrough() : $style;
    }

    private function isIdentity(Style $style): bool
    {
        $border = $style->getBorder();
        $padding = $style->getPadding();

        return !($border->top ?? 0) && !($border->right ?? 0) && !($border->bottom ?? 0) && !($border->left ?? 0) && !($padding->top ?? 0) && !($padding->right ?? 0) && !($padding->bottom ?? 0) && !($padding->left ?? 0) && null === $style->getTextAlign() && null === $style->getMinRows() && null === $style->getMaxRows() && null === $style->getInsetShadow() && $style->isPlain();
    }
}
