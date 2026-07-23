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
     *
     * @param string[] $lines
     *
     * @return string[]
     */
    public function apply(array $lines, int $width, Style $style, AbstractWidget $widget): array
    {
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
        $hasHorizontalPadding = $paddingLeft || $paddingRight;
        $hasBorder = $borderTop || $borderBottom || $borderLeft || $borderRight;

        if (!$hasBorder && !$hasHorizontalPadding && !$hasVerticalPadding && $style->isPlain() && null === $style->getTextAlign()) {
            return $lines;
        }

        if (!$lines && !$hasVerticalPadding && !$borderTop && !$borderBottom) {
            return [];
        }

        $outerStyle = $this->resolveOuterStyle($widget);

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
        foreach ($lines as $line) {
            $processedLines[] = AnsiUtils::truncateToWidth($line, $contentWidth);
        }

        // If no content and no padding/border, return empty
        if (!$processedLines && !$paddingTop && !$paddingBottom
            && !$borderTop && !$borderBottom) {
            return [];
        }

        $styledEmptyLine = $style->apply(str_repeat(' ', $innerWidth));
        $topPadding = $paddingTop > 0 ? array_fill(0, $paddingTop, $styledEmptyLine) : [];
        $bottomPadding = $paddingBottom > 0 ? array_fill(0, $paddingBottom, $styledEmptyLine) : [];
        $textAlign = $style->getTextAlign() ?? TextAlign::Left;

        // For center/right alignment, compute offset from the widest line
        // so all lines shift uniformly (preserving internal alignment of
        // multi-line content like FIGlet).
        $alignPadLeft = 0;
        if (TextAlign::Left !== $textAlign) {
            $maxContentWidth = 0;
            foreach ($processedLines as $line) {
                $maxContentWidth = max($maxContentWidth, AnsiUtils::visibleWidth($line));
            }
            $availableSpace = max(0, $contentWidth - $maxContentWidth);
            $alignPadLeft = match ($textAlign) {
                TextAlign::Center => (int) floor($availableSpace / 2),
                TextAlign::Right => $availableSpace,
            };
        }

        $contentLines = [];
        foreach ($processedLines as $line) {
            $lineWithPad = str_repeat(' ', $paddingLeft + $alignPadLeft).$line;
            $visibleWidth = AnsiUtils::visibleWidth($lineWithPad);
            $rightPad = str_repeat(' ', max(0, $innerWidth - $visibleWidth));
            $contentLines[] = $style->apply($lineWithPad.$rightPad);
        }

        $innerLines = [...$topPadding, ...$contentLines, ...$bottomPadding];

        return $border?->wrapLines(
            $innerLines,
            $innerWidth,
            $style,
            $outerStyle,
        ) ?? $innerLines;
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
