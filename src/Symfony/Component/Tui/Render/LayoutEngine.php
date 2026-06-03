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
    public function layout(array $children, int $columns, int $rows, int $gap, Direction $direction, ?string $gapLine = null): array
    {
        if (Direction::Horizontal === $direction) {
            return $this->layoutHorizontal($children, $columns, $rows, $gap);
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
            Align::Center => (int) floor($availableSpace / 2),
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
            VerticalAlign::Center => (int) floor($space / 2),
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
        $nonFillNeedsDescendantTracking = [];
        $savedStack = $this->positionTracker->suppressStack();

        foreach ($children as $index => $child) {
            if ($child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded()) {
                $fillChildren[$index] = $child;
            } else {
                // Suppress descendant position tracking during measurement.
                // Children that can have descendants must be re-rendered later
                // with the final absolute offset to populate descendant rects.
                $context = new RenderContext($columns, $rows, null, $this->fontRegistry);
                $childLines = $this->widgetRenderer->renderWidget($child, $context);
                $nonFillRenders[$index] = $childLines;
                $nonFillNeedsDescendantTracking[$index] = $child instanceof ParentInterface;

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
            if (isset($fillChildren[$index])) {
                // Fill child gets calculated rows, distributing remainder to first children
                $childFillRows = $baseFillRows + ($fillIndex < $extraRows ? 1 : 0);
                ++$fillIndex;

                // Add gap before this child (so line count is correct for position)
                if (!$first && $gapLines) {
                    array_push($lines, ...$gapLines);
                }

                $context = new RenderContext($columns, $childFillRows, null, $this->fontRegistry);

                // Push correct absolute position so descendants get proper coordinates
                if ($hasPositionStack) {
                    [$parentAbsRow, $parentAbsCol] = $this->positionTracker->currentOffset();
                    $this->positionTracker->push($parentAbsRow + \count($lines), $parentAbsCol);
                }
                $childLines = $this->widgetRenderer->renderWidget($child, $context);
                if ($hasPositionStack) {
                    $this->positionTracker->pop();
                }

                // Pad fill children to their allocated rows so they actually fill the space
                while (\count($childLines) < $childFillRows) {
                    $childLines[] = '';
                }
            } else {
                $childLines = $nonFillRenders[$index] ?? $this->widgetRenderer->renderWidget($child, new RenderContext($columns, $rows, null, $this->fontRegistry));

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

                $this->positionTracker->setWidgetRect($child, new WidgetRect(
                    $childAbsRow,
                    $childAbsCol,
                    $columns,
                    \count($childLines),
                ));

                // For non-fill parent widgets rendered during measurement,
                // re-render to track descendant positions with the correct
                // absolute offset. Leaf widgets don't need this extra pass.
                // Clear the render cache first so the re-render walks the
                // subtree instead of returning the cached measurement output.
                if (($nonFillNeedsDescendantTracking[$index] ?? false) && !isset($fillChildren[$index])) {
                    $child->clearRenderCache();
                    $this->positionTracker->push($childAbsRow, $childAbsCol);
                    $this->widgetRenderer->renderWidget($child, new RenderContext($columns, $rows, null, $this->fontRegistry));
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
    private function layoutHorizontal(array $children, int $columns, int $rows, int $gap): array
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
            $childLines = $this->widgetRenderer->renderWidget($child, $context);
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

        // Track widget positions for horizontal children
        if ($hasPositionStack) {
            [$absRow, $absCol] = $this->positionTracker->currentOffset();
            $colOffset = 0;
            foreach ($children as $index => $child) {
                $this->positionTracker->setWidgetRect($child, new WidgetRect(
                    $absRow,
                    $absCol + $colOffset,
                    $childColumnCounts[$index],
                    \count($childRenders[$index]),
                ));
                $colOffset += $childColumnCounts[$index] + $gap;
            }
        }

        $gapSpaces = $gap > 0 ? str_repeat(' ', $gap) : '';
        $lines = [];

        for ($row = 0; $row < $maxRows; ++$row) {
            $lineParts = [];
            foreach ($children as $index => $child) {
                $line = $childRenders[$index][$row] ?? '';
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
}
