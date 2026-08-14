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
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

/**
 * Lays out children vertically or horizontally with gap, fill, and alignment.
 *
 * The layout engine distributes available space among children, handles
 * fill-expanding children, and applies horizontal/vertical alignment.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class LayoutEngine
{
    public function __construct(
        private readonly WidgetRendererInterface $widgetRenderer,
        private readonly PositionTracker $positionTracker,
        private readonly FontRegistry $fontRegistry,
    ) {
    }

    /**
     * Layout children based on direction.
     *
     * @param AbstractWidget[] $children
     *
     * @return string[]
     */
    public function layout(array $children, int $columns, int $rows, int $gap, Direction $direction, ?string $gapLine = null, ?VerticalAlign $verticalAlign = null): array
    {
        if (Direction::Horizontal === $direction) {
            return $this->layoutHorizontal($children, $columns, $rows, $gap, $verticalAlign);
        }

        return $this->layoutVertical($children, $columns, $rows, $gap, $gapLine);
    }

    /**
     * Compute the horizontal offset needed to align content within the available width.
     *
     * @param string[] $lines
     */
    public function computeAlignOffset(array $lines, int $columns, Align $align): int
    {
        if (!$lines) {
            return 0;
        }

        $maxWidth = 0;
        foreach ($lines as $line) {
            $maxWidth = max($maxWidth, AnsiUtils::visibleWidth($line));
        }

        $availableSpace = max(0, $columns - $maxWidth);

        return match ($align) {
            Align::Center => $availableSpace >> 1,
            Align::Right => $availableSpace,
            Align::Left => 0,
        };
    }

    /**
     * Compute the vertical offset (number of top-padding rows) for alignment.
     */
    public function computeVerticalAlignOffset(int $contentRows, int $availableRows, VerticalAlign $verticalAlign): int
    {
        $space = max(0, $availableRows - $contentRows);

        return match ($verticalAlign) {
            VerticalAlign::Top => 0,
            VerticalAlign::Center => $space >> 1,
            VerticalAlign::Bottom => $space,
        };
    }

    /**
     * Shift all lines by prepending spaces.
     *
     * @param string[] $lines
     *
     * @return string[]
     */
    public function shiftLines(array $lines, int $offset): array
    {
        $prefix = str_repeat(' ', $offset);
        $result = [];
        foreach ($lines as $line) {
            $result[] = $prefix.$line;
        }

        return $result;
    }

    /**
     * Layout children vertically with gap and fill support.
     *
     * @param AbstractWidget[] $children
     *
     * @return string[]
     */
    private function layoutVertical(array $children, int $columns, int $rows, int $gap, ?string $gapLine = null): array
    {
        if (!$children) {
            return [];
        }

        $lines = [];
        $gapLine ??= str_repeat(' ', max(1, $columns));
        $gapLines = $gap > 0 ? array_fill(0, $gap, $gapLine) : [];
        $first = true;

        // Calculate total gap rows
        $totalGapRows = $gap * max(0, \count($children) - 1);
        $remainingRows = $rows - $totalGapRows;

        // First pass: identify fill children and measure non-fill children.
        // During this pass, suppress position tracking for descendants since
        // we don't yet know each child's final absolute row offset.
        $fillChildren = [];
        $nonFillRenders = [];
        $nonFillCacheHits = [];
        $savedStack = $this->positionTracker->suppressStack();

        foreach ($children as $index => $child) {
            if ($child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded()) {
                $fillChildren[$index] = $child;
            } else {
                // Suppress descendant position tracking during measurement.
                // Uncached parent widgets are re-rendered later with their final
                // absolute offset to populate descendant rects.
                $context = new RenderContext($columns, $rows, null, $this->fontRegistry);
                $revision = $child->getRenderRevision();
                $hadCachedRender = null !== $child->getRenderCache($columns, $rows);
                $childLines = $this->widgetRenderer->renderWidget($child, $context);
                $nonFillRenders[$index] = $childLines;
                $nonFillCacheHits[$index] = $hadCachedRender && $revision === $child->getRenderRevision();

                $remainingRows -= \count($childLines);
            }
        }

        $this->positionTracker->restoreStack($savedStack);

        // Calculate rows for fill children
        $fillCount = \count($fillChildren);
        $baseFillRows = $fillCount > 0 ? max(1, intdiv(max(0, $remainingRows), $fillCount)) : 0;
        $extraRows = $fillCount > 0 ? max(0, $remainingRows) % $fillCount : 0;

        // Second pass: render all children in order with correct position tracking.
        // At this point we know the accumulated line count for each child's offset.
        $fillIndex = 0;
        $hasPositionStack = $this->positionTracker->isActive();
        foreach ($children as $index => $child) {
            $cacheHit = false;
            if (isset($fillChildren[$index])) {
                // Fill child gets calculated rows, distributing remainder to first children
                $childFillRows = $baseFillRows + ($fillIndex < $extraRows ? 1 : 0);
                ++$fillIndex;

                // Add gap before this child (so line count is correct for position)
                if (!$first && $gapLines) {
                    array_push($lines, ...$gapLines);
                }

                $context = new RenderContext($columns, $childFillRows, null, $this->fontRegistry);
                $revision = $child->getRenderRevision();
                $hadCachedRender = null !== $child->getRenderCache($columns, $childFillRows);

                // Push correct absolute position so descendants get proper coordinates
                if ($hasPositionStack) {
                    [$parentAbsRow, $parentAbsCol] = $this->positionTracker->currentOffset();
                    $this->positionTracker->push($parentAbsRow + \count($lines), $parentAbsCol);
                }
                $childLines = $this->widgetRenderer->renderWidget($child, $context);
                $cacheHit = $hadCachedRender && $revision === $child->getRenderRevision();
                if ($hasPositionStack) {
                    $this->positionTracker->pop();
                }

                // Pad fill children to their allocated rows so they actually fill the space
                while (\count($childLines) < $childFillRows) {
                    $childLines[] = '';
                }
            } else {
                $context = new RenderContext($columns, $rows, null, $this->fontRegistry);
                $childLines = $nonFillRenders[$index] ?? $this->widgetRenderer->renderWidget($child, $context);
                $cacheHit = $nonFillCacheHits[$index] ?? false;

                // Skip gap for children that render nothing
                if (!$childLines) {
                    continue;
                }

                if (!$first && $gapLines) {
                    array_push($lines, ...$gapLines);
                }
            }

            // Track widget position
            if ($hasPositionStack) {
                [$parentAbsRow, $parentAbsCol] = $this->positionTracker->currentOffset();
                $childAbsRow = $parentAbsRow + \count($lines);
                $childAbsCol = $parentAbsCol;

                $rect = new WidgetRect(
                    $childAbsRow,
                    $childAbsCol,
                    $columns,
                    \count($childLines),
                );
                $positionsTracked = isset($fillChildren[$index]) && !$cacheHit;
                if ($cacheHit && $child instanceof ParentInterface) {
                    $positionsTracked = $this->positionTracker->moveSubtree($child, $rect);
                }

                $this->positionTracker->setWidgetRect($child, $rect);

                if ($child instanceof ParentInterface && !$positionsTracked) {
                    // Clear the render cache first so the re-render walks the
                    // subtree and records descendant rects, instead of
                    // returning the cached measurement output.
                    $child->clearRenderCache();
                    $this->positionTracker->push($childAbsRow, $childAbsCol);
                    $this->widgetRenderer->renderWidget($child, $context);
                    $this->positionTracker->pop();
                }
            }

            array_push($lines, ...$childLines);
            $first = false;
        }

        return $lines;
    }

    /**
     * Layout children horizontally with gap and flex-based column distribution.
     *
     * Flex modes:
     * - No child has flex set: equal distribution (backward compatible)
     * - flex: 0: intrinsic width (render to measure, then use actual width, capped by maxColumns)
     * - flex: N (N > 0): proportional weight (remaining space after fixed children is distributed by weight)
     *
     * @param AbstractWidget[] $children
     *
     * @return string[]
     */
    private function layoutHorizontal(array $children, int $columns, int $rows, int $gap, ?VerticalAlign $verticalAlign = null): array
    {
        if (!$count = \count($children)) {
            return [];
        }

        // When there are more children than available columns (accounting
        // for gap), only the first N that fit are rendered. Each child
        // needs at least 1 column, and each gap between children takes
        // $gap columns: maxChildren = floor((columns + gap) / (1 + gap)).
        $maxChildren = (int) floor(($columns + $gap) / (1 + $gap));
        if ($maxChildren < 1) {
            $maxChildren = 1;
        }
        if ($count > $maxChildren) {
            $children = \array_slice($children, 0, $maxChildren);
            $count = $maxChildren;
        }

        $gapColumns = $gap * max(0, $count - 1);
        $availableColumns = max(1, $columns - $gapColumns);

        // Resolve flex values for each child
        $flexValues = [];
        $anyFlexSet = false;
        foreach ($children as $index => $child) {
            $childStyle = $this->widgetRenderer->resolveStyle($child);
            $flexValues[$index] = $childStyle->getFlex();
            if (null !== $childStyle->getFlex()) {
                $anyFlexSet = true;
            }
        }

        // Compute column widths based on flex values
        $childColumnCounts = $this->computeFlexColumnWidths(
            $children,
            $flexValues,
            $anyFlexSet,
            $availableColumns,
            $rows,
        );

        $childRenders = [];
        $childContexts = [];
        $cacheHits = [];
        $maxRows = 0;
        $hasPositionStack = $this->positionTracker->isActive();

        $colOffset = 0;
        foreach ($children as $index => $child) {
            $childColumns = $childColumnCounts[$index];

            // Push correct absolute position for this horizontal child
            // so descendants get proper coordinates during rendering
            if ($hasPositionStack) {
                [$absRow, $absCol] = $this->positionTracker->currentOffset();
                $this->positionTracker->push($absRow, $absCol + $colOffset);
            }

            $context = new RenderContext($childColumns, $rows, null, $this->fontRegistry);
            $revision = $child->getRenderRevision();
            $hadCachedRender = null !== $child->getRenderCache($childColumns, $rows);
            $childLines = $this->widgetRenderer->renderWidget($child, $context);
            $cacheHits[$index] = $hadCachedRender && $revision === $child->getRenderRevision();
            $childContexts[$index] = $context;
            $childRenders[$index] = $childLines;
            $maxRows = max($maxRows, \count($childLines));

            if ($hasPositionStack) {
                $this->positionTracker->pop();
            }

            $colOffset += $childColumns + $gap;
        }

        if (0 === $maxRows) {
            return [];
        }

        // Compute per-child vertical offset for cross-axis alignment (align-items).
        // This positions shorter children relative to the tallest, analogous to
        // CSS align-items on a flex row.
        $childOffsets = [];
        foreach ($children as $index => $child) {
            $childHeight = \count($childRenders[$index]);
            $childOffsets[$index] = match ($verticalAlign) {
                VerticalAlign::Center => ($maxRows - $childHeight) >> 1,
                VerticalAlign::Bottom => $maxRows - $childHeight,
                default => 0,
            };
        }

        // Track widget positions for horizontal children, accounting for vertical offsets.
        if ($hasPositionStack) {
            [$absRow, $absCol] = $this->positionTracker->currentOffset();
            $colOffset = 0;
            foreach ($children as $index => $child) {
                $childAbsRow = $absRow + $childOffsets[$index];
                $childAbsCol = $absCol + $colOffset;
                $rect = new WidgetRect(
                    $childAbsRow,
                    $childAbsCol,
                    $childColumnCounts[$index],
                    \count($childRenders[$index]),
                );

                if ($child instanceof ParentInterface) {
                    $positionsTracked = true;
                    if ($cacheHits[$index]) {
                        // A cached child was not walked, so its descendants still
                        // carry the previous frame's rects: move them as a block.
                        $positionsTracked = $this->positionTracker->moveSubtree($child, $rect);
                    } else {
                        // They were tracked during the render, before the
                        // cross-axis offset was known, so only that offset is due.
                        $this->positionTracker->shiftContentPositions([$child], 0, $childOffsets[$index]);
                    }

                    if (!$positionsTracked) {
                        $child->clearRenderCache();
                        $this->positionTracker->push($childAbsRow, $childAbsCol);
                        $this->widgetRenderer->renderWidget($child, $childContexts[$index]);
                        $this->positionTracker->pop();
                    }
                }

                $this->positionTracker->setWidgetRect($child, $rect);
                $colOffset += $childColumnCounts[$index] + $gap;
            }
        }

        $gapSpaces = $gap > 0 ? str_repeat(' ', $gap) : '';
        $lines = [];

        for ($row = 0; $row < $maxRows; ++$row) {
            $lineParts = [];
            foreach ($children as $index => $child) {
                $childRow = $row - $childOffsets[$index];
                $line = ($childRow >= 0 && isset($childRenders[$index][$childRow])) ? $childRenders[$index][$childRow] : '';
                $visibleLen = AnsiUtils::visibleWidth($line);
                $cols = $childColumnCounts[$index];

                if ($visibleLen > $cols) {
                    $line = AnsiUtils::truncateToWidth($line, $cols, '');
                } elseif ($visibleLen < $cols) {
                    $line .= str_repeat(' ', $cols - $visibleLen);
                }

                $lineParts[] = $line;
            }

            $lines[] = implode($gapSpaces, $lineParts);
        }

        return $lines;
    }

    /**
     * Compute column widths for horizontal children based on flex values.
     *
     * When no child has flex set, falls back to equal distribution.
     * flex: 0 children get their intrinsic width (measured by rendering).
     * flex: N children share remaining space proportionally.
     *
     * @param AbstractWidget[] $children
     * @param array<int, ?int> $flexValues
     *
     * @return array<int, int>
     */
    private function computeFlexColumnWidths(array $children, array $flexValues, bool $anyFlexSet, int $availableColumns, int $rows): array
    {
        $count = \count($children);

        // No flex set: equal distribution (backward compatible)
        if (!$anyFlexSet) {
            $baseColumns = intdiv($availableColumns, $count);
            $extra = $availableColumns % $count;
            $result = [];
            foreach ($children as $index => $child) {
                $result[$index] = max(1, $baseColumns + ($index < $extra ? 1 : 0));
            }

            return $result;
        }

        // First pass: measure intrinsic-width children (flex: 0) and collect flex weights.
        // Suppress position tracking during measurement (same pattern as vertical fill).
        $intrinsicWidths = [];
        $flexWeights = [];
        $totalFlexWeight = 0;
        $usedColumns = 0;
        $savedStack = $this->positionTracker->suppressStack();

        foreach ($children as $index => $child) {
            if (0 === $flex = $flexValues[$index]) {
                // Intrinsic width: measure the child's natural content width
                // plus chrome (border/padding). This uses measureIntrinsicWidth()
                // instead of renderWidget() because renderWidget() pads lines
                // to the full allocated width via ChromeApplier.
                $width = $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows);
                $intrinsicWidths[$index] = $width;
                $usedColumns += $width;
            } elseif (null !== $flex && $flex > 0) {
                $flexWeights[$index] = $flex;
                $totalFlexWeight += $flex;
            } else {
                // null flex when other siblings have flex set: treat as flex: 1
                $flexWeights[$index] = 1;
                ++$totalFlexWeight;
            }
        }

        $this->positionTracker->restoreStack($savedStack);

        // Each intrinsic child is measured on its own against the full width, so
        // several of them can add up to more than the row. Every flex sibling
        // still ends up with at least one column, so clamp the intrinsic widths
        // to what is left, or the row overflows and the renderer rejects it.
        $intrinsicBudget = $availableColumns - \count($flexWeights);
        if ($intrinsicWidths && array_sum($intrinsicWidths) > $intrinsicBudget) {
            $intrinsicWidths = self::shrinkToFit($intrinsicWidths, $intrinsicBudget);
            $usedColumns = array_sum($intrinsicWidths);
        }

        // Second pass: distribute remaining space among flex children
        $remainingColumns = max(0, $availableColumns - $usedColumns);
        $result = [];
        $flexAllocated = 0;
        $flexColumnsUsed = 0;
        $flexCount = \count($flexWeights);

        foreach ($children as $index => $child) {
            if (isset($intrinsicWidths[$index])) {
                $result[$index] = $intrinsicWidths[$index];
            } elseif (isset($flexWeights[$index])) {
                if ($totalFlexWeight > 0 && $remainingColumns > 0) {
                    // Last flex child gets whatever is left to avoid rounding errors
                    ++$flexAllocated;
                    if ($flexAllocated === $flexCount) {
                        $allocated = $remainingColumns - $flexColumnsUsed;
                    } else {
                        $allocated = (int) floor($remainingColumns * $flexWeights[$index] / $totalFlexWeight);
                        // Each flex child still to come keeps at least one
                        // column, so a heavier weight cannot spend their share:
                        // they are floored to 1 below and the row would overflow.
                        $allocated = min($allocated, $remainingColumns - $flexColumnsUsed - ($flexCount - $flexAllocated));
                    }
                } else {
                    $allocated = 0;
                }
                $result[$index] = max(1, $allocated);
                $flexColumnsUsed += $result[$index];
            }
        }

        return $result;
    }

    /**
     * Reduce widths so they sum to at most the budget, keeping one column each.
     *
     * Scales proportionally first, then trims the widest entries to absorb the
     * rounding, so the narrow children keep their content.
     *
     * @param array<int, int> $widths
     *
     * @return array<int, int>
     */
    private static function shrinkToFit(array $widths, int $budget): array
    {
        $total = array_sum($widths);
        // layoutHorizontal() caps the child count at the column count, so every
        // child is guaranteed at least one column here.
        $budget = max(\count($widths), $budget);

        // Carry the fraction each child loses over to the next one, so the
        // scaled widths add up to the budget instead of leaving columns unused.
        $used = 0;
        $carry = 0;
        foreach ($widths as $index => $width) {
            $carry += $width * $budget;
            $share = intdiv($carry, $total);
            $carry -= $share * $total;
            $used += $widths[$index] = max(1, $share);
        }

        // The carry only overshoots by the number of entries raised to 1.
        while ($used > $budget) {
            $widest = null;
            foreach ($widths as $index => $width) {
                if ($width > 1 && (null === $widest || $width > $widths[$widest])) {
                    $widest = $index;
                }
            }
            if (null === $widest) {
                break;
            }
            --$widths[$widest];
            --$used;
        }

        return $widths;
    }
}
