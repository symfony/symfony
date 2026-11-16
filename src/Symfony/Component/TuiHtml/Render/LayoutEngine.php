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

use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\LineBufferInterface;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\Align;
use Symfony\Component\TuiHtml\Style\AutoMargin;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Direction;
use Symfony\Component\TuiHtml\Style\GridPlacement;
use Symfony\Component\TuiHtml\Style\GridTemplate;
use Symfony\Component\TuiHtml\Style\Justify;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\TableRole;
use Symfony\Component\TuiHtml\Style\VerticalAlign;
use Symfony\Component\TuiHtml\Widget\FrameWidget;
use Symfony\Component\TuiHtml\Widget\HorizontalRuleWidget;
use Symfony\Component\TuiHtml\Widget\ImageAlternativeWidget;
use Symfony\Component\TuiHtml\Widget\ReplacedElementInterface;
use Symfony\Component\TuiHtml\Widget\TextWidget;

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
    /**
     * The most rows a grid item spans, which bounds the cost of placing items.
     */
    private const MAX_GRID_ROW_SPAN = 100;
    /** The most lines cut beside floats kept from a frame to the next, and their bytes, see cutBesideFloats() */
    private const MAX_CUTS = 32768;
    private const MAX_CUT_BYTES = 16000000;

    /** The lines of a box each glyph draws, as bits: up, down, left, right; the first glyph of the arms wins, see joinFirstGlyph() */
    /**
     * @internal How many children a block holds from which its layout and what it asks of its children are kept, to be
     *           made again for those that changed only, see layoutVertical() and Renderer::plainChildren(): for fewer,
     *           made again for all costs less than keeping them
     */
    public const MANY_CHILDREN = 16;
    /** @internal The lines of each glyph of a box, as bits: up, down, left and right */
    public const BOX_ARMS = ['─' => 0b11, '│' => 0b1100, '┌' => 0b101, '┐' => 0b110, '└' => 0b1001, '┘' => 0b1010, '├' => 0b1101, '┤' => 0b1110, '┬' => 0b111, '┴' => 0b1011, '┼' => 0b1111, '╭' => 0b101, '╮' => 0b110, '╰' => 0b1001, '╯' => 0b1010];
    /** @var \WeakMap<AbstractWidget, array{0: int, 1: LineBufferInterface, 2: int, 3: int, 4?: bool, 5?: int}> */
    private \WeakMap $overflowingItems;
    /** @var \WeakMap<AbstractWidget, array<string, array<string, mixed>>> The last vertical layouts of each container, by the key of the renders, each step of it by child, see layoutVertical() */
    private \WeakMap $verticalSteps;
    /** @var array<int, array<string, string>> The lines cut beside floats, by width, see cutBesideFloats() */
    private array $floatCuts = [];
    private int $floatCutCount = 0;
    private int $floatCutBytes = 0;

    /** @return \WeakMap<AbstractWidget, array{0: int, 1: LineBufferInterface, 2: int, 3: int, 4?: bool, 5?: int}> */
    public function getOverflowingItems(): \WeakMap
    {
        return $this->overflowingItems;
    }

    public function __construct(private readonly WidgetRendererInterface $widgetRenderer, private readonly PositionTracker $positionTracker, private readonly FontRegistry $fontRegistry)
    {
        $this->overflowingItems = new \WeakMap();
        $this->verticalSteps = new \WeakMap();
        $this->cellWidths = new \WeakMap();
        $this->preparedTables = new \WeakMap();
        $this->cellOffsets = new \WeakMap();
        $this->cellColumnBackgrounds = new \WeakMap();
        $this->cellColumnBands = new \WeakMap();
        $this->rowHeights = new \WeakMap();
        $this->spanningCells = new \WeakMap();
        $this->carried = new \WeakMap();
        $this->spanHeights = new \WeakMap();
        $this->cellHeights = new \WeakMap();
        $this->drawsReplaced = new \WeakMap();
    }
    /**
     * The backgrounds the <col> elements of a table give the cells of their columns, like CSS column backgrounds.
     *
     * @var \WeakMap<AbstractWidget, Color>
     */
    private \WeakMap $cellColumnBackgrounds;

    /**
     * The background of the column of a table cell, from its <col>, null for none.
     *
     * @internal
     */
    public function getColumnBackground(AbstractWidget $cell): ?Color
    {
        return $this->cellColumnBackgrounds[$cell] ?? null;
    }

    /**
     * The backgrounds of the columns a cell spans, when they differ: each with its width, the gap after it included.
     *
     * @var \WeakMap<AbstractWidget, list<array{?Color, int}>>
     */
    private \WeakMap $cellColumnBands;

    /**
     * The background of each column a table cell spans with its width in cells, from left to right, null for a cell in
     * one column or in columns of the same background.
     *
     * @internal
     *
     * @return list<array{?Color, int}>|null
     */
    public function getColumnBands(AbstractWidget $cell): ?array
    {
        return $this->cellColumnBands[$cell] ?? null;
    }
    /**
     * Counts the changes of the column backgrounds of cells, which change their resolved styles.
     */
    private int $columnBackgroundRevision = 0;

    /**
     * @internal
     */
    public function getColumnBackgroundRevision(): int
    {
        return $this->columnBackgroundRevision;
    }
    /**
     * The columns left blank before a table cell, taken by a cell of a row above that spans rows.
     *
     * @var \WeakMap<AbstractWidget, int>
     */
    private \WeakMap $cellOffsets;
    /**
     * The widths of the cells of the tables laid out, so that their rows line up.
     *
     * @var \WeakMap<AbstractWidget, int>
     */
    private \WeakMap $cellWidths;
    /** @var \WeakMap<AbstractWidget, array{string, int, ?int}> The last layout of each table: its key, its room, the width of its columns when they fit there, see prepareTable() */
    private \WeakMap $preparedTables;
    /**
     * The height of each row of a table with cells spanning rows, those cells left out of their first row.
     *
     * @var \WeakMap<AbstractWidget, int>
     */
    private \WeakMap $rowHeights;
    /**
     * The cells spanning rows: their height, and the rows below they reach into.
     *
     * @var \WeakMap<AbstractWidget, array{int, list<AbstractWidget>}>
     */
    private \WeakMap $spanningCells;
    /**
     * The lines of the cells spanning rows that each row below their first draws, by cell: their column, width and lines.
     *
     * @var \WeakMap<AbstractWidget, array<int, array{int, int, list<string>}>>
     */
    private \WeakMap $carried;

    /**
     * Whether a float draws a replaced element, see drawsReplacedElement(), for the revision of its render and the epoch
     * of the styles it was found for.
     *
     * @var \WeakMap<AbstractWidget, array{int, int, bool}>
     */
    private \WeakMap $drawsReplaced;
    /** The columns the last row laid out by layoutHorizontal() leaves free at its end, see layoutWrapped() */
    private int $freeColumns = 0;
    /**
     * The row heights of each table with cells spanning rows, with what they were measured for.
     *
     * @var \WeakMap<AbstractWidget, array{string, list<int>}>
     */
    private \WeakMap $spanHeights;
    /**
     * The height of each cell of a table with cells spanning rows, with what it was measured for.
     *
     * @var \WeakMap<AbstractWidget, array{string, int}>
     */
    private \WeakMap $cellHeights;
    /**
     * The widths of the columns of its parent grid a subgrid spans, and their gap, set as the parent lays it out.
     *
     * @var \WeakMap<AbstractWidget, array{list<int>, int}>|null
     */
    private ?\WeakMap $subgridTracks = null;
    /**
     * The heights of the rows of its parent grid a subgrid of rows spans, none while the parent measures it, their gap,
     * the heights it was last given, and the width the parent measures it at.
     *
     * @var \WeakMap<AbstractWidget, array{list<int>, int, list<int>, int, int}>|null
     */
    private ?\WeakMap $subgridRowTracks = null;
    /**
     * The heights the content of the rows of a subgrid of rows needs, as its parent last measured it: at that revision
     * and width.
     *
     * @var \WeakMap<AbstractWidget, array{int, int, list<int>}>|null
     */
    private ?\WeakMap $subgridRowHeights = null;

    /**
     * Sizes the columns of a table across its rows, like CSS automatic table layout.
     *
     * Each column is as wide as its widest cell (a cell spanning columns
     * widens them evenly when they are too narrow); a table too wide for the
     * room shrinks its columns, and a stretched one (a percentage width)
     * shares the room left among them.
     */
    public function prepareTable(AbstractWidget $table, int $columns, int $rows, bool $stretch): void
    {
        // Laid out again only when the table changed, or its room when its columns do not fit there as wide as they
        // are, like when it is measured, then drawn as wide as them, in a frame
        $key = $table->getRenderRevision().':'.$rows.':'.($stretch ? 1 : 0).':'.$this->widgetRenderer->getStyleEpoch();
        $last = $this->preparedTables[$table] ?? null;
        if (null !== $last && $last[0] === $key && ($columns === $last[1] || !$stretch && null !== $last[2] && $columns >= $last[2])) {
            return;
        }
        // The width of its columns as wide as they are, when they fit and nothing stretched them; null otherwise
        $this->preparedTables[$table] = [$key, $columns, null];
        $tableRows = [];
        // The index past the last row of the group of each row, and the first of the rows without a group before
        $groupEnds = [];
        $run = null;
        // The least widths the columns are given, like <col style="width: 10ch">
        $columnWidths = $this->columnElementWidths($table, $columns);
        $tableStyle = StyleBridge::resolve($this->widgetRenderer, $table);
        // table-layout: fixed needs a width to share: without one, the columns are sized by their cells
        $fixed = true === $tableStyle->getFixedLayout() && (null !== $tableStyle->getMaxColumns() || $stretch);
        foreach ($table instanceof ParentInterface ? $this->tableOrder($this->tableChildren($table)) : [] as $child) {
            $role = $this->widgetRenderer->resolveComputedStyle($child)->getTableRole();
            if (TableRole::Row === $role) {
                // The rows side by side in the table, without a group of their own, like those of a tbody { display:
                // contents }, are one group, like the anonymous one of CSS
                $run ??= \count($tableRows);
                $tableRows[] = $child;
                $groupEnds[] = 0;
                continue;
            }
            $groupEnds = self::endRowRun($groupEnds, $run);
            $run = null;
            if (TableRole::RowGroup === $role && $child instanceof ParentInterface) {
                $start = \count($tableRows);
                foreach ($child->all() as $row) {
                    if (TableRole::Row === $this->widgetRenderer->resolveComputedStyle($row)->getTableRole()) {
                        $tableRows[] = $row;
                    }
                }
                // Like HTML, a cell spans no rows past the end of its row group
                array_push($groupEnds, ...array_fill(0, \count($tableRows) - $start, \count($tableRows)));
            }
        }
        $groupEnds = self::endRowRun($groupEnds, $run);
        if (!$tableRows) {
            return;
        }
        // The backgrounds of the <col> elements, under the cells of their columns
        $backgrounds = $this->columnElementBackgrounds($table);
        // The cells of each row, with their first column and span
        $savedStack = $this->positionTracker->suppressStack();
        $layout = [];
        $gap = StyleBridge::resolve($this->widgetRenderer, $tableRows[0])?->getGap() ?? 0;
        $columnCount = 0;
        // No table shows more columns than it has room for, one each: spans are clamped to it, whatever colspan says
        $limit = max(1, $columns);
        // The columns taken in each row by cells of the rows above that span rows
        $taken = [];
        foreach ($tableRows as $rowIndex => $row) {
            $column = 0;
            foreach ($row instanceof ParentInterface ? $row->all() : [] as $cell) {
                $style = StyleBridge::resolve($this->widgetRenderer, $cell);
                if ($style->getHidden()) {
                    continue;
                }
                $first = $column;
                while (isset($taken[$rowIndex][$column])) {
                    ++$column;
                }
                if ($column >= $limit) {
                    // No room left: the cell gets no column, and its row falls back to a plain row layout
                    break;
                }
                $span = min($style->getColumnSpan() ?? 1, $limit - $column);
                $background = $backgrounds[$column] ?? null;
                if ($background !== ($this->cellColumnBackgrounds[$cell] ?? null)) {
                    ++$this->columnBackgroundRevision;
                }
                if (null !== $background) {
                    $this->cellColumnBackgrounds[$cell] = $background;
                } else {
                    unset($this->cellColumnBackgrounds[$cell]);
                }
                // A fixed table does not measure its cells; the others at their widest, shrunk to the room below
                $layout[] = [$cell, $column, $span, $fixed ? 0 : $this->widgetRenderer->measureIntrinsicWidth($cell, max($columns, 4096), $rows), $column - $first, $rowIndex];
                for ($below = 1; $below < min($style->getRowSpan() ?? 1, $groupEnds[$rowIndex] - $rowIndex); ++$below) {
                    for ($i = 0; $i < $span; ++$i) {
                        $taken[$rowIndex + $below][$column + $i] = true;
                    }
                }
                $column += $span;
            }
            $columnCount = max($columnCount, $column, ...array_map(static fn (int $c) => $c + 1, array_keys($taken[$rowIndex] ?? [])));
        }
        $this->positionTracker->restoreStack($savedStack);
        if (0 === $columnCount) {
            return;
        }
        if ($fixed) {
            $firstRow = array_values(array_filter($layout, static fn (array $entry) => 0 === $entry[5]));
            $widths = $this->fixedTableWidths($columnWidths, array_map(static fn (array $entry) => [$entry[0], $entry[1], $entry[2]], $firstRow), $columnCount, $columns, max($columnCount, $columns - $gap * ($columnCount - 1)), $gap);
            $this->assignCellWidths($table, $layout, $widths, $columnCount, $gap);
            $this->spanRows($table, $tableRows, $groupEnds, $layout, $rows);

            return;
        }
        // The columns first, then the single cells, then spanning cells widen what they span when it is too narrow
        $widths = array_fill(0, $columnCount, 1);
        // The columns of a cell or a <col> of a set width, which keep it while the others take the room left, like CSS
        $set = [];
        foreach (\array_slice($columnWidths, 0, $columnCount) as $column => $least) {
            $widths[$column] = max(1, $least);
            if ($least > 0) {
                $set[$column] = true;
            }
        }
        foreach ($layout as [$cell, $column, $span, $width]) {
            if (1 === $span) {
                // A cell width, like <td width> or td { width: 12ch }, is a least width of its column too
                $cellStyle = StyleBridge::resolve($this->widgetRenderer, $cell);
                $widths[$column] = max($widths[$column], $width, $cellStyle->getMaxColumns() ?? $cellStyle->resolveWidthPercent($columns) ?? 0);
                if (null !== $cellStyle->getMaxColumns() || null !== $cellStyle->getWidthPercent()) {
                    $set[$column] = true;
                }
            }
        }
        foreach ($layout as [, $column, $span, $width]) {
            $span = min($span, $columnCount - $column);
            $missing = $width - (array_sum(\array_slice($widths, $column, $span)) + $gap * ($span - 1));
            for ($i = 0; $missing > 0 && $i < $span; ++$i) {
                $share = intdiv($missing + $span - 1 - $i, $span - $i);
                $widths[$column + $i] += $share;
                $missing -= $share;
            }
        }
        $budget = max($columnCount, $columns - $gap * ($columnCount - 1));
        if (!$stretch && array_sum($widths) <= $budget) {
            $this->preparedTables[$table] = [$this->preparedTables[$table][0], $columns, array_sum($widths) + $gap * ($columnCount - 1)];
        }
        if (array_sum($widths) > $budget) {
            // Like CSS, the columns give way down to their longest words; past that, they are cut evenly
            $floors = array_fill(0, $columnCount, 1);
            $saved = $this->positionTracker->suppressStack();
            foreach ($layout as [$cell, $column, $span, $width]) {
                if (1 === $span) {
                    $floors[$column] = max($floors[$column], min($width, $this->widgetRenderer->measureMinContentWidth($cell, $columns, $rows)));
                }
            }
            // Then the cells spanning columns: their longest words too, over the columns they span, when they fit with
            // those of the other cells, which are kept otherwise
            $spanned = $floors;
            foreach (array_sum($floors) <= $budget ? $layout : [] as [$cell, $column, $span, $width]) {
                if (($span = min($span, $columnCount - $column)) > 1) {
                    $spanned = self::widenFloors($spanned, $widths, $column, $span, min($width, $this->widgetRenderer->measureMinContentWidth($cell, $columns, $rows)) - $gap * ($span - 1));
                }
            }
            $floors = array_sum($spanned) <= $budget ? $spanned : $floors;
            $this->positionTracker->restoreStack($saved);
            $widths = array_sum($floors) <= $budget ? self::shrinkAboveFloors($widths, $floors, $budget) : array_values(self::shrinkToFit($floors, $budget));
        } elseif ($stretch && array_sum($widths) < $budget) {
            // Like a table with a percentage width: the room left goes to the columns, by their width; to those of no
            // set width when there are some, like the content beside a navigation of 12rem
            $extra = $budget - array_sum($widths);
            $growing = array_diff_key($widths, \count($set) < $columnCount ? $set : []);
            $total = max(1, array_sum($growing));
            $last = array_key_last($growing);
            $given = 0;
            foreach ($growing as $i => $width) {
                $share = $i === $last ? $extra - $given : intdiv($extra * $width, $total);
                $widths[$i] += $share;
                $given += $share;
            }
        }
        $this->assignCellWidths($table, $layout, $widths, $columnCount, $gap);
        $this->spanRows($table, $tableRows, $groupEnds, $layout, $rows);
    }

    /**
     * The width of a table as wide as its columns, its rows laid out for that room: the widest row of its cells, the
     * gaps between them; null for a table without rows.
     *
     * @internal
     */
    public function tableContentWidth(AbstractWidget $table, int $columns, int $rows): ?int
    {
        $this->prepareTable($table, $columns, $rows, false);
        $widest = null;
        foreach ($table instanceof ParentInterface ? $this->tableChildren($table) : [] as $child) {
            $role = $this->widgetRenderer->resolveComputedStyle($child)->getTableRole();
            $tableRows = TableRole::Row === $role ? [$child] : (TableRole::RowGroup === $role && $child instanceof ParentInterface ? $child->all() : []);
            foreach ($tableRows as $row) {
                if (!$row instanceof ParentInterface || TableRole::Row !== $this->widgetRenderer->resolveComputedStyle($row)->getTableRole()) {
                    continue;
                }
                $gap = $this->widgetRenderer->resolveComputedStyle($row)->getGap() ?? 0;
                $width = 0;
                $cells = 0;
                foreach ($row->all() as $cell) {
                    if (isset($this->cellWidths[$cell])) {
                        $width += $this->cellWidths[$cell] + ($this->cellOffsets[$cell] ?? 0);
                        ++$cells;
                    }
                }
                if ($cells > 0) {
                    $widest = max($widest ?? 0, $width + $gap * ($cells - 1));
                }
            }
        }

        return $widest;
    }

    /**
     * The lines of a table row without cells of its own that cells spanning rows reach into: their lines in their
     * columns, as tall as the row; null for another row.
     *
     * @return list<string>|null
     */
    public function spannedRowLines(AbstractWidget $row, int $columns): ?array
    {
        if (!isset($this->rowHeights[$row]) || !$carried = $this->carried[$row] ?? []) {
            return null;
        }
        $lines = [];
        for ($i = 0; $i < $this->rowHeights[$row]; ++$i) {
            $line = str_repeat(' ', $columns);
            foreach ($carried as [$x, $width, $part]) {
                $piece = AnsiUtils::sliceToWidth($part[$i] ?? '', 0, $width);
                $line = AnsiUtils::sliceToWidth($line, 0, $x)."\x1b[0m".$piece."\x1b[0m".AnsiUtils::sliceToWidth($line, $x + $width, max(0, $columns - $x - $width));
            }
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Narrows widths to a budget, each no narrower than its floor: the room above the floors gives way in proportion,
     * the columns rounding leaves going to the largest shares.
     *
     * @param list<int> $widths
     * @param list<int> $floors
     *
     * @return list<int>
     *
     * @internal
     */
    public static function shrinkAboveFloors(array $widths, array $floors, int $budget): array
    {
        $slacks = array_map(static fn (int $width, int $floor) => max(0, $width - $floor), $widths, $floors);
        $totalSlack = array_sum($slacks);
        $deficit = array_sum($widths) - $budget;
        if ($totalSlack <= 0 || $deficit <= 0) {
            return $widths;
        }
        $losses = [];
        $remainders = [];
        foreach ($slacks as $i => $slack) {
            $losses[$i] = intdiv($deficit * $slack, $totalSlack);
            $remainders[$i] = $deficit * $slack % $totalSlack;
        }
        arsort($remainders);
        foreach (\array_slice(array_keys($remainders), 0, $deficit - array_sum($losses)) as $i) {
            ++$losses[$i];
        }

        return array_map(static fn (int $width, int $loss, int $floor) => max($floor, $width - $loss), $widths, $losses, $floors);
    }

    /**
     * The children of a table in the order they are drawn, like CSS: its first header group before its other rows, its
     * first footer group after them, the others where they are, like a <tfoot> written before the <tbody>.
     *
     * @template T of AbstractWidget
     *
     * @param array<T> $children
     *
     * @return array<T>
     *
     * @internal
     */
    public function tableOrder(array $children): array
    {
        $header = $footer = $first = $last = null;
        foreach ($children as $index => $child) {
            $style = $this->widgetRenderer->resolveComputedStyle($child);
            if (TableRole::Row !== $role = $style->getTableRole()) {
                if (TableRole::RowGroup !== $role || $style->getHidden()) {
                    continue;
                }
                $place = $style->getRowGroupPlace();
                if (-1 === $place && null === $header) {
                    $header = $index;
                } elseif (1 === $place && null === $footer) {
                    $footer = $index;
                }
            }
            $first ??= $index;
            $last = $index;
        }
        if (null === $header && null === $footer) {
            return $children;
        }
        $ordered = [];
        foreach ($children as $index => $child) {
            if ($index === $first && null !== $header) {
                $ordered[$header] = $children[$header];
            }
            if ($index !== $header && $index !== $footer) {
                $ordered[$index] = $child;
            }
            if ($index === $last && null !== $footer) {
                $ordered[$footer] = $children[$footer];
            }
        }

        return array_is_list($children) ? array_values($ordered) : $ordered;
    }

    /**
     * The children of a table, those of a child without a box, like tbody { display: contents }, in its place.
     *
     * @return list<AbstractWidget>
     */
    private function tableChildren(ParentInterface $table, int $depth = 0): array
    {
        $children = [];
        foreach ($table->all() as $child) {
            $style = $this->widgetRenderer->resolveComputedStyle($child);
            if ($depth < 32 && $child instanceof ParentInterface && true === $style->getContents() && !$style->getHidden()) {
                array_push($children, ...$this->tableChildren($child, $depth + 1));
            } else {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Widens the floors of the columns a cell spans to its own, no floor past the width of its column: what is missing
     * goes to the columns by the room they have above their floor, the columns rounding leaves going first.
     *
     * @param list<int> $floors
     * @param list<int> $widths
     *
     * @return list<int>
     *
     * @internal
     */
    public static function widenFloors(array $floors, array $widths, int $column, int $span, int $floor): array
    {
        $slacks = [];
        for ($i = $column; $i < $column + $span; ++$i) {
            $slacks[$i] = max(0, $widths[$i] - $floors[$i]);
        }
        $missing = min($floor - array_sum(\array_slice($floors, $column, $span)), array_sum($slacks));
        if ($missing <= 0) {
            return $floors;
        }
        $total = array_sum($slacks);
        $given = 0;
        foreach ($slacks as $i => $slack) {
            $floors[$i] += $share = intdiv($missing * $slack, $total);
            $slacks[$i] -= $share;
            $given += $share;
        }
        foreach ($slacks as $i => $slack) {
            if ($given < $missing && $slack > 0) {
                ++$floors[$i];
                ++$given;
            }
        }

        return $floors;
    }

    /**
     * The least widths the <col> and <colgroup> elements of a table give its columns, in order.
     *
     * @return list<int>
     */
    public function columnElementWidths(AbstractWidget $table, int $columns): array
    {
        $widths = [];
        foreach ($table instanceof ParentInterface ? $table->all() : [] as $child) {
            if (TableRole::Column !== $this->widgetRenderer->resolveComputedStyle($child)->getTableRole()) {
                continue;
            }
            $groupColumns = $child instanceof ParentInterface ? array_filter($child->all(), fn (AbstractWidget $col) => TableRole::Column === $this->widgetRenderer->resolveComputedStyle($col)->getTableRole()) : [];
            foreach ($groupColumns ?: [$child] as $column) {
                $style = StyleBridge::resolve($this->widgetRenderer, $column);
                $least = $style->getMaxColumns() ?? $style->resolveWidthPercent($columns) ?? 0;
                // A group with columns spans theirs; one without spans its own
                array_push($widths, ...array_fill(0, min(1000, $style->getColumnSpan() ?? 1), $least));
            }
        }

        return $widths;
    }

    /**
     * The column widths of a table with table-layout: fixed: those of the <col> elements, then of the cells of the
     * first row (a spanning one shared among the columns it spans), the other columns sharing what is left evenly.
     *
     * @param list<int>                             $columnWidths The widths of the <col> elements
     * @param list<array{AbstractWidget, int, int}> $firstRow     The cells of the first row, with their column and span
     *
     * @return list<int>
     */
    public function fixedTableWidths(array $columnWidths, array $firstRow, int $count, int $columns, int $budget, int $gap): array
    {
        $given = [];
        foreach (\array_slice($columnWidths, 0, $count) as $column => $width) {
            if ($width > 0) {
                $given[$column] = $width;
            }
        }
        foreach ($firstRow as [$cell, $column, $span]) {
            $style = StyleBridge::resolve($this->widgetRenderer, $cell);
            if (null === $width = $style->getMaxColumns() ?? $style->resolveWidthPercent($columns)) {
                continue;
            }
            $span = min($span, $count - $column);
            $open = array_values(array_filter(range($column, $column + $span - 1), static fn (int $c) => !isset($given[$c])));
            $left = $width - $gap * ($span - 1) - array_sum(array_intersect_key($given, array_flip(range($column, $column + $span - 1))));
            foreach ($open as $k => $c) {
                $given[$c] = max(1, intdiv($left, \count($open)) + ($k < $left % \count($open) ? 1 : 0));
            }
        }

        return self::shareFixedWidths($count, $budget, $given);
    }

    /**
     * Gives each column its width, the columns without one sharing the room left evenly; widths over the room are shrunk.
     *
     * @param array<int, int> $given By column
     *
     * @return list<int>
     *
     * @internal
     */
    public static function shareFixedWidths(int $count, int $budget, array $given): array
    {
        $free = $count - \count($given);
        if ($given && array_sum($given) > $budget - $free) {
            // Too wide: the given widths shrink, each other column keeping a cell
            $given = self::shrinkToFit($given, max(\count($given), $budget - $free));
        }
        $left = max($free, $budget - array_sum($given));
        if (0 === $free && $given && $left > 0) {
            // Every column has a width, narrower than the table: the room left goes to them by their widths, like CSS
            $total = array_sum($given);
            $extra = $left;
            $last = array_key_last($given);
            foreach ($given as $column => $width) {
                $share = $column === $last ? $extra : intdiv($left * $width, $total);
                $given[$column] += $share;
                $extra -= $share;
            }
        }
        $widths = [];
        for ($column = 0, $k = 0; $column < $count; ++$column) {
            if (isset($given[$column])) {
                $widths[] = $given[$column];
                continue;
            }
            $widths[] = max(1, intdiv($left, $free) + ($k++ < $left % $free ? 1 : 0));
        }

        return $widths;
    }

    /**
     * Layout children based on direction.
     *
     * @param AbstractWidget[] $children
     */
    public function layout(array $children, int $columns, int $rows, int $gap, Direction $direction, ?string $gapLine = null, ?VerticalAlign $verticalAlign = null, ?Justify $justify = null, ?int $wrapRowGap = null, bool $reverse = false, bool $collapseMargins = false, ?Align $crossAlign = null, bool $flexColumn = false, ?int $contentRows = null, ?string $alignContent = null, bool $wrapReverse = false, bool $inlineFlow = false, ?AbstractWidget $owner = null): LineBufferInterface
    {
        $children = $this->ordered($children);
        if ($reverse) {
            $children = array_reverse($children);
        }
        if (Direction::Horizontal === $direction && null !== $wrapRowGap) {
            return $this->layoutWrapped($children, $columns, $rows, $gap, $verticalAlign, $justify, $wrapRowGap, $contentRows, $alignContent, $wrapReverse, $inlineFlow, $crossAlign);
        }
        if (Direction::Horizontal === $direction) {
            // A row of a set height is as tall: its items stretch to it, like a single-line flex container
            return $this->layoutHorizontal($children, $columns, $rows, $gap, $verticalAlign, $justify, $contentRows);
        }

        return $this->layoutVertical($children, $columns, $rows, $gap, $gapLine, $collapseMargins, $crossAlign, $flexColumn, $flexColumn ? $contentRows : null, $justify, $flexColumn ? $verticalAlign : null, $reverse ? null : $owner);
    }

    /**
     * Compute the horizontal offset needed to align content within the available width.
     */
    public function computeAlignOffset(LineBufferInterface $lines, int $columns, Align $align): int
    {
        $availableSpace = max(0, $columns - $lines->getMaxVisibleWidth());

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
            VerticalAlign::Top, VerticalAlign::Stretch => 0,
            VerticalAlign::Center => $space >> 1,
            VerticalAlign::Bottom => $space,
        };
    }

    /**
     * Lays children out on a grid, like CSS display: grid.
     *
     * Items with a place (an area, or lines) go there; the others fill the
     * free cells row after row, each taking as many columns and rows as it
     * spans. A row is as tall as its tallest single-row item and its
     * template minimum; an item spanning rows makes the last of them taller
     * when they are not enough. Items are stretched to their area, unless
     * the alignment says otherwise.
     *
     * @param AbstractWidget[] $children
     * @param bool             $contentSized Its columns sized by their content, to measure the width it needs
     */
    public function layoutGrid(array $children, int $columns, int $rows, GridTemplate $template, int $columnGap, int $rowGap, ?VerticalAlign $verticalAlign = null, bool $mirrored = false, ?string $justifyItems = null, ?int $contentRows = null, ?string $alignContent = null, bool $contentSized = false, ?int $maxRows = Renderer::MAX_LINES, ?Justify $justifyContent = null, bool $stretchColumns = true): LineBufferInterface
    {
        $children = array_values($this->ordered($children));
        if (!$children) {
            return new ArrayLineBuffer([]);
        }
        // A subgrid of rows: the rows of its parent it spans and their gap, like cards side by side whose titles, texts
        // and links line up; only while its parent is a grid, like for its columns below
        $rowTracks = null;
        if ($template->subgridRows && null !== ($box = $children[0]->getParent()) && null !== ($rowTracks = $this->subgridRowTracks[$box] ?? null) && null !== ($grandParent = $box->getParent()) && null !== $this->widgetRenderer->resolveComputedStyle($grandParent)->getGridTemplate()) {
            // The gap of its parent, its rows on the lines of its parent
            $rowGap = $rowTracks[1];
        } else {
            $rowTracks = null;
        }
        $inheritedColumns = false;
        // A subgrid: the columns of its parent it spans, their widths and their gap, like the rows of a table of the
        // documentation of Tailwind, a grid of rows each a subgrid
        // Only while its parent is a grid, which a media query can change: a grid of its own otherwise
        if ($template->subgrid && null !== ($parent = $children[0]->getParent()) && null !== ($tracks = $this->subgridTracks[$parent] ?? null) && null !== ($grandParent = $parent->getParent()) && null !== $this->widgetRenderer->resolveComputedStyle($grandParent)->getGridTemplate()) {
            $inheritedColumns = true;
            // Its own column-gap, else that of its parent
            $columnGap = null !== $this->widgetRenderer->resolveComputedStyle($parent)->getGap() ? $columnGap : $tracks[1];
            $template = new GridTemplate(array_map(static fn (int $width): array => ['cells', (float) $width, 0], $tracks[0]), null, $template->rows, $template->areas, $template->autoRows, $template->dense, [], $template->rowLines, $template->columnFlow, null, $template->rowFractions, $template->autoRowCycle);
        }
        $count = max(1, $template->count($columns, $columnGap));
        // The implicit columns before the explicit ones, from lines before the first one: the explicit ones start past them
        $lead = $rowLead = 0;
        $placed = $this->placeGridItems($children, $template, $count, $inheritedColumns, $lead, $rowLead);
        // The rows before the explicit ones: explicit rows of the heights of grid-auto-rows, from its last backwards
        if ($rowLead > 0) {
            $cycle = max(1, \count($template->autoRowCycle));
            $before = array_map(static fn (int $row): ?int => $template->autoRow((($row - $rowLead) % $cycle + $cycle) % $cycle), range(0, $rowLead - 1));
            $template = new GridTemplate($template->tracks, $template->autoRepeat, [...$before, ...$template->rows], $template->areas, $template->autoRows, $template->dense, $template->columnLines, $template->rowLines, $template->columnFlow, $template->autoColumns, [...array_fill(0, $rowLead, 0.0), ...$template->rowFractions], $template->autoRowCycle, $template->autoColumnCycle, $template->repeatAt, $template->repeatLines, $template->autoFit, $template->repeatLimit, $template->subgrid, $template->subgridRows);
        }
        // auto-fit: the repeated tracks no item is in are dropped, those left sharing the room, like the .grid of Pico
        if ($template->autoFit && null !== $template->autoRepeat && null === $template->repeatLimit) {
            $used = max(0, ...array_map(static fn (array $item) => $item[2] + $item[4] - $lead, $placed));
            if ($used < $count) {
                $template = $template->withRepeatLimit($used - \count($template->tracks));
                $count = max(1, $template->count($columns, $columnGap));
            }
        }
        // Column after column without grid-template-columns, every column is implicit, like CSS
        $explicit = $template->columnFlow && !$template->tracks && null === $template->autoRepeat && !$template->areas ? 0 : $count;
        $columnCount = max(1, $lead + $explicit, ...array_map(static fn (array $item) => $item[2] + $item[4], $placed));
        // The rows: their template minimum, then their items (see below)
        $rowCount = max(\count($template->rows), ...array_map(static fn (array $item) => $item[1] + $item[3], $placed));
        $heights = [];
        for ($row = 0; $row < $rowCount; ++$row) {
            // The explicit rows, then the implicit ones, at least as tall as grid-auto-rows, its values in turn
            $heights[$row] = \array_key_exists($row, $template->rows) ? $template->rows[$row] ?? 0 : $template->autoRow($row - \count($template->rows)) ?? 0;
        }
        // The rows of a set height, and the fr ones sharing the free rows of a grid of a set height when the others are
        // set: the text an item writes vertically in them has lines as long as its area, its inline size in CSS
        $definite = [];
        foreach ($heights as $row => $height) {
            if (\is_int(\array_key_exists($row, $template->rows) ? $template->rows[$row] : $template->autoRow($row - \count($template->rows)))) {
                $definite[$row] = $height;
            }
        }
        $fractions = array_filter(array_intersect_key($template->rowFractions, $heights), static fn (float $fraction) => $fraction > 0);
        if (null !== $contentRows && $fractions && \count($definite) + \count($fractions) === \count($heights) && !array_intersect_key($definite, $fractions)) {
            $free = max(0, $contentRows - array_sum($definite) - $rowGap * (\count($heights) - 1));
            $sum = array_sum($fractions);
            $given = 0;
            $last = array_key_last($fractions);
            foreach ($fractions as $row => $fraction) {
                $definite[$row] = $row === $last ? $free - $given : (int) floor($free * $fraction / $sum);
                $given += $definite[$row];
            }
        }
        $inline = [];
        foreach ($placed as $index => [$child, $row, , $rowSpan]) {
            $spanned = array_intersect_key($definite, array_flip(range($row, $row + $rowSpan - 1)));
            if (\count($spanned) === $rowSpan && null !== $this->widgetRenderer->resolveComputedStyle($child)->getWritingMode()) {
                $inline[$index] = max(1, array_sum($spanned) + $rowGap * ($rowSpan - 1));
            }
        }
        // The tracks: the content of an auto track is its widest single-column item; written vertically in rows of a
        // set height, as many columns as its lines as long as them
        $savedStack = $this->positionTracker->suppressStack();
        $contentWidths = [];
        foreach ($placed as $index => [$child, , $column, , $span]) {
            // A subgrid: its items in the columns of this grid they are in
            if ($child instanceof ContainerWidget && $this->isSubgrid($child)) {
                foreach ($this->subgridWidths($child, $span, $columns, $rows) as $offset => $width) {
                    $contentWidths[$column + $offset] = max($contentWidths[$column + $offset] ?? 0, $width);
                }
                continue;
            }
            if (1 === $span) {
                $contentWidths[$column] = max($contentWidths[$column] ?? 0, isset($inline[$index]) ? min($columns, max(0, ...array_map(AnsiUtils::visibleWidth(...), $this->widgetRenderer->renderStretchedLines($child, new RenderContext($columns, $rows, null, $this->fontRegistry), $inline[$index])->toArray() ?: ['']))) : $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows));
            }
        }
        // Then the items spanning auto columns only, the fewest columns first: what they need past those columns is
        // shared among them, like CSS; not those spanning a column of a size or a fraction, which takes it
        $spanning = array_filter($placed, static fn (array $item) => $item[4] > 1);
        uasort($spanning, static fn (array $a, array $b) => $a[4] <=> $b[4]);
        foreach ($spanning as $index => [$child, , $column, , $span]) {
            if (isset($inline[$index]) || $child instanceof ContainerWidget && $this->isSubgrid($child)) {
                continue;
            }
            for ($c = $column; $c < $column + $span; ++$c) {
                $kind = $c >= $lead + $explicit || $c < $lead ? $template->autoColumn($c < $lead ? (($c - $lead) % max(1, \count($template->autoColumnCycle)) + max(1, \count($template->autoColumnCycle))) % max(1, \count($template->autoColumnCycle)) : $c - $lead - $explicit)[0] ?? 'auto' : (null !== $template->autoRepeat || $inheritedColumns ? null : ($template->tracks[$c - $lead][0] ?? ($template->areas ? 'auto' : null)));
                if ('auto' !== $kind) {
                    continue 2;
                }
            }
            $missing = $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows) - $columnGap * ($span - 1);
            for ($c = $column; $c < $column + $span; ++$c) {
                $missing -= $contentWidths[$c] ?? 0;
            }
            for ($i = 0; $missing > 0 && $i < $span; ++$i) {
                $share = intdiv($missing + $span - 1 - $i, $span - $i);
                $contentWidths[$column + $i] = ($contentWidths[$column + $i] ?? 0) + $share;
                $missing -= $share;
            }
        }
        // The implicit columns first, sized by grid-auto-columns or their content; the explicit ones share the room left
        $implicit = [];
        $leading = [];
        for ($column = 0; $column < $columnCount; ++$column) {
            if ($column >= $lead && $column < $lead + $explicit) {
                continue;
            }
            // Those before the explicit ones take the sizes of grid-auto-columns from its last, backwards, like CSS
            $cycle = max(1, \count($template->autoColumnCycle));
            $track = $template->autoColumn($column < $lead ? (($column - $lead) % $cycle + $cycle) % $cycle : $column - $lead - $explicit);
            $size = max('auto' === ($track[0] ?? 'auto') ? 0 : 1, match ($track[0] ?? 'auto') {
                'cells' => (int) $track[1],
                'percent' => intdiv((int) ($columns * $track[1]), 100),
                'length' => GridTemplate::minimum($track[2], $columns),
                default => max(GridTemplate::minimum($track[2] ?? 0, $columns), $contentWidths[$column] ?? 0),
            });
            if ($column < $lead) {
                $leading[] = $size;
            } else {
                $implicit[] = $size;
            }
        }
        // Only implicit columns, like grid-auto-flow: column with grid-auto-columns: 1fr: those of fr are flexible, as
        // wide as the widest content needs by their weight when sized by their content, else sharing the free room
        if (0 === $explicit && $implicit) {
            $fractions = [];
            for ($column = 0; $column < $columnCount; ++$column) {
                $track = $template->autoColumn($column);
                if ('fr' === ($track[0] ?? null) && $track[1] > 0) {
                    $fractions[$column] = (float) $track[1];
                }
            }
            if ($fractions) {
                if ($contentSized) {
                    $unit = max(array_map(static fn (int $column, float $fraction) => ($contentWidths[$column] ?? 0) / $fraction, array_keys($fractions), $fractions));
                    foreach ($fractions as $column => $fraction) {
                        $implicit[$column] = max(1, (int) ceil($unit * $fraction));
                    }
                } else {
                    $free = max(\count($fractions), $columns - $columnGap * ($columnCount - 1) - array_sum(array_diff_key($implicit, $fractions)));
                    $total = array_sum($fractions);
                    $given = 0;
                    $last = array_key_last($fractions);
                    foreach ($fractions as $column => $fraction) {
                        $implicit[$column] = max(1, $column === $last ? $free - $given : (int) floor($free * $fraction / $total));
                        $given += $implicit[$column];
                    }
                }
            }
        }
        $explicitRoom = max($explicit, $columns - array_sum($implicit) - array_sum($leading) - $columnGap * (\count($implicit) + \count($leading)));
        // The longest word of the single-column items of a track, the least width of a fraction like 1fr, measured when
        // a track asks for it
        // The single-column items of each column, gathered once, not for each track
        $byColumn = [];
        foreach ($placed as $i => [$child, , $column, , $span]) {
            if (1 === $span && !isset($inline[$i]) && !($child instanceof ContainerWidget && $this->isSubgrid($child))) {
                $byColumn[$column][] = $child;
            }
        }
        $minContentWidth = function (int $index) use ($byColumn, $columns, $rows, $lead): int {
            $width = 0;
            $saved = $this->positionTracker->suppressStack();
            foreach ($byColumn[$index + $lead] ?? [] as $child) {
                $width = max($width, $this->widgetRenderer->measureMinContentWidth($child, $columns, $rows));
            }
            $this->positionTracker->restoreStack($saved);

            return $width;
        };
        $widths = [...$leading, ...$explicit > 0 ? $template->resolve($explicitRoom, $columnGap, static fn (int $index) => $contentWidths[$index + $lead] ?? 0, $contentSized, $minContentWidth, $stretchColumns) : [], ...$implicit];
        $budget = max($columnCount, $columns - $columnGap * ($columnCount - 1));
        if (!$inheritedColumns && array_sum($widths) > $budget) {
            $widths = array_values(self::shrinkToFit($widths, $budget));
        }
        // justify-content: space-between, space-around and space-evenly share the free columns of the grid between its
        // tracks, an item spanning tracks taking the room between them (start, center and end place the grid as a whole)
        $before = array_fill(0, \count($widths), 0);
        if (null !== $justifyContent && 0 < $free = $columns - array_sum($widths) - $columnGap * (\count($widths) - 1)) {
            $shift = 0;
            foreach (self::contentSlots($justifyContent->value, \count($widths), $free) as $column => $slot) {
                if ($column < \count($widths)) {
                    $before[$column] = $shift += $slot;
                }
            }
        }
        $geometry = [];
        foreach ($placed as $index => [$child, , $column, , $span]) {
            $geometry[$index] = [array_sum(\array_slice($widths, $column, $span)) + $columnGap * ($span - 1) + ($before[$column + $span - 1] ?? 0) - ($before[$column] ?? 0), array_sum(\array_slice($widths, 0, $column)) + $columnGap * $column + ($before[$column] ?? 0)];
            // The columns a subgrid spans, for its own layout
            if ($child instanceof ContainerWidget && $this->isSubgrid($child)) {
                $this->subgridTracks ??= new \WeakMap();
                $this->subgridTracks[$child] = [array_values(\array_slice($widths, $column, $span)), $columnGap];
            }
        }
        // The rows: their template minimum, their single-row items, then the items spanning rows
        $natural = [];
        $rowSubgrids = [];
        // Row after row: the items of the rows that start past the rows drawn are not laid out, like a table of a thousand
        // rows of cells of a huge padding, which would fill memory with lines never drawn
        $byRow = $placed;
        uasort($byRow, static fn (array $a, array $b) => $a[1] <=> $b[1]);
        $skipped = [];
        $measuredRow = 0;
        $rowsAbove = 0;
        $gridWidth = array_sum($widths) + $columnGap * (\count($widths) - 1) + ($before[\count($widths) - 1] ?? 0);
        foreach ($byRow as $index => [$child, $row, , $rowSpan]) {
            for (; $measuredRow < $row; ++$measuredRow) {
                $rowsAbove += $heights[$measuredRow] + $rowGap;
            }
            // Nor past the columns drawn, like the items of huge gaps, from the right in a grid written from the right
            $drawnAt = $mirrored ? max(0, $gridWidth - $geometry[$index][1] - $geometry[$index][0]) : $geometry[$index][1];
            if (null !== $maxRows && $rowsAbove > $maxRows || $drawnAt >= Renderer::MAX_CLIPPED_COLUMNS) {
                $skipped[$index] = true;
                $natural[$index] = 0;
                continue;
            }
            // Measured at the width it is laid out at, no wider than the columns drawn
            $context = new RenderContext(max(1, min($geometry[$index][0], Renderer::MAX_CLIPPED_COLUMNS - $drawnAt)), $rows, null, $this->fontRegistry);
            // A subgrid of rows: laid out with the gap of this grid, the heights its rows need recorded as it does
            if ($rowSubgrid = $this->isRowSubgrid($child)) {
                $this->subgridRowTracks ??= new \WeakMap();
                $this->subgridRowHeights ??= new \WeakMap();
                $this->subgridRowTracks[$child] = [[], $rowGap, $this->subgridRowTracks[$child][2] ?? [], $geometry[$index][0], $rowSpan];
            }
            $natural[$index] = \count(isset($inline[$index]) ? $this->widgetRenderer->renderStretchedLines($child, $context, $inline[$index]) : $this->widgetRenderer->renderLines($child, $context));
            if ($rowSubgrid) {
                // Drawn from the cache of another size or revision than it recorded them for: laid out again
                $recorded = $this->subgridRowHeights[$child] ?? null;
                if ([$child->getRenderRevision(), $geometry[$index][0]] !== [$recorded[0] ?? null, $recorded[1] ?? null]) {
                    $child->clearRenderCache();
                    $natural[$index] = \count($this->widgetRenderer->renderLines($child, $context));
                    $recorded = $this->subgridRowHeights[$child] ?? null;
                }
                // Each row it spans as tall as its row needs; with more rows than it spans, a grid of its own
                if (null !== $recorded && \count($recorded[2]) <= $rowSpan) {
                    $rowSubgrids[$index] = true;
                    foreach ($recorded[2] as $offset => $height) {
                        $heights[$row + $offset] = max($heights[$row + $offset], $height);
                    }
                }
            }
            if (1 === $rowSpan) {
                $heights[$row] = max($heights[$row], $natural[$index]);
            }
        }
        foreach ($placed as $index => [, $row, , $rowSpan]) {
            $last = $row + $rowSpan - 1;
            $missing = $natural[$index] - (array_sum(\array_slice($heights, $row, $rowSpan)) + $rowGap * ($rowSpan - 1));
            if ($rowSpan > 1 && $missing > 0) {
                $heights[$last] += $missing;
            }
        }
        $this->positionTracker->restoreStack($savedStack);
        // A subgrid of rows: the heights its rows need recorded for its parent, each as tall as the row of its parent
        if (null !== $rowTracks) {
            $box = $children[0]->getParent();
            // Its padding and border above its first row and below its last, in the rows of its parent, like CSS
            $boxStyle = StyleBridge::resolve($this->widgetRenderer, $box);
            $above = ($boxStyle->getPadding()->top ?? 0) + ($boxStyle->getBorder()->top ?? 0);
            $below = ($boxStyle->getPadding()->bottom ?? 0) + ($boxStyle->getBorder()->bottom ?? 0);
            // Every row it spans, those its items leave empty too: its bottom below the last of them, like CSS
            $span = max(\count($heights), $rowTracks[4] ?? 0);
            foreach ($rowTracks[0] && \count($heights) <= \count($rowTracks[0]) ? range(0, $span - 1) : [] as $row) {
                $heights[$row] ??= 0;
            }
            $last = array_key_last($heights);
            // While its parent measures it, before it gives it the heights of its rows
            if (!$rowTracks[0]) {
                $needed = array_pad(array_values($heights), $span, 0);
                if ([] !== $needed) {
                    $needed[0] += $above;
                    $needed[\count($needed) - 1] += $below;
                }
                $this->subgridRowHeights[$box] = [$box->getRenderRevision(), $rowTracks[3], $needed];
            }
            foreach (\count($heights) <= \count($rowTracks[0]) ? $rowTracks[0] : [] as $row => $height) {
                if (isset($heights[$row])) {
                    $heights[$row] = max($heights[$row], $height - (0 === $row ? $above : 0) - ($row === $last ? $below : 0));
                }
            }
        }
        $tops = [];
        $top = 0;
        foreach ($heights as $row => $height) {
            $tops[$row] = $top;
            $top += $height + $rowGap;
        }
        $total = max(0, $top - $rowGap);
        // fr rows: the free rows of a set height, shared as their fractions say, before align-content
        $fractions = array_filter(array_intersect_key($template->rowFractions, $heights), static fn (float $fraction) => $fraction > 0);
        if (null !== $contentRows && $contentRows > $total && $fractions) {
            // Like CSS, the rows their content makes taller than their share keep their height, the others sharing what
            // is left: as tall as their fractions say, their content within it
            $space = $contentRows - ($total - array_sum(array_intersect_key($heights, $fractions)));
            do {
                $sum = array_sum($fractions);
                $inflexible = array_filter($fractions, static fn (float $fraction, int $row) => $heights[$row] > $space * $fraction / $sum, \ARRAY_FILTER_USE_BOTH);
                $space -= array_sum(array_intersect_key($heights, $inflexible));
                $fractions = array_diff_key($fractions, $inflexible);
            } while ($inflexible && $fractions);
            $given = 0;
            $last = array_key_last($fractions);
            foreach ($fractions as $row => $fraction) {
                $share = $row === $last ? $space - $given : (int) floor($space * $fraction / $sum);
                $heights[$row] = $share;
                $given += $share;
            }
            $top = 0;
            foreach ($heights as $row => $height) {
                $tops[$row] = $top;
                $top += $height + $rowGap;
            }
            $total = max(0, $top - $rowGap);
        }
        // align-content: the free rows of a set height shared between the rows, or by the auto rows when stretched, like
        // normal, its default in a grid
        $alignContent ??= 'stretch';
        if (null !== $contentRows && $contentRows > $total && $heights) {
            $free = $contentRows - $total;
            if ('stretch' === $alignContent) {
                $auto = array_keys(array_filter($heights, static fn (int $height, int $row) => null === ($template->rows[$row] ?? null), \ARRAY_FILTER_USE_BOTH)) ?: array_keys($heights);
                foreach ($auto as $i => $row) {
                    $heights[$row] += intdiv($free, \count($auto)) + ($i < $free % \count($auto) ? 1 : 0);
                }
                $top = 0;
                foreach ($heights as $row => $height) {
                    $tops[$row] = $top;
                    $top += $height + $rowGap;
                }
            } else {
                $slots = self::contentSlots($alignContent, \count($heights), $free);
                $shift = 0;
                foreach (array_keys($heights) as $i => $row) {
                    $shift += $slots[$i];
                    $tops[$row] += $shift;
                }
            }
            $total = $contentRows;
        }
        // The subgrids of rows given the heights of the rows they span, laid out again when they changed
        foreach ($rowSubgrids as $index => $_) {
            [$child, $row, , $rowSpan] = $placed[$index];
            $given = array_values(\array_slice($heights, $row, $rowSpan));
            if ($given !== $this->subgridRowTracks[$child][2]) {
                $child->clearRenderCache();
            }
            $this->subgridRowTracks[$child] = [$given, $rowGap, $given, $geometry[$index][0], $rowSpan];
        }
        // Each item at its area, tracked, stretched to its height unless aligned
        $hasPositionStack = $this->positionTracker->isActive();
        [$absRow, $absCol] = $hasPositionStack ? $this->positionTracker->currentOffset() : [0, 0];
        $stretch = null === $verticalAlign || VerticalAlign::Stretch === $verticalAlign;
        $items = [];
        // Right to left, the first column is on the right
        foreach ($placed as $index => [$child, $row, , $rowSpan]) {
            // Not drawn: where it and what it holds were forgotten
            if (isset($skipped[$index])) {
                if ($hasPositionStack) {
                    $this->positionTracker->forget($child);
                }
                continue;
            }
            [$width, $offset] = $geometry[$index];
            if ($mirrored) {
                $offset = max(0, $gridWidth - $offset - $width);
            }
            // justify-self: an item narrower than its area, at its start, center or end; right to left, the start is on the right
            $justify = $this->widgetRenderer->resolveComputedStyle($child)->getJustifySelf() ?? $justifyItems ?? 'stretch';
            if ('stretch' !== $justify) {
                $saved = $this->positionTracker->suppressStack();
                $content = min($width, $this->widgetRenderer->measureIntrinsicWidth($child, $width, $rows));
                $this->positionTracker->restoreStack($saved);
                $free = $width - $content;
                $offset += match ($mirrored ? ['start' => 'end', 'end' => 'start'][$justify] ?? $justify : $justify) {
                    'center' => intdiv($free, 2),
                    'end', 'right' => $free,
                    default => 0,
                };
                $width = $content;
            }
            $areaHeight = array_sum(\array_slice($heights, $row, $rowSpan)) + $rowGap * ($rowSpan - 1);
            $align = $this->widgetRenderer->resolveComputedStyle($child)->getAlignSelf() ?? ($stretch ? VerticalAlign::Stretch : $verticalAlign);
            // Not past the columns drawn, like the items of huge gaps: not laid out, their rows not padded out to them,
            // where they were drawn no more
            if ($offset >= Renderer::MAX_CLIPPED_COLUMNS) {
                if ($hasPositionStack) {
                    $this->positionTracker->forget($child);
                }
                continue;
            }
            // Laid out no wider than the columns drawn, not in a box of a million columns
            $width = min($width, Renderer::MAX_CLIPPED_COLUMNS - $offset);
            $lines = $this->renderGridItem($child, $width, $rows, $inline[$index] ?? (VerticalAlign::Stretch === $align && $natural[$index] < $areaHeight && !$this->setsHeight($child) ? $areaHeight : null), $absRow + $tops[$row], $absCol + $offset, $hasPositionStack);
            // Written from the right, text narrower than its area is at its right, where its lines start
            if ('stretch' === $justify && \in_array($this->widgetRenderer->resolveComputedStyle($child)->getWritingMode(), ['vertical-rl', 'sideways-rl'], true) && ($drawn = max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines->toArray() ?: ['']))) < $width) {
                // Its tracked box moved with it, for the pointer
                if ($hasPositionStack) {
                    $this->positionTracker->shiftContentPositions([$child], $width - $drawn, 0);
                }
                $offset += $width - $drawn;
                $width = $drawn;
            }
            $shift = match ($align) {
                VerticalAlign::Center => max(0, $areaHeight - \count($lines) >> 1),
                VerticalAlign::Bottom => max(0, $areaHeight - \count($lines)),
                default => 0,
            };
            // Its tracked box, and those of what it holds, moved down with it
            if ($hasPositionStack) {
                $this->positionTracker->shiftContentPositions([$child], 0, $shift);
            }
            // The order it is painted in over the items it overlaps: by its z-index, a grid item taking one positioned
            // or not, then in the order of the document
            $childStyle = StyleBridge::resolve($this->widgetRenderer, $child);
            $items[] = [$lines, $tops[$row] + $shift, $offset, $width, [$childStyle->getPosition()->zIndex ?? $childStyle->getZIndex() ?? 0, $index]];
        }
        usort($items, static fn (array $a, array $b) => $a[2] <=> $b[2]);
        // The items drawn on each row, left to right: each row goes through those it crosses, not through all of them,
        // found by sweeping down from where each starts, not listed for each row, which grids of items thousands of
        // rows tall would fill memory with
        $starts = [];
        foreach ($items as $i => [, $itemTop]) {
            $starts[max(0, $itemTop)][] = $i;
        }
        $active = [];
        $output = [];
        $padTo = min($gridWidth, Renderer::MAX_CLIPPED_COLUMNS);
        // The widest row: each is as wide as its items, padded to their widths
        $widest = 0;
        // No more rows than a widget draws, like rows of items of huge margins: the rows past them are not drawn; all of
        // those of a grid that scrolls, seen through its window
        for ($y = 0, $rowsDrawn = null !== $maxRows ? min($total, $maxRows) : $total; $y < $rowsDrawn; ++$y) {
            if (isset($starts[$y])) {
                foreach ($starts[$y] as $i) {
                    $active[$i] = $items[$i][1] + \count($items[$i][0]);
                }
                ksort($active);
            }
            $text = '';
            $used = 0;
            $parts = [];
            $overlap = false;
            foreach ($active as $i => $end) {
                if ($y >= $end) {
                    unset($active[$i]);
                    continue;
                }
                [$lines, $itemTop, $offset, $width, $order] = $items[$i];
                $at = $y - $itemTop;
                $part = $lines->getLine($at);
                $partWidth = AnsiUtils::visibleWidth($part);
                if ($partWidth > $width) {
                    // Cut, padded back where a wide character did not fit at its end
                    $part = AnsiUtils::truncateToWidth($part, $width, '');
                    $partWidth = AnsiUtils::visibleWidth($part);
                }
                $part .= str_repeat(' ', max(0, $width - $partWidth));
                $parts[] = [$order, $offset, $part];
                $overlap = $overlap || $offset < $used;
                if (!$overlap) {
                    $text .= str_repeat(' ', $offset - $used).$part;
                }
                $used = max($used, $offset + $width);
            }
            // Items over each other in the same area, like the boxes of an example of Tailwind over its pattern: each
            // painted in its order over those before it, its blank cells letting them through, a blank row of it without a
            // background drawing nothing
            if ($overlap) {
                usort($parts, static fn (array $a, array $b) => $a[0] <=> $b[0]);
                $layers = [new Layer([''], 0, 0, false, $used, 1)];
                foreach ($parts as [, $offset, $part]) {
                    if (!self::blankWithoutBackground($part)) {
                        $layers[] = new Layer([$part], 0, $offset, true);
                    }
                }
                $text = Compositor::composite(...$layers)[0];
            }
            // As wide as its tracks, the room its items leave in them too: placed as a whole, like by justify-content, it
            // keeps where they are in them, like justify-self
            if ($used < $padTo) {
                $text .= str_repeat(' ', $padTo - $used);
                $used = $padTo;
            }
            $output[] = $text;
            $widest = max($widest, $used);
        }

        return new ArrayLineBuffer($output, $widest);
    }

    /**
     * Lays a block out with no float of the block around in it: no exclusion, no insets nor clearance, kept from a
     * layout beside floats until they change, see layoutFloats(); whether its own floats may go on past it.
     *
     * @internal
     */
    public static function clearFloatsAround(AbstractWidget $child, bool $floatsPast): void
    {
        // Clear of floats already, like a block laid out again: nothing a later layout drops, the one now not marked as
        // the one that set it, see dropStaleFloats()
        if ($child instanceof ContainerWidget) {
            if (!$floatsPast && ContainerState::of($child)->isClearOfFloats()) {
                return;
            }
            ContainerState::of($child)->setFloatExclusion([]);
            ContainerState::of($child)->setFloatsPast($floatsPast);
        } elseif ($child instanceof TextWidget) {
            if ($child->isClearOfFloats()) {
                return;
            }
            $child->setLineInsets([]);
            $child->setClearance(0);
        }
    }

    /**
     * Lays the children of a block container out around its floats, like CSS float: a floated child is at the left
     * or right, at its width or its content's; a block that starts beside floats is laid out in the room between
     * them, the rows it takes past them included; clear puts a block below them.
     *
     * @param AbstractWidget[]                                                                                                             $children
     * @param list<array{string, int, int, int, 4?: list<int>|null, 5?: int}>                                                              $exclusion      The floats of a block around, drawn by it: side, first row, rows, width, the columns its shape takes at each of its rows, and its columns from the side
     * @param list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface, int, array{int, int, int, int}|null}>|null $escaped        When not null, the block is as tall as its content, not as its floats, and gets the floats going on past it, with their lines, see ContainerWidget::setEscapedFloats()
     * @param int                                                                                                                          $collapsedAbove The rows at the top of the block of the margins collapsed above it, see ContainerWidget::setFloatExclusion()
     */
    public function layoutFloats(array $children, int $columns, int $rows, int $gap, array $exclusion = [], bool $collapseMargins = false, ?array &$escaped = null, int $collapsedAbove = 0, bool $adjoining = false): LineBufferInterface
    {
        // The floats before the first block in the flow of a block whose top margin collapses with it: beside it, under
        // that margin, like CSS, its rows above them dropped with those of the block, see Renderer::collapseParentMargins()
        $leading = $collapseMargins && $adjoining && null !== ($start = $this->flowStart($children)) && $start !== reset($children) ? $this->collapsedMargin($start, true, $columns) : 0;
        $tracked = $this->positionTracker->isActive();
        [$absRow, $absCol] = $tracked ? $this->positionTracker->currentOffset() : [0, 0];
        // Each piece drawn: its lines, row, column, width, whether text flows in it, and the shape of a float; the floats
        // as well: side, first row, rows, width, the columns its shape takes at each of its rows, and its columns from the side
        $pieces = [];
        $floats = array_map(
            /** @return array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface|null, int|null, array{int, int, int, int}|null} */
            static fn (array $float): array => [$float[0], $float[1], $float[2], $float[3], $float[4] ?? null, $float[5] ?? 0, $float[6] ?? false, $float[7] ?? null, $float[8] ?? null, $float[9] ?? null],
            $exclusion
        );
        $y = 0;
        // The top of the last float placed, which the next one may not be above
        $floatTop = 0;
        // The last child in the flow and its piece: a float after it, out of the flow, placed under its margin, which
        // collapses with the one of the next block all the same, like CSS
        $previous = null;
        $marginOwner = null;
        $adjoiningMargin = 0;
        $marginPieces = [];
        // The pieces of the blocks pulled up over those before them by negative margins: their rows drawn over the others,
        // see pulledPiece(); the negative margins collapsed after the last block, the most negative, and the rows the
        // empty blocks through which they collapse with the next one were pulled by, see layoutVertical(); the bottom of
        // the rows drawn before the blocks pulled
        $pulled = [];
        $pullGroup = [0, 0];
        $previousEmpty = false;
        $overhang = 0;
        // The bottom of the floats of the blocks laid out that go on past them
        $escapedBottom = 0;
        // The floats not ended above the last block in the flow, those its rows and the ones after it may be beside, the
        // margin it collapses with taken back at most: those looked at, not all those placed, like thousands of icons
        $live = [];
        $seen = 0;
        foreach ($children as $index => $child) {
            $floor = null !== $previous ? $pieces[$previous[1]]->row : 0;
            $live = array_filter([...$live, ...\array_slice($floats, $seen)], static fn (array $float): bool => $float[1] + $float[2] >= $floor);
            $seen = \count($floats);
            $style = StyleBridge::resolve($this->widgetRenderer, $child);
            if ($index > 0 && null === $style->getFloat()) {
                $y += $gap;
            }
            $clear = $style->getClear();
            // The text of a block beside floats flows around them, see below; a block holding blocks passes them on to
            // what it holds
            $container = $child instanceof ContainerWidget && !ContainerState::of($child)->isInlineFlow() && null === $style->getTableRole() && null === $style->getGridTemplate() && Direction::Horizontal !== $style->getDirection() && null === $style->getScroll();
            // A ::before that clears, see Style::withBeforeClear(): its content below the floats, its box where it is, see
            // below; or the box below them, its content with it
            $beforeClear = self::beforeClear($child, $style, $container);
            if (null !== $beforeClear && !$beforeClear[1]) {
                $clear = null === $clear || $beforeClear[0] === $clear ? $beforeClear[0] : 'both';
            }
            // A block that clears floats: its border under them, like CSS, the rows of its top margin above it
            $floated = null !== $style->getFloat();
            $clearedTo = null !== $clear ? self::floatsBottom($live, 'both' === $clear ? null : $clear) : 0;
            // The rows of the margins collapsed above it, from the previous block or the block around, which its ::before
            // is under, like CSS, not those of its first child
            $lead = $collapseMargins && null === $previous && 0 === $y && !$floated ? $collapsedAbove : 0;
            // A block whose ::before clears floats beside it: its own margin, see collapsedMargin()
            $cleared = null !== $beforeClear && $beforeClear[1] && self::floatsBottom($live, 'both' === $beforeClear[0] ? null : $beforeClear[0]) > $y + $lead;
            if ($lead > 0) {
                $lead = min($lead, $this->collapsedMargin($child, true, $columns, 0, $cleared));
            }
            $marginAbove = null !== $clear && !$floated ? $this->collapsedMargin($child, true, $columns, 0, $cleared) : 0;
            // Block layout, like CSS: the margins of two blocks one after the other collapse, beside floats or under them,
            // a cleared block's only as far as its border stays under the floats it clears
            if ($collapseMargins && null !== $previous && !$floated) {
                [$before, $at] = $previous;
                $lines = $pieces[$at]->lines;
                if ($marginOwner !== $before) {
                    $bottom = $this->collapsedMargin($before, false, $columns);
                    $beforeStyle = StyleBridge::resolve($this->widgetRenderer, $before);
                    $empty = $before instanceof ContainerWidget && null === $beforeStyle->getDirection() && null === $beforeStyle->getGridTemplate() && null === $beforeStyle->getScroll() && \count($lines) + $pieces[$at]->over === max($bottom, $beforeStyle->withResolvedBox($columns)->getMargin()->top ?? 0);
                    $adjoiningMargin = $empty ? max($adjoiningMargin, $bottom) : $bottom;
                    $previousEmpty = $empty;
                    $marginOwner = $before;
                    if (!$empty) {
                        $marginPieces = [];
                    }
                    $marginPieces[] = $at;
                }
                $collapsed = min($adjoiningMargin, $this->collapsedMargin($child, true, $columns, 0, $cleared));
                if (null !== $clear && $clearedTo > $y - $collapsed + $marginAbove) {
                    $collapsed = max(0, min($collapsed, $y + $marginAbove - $clearedTo));
                }
                if ($collapsed > 0 && $pieces[$at]->row + \count($lines) === $y) {
                    $remaining = $collapsed;
                    while ($remaining > 0 && $marginPieces) {
                        $piece = $marginPieces[array_key_last($marginPieces)];
                        $lines = $pieces[$piece]->lines;
                        if ($pieces[$piece]->row + \count($lines) !== $y) {
                            break;
                        }
                        $taken = min($remaining, \count($lines));
                        $pieces[$piece]->lines = new ArrayLineBuffer(\array_slice($lines->toArray(), 0, \count($lines) - $taken));
                        $y -= $taken;
                        $remaining -= $taken;
                        if ($taken === \count($lines)) {
                            array_pop($marginPieces);
                        }
                    }
                    // The rest in the rows of a block pulled up over those before it, which stay: this one pulled over them
                    if ($remaining > 0 && $pieces[$at]->over > 0) {
                        $overhang = max($overhang, $y);
                        $y -= min($y, $remaining);
                        $remaining = 0;
                    }
                    $lead = $collapsed - $remaining;
                }
            }
            // Negative margins: the block pulled up over the rows before it, like layoutVertical(); collapsed with those of
            // the empty blocks between them, the most negative of them all, less the rows those blocks were pulled by
            if (null !== $previous && !$floated) {
                [$pullAbove, $pullBelow] = $this->pullsBetween($previous[0], $child, $collapseMargins);
                $pullGroup = $collapseMargins && $previousEmpty ? $pullGroup : [0, 0];
                $most = max($pullGroup[0], $pullAbove, $pullBelow);
                // An empty block of no positive margin, drawn as nothing: its margins collapse through it with those of
                // the next block, which is pulled for them all, the positive ones collapsed first
                if ($collapseMargins && 0 === ($style->getMargin()->top ?? 0) + ($style->getMargin()->bottom ?? 0) && $this->collapsesThrough($child)) {
                    $pullGroup = [max($most, $this->collapsedPull($child, false)), $pullGroup[1]];
                } else {
                    $pullBy = max(0, min($y, $collapseMargins ? $most - $pullGroup[1] : $pullAbove + $pullBelow));
                    $pullGroup = [$most, $most];
                    if ($pullBy > 0) {
                        $overhang = max($overhang, $y);
                        $y -= $pullBy;
                    }
                }
            }
            // A float that clears goes below the floats it clears, the flow where it is, like CSS, see below
            $movedPast = false;
            if (null !== $clear && !$floated) {
                $y = max($y, $clearedTo - $marginAbove);
                // The floats it clears ending beside the rows of its margin, moved down or not: its border under them,
                // those floats no more beside it nor what follows, drawn over its margin rows, see below
                if ($marginAbove > 0 && $clearedTo > $y && $clearedTo <= $y + $marginAbove) {
                    $movedPast = true;
                    $live = array_filter($live, static fn (array $float): bool => !('both' === $clear || $clear === $float[0]) || $float[1] + $float[2] > $clearedTo);
                }
            }
            // Past the rows drawn: not laid out, like hundreds of floats of a huge height, each painted for nothing
            if ($y > Renderer::MAX_LINES) {
                break;
            }
            // Floats and boxes beside floats are put by their boxes, only lines wrap around their shapes; a cleared block
            // by its border, under the floats it cleared
            $left = self::floatsWidth($live, 'left', $movedPast ? $y + $marginAbove : $y, false);
            $right = self::floatsWidth($live, 'right', $movedPast ? $y + $marginAbove : $y, false);
            $room = max(1, $columns - $left - $right);
            if (null !== $side = $style->getFloat()) {
                self::clearFloatsAround($child, false);
                // At its width, a percentage of the block it is in, like the columns of float: left; width: 70% and 25%
                // side by side, or its content's, beside the floats already on that side
                $percent = null === $style->getMaxColumns() && null !== $style->getWidthPercent() ? (int) $style->resolveWidthPercent($columns) + ($style->getMargin()->left ?? 0) + ($style->getMargin()->right ?? 0) : null;
                $saved = $this->positionTracker->suppressStack();
                // A width of its own, its margins beside it, like those of a percentage; a max-width alone, as wide as its
                // content up to it, like CSS: the width set gives its flex basis, see DeclarationMapper
                $wanted = $percent;
                if (null === $wanted && null !== $max = $style->getMaxColumns()) {
                    $max += max(0, $style->withResolvedBox($columns)->getMargin()->left ?? 0) + max(0, $style->withResolvedBox($columns)->getMargin()->right ?? 0);
                    // Shrink-to-fit, like CSS: its content unwrapped and its margins, up to the max-width and its margins, the
                    // lines of content wider than it wrapped in all of it
                    if (null === $style->getFlexBasis()) {
                        $max = min($max, $this->widgetRenderer->measureMaxContentWidth($child, $columns, $rows));
                    }
                    $wanted = $max;
                }
                $wanted ??= $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows);
                $this->positionTracker->restoreStack($saved);
                // Too wide for the room beside the floats of its row, like the next item of a menu of floats: below the
                // first of them to end, like CSS, not squeezed beside them
                // Not above a float before it, like CSS: the next item of a menu after one moved down goes beside it
                $top = max($y, $floatTop, null !== $clear ? $clearedTo : 0, null === $previous ? $leading : 0);
                if ($top > $y) {
                    $left = self::floatsWidth($live, 'left', $top, false);
                    $right = self::floatsWidth($live, 'right', $top, false);
                    $room = max(1, $columns - $left - $right);
                }
                // Not one of a percentage too wide only by its columns rounded up, the column a terminal leaves after
                // each float, and its borders of a pixel made a column, which fits beside the others in a browser, like
                // the six tabs of 16.6% of python.org: squeezed; the widgets of python.org of 49% two by two, below; nor
                // one too wide by the column only a terminal leaves between floats
                while ($wanted > $room + (null === $percent ? min(1, self::floatsAt($live, $top)) : self::floatsAt($live, $top, true) + 1) && null !== $below = self::nextFloatEnd($live, $top)) {
                    $top = $below;
                    $left = self::floatsWidth($live, 'left', $top, false);
                    $right = self::floatsWidth($live, 'right', $top, false);
                    $room = max(1, $columns - $left - $right);
                }
                // A percentage after one on its side, like the tabs of 16.6% of python.org: against it, like CSS, no
                // column between them
                if (null !== $percent) {
                    foreach (array_reverse($live) as $float) {
                        if ($side === $float[0] && $float[1] <= $top && $top < $float[1] + $float[2]) {
                            if (($float[6] ?? false) && ($float[5] ?? 0) + $float[3] + 1 === ('left' === $side ? $left : $right)) {
                                'left' === $side ? --$left : --$right;
                                $room = max(1, $columns - $left - $right);
                            }
                            break;
                        }
                    }
                }
                if ($top > Renderer::MAX_LINES) {
                    // The next one not above it, like CSS
                    $floatTop = $top;
                    continue;
                }
                $width = max(1, min($room, $wanted));
                $x = 'left' === $side ? $left : $columns - $right - $width;
                // A right float after the text of its line, like the badge of <li>Inbox <span class="float-end">14</span>:
                // on the last line of that text when there is room beside it, like CSS, not below it
                $last = array_key_last($pieces);
                // Its last line of text, not the margin, padding or border under it, like those of a paragraph
                $before = $index > 0 ? StyleBridge::resolve($this->widgetRenderer, $children[$index - 1]) : null;
                $bare = null !== $before && 0 === ($before->getMargin()->bottom ?? 0) && 0 === ($before->getPadding()->bottom ?? 0) && 0 === ($before->getBorder()->bottom ?? 0) && !$this->widgetRenderer->isElementBox($children[$index - 1]);
                if ('right' === $side && $top === $y && null !== $last && $y > 0 && $bare && $children[$index - 1] instanceof TextWidget && !$pieces[$last]->flow && \count($pieces[$last]->lines) > 0 && $pieces[$last]->row + \count($pieces[$last]->lines) === $y && $x >= $pieces[$last]->column && $x > $pieces[$last]->column + AnsiUtils::visibleWidth(rtrim(AnsiUtils::stripAnsiCodes($pieces[$last]->lines->getLine(\count($pieces[$last]->lines) - 1)))) && self::floatsWidth($live, 'right', $y - 1, false) === $right) {
                    $top = $y - 1;
                    // Drawn over the room its line leaves, like the text flowing around a float
                    $pieces[$last]->flow = true;
                }
                // A left float after the text of its line, like an icon floated after a word: at the start of the last line of
                // that text when there is room on it, the line moved right beside it, like CSS, not below it
                if ('left' === $side && $top === $y && null !== $last && $y > 0 && $bare && $children[$index - 1] instanceof TextWidget && !$pieces[$last]->flow && \count($pieces[$last]->lines) > 0 && $pieces[$last]->row + \count($pieces[$last]->lines) === $y && $x === $pieces[$last]->column && self::floatsWidth($live, 'left', $y - 1, false) === $left && $width + 1 + AnsiUtils::visibleWidth(rtrim(AnsiUtils::stripAnsiCodes($lastLine = $pieces[$last]->lines->getLine(\count($pieces[$last]->lines) - 1)))) <= $pieces[$last]->columns) {
                    $top = $y - 1;
                    $moved = $pieces[$last]->lines->toArray();
                    $moved[\count($moved) - 1] = AnsiUtils::sliceToWidth(str_repeat(' ', $width + 1).$lastLine, 0, $pieces[$last]->columns);
                    $pieces[$last]->lines = new ArrayLineBuffer($moved);
                    $pieces[$last]->flow = true;
                }
                // Its percentages, of its width and of its margins, shares of the block it is in
                $lines = $this->renderGridItem($child, $width, $rows, null, $absRow + $top, $absCol + $x, $tracked, $columns);
                $profile = null;
                if (null !== $shape = $style->getFloatShape()) {
                    $edges = static fn (?object $box): array => null === $box ? [0, 0, 0, 0] : [$box->top, $box->right, $box->bottom, $box->left];
                    $profile = $shape->profile($width, max(1, \count($lines)), $side, $edges($style->getMargin()), $edges($style->getBorder()), $edges($style->getPadding()), $style->getShapeMargin() ?? 0.0, $style->getShapeImageThreshold() ?? 0.0);
                }
                $pieces[] = $piece = new FlowPiece($lines, $top, $x, $width, false, null === $profile ? null : [$side, $profile]);
                // A replaced element, like an image, paints its box, like CSS: what flows under it shows nowhere, on all
                // its rows; a float drawing one too, its rows printed under it seen in the image else, like the text
                // alternative of an image, which stands for it, and a frame, decided once for all its rows. Over its
                // whole box: where in it the element is drawn is known to the tracker of positions only, which tracks
                // them only for the pointer, and the lines drawn tell nothing of it. Another float holding an image
                // drawn by the terminal, see floatOver(), hides what is under it only on the row the image starts on
                if ($this->drawsReplacedElement($child)) {
                    $margin = $style->withResolvedBox($columns)->getMargin();
                    $piece->opaqueBox = [max(0, $margin->top ?? 0), max(0, $margin->right ?? 0), max(0, $margin->bottom ?? 0), max(0, $margin->left ?? 0)];
                }
                // A percentage, the next one against it; its lines, for those going on past the block
                $floats[] = [$side, $top, max(1, \count($lines)), $width, $profile, 'left' === $side ? $left : $right, null !== $percent, $lines, $top, $piece->opaqueBox];
                $floatTop = $top;
                continue;
            }
            // The text of a block beside floats flows around them: under them once they end, like CSS; a block holding
            // blocks passes them on to what it holds, the text of its paragraphs flowing around them too; not a formatting
            // context of its own, like overflow: hidden, beside them, see below
            if (($child instanceof TextWidget || $child instanceof ContainerWidget && $container) && self::floatsBottom($live, null) > $y && !$this->widgetRenderer->establishesFormattingContext($child) && array_any($live, static fn (array $float) => $float[1] + $float[2] > $y)) {
                $chromeTop = ($style->getMargin()->top ?? 0) + ($style->getBorder()->top ?? 0) + ($style->getPadding()->top ?? 0);
                $chromeLeft = ($style->getMargin()->left ?? 0) + ($style->getBorder()->left ?? 0) + ($style->getPadding()->left ?? 0);
                $chromeRight = ($style->getMargin()->right ?? 0) + ($style->getBorder()->right ?? 0) + ($style->getPadding()->right ?? 0);
                // A ::before that clears, an empty block as tall as the rows of the floats it clears beside its content,
                // like CSS: its content under them, among the floats left
                // Its ::before under the margins collapsed above it, its own included, not that of its first child, like CSS:
                // none when that is past the floats
                // No more than the rows drawn
                $edge = ($style->getBorder()->top ?? 0) + ($style->getPadding()->top ?? 0);
                $above = max($chromeTop - $edge, $lead);
                $clearance = null !== $beforeClear ? min(Renderer::MAX_LINES, max(0, self::floatsBottom($live, 'both' === $beforeClear[0] ? null : $beforeClear[0]) - $y - $above - $edge)) : 0;
                $contentTop = $y + $chromeTop + $clearance;
                if ($child instanceof TextWidget) {
                    $insets = [];
                    for ($row = $contentTop, $bottom = self::floatsBottom($live, null); $row <= $bottom; ++$row) {
                        $insets[] = [max(0, self::floatsWidth($live, 'left', $row) - $chromeLeft), max(0, self::floatsWidth($live, 'right', $row) - $chromeRight)];
                    }
                    // None left beside its lines: aligned in its width, like a block under the floats
                    // Kept until they change, so that its render stays cached while the floats stay
                    $child->setLineInsets(array_any($insets, static fn (array $inset) => [0, 0] !== $inset) ? $insets : []);
                    $child->setClearance($clearance);
                } else {
                    // The floats in its content box; the gap beside them counted once, by it
                    $inner = [];
                    foreach ($live as $float) {
                        [$floatSide, $floatRow, $floatRows, $floatWidth] = $float;
                        $sideChrome = 'left' === $floatSide ? $chromeLeft : $chromeRight;
                        if ($floatRow + $floatRows > $contentTop && $floatWidth > $sideChrome) {
                            // Its far edge moved in by the chrome once, measured from the side of the content box
                            $inner[] = [$floatSide, $floatRow - $contentTop, $floatRows, $floatWidth, $float[4] ?? null, ($float[5] ?? 0) - $sideChrome];
                        }
                    }
                    // Kept until it changes, so that its render stays cached while the floats stay
                    // The margins collapsed above it through its top, which the ::before of its first child is under
                    ContainerState::of($child)->setFloatExclusion($inner, $clearance, 0 === $clearance && 0 === $edge ? $above - $chromeTop : 0);
                    ContainerState::of($child)->setFloatsPast(true);
                }
                $lines = $this->renderGridItem($child, $columns, $rows, null, $absRow + $y, $absCol, $tracked);
                $pieces[] = $this->pulledPiece(new FlowPiece($lines, $y, 0, $columns, true, null), $overhang - $y, $child, $pulled);
                $previous = [$child, array_key_last($pieces)];
                $escapedBottom = max($escapedBottom, $this->escapeFloats($child, $style, $y, 0, $columns, $columns, $pieces, $floats));
                $y += \count($lines);
                continue;
            }
            self::clearFloatsAround($child, true);
            // A rule beside floats, a block box of no formatting context of its own: its whole width, under them, like
            // CSS draws its border; one of its own, like overflow: hidden, beside them
            if ($child instanceof HorizontalRuleWidget && ($left > 0 || $right > 0) && !$this->widgetRenderer->establishesFormattingContext($child)) {
                $lines = $this->renderGridItem($child, $columns, $rows, null, $absRow + $y, $absCol, $tracked);
                $pieces[] = $this->pulledPiece(new FlowPiece($lines, $y, 0, $columns, true, null), $overhang - $y, $child, $pulled);
                $previous = [$child, array_key_last($pieces)];
                $y += \count($lines);
                continue;
            }
            $lines = $this->renderGridItem($child, $room, $rows, null, $absRow + $y, $absCol + $left, $tracked);
            // A cleared block: the floats over the rows of its margin beside them, see above
            $pieces[] = $this->pulledPiece(new FlowPiece($lines, $y, $left, $room, $movedPast && 0 === $left, null), $overhang - $y, $child, $pulled);
            $previous = [$child, array_key_last($pieces)];
            $escapedBottom = max($escapedBottom, $this->escapeFloats($child, $style, $y, $left, $room, $columns, $pieces, $floats));
            $y += \count($lines);
        }
        // As tall as its own floats, not those of the block around it; or as its content, its floats going on past it
        $own = \array_slice($floats, \count($exclusion));
        // The rows drawn before a block pulled up over them and shorter than them: as tall, like layoutVertical()
        $y = max($y, $overhang);
        $total = null === $escaped ? max($y, self::floatsBottom($own, null)) : $y;
        // The margin under the last block, which may collapse with the next block or the one around: under the floats
        // going on past that block, not over them, as when the block held them
        if (null === $escaped && $collapseMargins && null !== $previous && $escapedBottom > $y - $margin = $this->collapsedMargin($previous[0], false, $columns)) {
            $total = max($total, $escapedBottom + $margin);
        }
        if (null !== $escaped) {
            foreach ($own as $float) {
                [$side, $top, $height, $width] = $float;
                // All their lines, drawn again over the same rows by the block around: the rows of its margin under
                // them may collapse with the next block
                if ($top + $height > $y && isset($float[7], $float[8])) {
                    $escaped[] = [$side, $top, $height, $width, $float[4] ?? null, $float[5] ?? 0, $float[6] ?? false, $float[7], $float[8], $float[9]];
                }
            }
        }
        // Drawn row by row, the pieces of a row from the left
        usort($pieces, static fn (FlowPiece $a, FlowPiece $b) => $a->column <=> $b->column);
        // By bands of rows between the tops and bottoms of the pieces, those of a band the same on each of its rows: the
        // rows of a block of many, like padding: 99999, not each looking at all of them
        $bounds = [0 => true, $total => true];
        foreach ($pieces as $piece) {
            $bounds[max(0, min($total, $piece->row))] = true;
            $bounds[max(0, min($total, $piece->row + \count($piece->lines)))] = true;
        }
        ksort($bounds);
        $bounds = array_keys($bounds);
        // A line of a piece cut as the row before it was, like those of the padding of a box: cut once
        $cuts = [];
        $cut = function (int $piece, string $line, int $width) use (&$cuts): string {
            if (($cuts[$piece][0] ?? null) !== $line || $cuts[$piece][1] !== $width) {
                $cuts[$piece] = [$line, $width, $this->cutBesideFloats($line, $width)];
            }

            return $cuts[$piece][2];
        };
        // The pieces of each band, swept down: those starting at it added, those ending at it dropped, in the order of
        // their columns
        $starts = array_keys($pieces);
        usort($starts, static fn (int $a, int $b) => $pieces[$a]->row <=> $pieces[$b]->row);
        $next = 0;
        $starting = \count($starts);
        // Each live piece with the row under it
        $live = [];
        $active = [];
        $out = [];
        for ($band = 0, $bands = \count($bounds) - 1; $band < $bands; ++$band) {
            $changed = false;
            for (; $next < $starting && $pieces[$starts[$next]]->row <= $bounds[$band]; ++$next) {
                $live[$starts[$next]] = $pieces[$starts[$next]]->row + \count($pieces[$starts[$next]]->lines);
                $changed = true;
            }
            foreach ($live as $index => $end) {
                if ($end <= $bounds[$band]) {
                    unset($live[$index]);
                    $changed = true;
                }
            }
            if ($changed) {
                $active = [];
                foreach ($live as $index => $_) {
                    $active[$index] = $pieces[$index];
                }
                ksort($active);
            }
            for ($row = $bounds[$band]; $row < $bounds[$band + 1]; ++$row) {
                // Text flowing around floats is under them: the floats are drawn over it
                $flowing = null;
                foreach ($active as $index => $piece) {
                    if ($piece->flow) {
                        $flowing = [$index, $piece->lines->getLine($row - $piece->row)];
                    }
                }
                if (null !== $flowing) {
                    $text = $cut($flowing[0], $flowing[1], $columns);
                    // Under the text that comes over them where their shape leaves room, the floats shaped
                    $under = [];
                    $over = [];
                    foreach ($active as $index => $piece) {
                        $lines = $piece->lines;
                        $top = $piece->row;
                        $x = $piece->column;
                        $width = $piece->columns;
                        $flow = $piece->flow;
                        $shape = $piece->shape;
                        if ($flow) {
                            continue;
                        }
                        $at = $row - $top;
                        $line = $cut($index, $lines->getLine($at), $width);
                        if (null === $shape) {
                            // A replaced element: its border box over what is under it, its margins letting it show
                            if (null !== $box = $piece->opaqueBox) {
                                [$marginTop, $marginRight, $marginBottom, $marginLeft] = $box;
                                $inner = $width - $marginLeft - $marginRight;
                                if ($inner > 0 && $at >= $marginTop && $at < \count($lines) - $marginBottom) {
                                    // Of no margins on its sides, its line as it is, not cut
                                    $text = self::floatOver($text, $inner === $width ? $line : AnsiUtils::sliceToWidth($line, $marginLeft, $inner), $x + $marginLeft, $inner, $columns, true);
                                    foreach ([[0, $marginLeft], [$marginLeft + $inner, $marginRight]] as [$from, $margin]) {
                                        if ($margin > 0) {
                                            $text = self::floatOver($text, AnsiUtils::sliceToWidth($line, $from, $margin), $x + $from, $margin, $columns, false);
                                        }
                                    }
                                    continue;
                                }
                            }
                            $text = self::floatOver($text, $line, $x, $width, $columns, false);
                            continue;
                        }
                        $under[] = new Layer([$line], 0, $x);
                        if (0 < $taken = min($width, $shape[1][$at] ?? $width)) {
                            $over[] = 'left' === $shape[0] ? new Layer([AnsiUtils::sliceToWidth($line, 0, $taken)], 0, $x) : new Layer([AnsiUtils::sliceToWidth($line, $width - $taken, $taken)], 0, $x + $width - $taken);
                        }
                    }
                    if ($under) {
                        $text = Compositor::composite(new Layer([''], 0, 0, false, $columns, 1), ...$under, ...[new Layer([$text], 0, 0, true)], ...$over)[0] ?? $text;
                    }
                    $out[] = $text;
                    continue;
                }
                $text = '';
                $used = 0;
                foreach ($active as $index => $piece) {
                    $lines = $piece->lines;
                    $top = $piece->row;
                    $x = $piece->column;
                    $width = $piece->columns;
                    if ($x < $used) {
                        continue;
                    }
                    $text .= str_repeat(' ', $x - $used).$cut($index, $lines->getLine($row - $top), $width);
                    $used = $x + $width;
                }
                $out[] = $text;
            }
        }
        // The rows of the blocks pulled up, over what is under them, their spaces letting it show
        foreach ($pulled as [$piece, $seeThrough]) {
            $count = min(\count($piece->lines), \count($out) - $piece->row);
            $under = \array_slice($out, $piece->row, $count);
            $prefix = str_repeat(' ', $piece->column);
            $lines = [];
            for ($i = 0; $i < $count; ++$i) {
                $lines[] = self::joinGlyphs($prefix.AnsiUtils::sliceToWidth($piece->lines->getLine($i), 0, $piece->columns), $under[$i]);
            }
            array_splice($out, $piece->row, $count, Compositor::composite(new Layer($under, 0, 0, false, $columns, $count), new Layer($lines, 0, 0, true, null, null, $seeThrough)));
        }

        return new ArrayLineBuffer($out);
    }

    /**
     * A line of a float drawn over the line of what flows under it, at its columns, like a composition of their cells:
     * its spaces that draw nothing on a space, of no background, line across nor colors swapped, showing what is under
     * them, like the border of a block beside it, which CSS draws under the float, its line boxes only shortened; all
     * of it over that when it paints its box, like an image, which the caller tells. The row split around it by
     * AnsiUtils::splitAround(), its style and link taken on after it once. Each sequence that is no cell once, in its
     * order: those before and after it where they were, its own at its columns; those under it that mark a cell, like
     * the cursor, text drawn larger or an image, at their columns where it shows what is under them, a wide character
     * of it never cut; an image drawn by the terminal where it covers it not drawn, the cells after it not drawn over;
     * the others under it after it.
     */
    private static function floatOver(string $text, string $line, int $x, int $width, int $columns, bool $opaque): string
    {
        // The sequences that are no cells, out of the row and of the float, with their columns: the rest read by AnsiUtils
        [$row, $rowSequences] = self::withoutSequences($text);
        [$float, $floatSequences] = self::withoutSequences($line);
        // Its tabs as the spaces they draw, each showing what is under it, a sequence put at any of them
        if (str_contains($float, "\t")) {
            $float = str_replace("\t", str_repeat(' ', AnsiUtils::TAB_WIDTH), $float);
        }
        // Only its columns in the row: those past either edge left out, its sequences at its edges
        if ($x < 0) {
            $float = AnsiUtils::sliceToWidth($float, -$x, $width + $x);
            $floatSequences = array_map(static fn (array $sequence): array => [max(0, $sequence[0] + $x), $sequence[1], $sequence[2]], $floatSequences);
            $width += $x;
            $x = 0;
        }
        if ($x + $width > $columns) {
            $float = AnsiUtils::sliceToWidth($float, 0, $columns - $x);
            $width = $columns - $x;
        }
        if ($width <= 0) {
            return $text;
        }
        // A row of it holding an image drawn by the terminal, like one a widget other than a replaced element draws: over
        // its columns, what is under it not printed into the image. Only the row it starts on, all of it, as the rows
        // and columns it takes are not known here: those of a replaced element are its box, see layoutFloats()
        $opaque = $opaque || array_any($floatSequences, static fn (array $sequence): bool => $sequence[2] && self::drawsAnImage($sequence[1]));
        // A wide character of the row across one of its edges, where its cell shows what is under it: whole, as a
        // composition of their cells draws it, the float a blank cell wider there
        if (!$opaque && $x > 0 && 2 <= self::glyphAt($row, $x - 1) && self::showsUnder($float, 0, $width)) {
            $float = ' '.$float;
            $floatSequences = array_map(static fn (array $sequence): array => [$sequence[0] + 1, $sequence[1], $sequence[2]], $floatSequences);
            --$x;
            ++$width;
        }
        if (!$opaque && $x + $width < $columns && 2 <= self::glyphAt($row, $x + $width - 1) && self::showsUnder($float, $width - 1, $width)) {
            $float .= ' ';
            ++$width;
        }
        // The row before and after it, the style and the link it is in taken on after it, once each
        [$before, $after] = AnsiUtils::splitAround($row, $x, $width);
        $under = AnsiUtils::sliceToWidth($row, $x, $width);
        $plain = AnsiUtils::stripAnsiCodes($float);
        // The cells under it, no more in a link: under the float, which is over it
        $cells = (string) preg_replace('/\e\]8;[^\x07\e]*(?:\x07|\e\\\\)/', '', $under);
        $drawn = match (true) {
            $opaque => $float,
            // A row of it that draws nothing, like those below its text: what is under it, as it is
            '' === trim($plain) && !self::drawsOnSpaces($float) => $cells,
            // Nothing painted under it, and nothing to show: it draws in all its columns, no wide character cut at its
            // edge, none of them hidden, or only spaces are under it
            !self::paints($under, new AnsiCodeTracker()) && ('' === trim(AnsiUtils::stripAnsiCodes($under)) || !str_contains($plain, ' ') && AnsiUtils::visibleWidth($float) === $width && !self::paints($float, new AnsiCodeTracker(), true)) => $float,
            // Its blank cells showing what is under them
            default => Compositor::composite(new Layer([$cells], 0, 0, false, $width, 1), new Layer([$float], 0, 0, true))[0] ?? $float,
        };
        // Each sequence that is no cell once, in its order: before it and after it where it was; its own where they were
        // in it; the markers of a cell under it, like the cursor or text drawn larger, at their columns where it shows
        // what is under it, else after it, like the others under it; an image starting under a cell it draws not drawn,
        // which it cannot be there: transmitted only when it has an id to be put by later, else dropped with its chunks
        $inBefore = $inDrawn = $inAfter = [];
        $passed = '';
        $chunks = false;
        foreach ($rowSequences as [$column, $sequence, $marker]) {
            if ($chunks && str_starts_with($sequence, "\x1b_G") && null === self::kittyKey($sequence, 'a')) {
                $chunks = '1' === self::kittyKey($sequence, 'm');
                continue;
            }
            $chunks = false;
            if ($column < $x) {
                $inBefore[$column][] = $sequence;
            } elseif ($column >= $x + $width) {
                $inAfter[$column - $x - $width][] = $sequence;
            } elseif ($marker && !$opaque && self::showsUnder($float, $column - $x, $width)) {
                $inDrawn[$column - $x][] = $sequence;
            } elseif ($marker && self::drawsAnImage($sequence)) {
                if ('T' === self::kittyKey($sequence, 'a') && (null !== self::kittyKey($sequence, 'i') || null !== self::kittyKey($sequence, 'I'))) {
                    $passed .= preg_replace('/^(\e_G(?:[^;\e]*,)?)a=T/', '$1a=t', $sequence);
                } else {
                    $chunks = '1' === self::kittyKey($sequence, 'm');
                }
            } else {
                $passed .= $sequence;
            }
        }
        foreach ($floatSequences as [$column, $sequence]) {
            $inDrawn[min($column, $width)][] = $sequence;
        }
        // Not in the link it is drawn over, which goes on after it
        $link = preg_match_all('/\e\]8;[^;\x07\e]*;([^\x07\e]*)/', $before, $links) && '' !== end($links[1]);

        return self::insertAt($before, $inBefore)."\x1b[0m".($link ? "\x1b]8;;\x07" : '').self::insertAt($drawn, $inDrawn)."\x1b[0m\x1b]8;;\x07".$passed.self::insertAt($after, $inAfter);
    }

    /**
     * A line without its sequences that are no cells, nor style or link, like the marker of the cursor, an image drawn by
     * the terminal or a code of the cursor shape: each with its column and whether it marks a cell, like the cursor or
     * text drawn larger, see TextMarkers.
     *
     * @return array{string, list<array{int, string, bool}>}
     */
    private static function withoutSequences(string $line): array
    {
        // None but those of style and links, the most common
        if (!str_contains($line, "\x1b") || !preg_match('/\e(?!\[[0-9;:]*m|\]8;)/', $line)) {
            return [$line, []];
        }
        $kept = '';
        $sequences = [];
        $column = 0;
        for ($i = 0, $len = \strlen($line); $i < $len;) {
            if ("\x1b" !== $line[$i]) {
                $end = strpos($line, "\x1b", $i);
                $text = false === $end ? substr($line, $i) : substr($line, $i, $end - $i);
                $kept .= $text;
                $column += AnsiUtils::visibleWidth(str_replace("\t", str_repeat(' ', AnsiUtils::TAB_WIDTH), $text));
                $i = false === $end ? $len : $end;
                continue;
            }
            if ('[' === ($line[$i + 1] ?? '') && ($j = $i + 2 + strspn($line, '0123456789:;<=>?', $i + 2)) < $len && \ord($line[$j]) >= 0x40 && \ord($line[$j]) <= 0x7E) {
                $code = substr($line, $i, $j + 1 - $i);
            } else {
                $code = AnsiUtils::extractAnsiCode($line, $i)['code'] ?? "\x1b";
            }
            $i += \strlen($code);
            if (str_starts_with($code, "\x1b[") && str_ends_with($code, 'm') || str_starts_with($code, "\x1b]8;")) {
                $kept .= $code;
            } else {
                $sequences[] = [$column, $code, self::marksACell($code)];
            }
        }

        return [$kept, $sequences];
    }

    /**
     * Whether a blank line draws over what is under it, like an overlay composes cells: a space of a background or an
     * attribute, see AnsiCodeTracker::paintsCells().
     */
    private static function drawsOnSpaces(string $line): bool
    {
        if (!str_contains($line, "\x1b[")) {
            return false;
        }
        $tracker = new AnsiCodeTracker();
        for ($i = 0, $len = \strlen($line); $i < $len;) {
            if ("\x1b" === $line[$i]) {
                $code = AnsiUtils::extractAnsiCode($line, $i)['code'] ?? "\x1b";
                $i += \strlen($code);
                $tracker->processText($code);
                continue;
            }
            if ($tracker->paintsCells()) {
                return true;
            }
            if (false === $i = strpos($line, "\x1b", $i)) {
                return false;
            }
        }

        return false;
    }

    /**
     * The columns of the character of a line starting at a column, 0 for none, like the second column of a wide one.
     */
    private static function glyphAt(string $line, int $at): int
    {
        // No wide character in a line of ASCII
        if (!preg_match('/[^\x00-\x7e]/', $line)) {
            return 0;
        }
        $column = 0;
        foreach (preg_split('/\e(?:\[[0-9;:<=>?]*[ -\/]*[@-~]|\][^\x07\e]*(?:\x07|\e\\\\))/', $line, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $text) {
            foreach (grapheme_str_split($text) ?: str_split($text) as $grapheme) {
                $cells = "\t" === $grapheme ? AnsiUtils::TAB_WIDTH : AnsiUtils::graphemeWidth($grapheme);
                // No cell of its own, like a combining mark alone
                if (0 === $cells) {
                    continue;
                }
                if ($column >= $at) {
                    // A tab: spaces, cut as they are
                    return $column === $at && "\t" !== $grapheme ? $cells : 0;
                }
                $column += $cells;
            }
        }

        return 0;
    }

    /**
     * Whether a sequence that is no cell belongs at its column, like the marker of the cursor or of text drawn larger,
     * see TextMarkers, or an image drawn by the terminal (kitty, iTerm2).
     */
    private static function marksACell(string $sequence): bool
    {
        return str_starts_with($sequence, "\x1b_pi:") || str_starts_with($sequence, "\x1b_G") || str_starts_with($sequence, "\x1b]1337;File=");
    }

    /**
     * Whether a sequence draws an image at the cursor, over the cells from there: one kitty puts or transmits and puts,
     * or one iTerm2 draws inline; not a command of no image, like one deleting them.
     */
    private static function drawsAnImage(string $sequence): bool
    {
        return \in_array(self::kittyKey($sequence, 'a'), ['T', 'p'], true) || preg_match('/^\e\]1337;File=(?:[^:\x07\e]*;)?inline=1(?:[;:]|$)/', $sequence);
    }

    /**
     * The value of a key of the control data of a sequence of the graphics protocol of kitty, like a for its action;
     * null for none, or another sequence.
     */
    private static function kittyKey(string $sequence, string $key): ?string
    {
        return preg_match('/^\e_G(?:[^;\e]*,)?'.$key.'=([^,;\e]*)/', $sequence, $match) ? $match[1] : null;
    }

    /**
     * Whether the cell of a float at a column draws nothing, showing what is under it: a blank or a hidden character,
     * of no background nor line across it, see AnsiCodeTracker::paintsCells(), not a column of a wide character.
     */
    private static function showsUnder(string $float, int $at, int $width): bool
    {
        $tracker = new AnsiCodeTracker();
        $column = 0;
        for ($i = 0, $len = \strlen($float); $i < $len && $column <= $at;) {
            if ("\x1b" === $float[$i]) {
                $code = AnsiUtils::extractAnsiCode($float, $i)['code'] ?? "\x1b";
                $i += \strlen($code);
                $tracker->processText($code);
                continue;
            }
            $end = strpos($float, "\x1b", $i);
            $text = false === $end ? substr($float, $i) : substr($float, $i, $end - $i);
            $i = false === $end ? $len : $end;
            foreach (grapheme_str_split($text) ?: str_split($text) as $grapheme) {
                $cells = "\t" === $grapheme ? AnsiUtils::TAB_WIDTH : AnsiUtils::graphemeWidth($grapheme);
                if ($at < $column + $cells) {
                    // A wide character cut at its edge: spaces, like a composition draws it
                    return !$tracker->paintsCells() && ($tracker->isHidden() || $column + $cells > $width || $column === $at && ' ' === $grapheme);
                }
                $column += $cells;
            }
        }

        return true;
    }

    /**
     * A line with sequences put at columns, before the cell there, after a wide character a column inside it.
     *
     * @param array<int, list<string>> $sequences By column
     */
    private static function insertAt(string $line, array $sequences): string
    {
        if (!$sequences) {
            return $line;
        }
        ksort($sequences);
        $out = '';
        $column = 0;
        $flush = static function (int $upTo) use (&$sequences, &$out): void {
            foreach ($sequences as $at => $codes) {
                if ($at > $upTo) {
                    break;
                }
                $out .= implode('', $codes);
                unset($sequences[$at]);
            }
        };
        for ($i = 0, $len = \strlen($line); $i < $len;) {
            if ("\x1b" === $line[$i]) {
                $code = AnsiUtils::extractAnsiCode($line, $i)['code'] ?? "\x1b";
                $i += \strlen($code);
                $out .= $code;
                continue;
            }
            $end = strpos($line, "\x1b", $i);
            $text = false === $end ? substr($line, $i) : substr($line, $i, $end - $i);
            $i = false === $end ? $len : $end;
            foreach (grapheme_str_split($text) ?: str_split($text) as $grapheme) {
                $flush($column);
                $out .= $grapheme;
                $column += "\t" === $grapheme ? AnsiUtils::TAB_WIDTH : AnsiUtils::graphemeWidth($grapheme);
            }
        }
        $flush(\PHP_INT_MAX);

        return $out;
    }

    /**
     * Whether a line draws on a cell, even a space, from the style of the tracker on: a background, a line across it or
     * its colors swapped, see AnsiCodeTracker::paintsCells(); or, with $hidden, whether it draws a hidden character.
     */
    private static function paints(string $line, AnsiCodeTracker $tracker, bool $hidden = false): bool
    {
        for ($i = 0, $len = \strlen($line); $i < $len;) {
            if ("\x1b" === $line[$i]) {
                $code = AnsiUtils::extractAnsiCode($line, $i)['code'] ?? "\x1b";
                $i += \strlen($code);
                $tracker->processText($code);
                continue;
            }
            if ($hidden ? $tracker->isHidden() : $tracker->paintsCells()) {
                return true;
            }
            if (false === $i = strpos($line, "\x1b", $i)) {
                return false;
            }
        }

        return false;
    }

    /**
     * The piece of a block laid out beside floats, pulled up over the rows before it by a negative margin: its rows over
     * them kept apart, drawn over them once all the others are, see layoutFloats(); the piece of the rows after them.
     *
     * @param list<array{FlowPiece, string|null}> $pulled
     */
    private function pulledPiece(FlowPiece $piece, int $pull, AbstractWidget $child, array &$pulled): FlowPiece
    {
        if ($pull <= 0) {
            return $piece;
        }
        $rows = min($pull, \count($piece->lines));
        $lines = $piece->lines->toArray();
        $seeThrough = null !== ($inherited = $this->widgetRenderer->getInheritedBackground($child)) ? substr($inherited->toBackgroundCode(), 2, -1) : null;
        $pulled[] = [new FlowPiece(new ArrayLineBuffer(\array_slice($lines, 0, $rows)), $piece->row, $piece->column, $piece->columns, $piece->flow, null), $seeThrough];

        $rest = new FlowPiece(new ArrayLineBuffer(\array_slice($lines, $rows)), $piece->row + $rows, $piece->column, $piece->columns, $piece->flow, null);
        $rest->over = $rows;

        return $rest;
    }

    /**
     * The rows a block is pulled up over the one before it by their negative margins, that of the bottom of the one before
     * and that of its own top, through their first and last children when margins collapse, see collapsedPull().
     *
     * @return array{int, int}
     */
    private function pullsBetween(AbstractWidget $previous, AbstractWidget $child, bool $collapseMargins): array
    {
        $before = $collapseMargins ? $this->collapsedPull($previous, false) : $this->widgetRenderer->resolveComputedStyle($previous)->getMarginPull()->bottom ?? 0;
        $after = $collapseMargins ? $this->collapsedPull($child, true) : $this->widgetRenderer->resolveComputedStyle($child)->getMarginPull()->top ?? 0;
        // A pull of less than a cell joins a border with the one it is pulled over: none when there is none,
        // like the links of a menu of PHP.net, each over a dotted bottom border, the next one not over it
        if (1 === $before && $this->edgeOf($previous, false)[1] && !$this->edgeOf($child, true)[0]) {
            $before = 0;
        }
        if (1 === $after && $this->edgeOf($child, true)[1] && !$this->edgeOf($previous, false)[0]) {
            $after = 0;
        }

        return [$before, $after];
    }

    /**
     * A line of a piece laid out beside floats cut to its width: kept from a frame to the next, the rows that did not change
     * cut again as they were, like those of the blocks around a hovered link laid out again with it; while they are not
     * too many nor too long, a cut that is the line itself kept as that string, not a copy.
     */
    private function cutBesideFloats(string $line, int $width): string
    {
        if (isset($this->floatCuts[$width][$line])) {
            return $this->floatCuts[$width][$line];
        }
        $cut = AnsiUtils::sliceToWidth($line, 0, $width);
        if ($this->floatCutCount >= self::MAX_CUTS || $this->floatCutBytes > self::MAX_CUT_BYTES) {
            $this->floatCuts = [];
            $this->floatCutCount = $this->floatCutBytes = 0;
        }
        ++$this->floatCutCount;
        $this->floatCutBytes += \strlen($line) + ($cut === $line ? 0 : \strlen($cut));

        return $this->floatCuts[$width][$line] = $cut === $line ? $line : $cut;
    }

    /**
     * Shift all lines by prepending spaces.
     */
    public function shiftLines(LineBufferInterface $lines, int $offset): LineBufferInterface
    {
        $prefix = str_repeat(' ', $offset);
        $result = [];
        foreach ($lines as $line) {
            $result[] = $prefix.$line;
        }
        // Lines all as wide stay so
        if (null !== $width = ArrayLineBuffer::uniformWidthOf($lines)) {
            return new ArrayLineBuffer($result, $result ? $width + max(0, $offset) : 0, true);
        }

        return new ArrayLineBuffer($result);
    }

    /**
     * The top or bottom margin of a block, as rows: with no border nor padding on that side, that of its first or last
     * child collapses into it, like CSS, the larger one winning, like the margin of a <p> through the <li> around it; not
     * at the top of a block whose ::before clears floats, which comes between them, see Style::withBeforeClear().
     *
     * @internal
     *
     * @param bool|null $cleared Whether its ::before clears floats, when known before it is laid out; else as its last layout
     */
    public function collapsedMargin(AbstractWidget $widget, bool $top, int $columns, int $depth = 0, ?bool $cleared = null): int
    {
        $style = StyleBridge::resolve($this->widgetRenderer, $widget);
        $margin = max(0, ($top ? $style->withResolvedBox($columns)->getMargin()?->top : $style->withResolvedBox($columns)->getMargin()?->bottom) ?? 0);
        $cleared ??= self::hasClearance($widget);
        // A block container whose content is in its flow: not a row, a grid, nor a formatting context of its own
        if ($depth >= 16 || $top && $cleared || !$widget instanceof ParentInterface || null !== $style->getDirection() || null !== $style->getGridTemplate() || $this->widgetRenderer->establishesFormattingContext($widget) || 0 !== (($top ? $style->getBorder()?->top : $style->getBorder()?->bottom) ?? 0) || 0 !== (($top ? $style->getPadding()?->top : $style->getPadding()?->bottom) ?? 0)) {
            return $margin;
        }
        // Its first and last children in the flow only, found from each end: not the styles of all of them, like the
        // items of a long list, whose margins its parent asks for each time it is laid out
        $all = array_values($widget->all());
        if (null === $first = $this->inFlowChild($all, true)) {
            // A box of no rows of its own, its top and bottom margins collapsed through it, the larger on each side,
            // see ChromeApplier::apply()
            $box = $style->withResolvedBox($columns);
            // Not a widget that draws itself, like the summary of a <details> closed
            if ($widget instanceof ContainerWidget && null === $style->getMinRows() && null === $style->getMinRowsPercent() && 0 === ($box->getBorder()->top ?? 0) + ($box->getBorder()->bottom ?? 0) + ($box->getPadding()->top ?? 0) + ($box->getPadding()->bottom ?? 0)) {
                return max(0, $box->getMargin()->top ?? 0, $box->getMargin()->bottom ?? 0);
            }

            return $margin;
        }
        // Not through a first child whose ::before cleared floats, its margin kept apart, see
        // Renderer::collapseParentMargins()
        if ($top && self::hasClearance($first)) {
            return $margin;
        }
        // Through the floats it starts with, to the first block in the flow, which they are beside, see layoutFloats();
        // not through a float it ends with, drawn over the rows of those margins, see Renderer::collapseParentMargins()
        $edge = $top ? $this->flowStartOf($all) : $this->inFlowChild($all, false);
        if (null === $edge || null !== $this->widgetRenderer->resolveComputedStyle($edge)->getFloat()) {
            return $margin;
        }

        return max($margin, $this->collapsedMargin($edge, $top, $columns, $depth + 1));
    }

    /**
     * The first child in the flow, past the floats before it, whose top margin collapses with that of its parent, the
     * floats under it, like CSS; null for none, or one that clears them, its clearance keeping the margins apart.
     *
     * @internal
     *
     * @param list<AbstractWidget> $children
     */
    public function flowStart(array $children): ?AbstractWidget
    {
        foreach ($children as $index => $child) {
            $style = $this->widgetRenderer->resolveComputedStyle($child);
            if ($style->getHidden() || $style->getPosition()?->isOutOfFlow()) {
                continue;
            }
            if (null === $style->getFloat()) {
                return $index > 0 && null !== $style->getClear() ? null : $child;
            }
        }

        return null;
    }

    /**
     * The first or the last of children in the flow, not hidden nor out of it, looked for from that end; null for none.
     *
     * @param list<AbstractWidget> $children
     */
    private function inFlowChild(array $children, bool $first): ?AbstractWidget
    {
        for ($i = $first ? 0 : \count($children) - 1, $step = $first ? 1 : -1; isset($children[$i]); $i += $step) {
            $style = $this->widgetRenderer->resolveComputedStyle($children[$i]);
            if (!$style->getHidden() && !$style->getPosition()?->isOutOfFlow()) {
                return $children[$i];
            }
        }

        return null;
    }

    /**
     * flowStart() of the children in the flow among all children, looked for from the first one: past the floats, the
     * first block, unless it clears them.
     *
     * @param list<AbstractWidget> $children
     */
    private function flowStartOf(array $children): ?AbstractWidget
    {
        $index = 0;
        foreach ($children as $child) {
            $style = $this->widgetRenderer->resolveComputedStyle($child);
            if ($style->getHidden() || $style->getPosition()?->isOutOfFlow()) {
                continue;
            }
            if (null === $style->getFloat()) {
                return $index > 0 && null !== $style->getClear() ? null : $child;
            }
            ++$index;
        }

        return null;
    }

    /**
     * Whether the ::before of a block or text cleared floats in its last layout.
     *
     * @internal
     */
    public static function hasClearance(AbstractWidget $widget): bool
    {
        return ($widget instanceof ContainerWidget || $widget instanceof TextWidget) && ContainerState::of($widget)->getClearance() > 0;
    }

    /**
     * The rows the children of a container pull into its top or bottom border by their negative margins there, like the
     * links of the tabs of Bootstrap over the line under them: the largest of the items of a row, that of the first or
     * last child of a column, through the blocks around them with nothing above or under them.
     *
     * @internal
     */
    public function pullInto(ParentInterface $container, bool $row, bool $top = false): int
    {
        // A column: its first or last child in the flow only, found from that end
        if (!$row) {
            return null !== ($edge = $this->inFlowChild(array_values($container->all()), $top)) ? $this->collapsedPull($edge, $top) : 0;
        }
        $children = array_values(array_filter($container->all(), fn (AbstractWidget $child) => !$this->widgetRenderer->resolveComputedStyle($child)->getHidden() && !$this->widgetRenderer->resolveComputedStyle($child)->getPosition()?->isOutOfFlow()));
        if (!$children) {
            return 0;
        }

        return max(array_map(fn (AbstractWidget $child) => $this->collapsedPull($child, $top), $children));
    }

    /**
     * Whether the first child in the flow of a block pulls its first row a row up, over the top border of the block: a
     * block, not a float, whose negative top margin collapses not with that of the block, see pullInto().
     *
     * @internal
     */
    public function pullsIntoTopBorder(ParentInterface $container): bool
    {
        return 1 === $this->edgePull($container, true);
    }

    /**
     * The rows the first or last child in the flow of a block pulls by its negative top or bottom margin, through its
     * first or last children, see collapsedPull(): none for a float, which is not pulled over what comes before or after
     * it in the block.
     *
     * @internal
     */
    public function edgePull(ParentInterface $container, bool $top): int
    {
        $edge = $this->inFlowChild(array_values($container->all()), $top);

        return null !== $edge && null === $this->widgetRenderer->resolveComputedStyle($edge)->getFloat() ? $this->collapsedPull($edge, $top) : 0;
    }

    /**
     * A line drawn over another, each line of a box in it joined with the one under it, like ┌ over └ making ├.
     *
     * @internal
     */
    public static function joinGlyphs(string $over, string $under): string
    {
        if (!preg_match('/[─│┌┐└┘├┤┬┴┼╭╮╰╯]/u', $over) || !preg_match('/[─│┌┐└┘├┤┬┴┼╭╮╰╯]/u', $under)) {
            return $over;
        }
        // The glyphs of boxes under it, by column
        $below = [];
        $column = 0;
        foreach (grapheme_str_split(AnsiUtils::stripAnsiCodes($under)) ?: [] as $glyph) {
            if (isset(self::BOX_ARMS[$glyph])) {
                $below[$column] = $glyph;
            }
            $column += AnsiUtils::graphemeWidth($glyph);
        }
        $result = '';
        $column = 0;
        // Its styles and hyperlinks, of no column
        foreach (preg_split('/(\e\[[0-9;:]*m|\e\][^\e\a]*(?:\e\\\\|\a))/', $over, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ("\x1b" === $part[0]) {
                $result .= $part;
                continue;
            }
            foreach (grapheme_str_split($part) ?: [] as $glyph) {
                if (isset(self::BOX_ARMS[$glyph], $below[$column])) {
                    $glyph = self::glyphOf(self::BOX_ARMS[$glyph] | self::BOX_ARMS[$below[$column]], $glyph);
                }
                $result .= $glyph;
                $column += AnsiUtils::graphemeWidth($glyph);
            }
        }

        return $result;
    }

    /**
     * Reduce widths so they sum to at most the budget, keeping one column each.
     *
     * Entries with a shrink factor of 0 keep their width when the others can
     * absorb the deficit; different factors weigh the loss like CSS
     * flex-shrink. Then scales proportionally, and trims the widest entries to
     * absorb the rounding, so the narrow children keep their content.
     *
     * @param array<int, int>       $widths
     * @param array<int, int|float> $shrinks Shrink factors, 1 when missing
     *
     * @return array<int, int>
     *
     * @internal
     */
    public static function shrinkToFit(array $widths, int $budget, array $shrinks = []): array
    {
        // Widgets that never shrink keep their width, if the others can absorb the deficit
        $fixed = array_filter($widths, static fn (int $width, int $index) => 0 === ($shrinks[$index] ?? 1), \ARRAY_FILTER_USE_BOTH);
        $flexible = array_diff_key($widths, $fixed);
        if ($fixed && $flexible && $budget - array_sum($fixed) >= \count($flexible)) {
            return array_replace($widths, self::shrinkToFit($flexible, $budget - array_sum($fixed), $shrinks));
        }
        // Weighted by width times factor, like CSS: the larger factor gives way first; in thousandths, for fractions
        $weights = [];
        foreach ($widths as $index => $width) {
            $weights[$index] = (int) round($width * ($shrinks[$index] ?? 1) * 1000);
        }
        if (!$fixed && \count(array_unique(array_map(strval(...), array_intersect_key($shrinks, $widths) ?: [1]))) > 1 && array_sum($weights) > 0) {
            $deficit = array_sum($widths) - max(\count($widths), $budget);
            $totalWeight = array_sum($weights);
            // Largest remainder: the columns rounding leaves go to the heaviest shares, not to the first ones
            $losses = [];
            $remainders = [];
            foreach ($widths as $index => $width) {
                $losses[$index] = intdiv($deficit * $weights[$index], $totalWeight);
                $remainders[$index] = $deficit * $weights[$index] % $totalWeight;
            }
            arsort($remainders);
            foreach (\array_slice(array_keys($remainders), 0, $deficit - array_sum($losses)) as $index) {
                ++$losses[$index];
            }
            foreach ($widths as $index => $width) {
                $widths[$index] = max(1, $width - $losses[$index]);
            }
        }
        $total = array_sum($widths);
        if ($total <= $budget) {
            return $widths;
        }
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

    /**
     * Gives the cells spanning rows the height of the rows they span, like CSS: each row as tall as its other cells,
     * the last one taller when the cell needs more; a cell is then drawn across them, see layout().
     *
     * @param list<AbstractWidget>                                 $tableRows
     * @param list<int>                                            $groupEnds The index past the last row of the group of each row
     * @param list<array{AbstractWidget, int, int, int, int, int}> $layout    The cells, with their first column, span, width, the columns skipped before them and their row
     */
    private function spanRows(AbstractWidget $table, array $tableRows, array $groupEnds, array $layout, int $rows): void
    {
        $spans = [];
        foreach ($layout as [$cell, , , , , $rowIndex]) {
            $span = min($this->widgetRenderer->resolveComputedStyle($cell)->getRowSpan() ?? 1, $groupEnds[$rowIndex] - $rowIndex);
            unset($this->spanningCells[$cell]);
            if ($span > 1) {
                $spans[] = [$cell, $rowIndex, $span];
            }
        }
        // Rows apart, like border-spacing, keep the cell in its first row
        if (($this->widgetRenderer->resolveComputedStyle($table)->getGap() ?? 0) > 0) {
            $spans = [];
        }
        // The lines carried from cells that span rows no more
        $kept = array_flip(array_map(static fn (array $span) => spl_object_id($span[0]), $spans));
        foreach ($tableRows as $row) {
            unset($this->rowHeights[$row]);
            if (isset($this->carried[$row]) && ($left = array_intersect_key($this->carried[$row], $kept)) !== $this->carried[$row]) {
                $this->carried[$row] = $left;
                $row->clearRenderCache();
            }
        }
        if (!$spans) {
            return;
        }
        // Measured again only when the table or the widths of its cells changed
        $key = $table->getRenderRevision().':'.$rows.':'.implode(',', array_map(fn (array $entry) => $this->cellWidths[$entry[0]] ?? 0, $layout));
        if (($this->spanHeights[$table][0] ?? null) === $key) {
            $heights = $this->spanHeights[$table][1];
        } else {
            $heights = $this->measureSpannedRows($tableRows, $layout, $spans, $rows);
            $this->spanHeights[$table] = [$key, $heights];
        }
        foreach ($tableRows as $index => $row) {
            $this->rowHeights[$row] = max(1, $heights[$index]);
        }
        foreach ($spans as [$cell, $rowIndex, $span]) {
            $this->spanningCells[$cell] = [array_sum(array_map(static fn (int $h) => max(1, $h), \array_slice($heights, $rowIndex, $span))), \array_slice($tableRows, $rowIndex + 1, $span - 1)];
        }
    }

    /**
     * The height of each row of a table with cells spanning rows: of its other cells, the last row a cell spans taller
     * when that cell needs more.
     *
     * @param list<AbstractWidget>                                 $tableRows
     * @param list<array{AbstractWidget, int, int, int, int, int}> $layout
     * @param list<array{AbstractWidget, int, int}>                $spans     The cells spanning rows, their first row and span
     *
     * @return list<int>
     */
    private function measureSpannedRows(array $tableRows, array $layout, array $spans, int $rows): array
    {
        $savedStack = $this->positionTracker->suppressStack();
        // Each cell drawn again only when it changed, or its width did
        $height = function (AbstractWidget $cell) use ($rows): int {
            if (!isset($this->cellWidths[$cell])) {
                return 0;
            }
            $key = $cell->getRenderRevision().':'.$this->cellWidths[$cell].':'.$rows;
            $cached = $this->cellHeights[$cell] ?? null;
            if (null === $cached || $cached[0] !== $key) {
                $this->cellHeights[$cell] = $cached = [$key, \count($this->widgetRenderer->renderLines($cell, new RenderContext($this->cellWidths[$cell], $rows, null, $this->fontRegistry)))];
            }

            return $cached[1];
        };
        $heights = array_fill(0, \count($tableRows), 0);
        $spanning = array_map(static fn (array $span) => $span[0], $spans);
        foreach ($layout as [$cell, , , , , $rowIndex]) {
            if (!\in_array($cell, $spanning, true)) {
                $heights[$rowIndex] = max($heights[$rowIndex], $height($cell));
            }
        }
        // The cells spanning fewer rows first: what they add is there for those spanning more
        usort($spans, static fn (array $a, array $b) => $a[2] <=> $b[2]);
        foreach ($spans as [$cell, $rowIndex, $span]) {
            $natural = $height($cell);
            $spanned = array_sum(array_map(static fn (int $h) => max(1, $h), \array_slice($heights, $rowIndex, $span)));
            if ($natural > $spanned) {
                $heights[$rowIndex + $span - 1] = max(1, $heights[$rowIndex + $span - 1]) + $natural - $spanned;
            }
        }
        $this->positionTracker->restoreStack($savedStack);

        return $heights;
    }

    /**
     * The backgrounds the <col> and <colgroup> elements of a table give its columns, by column; a column's own over
     * its group's.
     *
     * @return array<int, Color>
     */
    private function columnElementBackgrounds(AbstractWidget $table): array
    {
        $backgrounds = [];
        $index = 0;
        foreach ($table instanceof ParentInterface ? $table->all() : [] as $child) {
            $groupStyle = StyleBridge::resolve($this->widgetRenderer, $child);
            if (TableRole::Column !== $groupStyle->getTableRole()) {
                continue;
            }
            $groupColumns = $child instanceof ParentInterface ? array_filter($child->all(), fn (AbstractWidget $col) => TableRole::Column === $this->widgetRenderer->resolveComputedStyle($col)->getTableRole()) : [];
            foreach ($groupColumns ?: [$child] as $column) {
                $style = StyleBridge::resolve($this->widgetRenderer, $column);
                $background = $style->getBackground() ?? $groupStyle->getBackground();
                for ($i = 0, $span = min(1000, $style->getColumnSpan() ?? 1); $i < $span; ++$i, ++$index) {
                    if (null !== $background) {
                        $backgrounds[$index] = $background;
                    }
                }
            }
        }

        return $backgrounds;
    }

    /**
     * Gives each cell the width of the columns it spans, its row laid out again when it changes.
     *
     * @param list<array{AbstractWidget, int, int, int, int, int}> $layout The cells, with their first column, span, width, the columns skipped before them and their row
     * @param list<int>                                            $widths
     */
    private function assignCellWidths(AbstractWidget $table, array $layout, array $widths, int $columnCount, int $gap): void
    {
        $backgrounds = $this->columnElementBackgrounds($table);
        foreach ($layout as [$cell, $column, $span, , $skipped]) {
            $span = min($span, $columnCount - $column);
            // A cell spanning columns of different backgrounds: each under the part of the cell in its column, like CSS
            $bands = null;
            if ($span > 1 && \count(array_unique(array_map(static fn (int $i): string => ($backgrounds[$column + $i] ?? null)?->toBackgroundCode() ?? '', range(0, $span - 1)))) > 1) {
                $bands = array_map(static fn (int $i): array => [$backgrounds[$column + $i] ?? null, ($widths[$column + $i] ?? 0) + ($i < $span - 1 ? $gap : 0)], range(0, $span - 1));
            }
            if ($bands != ($this->cellColumnBands[$cell] ?? null)) {
                ++$this->columnBackgroundRevision;
                if (null === $bands) {
                    unset($this->cellColumnBands[$cell]);
                } else {
                    $this->cellColumnBands[$cell] = $bands;
                }
            }
            $width = array_sum(\array_slice($widths, $column, $span)) + $gap * ($span - 1);
            // The columns skipped before the cell, and the gaps after them
            $offset = $skipped > 0 ? array_sum(\array_slice($widths, $column - $skipped, $skipped)) + $gap * $skipped : 0;
            if (($this->cellWidths[$cell] ?? null) !== $width || ($this->cellOffsets[$cell] ?? 0) !== $offset) {
                $this->cellOffsets[$cell] = $offset;
                // Its row, laid out with other widths, must be laid out again
                $this->cellWidths[$cell] = $width;
                for ($ancestor = $cell->getParent(); null !== $ancestor && $ancestor !== $table; $ancestor = $ancestor->getParent()) {
                    $ancestor->clearRenderCache();
                }
            }
        }
    }

    /**
     * Collapses the bottom margin of a child and the top margin of the next
     * one, like CSS in block layout: the larger one stays, the other goes.
     *
     * The previous child's margin rows are the last ones of its segment, painted like the parent: they are dropped.
     *
     * @param list<LineBufferInterface> $segments
     */
    private function collapseMargins(array &$segments, int &$lineCount, AbstractWidget $previous, AbstractWidget $child, int $columns, ?int $adjoiningMargin, int &$carry): int
    {
        // Their margins with those of their last and first children through them, like a <p> in a <li> with none: the
        // rows each draws there, which collapse like CSS
        $collapsed = min($adjoiningMargin ?? $this->collapsedMargin($previous, false, $columns), $this->collapsedMargin($child, true, $columns));
        if ($collapsed <= 0 || !$segments) {
            return 0;
        }
        // The previous child pulled up over more rows than it has: its margin rows are among those it was drawn over, the
        // rows at the end are of the children before it, which stay: the next child pulled over them instead
        if ($carry > 0) {
            $carry += $collapsed;

            return 0;
        }
        $remaining = min($collapsed, $lineCount);
        $lineCount -= $remaining;
        while ($remaining > 0 && $segments) {
            $last = array_key_last($segments);
            $lines = $segments[$last];
            $taken = min($remaining, \count($lines));
            $remaining -= $taken;
            if ($taken === \count($lines)) {
                array_pop($segments);
            } else {
                $segments[$last] = ArrayLineBuffer::takenFrom($lines, \array_slice($lines->toArray(), 0, \count($lines) - $taken));
            }
        }

        return $collapsed;
    }

    /**
     * The floats beside a row, each with the column a terminal leaves after it; those of a percentage width only, each
     * rounded.
     *
     * @param list<array{string, int, int, int, ...}> $floats See layoutFloats()
     */
    private static function floatsAt(array $floats, int $row, bool $percentages = false): int
    {
        return \count(array_filter($floats, static fn (array $float) => $float[1] <= $row && $row < $float[1] + $float[2] && (!$percentages || ($float[6] ?? false))));
    }

    /**
     * The side the ::before of a block clears, see Style::withBeforeClear(), and whether the block puts its content
     * below the floats, its box where it is, its border and background beside them, like CSS: the text of a block or a
     * block passing the floats on to what it holds, see layoutFloats(), the rows they take blank in its content box, see
     * Renderer::renderContainer(); otherwise, like a block of several columns, the box goes below them, its content with
     * it. Null for none, or a float, which a ::before in it does not move.
     *
     * @return array{string, bool}|null
     */
    private static function beforeClear(AbstractWidget $child, Style $style, bool $container): ?array
    {
        if (null === ($side = $style->getBeforeClear()) || null !== $style->getFloat()) {
            return null;
        }

        // Not text written vertically, whose rows are its lines: its box below them
        return [$side, ($child instanceof TextWidget && null === $style->getWritingMode() || $container) && null === $style->getColumns()];
    }

    /**
     * The first row under a float beside a row, where one of the floats there ends; null when none is beside it.
     *
     * @param list<array{string, int, int, int, ...}> $floats See layoutFloats()
     */
    private static function nextFloatEnd(array $floats, int $row): ?int
    {
        $end = null;
        foreach ($floats as [, $top, $height]) {
            if ($top <= $row && $row < $top + $height) {
                $end = min($end ?? \PHP_INT_MAX, $top + $height);
            }
        }

        return $end;
    }

    /**
     * Places the floats of a block that go on past it, see ContainerWidget::setEscapedFloats(): from its content box,
     * drawn over it again and past it, over its padding, border and margin too, and beside the blocks after it, like
     * CSS; the row under them, 0 when none. The only place they are moved from the box of the block to the one around.
     *
     * @param list<FlowPiece>                                                                                                                   $pieces See layoutFloats()
     * @param list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface|null, int|null, array{int, int, int, int}|null}> $floats See layoutFloats()
     *
     * @param-out list<FlowPiece> $pieces
     * @param-out list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface|null, int|null, array{int, int, int, int}|null}> $floats
     */
    private function escapeFloats(AbstractWidget $child, Style $style, int $y, int $x, int $width, int $columns, array &$pieces, array &$floats): int
    {
        if (!$child instanceof ContainerWidget) {
            return 0;
        }
        // Those of the render just made, in its content box
        $escaped = ContainerState::of($child)->getEscapedFloats();
        if (!$escaped) {
            return 0;
        }
        // The rows of the block past its content, its padding, border and margin, drawn as text is: the floats over
        // them, like CSS paints floats over the borders of the blocks in the flow
        if (!$pieces) {
            return 0;
        }
        $pieces[array_key_last($pieces)]->flow = true;
        $margin = $style->getMargin();
        $border = $style->getBorder();
        $padding = $style->getPadding();
        $base = $y + ($margin->top ?? 0) + ($border->top ?? 0) + ($padding->top ?? 0);
        $start = $x + ($margin->left ?? 0) + ($border->left ?? 0) + ($padding->left ?? 0);
        $end = $columns - $x - $width + ($margin->right ?? 0) + ($border->right ?? 0) + ($padding->right ?? 0);
        $bottom = 0;
        foreach ($escaped as $float) {
            [$side, $top, $height, $floatWidth, $profile, $edge, $percent, $floatLines, $from] = $float;
            $edge += 'left' === $side ? $start : $end;
            $floatX = 'left' === $side ? $edge : $columns - $edge - $floatWidth;
            $pieces[] = $piece = new FlowPiece($floatLines, $base + $from, $floatX, $floatWidth, false, null === $profile ? null : [$side, \array_slice($profile, $from - $top)]);
            $piece->opaqueBox = $float[9];
            $floats[] = [$side, $base + $top, $height, $floatWidth, $profile, $edge, $percent, $floatLines, $base + $from, $piece->opaqueBox];
            $bottom = max($bottom, $base + $top + $height);
        }

        return $bottom;
    }

    /**
     * The columns the floats take at a row on its left and on its right, see floatsWidth().
     *
     * @internal
     *
     * @param list<array{string, int, int, int, 4?: list<int>|null, 5?: int, 6?: bool}> $floats
     *
     * @return array{int, int}
     */
    public static function floatInsetsAt(array $floats, int $row): array
    {
        return [self::floatsWidth($floats, 'left', $row), self::floatsWidth($floats, 'right', $row)];
    }

    /**
     * Floats as the rows after some rows see them: moved up as much, those ended above them gone.
     *
     * @internal
     *
     * @template T of array{string, int, int, int, ...}
     *
     * @param list<T> $floats
     *
     * @return list<T>
     */
    public static function floatsMovedUp(array $floats, int $rows): array
    {
        $moved = [];
        foreach ($floats as $float) {
            if ($float[1] + $float[2] > $rows) {
                $float[1] -= $rows;
                $moved[] = $float;
            }
        }

        return $moved;
    }

    /**
     * The columns floats take at a row on a side, a column apart from the content beside them.
     *
     * @param list<array{string, int, int, int, 4?: list<int>|null, 5?: int, 6?: bool}> $floats Each float: its side, first row, rows, width, the columns its shape takes at each of its rows, its columns from the side, and whether its width is a percentage
     * @param bool                                                                      $shaped Whether the shapes count, for lines, or only the boxes
     */
    private static function floatsWidth(array $floats, string $side, int $row, bool $shaped = true): int
    {
        $width = 0;
        // With shapes, the furthest edge of a shape or of a box from the side, each float from where it is
        $far = 0;
        $shapes = false;
        // Without, the furthest edge of the boxes where each is known, not their widths added: a float beside one that
        // ended before the row, like the aside of a pair of columns, still as far from the side
        $edge = 0;
        $placed = true;
        foreach ($floats as $float) {
            [$floatSide, $top, $height, $floatWidth] = $float;
            if ($side === $floatSide && $row >= $top && $row < $top + $height) {
                $width += $floatWidth + 1;
                $placed = $placed && isset($float[5]);
                $edge = max($edge, ($float[5] ?? 0) + $floatWidth + 1);
                $shapes = $shapes || null !== ($float[4] ?? null);
                $taken = null !== ($float[4] ?? null) ? $float[4][$row - $top] ?? $floatWidth : $floatWidth;
                if ($taken > 0) {
                    $far = max($far, ($float[5] ?? 0) + $taken + 1);
                }
            }
        }
        if ($shaped && $shapes) {
            return $far;
        }

        return $placed ? $edge : $width;
    }

    /**
     * The row below the floats on a side, or on both.
     *
     * @param list<array{string, int, int, int}> $floats
     */
    private static function floatsBottom(array $floats, ?string $side): int
    {
        $end = 0;
        foreach ($floats as [$floatSide, $top, $height]) {
            if (null === $side || $side === $floatSide) {
                $end = max($end, $top + $height);
            }
        }

        return $end;
    }

    /**
     * The widest content of the items of a subgrid in each column of its parent it spans, from 0, those of the subgrids
     * in it too; an item spanning columns widens none, like in a grid.
     *
     * @return array<int, int>
     */
    private function subgridWidths(ContainerWidget $subgrid, int $span, int $columns, int $rows, int $depth = 0): array
    {
        $template = $this->widgetRenderer->resolveComputedStyle($subgrid)->getGridTemplate() ?? new GridTemplate([]);
        $children = array_values(array_filter($subgrid->all(), function (AbstractWidget $child): bool {
            $style = StyleBridge::resolve($this->widgetRenderer, $child);

            return !$style->getHidden() && !$style->getPosition()?->isOutOfFlow();
        }));
        $widths = [];
        foreach ($children ? $this->placeGridItems(array_values($this->ordered($children)), $template, $span, true) : [] as [$child, , $column, , $childSpan]) {
            if ($depth < 8 && $child instanceof ContainerWidget && $this->isSubgrid($child)) {
                foreach ($this->subgridWidths($child, $childSpan, $columns, $rows, $depth + 1) as $offset => $width) {
                    $widths[$column + $offset] = max($widths[$column + $offset] ?? 0, $width);
                }
            } elseif (1 === $childSpan) {
                $widths[$column] = max($widths[$column] ?? 0, $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows));
            }
        }

        return array_filter($widths, static fn (int $width, int $column): bool => $column < $span, \ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Whether a line is blank, without a background in any of its cells nor reverse video, which draws one.
     */
    private static function blankWithoutBackground(string $line): bool
    {
        if ('' !== trim(AnsiUtils::stripAnsiCodes($line)) || preg_match('/\e\[(?:[0-9]+;)*7[;m]/', $line)) {
            return false;
        }
        foreach (AnsiUtils::walkCells($line) as $cell) {
            if ($cell['bg'] && 0 !== $cell['width']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a widget is a subgrid, its columns those of its parent grid, like grid-template-columns: subgrid.
     */
    private function isSubgrid(AbstractWidget $widget): bool
    {
        return $widget instanceof ContainerWidget && true === $this->widgetRenderer->resolveComputedStyle($widget)->getGridTemplate()?->subgrid;
    }

    /**
     * Whether a widget is a subgrid of rows, its rows those of its parent grid, like grid-template-rows: subgrid.
     */
    private function isRowSubgrid(AbstractWidget $widget): bool
    {
        return $widget instanceof ContainerWidget && true === $this->widgetRenderer->resolveComputedStyle($widget)->getGridTemplate()?->subgridRows;
    }

    /**
     * Ends the run of rows without a group of their own that starts at $run: they are one group, ending past the last.
     *
     * @param list<int> $groupEnds
     *
     * @return list<int>
     */
    private static function endRowRun(array $groupEnds, ?int $run): array
    {
        for ($i = $run ?? \count($groupEnds); $i < \count($groupEnds); ++$i) {
            $groupEnds[$i] = \count($groupEnds);
        }

        return $groupEnds;
    }

    /**
     * Places the items of a grid: those with an area or lines first, then the others in the free cells, row after row.
     *
     * @param list<AbstractWidget> $children
     * @param int                  $lead     Set to the columns added before the explicit ones, like those an item of grid-column: -5 / -1
     *                                       takes in a grid of three: the explicit ones then start there
     * @param int                  $rowLead  Set to the rows added before the explicit ones, likewise
     *
     * @return list<array{AbstractWidget, int, int, int, int}> Each item: its first row, first column, rows and columns
     */
    private function placeGridItems(array $children, GridTemplate $template, int $count, bool $subgrid = false, int &$lead = 0, int &$rowLead = 0): array
    {
        $lead = 0;
        $rowLead = 0;
        $explicitRows = max(\count($template->rows), ...array_values(array_map(static fn (array $area) => $area[2] + 1, $template->areas ?: [[0, 0, 0, 0]])));
        // Column after column, the items fill the explicit rows, or a single row without any
        $flowRows = max(1, $explicitRows);
        // A line from 1, or from the end when negative, to an index from 0
        $index = static fn (?int $line, int $tracks): ?int => null === $line ? null : ($line > 0 ? $line - 1 : $tracks + 1 + $line);
        $resolve = static function (?int $start, ?int $end, int $span, int $tracks) use ($index): array {
            $from = $index($start, $tracks);
            $to = $index($end, $tracks);
            if (null !== $from && null !== $to) {
                return [min($from, $to), max(1, abs($to - $from))];
            }
            if (null === $from && null !== $to) {
                return [max(0, $to - $span), $span];
            }

            return [$from, $span];
        };
        $taken = [];
        $occupy = static function (int $row, int $column, int $rowSpan, int $columnSpan) use (&$taken): void {
            for ($r = $row; $r < $row + $rowSpan; ++$r) {
                for ($c = $column; $c < $column + $columnSpan; ++$c) {
                    $taken[$r][$c] = true;
                }
            }
        };
        $free = static function (int $row, int $column, int $rowSpan, int $columnSpan) use (&$taken): bool {
            for ($r = $row; $r < $row + $rowSpan; ++$r) {
                for ($c = $column; $c < $column + $columnSpan; ++$c) {
                    if (isset($taken[$r][$c])) {
                        return false;
                    }
                }
            }

            return true;
        };
        $items = [];
        foreach ($children as $order => $child) {
            $style = StyleBridge::resolve($this->widgetRenderer, $child);
            $placement = $style->getGridPlacement() ?? new GridPlacement(null, null, null, $style->getColumnSpan() ?? 1);
            $area = null !== $placement->area ? $template->areas[$placement->area] ?? null : null;
            if (null !== $area) {
                // Its columns, past those grid-template-columns sizes too: they are sized like grid-auto-columns, like CSS
                $items[$order] = [$child, $area[0], $area[1], $area[2] - $area[0] + 1, $area[3] - $area[1] + 1];
                continue;
            }
            // An area name that is no area: the lines of that name, name-start and name-end
            if (null !== $placement->area) {
                $name = strtolower($placement->area);
                $placement = $placement->withColumns(null, null, 1, $name, $name)->withRows(null, null, 1, $name, $name);
            }
            // Named lines, like [content-start]: their numbers, a missing one auto
            $line = static fn (?int $number, ?string $name, bool $start, bool $column): ?int => null !== $name && !str_starts_with($name, 'span ') ? $template->line($name, $start, $column, $count) : $number;
            // span name: to the Nth line of that name after the start, or from the Nth before the end, like CSS
            $lines = static function (?int $number, ?string $name, bool $start, bool $column, ?string $otherName, ?int $otherNumber, int $tracks) use ($line, $template, $count): array {
                $from = $line($number, $name, $start, $column);
                $to = $line($otherNumber, $otherName, !$start, $column);
                foreach ([[$name, true], [$otherName, false]] as [$spanName, $isStart]) {
                    if (null === $spanName || !preg_match('/^span (\S+) (\d+)$/', $spanName, $m)) {
                        continue;
                    }
                    $named = $template->lines($column, $count)[$m[1]] ?? [];
                    $anchor = $isStart ? $to : $from;
                    if (null === $anchor) {
                        continue;
                    }
                    // A line counted from the end, like -1 for the last one
                    if ($anchor < 0) {
                        $anchor = max(1, $tracks + 2 + $anchor);
                    }
                    $found = $isStart ? array_values(array_reverse(array_filter($named, static fn (int $l) => $l < $anchor))) : array_values(array_filter($named, static fn (int $l) => $l > $anchor));
                    // Fewer lines of that name: auto, like a name no line has
                    $target = $found[(int) $m[2] - 1] ?? null;
                    $isStart ? $from = $target : $to = $target;
                }

                return [$from, $to];
            };
            [$columnFrom, $columnTo] = $lines($placement->columnStart, $placement->columnStartName, true, true, $placement->columnEndName, $placement->columnEnd, $count);
            [$rowFrom, $rowTo] = $lines($placement->rowStart, $placement->rowStartName, true, false, $placement->rowEndName, $placement->rowEnd, $explicitRows);
            [$column, $columnSpan] = $resolve($columnFrom, $columnTo, $placement->columnSpan, $count);
            [$row, $rowSpan] = $resolve($rowFrom, $rowTo, $placement->rowSpan, $explicitRows);
            if ($template->columnFlow) {
                // Column after column, the columns are added as needed: the explicit rows bound the spans instead
                $columnSpan = min(self::MAX_GRID_ROW_SPAN, $columnSpan);
                $column = null === $column ? null : min(1000, max(0, $column));
                $rowSpan = min($flowRows, $rowSpan);
            } else {
                $columnSpan = min($subgrid ? $count : self::MAX_GRID_ROW_SPAN, $columnSpan);
                if (null !== $column) {
                    // A line before the first one, like -5 in a grid of three columns, adds columns before them
                    $column = $subgrid ? min(max(0, $column), $count - $columnSpan) : min(max(-self::MAX_GRID_ROW_SPAN, $column), 1000);
                }
            }
            $items[$order] = [$child, null === $row ? null : min(1000, max(-self::MAX_GRID_ROW_SPAN, $row)), $column, min(self::MAX_GRID_ROW_SPAN, $rowSpan), $columnSpan];
        }
        // The rows added before the explicit ones, from a line before the first one, like grid-row: -5 / -1 in a grid of
        // three rows: every row moves past them
        if (!$subgrid && !$template->subgridRows && $items && 0 < $rowLead = max(0, -min(0, ...array_map(static fn (array $item): int => $item[1] ?? 0, $items)))) {
            foreach ($items as $order => $item) {
                if (null !== $item[1]) {
                    $items[$order][1] += $rowLead;
                }
            }
            $flowRows += $rowLead;
        }
        if (!$template->columnFlow && !$subgrid && $items) {
            // The columns added before the explicit ones: every column moves past them
            if (0 < $lead = max(0, -min(0, ...array_map(static fn (array $item): int => $item[2] ?? 0, $items)))) {
                foreach ($items as $order => $item) {
                    if (null !== $item[2]) {
                        $items[$order][2] += $lead;
                    }
                }
                $count += $lead;
            }
            $count = max($count, ...array_map(static fn (array $item): int => ($item[2] ?? 0) + $item[4], $items));
        }
        // Fixed first: areas and items with both lines; then the ones with a row (a column, column after column); then the others, in order
        $placed = [];
        if ($template->columnFlow) {
            foreach ([true, false] as $both) {
                foreach ($items as $order => [$child, $row, $column, $rowSpan, $columnSpan]) {
                    if (null === $column || isset($placed[$order]) || $both && null === $row) {
                        continue;
                    }
                    if (null === $row) {
                        for ($row = 0; !$free($row, $column, $rowSpan, $columnSpan); ++$row) {
                        }
                    }
                    $occupy(max(0, $row), $column, $rowSpan, $columnSpan);
                    $placed[$order] = [$child, max(0, $row), $column, $rowSpan, $columnSpan];
                }
            }
        }
        // The items of a row and no column: past those placed in their row before them, unless dense, in the columns
        // added past the explicit ones when there is no room left, like CSS
        $rowEnds = [];
        foreach ($template->columnFlow ? [] : [true, false] as $both) {
            foreach ($items as $order => [$child, $row, $column, $rowSpan, $columnSpan]) {
                if (null === $row || isset($placed[$order]) || $both && null === $column) {
                    continue;
                }
                $row = max(0, $row);
                if (null === $column) {
                    for ($column = $template->dense ? 0 : $rowEnds[$row] ?? 0; $column < 1000 && !$free($row, $column, $rowSpan, $columnSpan); ++$column) {
                    }
                    $rowEnds[$row] = $column + $columnSpan;
                    if (!$subgrid) {
                        $count = max($count, $column + $columnSpan);
                    }
                    $column = min($column, $count - $columnSpan);
                }
                $occupy($row, $column, $rowSpan, $columnSpan);
                $placed[$order] = [$child, $row, $column, $rowSpan, $columnSpan];
            }
        }
        $cursorRow = 0;
        $cursorColumn = 0;
        foreach ($items as $order => [$child, $row, $column, $rowSpan, $columnSpan]) {
            if (isset($placed[$order])) {
                continue;
            }
            if ($template->columnFlow) {
                // Dense: from the first cell again; the next free cells from the cursor, column after column, a fixed row keeping its row
                if ($template->dense) {
                    $cursorRow = 0;
                    $cursorColumn = 0;
                }
                if (null !== $row && $row < $cursorRow) {
                    ++$cursorColumn;
                }
                for ($guard = 0; $guard < 100000; ++$guard) {
                    $tryRow = $row ?? $cursorRow;
                    if (null === $row && $tryRow + $rowSpan > $flowRows) {
                        ++$cursorColumn;
                        $cursorRow = 0;
                        continue;
                    }
                    if ($free($tryRow, $cursorColumn, $rowSpan, $columnSpan)) {
                        break;
                    }
                    if (null === $row) {
                        ++$cursorRow;
                    } else {
                        ++$cursorColumn;
                    }
                }
                $at = $row ?? $cursorRow;
                $occupy($at, $cursorColumn, $rowSpan, $columnSpan);
                $placed[$order] = [$child, $at, $cursorColumn, $rowSpan, $columnSpan];
                // Past it, a fixed row too: the items after it do not go back to the cells left free before it, like CSS
                $cursorRow = $at + $rowSpan;
                continue;
            }
            // Dense: from the first cell again, filling what the items before left free
            if ($template->dense) {
                $cursorRow = 0;
                $cursorColumn = 0;
            }
            // The next free cells from the cursor, row after row; a fixed column keeps its column
            if (null !== $column && $column < $cursorColumn) {
                ++$cursorRow;
            }
            for ($guard = 0; $guard < 100000; ++$guard) {
                $tryColumn = $column ?? $cursorColumn;
                if (null === $column && $tryColumn + $columnSpan > $count) {
                    ++$cursorRow;
                    $cursorColumn = 0;
                    continue;
                }
                if ($free($cursorRow, $tryColumn, $rowSpan, $columnSpan)) {
                    break;
                }
                if (null === $column) {
                    ++$cursorColumn;
                } else {
                    ++$cursorRow;
                }
            }
            $at = $column ?? $cursorColumn;
            $occupy($cursorRow, $at, $rowSpan, $columnSpan);
            $placed[$order] = [$child, $cursorRow, $at, $rowSpan, $columnSpan];
            // Past it, a fixed column too: the items after it do not go back to the cells left free before it, like CSS
            $cursorColumn = $at + $columnSpan;
        }
        ksort($placed);

        // [child, row, column, rows, columns]
        return array_values($placed);
    }

    /**
     * Whether an item sets its height in rows, like height: 3: not stretched to its row or its area, like CSS, whose
     * align-self: stretch stretches an item of height: auto only.
     */
    private function setsHeight(AbstractWidget $child): bool
    {
        $style = $this->widgetRenderer->resolveComputedStyle($child);

        return null !== $style->getMaxRows() && $style->getMinRows() === $style->getMaxRows();
    }

    /**
     * Renders a grid item at its place, tracking its position; at least $stretchRows tall when given.
     */
    /**
     * @param int|null $containing The columns of the block it is in, which its percentage width is of, when not its own
     */
    private function renderGridItem(AbstractWidget $child, int $width, int $rows, ?int $stretchRows, int $absRow, int $absCol, bool $track, ?int $containing = null): LineBufferInterface
    {
        $context = new RenderContext($width, $rows, null, $this->fontRegistry);
        if (null !== $containing) {
            $context = $context->withContainingColumns($containing);
        }
        if ($track) {
            $this->positionTracker->push($absRow, $absCol);
        }
        $revision = $child->getRenderRevision();
        $lines = null !== $stretchRows ? $this->widgetRenderer->renderStretchedLines($child, $context, $stretchRows) : $this->widgetRenderer->renderLines($child, $context);
        $cached = $this->widgetRenderer->wasRenderCached($child);
        if ($track) {
            $this->positionTracker->pop();
            $rect = new WidgetRect($absRow, $absCol, $width, \count($lines));
            // A cached item was not walked: its descendants move with it, or are tracked again
            if ($cached && $revision === $child->getRenderRevision() && $child instanceof ParentInterface && !$this->positionTracker->moveSubtree($child, $rect)) {
                $child->clearRenderCache();
                $this->positionTracker->push($absRow, $absCol);
                $lines = null !== $stretchRows ? $this->widgetRenderer->renderStretchedLines($child, $context, $stretchRows) : $this->widgetRenderer->renderLines($child, $context);
                $this->positionTracker->pop();
            }
            $this->positionTracker->setWidgetRect($child, $rect);
        }

        return $lines;
    }

    /**
     * Breaks a row into lines of the children that fit, each laid out like a row of its own.
     *
     * Children are measured by their content (or their percentage of the
     * row), like CSS flex-wrap with "flex-basis: auto".
     *
     * @param AbstractWidget[] $children
     */
    private function layoutWrapped(array $children, int $columns, int $rows, int $gap, ?VerticalAlign $verticalAlign, ?Justify $justify, int $rowGap, ?int $contentRows = null, ?string $alignContent = null, bool $reverse = false, bool $inlineFlow = false, ?Align $lineAlign = null): LineBufferInterface
    {
        $savedStack = $this->positionTracker->suppressStack();
        $lines = [[]];
        $used = 0;
        // After a widget on a line of its own, like a block among inline boxes: the next one on another
        $broken = false;
        // The last one shown on the current line, for the space between it and the next
        $shown = null;
        $source = $this->sourceOrder($children);
        // Those shown on the current line: where each is in it, its width, the space before it and whether it is a box,
        // for the boxes written together that a line wrapped in a row of inline content moves together
        $placed = [];
        foreach ($children as $child) {
            $style = StyleBridge::resolve($this->widgetRenderer, $child);
            if ($style->getHidden()) {
                $lines[array_key_last($lines)][] = $child;
                continue;
            }
            $ownLine = (bool) $style->getOwnLine();
            if (($broken || $ownLine) && $lines[array_key_last($lines)]) {
                $lines[] = [];
                $used = 0;
                $shown = null;
                $placed = [];
            }
            $broken = $ownLine;
            // The least a text or a box of them takes, its longest word; a control, like a field, its own width
            $least = fn (): int => $child instanceof TextWidget || $child instanceof ContainerWidget ? $this->widgetRenderer->measureMinContentWidth($child, $columns, $rows) : 0;
            // A size set within its min-width and max-width, its margins around it, like the hypothetical size of CSS
            $margin = $style->getMargin();
            $most = $style->getRowMaxColumns() ?? \PHP_INT_MAX;
            $outer = static fn (int $box): int => max(min($box, $most), $style->getMinColumns() ?? 0) + max(0, $margin->left ?? 0) + max(0, $margin->right ?? 0);
            $cap = \PHP_INT_MAX === $most ? \PHP_INT_MAX : $outer($most);
            $width = match (true) {
                // A flex-basis set is the size it takes in the line, over its width, like CSS: at least its longest word,
                // within its max-width
                null !== $style->getFlexBasisPercent() => max($outer((int) floor($columns * $style->getFlexBasisPercent() / 100)), min($least(), $cap)),
                null !== $style->getFlexBasis() => max($outer($style->getFlexBasis()), min($least(), $cap)),
                // A max-width of a percentage bounds its content, measured below
                null !== $style->getWidthPercent() && !$style->isWidthPercentMax() => $outer((int) $style->resolveWidthPercent($columns)),
                default => max($this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows), min($style->getMinColumns() ?? 0, $columns)),
            };
            $current = array_key_last($lines);
            // A space between it and the one shown before it on its line, like between two words
            $space = null !== $shown && $this->spaceBetween($shown, $child, $source) ? 1 : 0;
            // Like text flows, a text that does not fit takes the room left, its longest word fitting: it shrinks there
            $flows = $inlineFlow && null !== $shown && $child instanceof TextWidget && $used + $gap + $space + $this->widgetRenderer->measureMinContentWidth($child, $columns, $rows) <= $columns;
            $box = !$child instanceof TextWidget;
            if (null !== $shown && $used + $gap + $space + $width > $columns && !$flows) {
                // In a row of inline content, a box written together with the boxes before it, like [ Three ][ Four ],
                // has no line break before it: they go to the next line with it, unless they start the line
                $from = \count($placed);
                if ($inlineFlow && $box && 0 === $space && $placed && $placed[$from - 1][3]) {
                    // The boxes before it, each written together with the one before it
                    for (--$from; $from > 0 && 0 === $placed[$from][2] && $placed[$from - 1][3]; --$from) {
                    }
                    // All of the line, or more than the next one holds with it: the line breaks there anyway
                    $run = $width + array_sum(array_column(\array_slice($placed, $from), 1)) + $gap * (\count($placed) - $from) + array_sum(array_column(\array_slice($placed, $from + 1), 2));
                    if (0 === $from || $run > $columns) {
                        $from = \count($placed);
                    }
                }
                $moved = $from < \count($placed) ? array_splice($lines[$current], $placed[$from][0]) : [];
                $carried = \array_slice($placed, $from);
                $lines[] = $moved;
                $current = array_key_last($lines);
                $used = 0;
                $placed = [];
                foreach ($carried as $index => [$position, $carriedWidth, $carriedSpace, $carriedBox]) {
                    $carriedSpace = 0 === $index ? 0 : $carriedSpace;
                    $used += (0 === $index ? 0 : $gap + $carriedSpace) + $carriedWidth;
                    $placed[] = [$position - $carried[0][0], $carriedWidth, $carriedSpace, $carriedBox];
                }
                if (!$placed) {
                    $space = 0;
                }
            }
            $used += ($placed ? $gap + $space : 0) + $width;
            $placed[] = [\count($lines[$current]), $width, $placed ? $space : 0, $box];
            $lines[$current][] = $child;
            $shown = $child;
        }
        // justify-content: center or end, or text-align of a row of inline content, each line in the room it leaves, like
        // CSS, not all of them by the longest; not a line of an item that grows or of an auto margin, which takes that room.
        // The room is the one the line leaves once drawn, see below, not the widths estimated here
        $aligns = [];
        if (null !== $lineAlign && Align::Left !== $lineAlign && null === $justify) {
            foreach ($lines as $i => $line) {
                // Inline content always: its auto margins and flex do not apply, like CSS
                $aligns[$i] = $inlineFlow || !array_any($line, fn (AbstractWidget $child) => ($this->widgetRenderer->resolveComputedStyle($child)->getFlex() ?? 0) > 0 || null !== $this->widgetRenderer->resolveComputedStyle($child)->getAutoMargin());
            }
        }
        // flex-wrap: wrap-reverse, the first line at the bottom
        if ($reverse) {
            $lines = array_reverse($lines);
            $aligns = array_reverse($aligns);
        }
        // align-content: the free rows of a set height shared between the lines, or stretching them, the default
        $count = \count($lines);
        $slots = array_fill(0, $count + 1, 0);
        $minRows = array_fill(0, $count, null);
        if (null !== $contentRows) {
            $heights = array_map(fn (array $line) => \count($this->layoutHorizontal($line, $columns, $rows, $gap, $verticalAlign, $justify, null, false, $reverse)), $lines);
            $free = $contentRows - array_sum($heights) - $rowGap * ($count - 1);
            if ($free > 0) {
                if ('stretch' === ($alignContent ?? 'stretch')) {
                    foreach ($heights as $i => $height) {
                        $minRows[$i] = $height + intdiv($free, $count) + ($i < $free % $count ? 1 : 0);
                    }
                } else {
                    $slots = self::contentSlots($alignContent, $count, $free);
                }
            }
        }
        $this->positionTracker->restoreStack($savedStack);
        if (1 === $count && null === $contentRows && !($aligns[0] ?? false)) {
            return $this->layoutHorizontal($children, $columns, $rows, $gap, $verticalAlign, $justify, null, $inlineFlow, $reverse);
        }
        $segments = [];
        $rowOffset = 0;
        $hasPositionStack = $this->positionTracker->isActive();
        $blank = static fn (int $rows) => new ArrayLineBuffer(array_fill(0, $rows, str_repeat(' ', $columns)));
        foreach ($lines as $i => $line) {
            $space = $slots[$i] + ($i > 0 ? $rowGap : 0);
            if ($space > 0) {
                $segments[] = $blank($space);
                $rowOffset += $space;
            }
            if ($hasPositionStack) {
                [$absRow, $absCol] = $this->positionTracker->currentOffset();
                $this->positionTracker->push($absRow + $rowOffset, $absCol);
            }
            $this->freeColumns = 0;
            $buffer = $this->layoutHorizontal($line, $columns, $rows, $gap, $verticalAlign, $justify, $minRows[$i], $inlineFlow, $reverse);
            if ($hasPositionStack) {
                $this->positionTracker->pop();
            }
            // Aligned in the room the line leaves drawn, at its end: moved by it, or by half of it, its positions too
            // Never more than the columns left blank at the end of its lines, nothing of it cut
            // Nothing drawn, nothing moved: not the room a row drawn inside it left
            $free = \count($buffer) > 0 ? $this->freeColumns : 0;
            foreach ($free > 0 ? $buffer->toArray() : [] as $drawn) {
                $free = min($free, $columns - AnsiUtils::visibleWidth(rtrim(AnsiUtils::stripAnsiCodes($drawn))));
            }
            $this->freeColumns = 0;
            $shift = ($aligns[$i] ?? false) && $free > 0 ? Align::Center === $lineAlign ? $free >> 1 : $free : 0;
            if ($shift > 0) {
                $buffer = $this->shiftWithin($buffer, $shift, $columns);
                if ($hasPositionStack) {
                    $this->positionTracker->shiftContentPositions($line, $shift);
                }
            }
            $segments[] = $buffer;
            $rowOffset += \count($buffer);
        }
        if ($slots[$count] > 0) {
            $segments[] = $blank($slots[$count]);
        }

        return new ConcatenatedLineBuffer($segments);
    }

    /**
     * The free rows before each line or row and after the last one, like align-content: start, center, end and space-*.
     *
     * @return list<int>
     */
    private static function contentSlots(string $mode, int $count, int $free): array
    {
        $none = array_fill(0, $count, 0);

        return match ($mode) {
            'center' => [intdiv($free, 2), ...\array_slice($none, 1), $free - intdiv($free, 2)],
            'end' => [$free, ...$none],
            'space-between', 'space-around', 'space-evenly' => array_values(self::distributeFreeColumns(Justify::from($mode), [], $count, $free, 0)),
            default => [...$none, $free],
        };
    }

    /**
     * Whether a widget is or holds a replaced element, a frame or the text alternative of an image, drawn: not of
     * display: none, visibility: hidden or opacity: 0, nor in what is, nor in a box of content-visibility: hidden, see
     * Renderer. The first one found is
     * enough; the answer kept while neither the widget nor what it holds is rendered anew, nor the styles changed.
     */
    private function drawsReplacedElement(AbstractWidget $widget): bool
    {
        $key = [$widget->getRenderRevision(), $this->widgetRenderer->getStyleEpoch()];
        if (null !== ($known = $this->drawsReplaced[$widget] ?? null) && $known[0] === $key[0] && $known[1] === $key[1]) {
            return $known[2];
        }
        $found = false;
        $pending = [$widget];
        while (!$found && $pending) {
            $current = array_pop($pending);
            // Neither it nor what it holds drawn: the renderer blanks an invisible or transparent box whole
            $style = $this->widgetRenderer->resolveComputedStyle($current);
            if ($style->getHidden() || $style->getInvisible() || $style->getTransparent()) {
                continue;
            }
            if ($current instanceof ReplacedElementInterface || $current instanceof ImageAlternativeWidget || $current instanceof FrameWidget) {
                $found = true;
            } elseif ($current instanceof ParentInterface && !$style->getContentHidden()) {
                // Not what a box of content-visibility: hidden holds, like Renderer::allowsFocus() reads it
                array_push($pending, ...$current->all());
            }
        }
        $this->drawsReplaced[$widget] = [...$key, $found];

        return $found;
    }

    private function containsReplacedElement(AbstractWidget $widget): bool
    {
        $pending = [$widget];
        while ($pending) {
            $child = array_pop($pending);
            if ($child instanceof ReplacedElementInterface || $child instanceof ImageAlternativeWidget) {
                return true;
            }
            if ($child instanceof ParentInterface) {
                array_push($pending, ...$child->all());
            }
        }

        return false;
    }

    /**
     * Sorts the children by their order style, keeping the tree order between equals.
     *
     * @param AbstractWidget[] $children
     *
     * @return AbstractWidget[]
     */
    private function ordered(array $children): array
    {
        // The groups of rows of a table only: in a flex or grid container they are items, in order
        foreach ($children as $child) {
            if (null !== $parent = $child->getParent()) {
                if (TableRole::Table === $this->widgetRenderer->resolveComputedStyle($parent)->getTableRole()) {
                    $children = $this->tableOrder($children);
                }
                break;
            }
        }
        $orders = [];
        foreach ($children as $index => $child) {
            $orders[$index] = $this->widgetRenderer->resolveComputedStyle($child)->getOrder() ?? 0;
        }
        if (!array_filter($orders)) {
            return $children;
        }
        $indexes = array_keys($children);
        usort($indexes, static fn ($a, $b) => [$orders[$a], $a] <=> [$orders[$b], $b]);

        return array_map(static fn ($index) => $children[$index], $indexes);
    }

    /**
     * Shifts the lines of a row by prepending spaces, the free columns at their end dropped: as wide as before.
     */
    private function shiftWithin(LineBufferInterface $lines, int $offset, int $columns): LineBufferInterface
    {
        $prefix = str_repeat(' ', $offset);
        $result = [];
        foreach ($lines as $line) {
            $result[] = match (true) {
                str_ends_with($line, $prefix) => $prefix.substr($line, 0, -$offset),
                AnsiUtils::visibleWidth($line) + $offset <= $columns => $prefix.$line,
                default => AnsiUtils::truncateToWidth($prefix.$line, $columns, ''),
            };
        }

        return new ArrayLineBuffer($result);
    }

    /**
     * Layout children vertically with gap and fill support.
     *
     * @param AbstractWidget[] $children
     */
    private function layoutVertical(array $children, int $columns, int $rows, int $gap, ?string $gapLine = null, bool $collapseMargins = false, ?Align $crossAlign = null, bool $flexColumn = false, ?int $contentRows = null, ?Justify $justify = null, ?VerticalAlign $mainAlign = null, ?AbstractWidget $owner = null): LineBufferInterface
    {
        $children = array_values($children);
        // In a flex column, an item not stretched across, by align-self or align-items, is as wide as its content, placed
        // at the start, the center or the end: its width and offset
        $placed = [];
        foreach ($flexColumn ? $children : [] as $index => $child) {
            $self = $this->widgetRenderer->resolveComputedStyle($child)->getAlignSelf();
            $placement = null !== $self ? match ($self) {
                VerticalAlign::Top => 'start',
                VerticalAlign::Center => 'center',
                VerticalAlign::Bottom => 'end',
                default => null,
            } : match ($crossAlign) {
                Align::Left => 'start',
                Align::Center => 'center',
                Align::Right => 'end',
                default => null,
            };
            if (null === $placement || $child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded()) {
                continue;
            }
            // A percentage width is of the column, not of the content, like the full-width bar of the header of GitHub
            $style = StyleBridge::resolve($this->widgetRenderer, $child);
            $margins = ($style->getMargin()->left ?? 0) + ($style->getMargin()->right ?? 0);
            // Within its max-width, the only width set besides the percentage
            $percent = null !== $style->getWidthPercent() && !$style->isWidthPercentMax() ? min((int) $style->resolveWidthPercent($columns), $style->getMaxColumns() ?? \PHP_INT_MAX) + $margins : null;
            // No narrower than its min-width, a percentage of it a share of the column, like a block: a width set or its
            // content within it
            $least = $this->leastColumns($style, $columns);
            $width = max(1, min($columns, max($percent ?? $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows), null !== $least ? $least + $margins : 0)));
            $placed[$index] = [$width, match ($placement) {
                'start' => 0,
                'center' => $columns - $width >> 1,
                'end' => $columns - $width,
            }];
        }
        $previous = null;
        $adjoiningMargin = 0;
        $carry = 0;
        // The negative margins collapsed so far through the empty blocks after the previous child, the most negative, and
        // the rows they pulled by already, see adjoiningPull()
        $pullGroup = [0, 0];
        if (!$children) {
            return new ArrayLineBuffer([]);
        }
        $segments = [];
        $lineCount = 0;
        $gapLine ??= str_repeat(' ', max(1, $columns));
        // Measured once, its rows all as wide, like those of the boxes between them
        $gapLines = $gap > 0 ? new ArrayLineBuffer(array_fill(0, $gap, $gapLine), AnsiUtils::visibleWidth($gapLine), true) : new ArrayLineBuffer([]);
        $first = true;
        $totalGapRows = $gap * max(0, \count($children) - 1);
        $remainingRows = $rows - $totalGapRows;
        $fillChildren = [];
        $nonFillRenders = [];
        $nonFillCacheHits = [];
        // A block container laid out again, like one of a child that changed: each child as it was, in the same state as
        // the layout before left the ones before it, placed as it was, its lines not asked for again, see replayStep()
        $stepKey = null;
        $steps = [];
        $known = [];
        $replay = [];
        $record = null;
        $ids = [];
        // The first and last children that changed since the layout before, see changedRange(): those before and after
        // them as they were, all at once
        $changed = null;
        if (null !== $owner && !$flexColumn && null === $contentRows && \count($children) > self::MANY_CHILDREN && null !== $stepKey = $this->widgetRenderer->getLayoutKey()) {
            $stepKey .= '|'.$columns.'|'.$rows.'|'.$gap.'|'.($collapseMargins ? 1 : 0).'|'.($this->positionTracker->isActive() ? 1 : 0).'|'.$gapLine;
            $record = $this->verticalSteps[$owner][$stepKey] ?? null;
            $known = $record['steps'] ?? [];
            $ids = array_map(spl_object_id(...), $children);
            $changed = null !== $record ? $this->changedRange($record, $owner, $ids) : null;
            // Not when one of them now fills the rows left, which the others share: all are measured then
            if (null !== $changed && array_any(\array_slice($children, $changed[0], $changed[1] - $changed[0] + 1), static fn (AbstractWidget $child): bool => $child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded())) {
                $changed = null;
            }
        }
        $lastIndex = array_key_last($children);
        // Where each child starts in the segments, past those it took back from the ones before, and the children whose
        // step read further back than the one before, see keepVerticalLayout()
        $keptAt = [];
        $loose = [];
        $start = 0;
        $measured = $lastIndex;
        // The row each step of the layout before started at, from the parts of its lines, see replacedRows(): what the
        // steps tell is of the layout they were made in, the rows before them moved since, see canResume()
        $rope = null !== $record && $ids === $record['ids'] ? $record['rope'] : null;
        $oldRows = 0;
        if (null === $changed && null !== $owner && null === $rope) {
            // Other children, or none known: what was moved later is by children the object ids of these may be of
            if ($this->positionTracker->isActive()) {
                $this->positionTracker->settle($owner);
            }
            // Not told where they were, the rows before them moved since
            if (true === ($record['shifted'] ?? false)) {
                $known = [];
            }
        }
        if (null !== $changed) {
            [$start, $measured] = $changed;
            $steps = $known;
            $loose = $record['loose'];
            // The state the children before the first changed one left, and their segments, with those that one took
            // back from them
            $step = $known[$ids[$start]];
            $oldRows = $rope->linesBefore($start);
            [$lineCount, $previous, $adjoiningMargin, $carry, $first, $pullGroup] = [$oldRows + self::replacedRows($step), $step[5]?->get(), $step[6], $step[7], $step[8], $step[22]];
            $segments = array_merge(...$record['rope']->getLeaves(0, max(0, $start - 1)));
            if ($start > 0) {
                $keptAt[$start - 1] = \count($segments);
                array_push($segments, ...$record['rope']->getLeaves($start - 1, $start)[0], ...\array_slice($step[10], \count($step[10]) + $step[9]));
            }
        }
        $savedStack = $this->positionTracker->suppressStack();
        for ($index = $start; $index <= $measured; ++$index) {
            $child = $children[$index];
            $rowsBefore = $oldRows;
            $oldRows += null !== $rope ? $rope->linesOf($index) : 0;
            if ($child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded()) {
                $fillChildren[$index] = $child;
                $stepKey = null;
            } elseif (null !== ($step = $known[spl_object_id($child)] ?? null) && $step[0]->get() === $child && $step[1] === $child->getRenderRevision() && $child->getCurrentRenderCache() === $step[2]) {
                if (null !== $rope) {
                    $step[4] = $rowsBefore + self::replacedRows($step);
                }
                $replay[$index] = $step;
                $nonFillRenders[$index] = $step[2];
                $nonFillCacheHits[$index] = true;
                $remainingRows -= \count($step[2]);
            } else {
                $childColumns = $placed[$index][0] ?? $columns;
                // An item of a flex column narrower than it takes percentages of the column
                $context = (new RenderContext($childColumns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                $revision = $child->getRenderRevision();
                $childLines = $this->widgetRenderer->renderLines($child, $context);
                $hadCachedRender = $this->widgetRenderer->wasRenderCached($child);
                $nonFillRenders[$index] = $childLines;
                $nonFillCacheHits[$index] = $hadCachedRender && $revision === $child->getRenderRevision();
                $remainingRows -= \count($childLines);
            }
        }
        $this->positionTracker->restoreStack($savedStack);
        // A flex column of a set height with no growing item: its free rows go to the auto margins of its items, like
        // margin-top: auto pushing a button to the bottom of a card, else where justify-content says
        $freeRowsBefore = [];
        // The heights of the items that grow, like flex: 1, by index
        $grown = [];
        if (null !== $contentRows && !$fillChildren) {
            $shown = array_keys(array_filter($nonFillRenders, static fn (LineBufferInterface $lines) => \count($lines) > 0));
            $count = \count($shown);
            $used = array_sum(array_map(static fn (int $index) => \count($nonFillRenders[$index]), $shown)) + $gap * max(0, $count - 1);
            // The items that grow take the free rows first, as their flex-grow says, like CSS
            $grow = [];
            foreach ($shown as $index) {
                $flex = StyleBridge::resolve($this->widgetRenderer, $children[$index])?->getFlex();
                if (null !== $flex && $flex > 0) {
                    $grow[$index] = (float) $flex;
                }
            }
            // Too tall: the scroll containers give way, like the min-height: 0 of a flex item that is not visible
            // in CSS, a list of messages scrolling above the field it leaves on the screen
            if ($contentRows < $used) {
                $scrollers = array_values(array_filter($shown, fn (int $index) => true === StyleBridge::resolve($this->widgetRenderer, $children[$index])?->getScroll()?->vertical));
                $excess = $used - $contentRows;
                $savedStack = $this->positionTracker->suppressStack();
                foreach ($scrollers as $i => $index) {
                    $natural = \count($nonFillRenders[$index]);
                    $share = min($natural - 1, intdiv($excess, \count($scrollers) - $i) + ($excess % (\count($scrollers) - $i) > 0 ? 1 : 0));
                    if ($share <= 0) {
                        continue;
                    }
                    $excess -= $share;
                    $grown[$index] = $natural - $share;
                    $context = (new RenderContext($placed[$index][0] ?? $columns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                    $nonFillRenders[$index] = $this->widgetRenderer->renderStretchedLines($children[$index], $context, $grown[$index]);
                    $nonFillCacheHits[$index] = false;
                }
                $this->positionTracker->restoreStack($savedStack);
                $used = $contentRows + $excess;
            }
            if ($grow && $contentRows > $used) {
                $free = $contentRows - $used;
                // The rows of each, its max-height and its box around them: one that its share takes past them is frozen
                // there, the others sharing the rest, like CSS
                $heights = [];
                while ($grow) {
                    $sum = array_sum($grow);
                    $given = 0;
                    $last = array_key_last($grow);
                    $shares = [];
                    foreach ($grow as $index => $weight) {
                        $shares[$index] = $index === $last ? $free - $given : (int) floor($free * $weight / $sum);
                        $given += $shares[$index];
                    }
                    $frozen = false;
                    foreach ($shares as $index => $share) {
                        $style = StyleBridge::resolve($this->widgetRenderer, $children[$index]);
                        if (null === $maxRows = $style?->getMaxRows()) {
                            continue;
                        }
                        $most = max(\count($nonFillRenders[$index]), $maxRows + ($style->getPadding()->top ?? 0) + ($style->getPadding()->bottom ?? 0) + ($style->getBorder()->top ?? 0) + ($style->getBorder()->bottom ?? 0) + ($style->getMargin()->top ?? 0) + ($style->getMargin()->bottom ?? 0));
                        if (\count($nonFillRenders[$index]) + $share > $most) {
                            $heights[$index] = $most;
                            $free -= $most - \count($nonFillRenders[$index]);
                            unset($grow[$index]);
                            $frozen = true;
                        }
                    }
                    if (!$frozen) {
                        foreach ($shares as $index => $share) {
                            $heights[$index] = \count($nonFillRenders[$index]) + $share;
                        }
                        $free = 0;
                        break;
                    }
                }
                $savedStack = $this->positionTracker->suppressStack();
                foreach ($heights as $index => $height) {
                    if ($height === \count($nonFillRenders[$index])) {
                        continue;
                    }
                    $grown[$index] = $height;
                    $context = (new RenderContext($placed[$index][0] ?? $columns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                    $nonFillRenders[$index] = $this->widgetRenderer->renderStretchedLines($children[$index], $context, $grown[$index]);
                    $nonFillCacheHits[$index] = false;
                }
                $this->positionTracker->restoreStack($savedStack);
                // What the frozen ones leave goes where justify-content says
                $used = $contentRows - $free;
            }
            $autos = [];
            foreach ($shown as $slot => $index) {
                $sides = StyleBridge::resolve($this->widgetRenderer, $children[$index])?->getAutoMarginRows();
                if (null !== $sides && null !== $auto = AutoMargin::fromSides($sides[0], $sides[1])) {
                    $autos[$slot] = $auto;
                }
            }
            // justify-content: center or end, the free rows before the first item, all or half of them
            $packed = !$autos && null === $justify && (VerticalAlign::Center === $mainAlign || VerticalAlign::Bottom === $mainAlign);
            if ($count > 0 && $contentRows > $used && ($autos || null !== $justify || $packed)) {
                $spaces = self::distributeFreeColumns($justify, $autos, $count, $contentRows - $used, $gap);
                if ($packed) {
                    $spaces[0] += VerticalAlign::Center === $mainAlign ? $contentRows - $used >> 1 : $contentRows - $used;
                }
                foreach ($shown as $slot => $index) {
                    $freeRowsBefore[$index] = $spaces[$slot] - (0 === $slot ? 0 : $gap);
                }
            }
        }
        $fillCount = \count($fillChildren);
        $baseFillRows = $fillCount > 0 ? max(1, intdiv(max(0, $remainingRows), $fillCount)) : 0;
        $extraRows = $fillCount > 0 ? max(0, $remainingRows) % $fillCount : 0;
        $fillIndex = 0;
        $hasPositionStack = $this->positionTracker->isActive();
        $noStep = 0;
        $resumed = null;
        $shifted = false;
        for ($index = $start; $index <= $lastIndex; ++$index) {
            $child = $children[$index];
            $cacheHit = false;
            $stepIn = null;
            $reach = 0;
            $rowIn = null;
            // Past the changed children, unchanged: its step as it was, see the first pass above
            $rowsBefore = $oldRows;
            $oldRows += $index > $measured && null !== $rope ? $rope->linesOf($index) : 0;
            if ($index > $measured && null !== $stepKey && null !== ($step = $known[$ids[$index]] ?? null) && $step[0]->get() === $child && $step[1] === $child->getRenderRevision() && $child->getCurrentRenderCache() === $step[2]) {
                $step[4] = $rowsBefore + self::replacedRows($step);
                $replay[$index] = $step;
                $nonFillRenders[$index] = $step[2];
                $nonFillCacheHits[$index] = true;
                // In the state the layout before laid it out in: it and all those after it as they were, moved down or
                // up as much as the rows before it, which the rects of those children tell when read
                if ($this->canResume($step, $index === $lastIndex, $segments, $lineCount, $previous, $adjoiningMargin, $carry, $first, $pullGroup, $loose, $index, $keptAt[$index - 1])) {
                    if ($lineCount !== $step[4] && $hasPositionStack) {
                        $this->positionTracker->shiftChildrenLater($owner, \array_slice($ids, $index), $lineCount - $step[4]);
                    }
                    $shifted = $lineCount !== $step[4];
                    $keptAt[$index] = \count($segments) + $step[9];
                    if (\count($segments) - \count($step[10]) < $keptAt[$index - 1]) {
                        $loose[$index] = true;
                    } else {
                        unset($loose[$index]);
                    }
                    array_splice($segments, $keptAt[$index]);
                    $resumed = $index;
                    break;
                }
            }
            if (isset($fillChildren[$index])) {
                $childFillRows = $baseFillRows + ($fillIndex < $extraRows ? 1 : 0);
                ++$fillIndex;
                if (!$first && \count($gapLines) > 0) {
                    $segments[] = $gapLines;
                    $lineCount += \count($gapLines);
                }
                if ($collapseMargins && null !== $previous) {
                    $this->collapseMargins($segments, $lineCount, $previous, $child, $columns, $adjoiningMargin, $carry);
                }
                // Stretched to its share: a set height, which its own column shares, like a card body of flex: 1
                $context = new RenderContext($columns, $childFillRows, null, $this->fontRegistry, true);
                $revision = $child->getRenderRevision();
                if ($hasPositionStack) {
                    [$parentAbsRow, $parentAbsCol] = $this->positionTracker->currentOffset();
                    $this->positionTracker->push($parentAbsRow + $lineCount, $parentAbsCol);
                }
                $childLines = $this->widgetRenderer->renderLines($child, $context);
                $cacheHit = $this->widgetRenderer->wasRenderCached($child) && $revision === $child->getRenderRevision();
                if ($hasPositionStack) {
                    $this->positionTracker->pop();
                }
                if (\count($childLines) < $childFillRows) {
                    $childLines = new ConcatenatedLineBuffer([$childLines, new ArrayLineBuffer(array_fill(0, $childFillRows - \count($childLines), ''))]);
                }
            } else {
                $countIn = \count($segments);
                if (null !== $stepKey && isset($replay[$index]) && null !== $step = $this->replayStep($replay[$index], $child, $index === $lastIndex, $segments, $lineCount, $previous, $adjoiningMargin, $carry, $first, $pullGroup, $hasPositionStack)) {
                    $steps[spl_object_id($child)] = $step;
                    $this->noteKept($keptAt, $loose, $index, $countIn + $step[9], $countIn - \count($step[10]));
                    continue;
                }
                $context = (new RenderContext($placed[$index][0] ?? $columns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                // A render kept in another size, for a measure, is drawn once rendered again: what it leaves besides its
                // lines, like where the lines of a text start, is that of the last render; what holds descendants is
                // rendered again below, its render being no hit
                if ($hasPositionStack && !$child instanceof ParentInterface && $this->widgetRenderer->wasRenderOfOtherSize($child)) {
                    unset($nonFillRenders[$index], $nonFillCacheHits[$index]);
                }
                $childLines = $nonFillRenders[$index] ?? (isset($grown[$index]) ? $this->widgetRenderer->renderStretchedLines($child, $context, $grown[$index]) : $this->widgetRenderer->renderLines($child, $context));
                $cacheHit = $nonFillCacheHits[$index] ?? false;
                // What the step reads and where it starts, see replayStep()
                $stepIn = null !== $stepKey ? [$segments, $lineCount, $previous, $adjoiningMargin, $carry, $first, $pullGroup] : null;
                if (0 === \count($childLines)) {
                    // Drawn as nothing, like a box of content-visibility: hidden: it and what it holds nowhere, as when
                    // drawn whole, not where an earlier frame drew them
                    if ($hasPositionStack) {
                        $this->positionTracker->forgetUndrawn($child);
                    }
                    // An empty block between two others: its negative margins collapse through it with theirs, like CSS
                    $pullIn = $pullGroup;
                    if ($collapseMargins && null !== $previous && $this->collapsesThrough($child)) {
                        $pullGroup[0] = max($pullGroup[0], $this->collapsedPull($child, true), $this->collapsedPull($child, false));
                    }
                    if (null !== $stepIn) {
                        $this->noteKept($keptAt, $loose, $index, \count($segments), \count($segments));
                    }
                    unset($steps[spl_object_id($child)]);
                    if (null === $stepIn) {
                        continue;
                    }
                    if (!$this->widgetRenderer->canReplayRender($child)) {
                        ++$noStep;
                    } else {
                        $steps[spl_object_id($child)] = [\WeakReference::create($child), $child->getRenderRevision(), $child->getCurrentRenderCache(), \count($segments), $lineCount, null === $previous ? null : \WeakReference::create($previous), $adjoiningMargin, $carry, $first, 0, [], [], 0, $carry, $adjoiningMargin, 0, null, $index === $lastIndex, 0, 0, true, $previous?->getRenderRevision(), $pullIn, $pullGroup];
                    }
                    continue;
                }
                if (!$first && \count($gapLines) > 0) {
                    $segments[] = $gapLines;
                    $lineCount += \count($gapLines);
                }
                if ($collapseMargins && null !== $previous) {
                    $reach = $this->collapseMargins($segments, $lineCount, $previous, $child, $columns, $adjoiningMargin, $carry);
                }
                if (($freeRowsBefore[$index] ?? 0) > 0) {
                    $segments[] = new ArrayLineBuffer(array_fill(0, $freeRowsBefore[$index], $gapLine), AnsiUtils::visibleWidth($gapLine), true);
                    $lineCount += $freeRowsBefore[$index];
                }
            }
            // Negative margins: the child is drawn over the last rows before it, the larger pull when margins collapse
            $tail = [];
            if (null !== $previous) {
                [$before, $after] = $this->pullsBetween($previous, $child, $collapseMargins);
                // Collapsed with those of the empty blocks between them, through which they collapse, like CSS: the most
                // negative of them all, less the rows those blocks were pulled by already
                $mostPull = $collapseMargins ? max($pullGroup[0], $before, $after) : 0;
                // The rows a shorter child pulled before it did not cover: what follows it starts at its end
                $pull = ($collapseMargins ? $mostPull - $pullGroup[1] : $before + $after) + $carry;
                $reach += $pull;
                $pull = min($pull, $lineCount);
                $carry = 0;
                if ($pull > 0) {
                    $tail = self::takeLastLines($segments, $pull);
                    $lineCount -= \count($tail);
                }
            }
            if ($hasPositionStack) {
                [$parentAbsRow, $parentAbsCol] = $this->positionTracker->currentOffset();
                $childAbsRow = $parentAbsRow + $lineCount;
                $childAbsCol = $parentAbsCol + ($placed[$index][1] ?? 0);
                $rect = new WidgetRect($childAbsRow, $childAbsCol, $placed[$index][0] ?? $columns, \count($childLines));
                $rowIn = null !== $stepIn ? $lineCount - $stepIn[1] : null;
                $positionsTracked = isset($fillChildren[$index]) && !$cacheHit;
                if ($cacheHit && $child instanceof ParentInterface) {
                    $positionsTracked = $this->positionTracker->moveSubtree($child, $rect);
                }
                $this->positionTracker->setWidgetRect($child, $rect);
                if ($child instanceof ParentInterface && !$positionsTracked) {
                    $child->clearRenderCache();
                    $this->positionTracker->push($childAbsRow, $childAbsCol);
                    $childLines = isset($grown[$index]) ? $this->widgetRenderer->renderStretchedLines($child, $context, $grown[$index]) : $this->widgetRenderer->renderLines($child, $context);
                    $this->positionTracker->pop();
                    $this->positionTracker->setWidgetRect($child, new WidgetRect($childAbsRow, $childAbsCol, $placed[$index][0] ?? $columns, \count($childLines)));
                }
            }
            if (0 < $offset = $placed[$index][1] ?? 0) {
                $childLines = $this->shiftLines($childLines, $offset);
            }
            // All its rows, those drawn over the rows before it included
            $childRows = \count($childLines);
            if ($tail) {
                // Its cells over those before it, but for its spaces, which let them show
                // A child shorter than its pull leaves the rows after it as they were
                $lines = $childLines->toArray();
                $overlap = \count($tail);
                $carry = max(0, $overlap - \count($lines));
                // Its border over the one before it, like the top of the active item of a list group over the bottom of the
                // item above it: their lines join, └ and ┌ making ├
                $joined = min($overlap, \count($lines));
                // Its spaces on the background it inherited, like its margin, show what is under them, like the heading
                // over a row of Bootstrap pulled up by its gutter
                $seeThrough = null !== ($inherited = $this->widgetRenderer->getInheritedBackground($child)) ? substr($inherited->toBackgroundCode(), 2, -1) : null;
                // In blocks of rows, the cells of a pull of thousands of rows, like a margin of -99999, never all at once
                $composited = [];
                for ($from = 0; $from < $overlap; $from += 1024) {
                    $rows = min(1024, $overlap - $from);
                    $under = \array_slice($tail, $from, $rows);
                    $over = array_map(self::joinGlyphs(...), \array_slice($lines, $from, max(0, min($rows, $joined - $from))), \array_slice($under, 0, max(0, min($rows, $joined - $from))));
                    array_push($composited, ...Compositor::composite(new Layer($under, 0, 0, false, $columns, $rows), new Layer($over, 0, 0, true, null, null, $seeThrough)));
                }
                $segments[] = new ArrayLineBuffer($composited);
                $childLines = new ArrayLineBuffer(\array_slice($lines, $overlap));
                $lineCount += $overlap;
            } elseif ($segments && \count($childLines) > 0 && \count($last = $segments[array_key_last($segments)]) > 0) {
                // Right under the box before it, its sides go on from that box's bottom, like the items of a list group
                // of Bootstrap without a top border: their lines join, └ over │ making ├
                [$above, $below] = self::joinStacked($last->getLine(\count($last) - 1), $childLines->getLine(0));
                if (null !== $above && null !== $below) {
                    self::takeLastLines($segments, 1);
                    $segments[] = new ArrayLineBuffer([$above]);
                    $childLines = new ArrayLineBuffer([$below, ...\array_slice($childLines->toArray(), 1)]);
                }
            }
            $segments[] = $childLines;
            $lineCount += \count($childLines);
            $first = false;
            if ($collapseMargins && $index !== array_key_last($children)) {
                $bottom = $this->collapsedMargin($child, false, $columns);
                $childStyle = StyleBridge::resolve($this->widgetRenderer, $child);
                $empty = $child instanceof ContainerWidget && null === $childStyle->getDirection() && null === $childStyle->getGridTemplate() && null === $childStyle->getScroll() && $childRows === max($bottom, $childStyle->withResolvedBox($columns)->getMargin()->top ?? 0);
                $adjoiningMargin = $empty ? max($adjoiningMargin, $bottom) : $bottom;
                // Its negative margins collapsing with those of the next block too, the rows it was pulled by counted
                $pullGroup = $empty && null !== $previous ? [max($mostPull, $this->collapsedPull($child, false)), $mostPull] : [0, 0];
            }
            $previous = $child;
            // Kept for the layout after: the segments it took back and those it added, the state it leaves; not a child
            // drawn over the rows before it, which shows them
            if (null !== $stepIn) {
                [$before, $lineCountIn] = $stepIn;
                $kept = min(\count($before), \count($segments));
                while ($kept > 0 && $segments[$kept - 1] !== $before[$kept - 1]) {
                    --$kept;
                }
                $from = max(0, min($kept, \count($before) - 1));
                // noteKept(), inline: once for each child laid out
                $keptAt[$index] = $kept;
                if ($index > 0 && $from < $keptAt[$index - 1]) {
                    $loose[$index] = true;
                } elseif (isset($loose[$index])) {
                    unset($loose[$index]);
                }
                if ($tail || isset($fillChildren[$index]) || !$this->widgetRenderer->canReplayRender($child)) {
                    unset($steps[spl_object_id($child)]);
                    ++$noStep;
                } else {
                    $steps[spl_object_id($child)] = [\WeakReference::create($child), $child->getRenderRevision(), $child->getCurrentRenderCache(), \count($before), $lineCountIn, null === $stepIn[2] ? null : \WeakReference::create($stepIn[2]), $stepIn[3], $stepIn[4], $stepIn[5], $kept - \count($before), \array_slice($before, $from), \array_slice($segments, $kept), $lineCount - $lineCountIn, $carry, $adjoiningMargin, $reach, $rowIn, $index === $lastIndex, $placed[$index][0] ?? $columns, \count($childLines), false, $stepIn[2]?->getRenderRevision(), $stepIn[6], $pullGroup];
                }
            }
        }
        if (null === $owner || null === $stepKey) {
            return new ConcatenatedLineBuffer($segments);
        }
        // The segments of each child laid out, from the one before the first, whose last ones that child can take back
        $from = max(0, $start - 1);
        $to = $resumed ?? $lastIndex + 1;
        $leaves = [];
        $monotone = true;
        for ($i = $from; $i < $to; ++$i) {
            $next = $keptAt[$i + 1] ?? ($i + 1 === $resumed ? $keptAt[$resumed] : \count($segments));
            $monotone = $monotone && $next >= $keptAt[$i];
            $leaves[] = \array_slice($segments, $keptAt[$i], $next - $keptAt[$i]);
        }
        if (null !== $changed && $monotone) {
            $rope->replace($from, $leaves);
            $noStep = $noStep > 0 ? 1 : 0;
            $direct = $record['direct'];
            $shifted = $shifted || $record['shifted'];
        } elseif (null !== $changed) {
            // Not kept: the steps after the last laid out tell rows the rows before them moved since; their segments
            // after those laid out
            if (null !== $resumed) {
                array_push($segments, ...array_merge(...$rope->getLeaves($resumed, $lastIndex + 1)));
            }
            $rope = null;
            $steps = [];
            $direct = false;
        } else {
            $rope = $monotone ? new SegmentRope($leaves) : null;
            $direct = array_all($children, static fn (AbstractWidget $child): bool => $child->getParent() === $owner);
        }

        return $this->keepVerticalLayout($owner, $stepKey, ['steps' => $steps, 'ids' => $ids, 'index' => null !== $changed ? $record['index'] : array_flip($ids), 'rope' => $rope, 'loose' => $loose, 'noStep' => $noStep, 'direct' => $direct, 'shifted' => $shifted]) ?? new ConcatenatedLineBuffer($segments);
    }

    /**
     * Keeps a vertical layout for the next one, the change serials it was made at, and gives its lines. By key: a
     * container is laid out tracked and measured untracked, each kept, the last few only.
     *
     * @param array<string, mixed> $record
     */
    private function keepVerticalLayout(AbstractWidget $owner, string $stepKey, array $record): ?ConcatenatedLineBuffer
    {
        $record['serial'] = AbstractWidget::getChangeSerial();
        $record['writes'] = $this->positionTracker->getWriteSerial();
        $record['origin'] = $this->positionTracker->isActive() ? $this->positionTracker->currentOffset() : null;
        // What changed since kept from now, see changedRange()
        $owner->getChildrenChangedSince($record['serial']);
        if ($this->positionTracker->isActive()) {
            $this->positionTracker->getChildrenWrittenSince($owner, $record['writes']);
        }
        $layouts = $this->verticalSteps[$owner] ?? [];
        unset($layouts[$stepKey]);
        $layouts[$stepKey] = $record;
        $this->verticalSteps[$owner] = \count($layouts) > 4 ? \array_slice($layouts, -4, null, true) : $layouts;

        return $record['rope']?->getRoot();
    }

    /**
     * The first and the last of children that changed since their vertical layout before, by index: invalidated, their
     * render stored or cleared, or their rect written, see AbstractWidget::getChildrenChangedSince() and
     * PositionTracker::getChildrenWrittenSince(). Null when the children before and after them cannot be taken as they
     * were: other children, placed elsewhere, the container changed itself, or a child whose render is made again each
     * time, see WidgetRendererInterface::canReplayRender(); or when none changed, like a layout made again for another
     * reason, rare.
     *
     * @param array<string, mixed> $record
     * @param list<int>            $ids
     *
     * @return array{int, int}|null
     */
    private function changedRange(array $record, AbstractWidget $owner, array $ids): ?array
    {
        $tracked = $this->positionTracker->isActive();
        if (null === $record['rope'] || 0 !== $record['noStep'] || !$record['direct'] || $ids !== $record['ids'] || ($tracked ? $this->positionTracker->currentOffset() : null) !== $record['origin'] || null === $changed = $owner->getChildrenChangedSince($record['serial'])) {
            return null;
        }
        $changedIds = array_map(spl_object_id(...), $changed);
        if ($tracked) {
            if (null === $written = $this->positionTracker->getChildrenWrittenSince($owner, $record['writes'])) {
                return null;
            }
            array_push($changedIds, ...$written);
        }
        // The other children, out of the flow or hidden, are not laid out here
        $indexes = array_intersect_key($record['index'], array_flip($changedIds));

        return $indexes ? [min($indexes), max($indexes)] : null;
    }

    /**
     * The rows of the segments a step of a vertical layout took back from the children before it: the rows it started
     * at are those of the parts of the children before, see SegmentRope, and these.
     */
    private static function replacedRows(array $step): int
    {
        return array_sum(array_map(\count(...), \array_slice($step[10], \count($step[10]) + $step[9])));
    }

    /**
     * Notes where a child starts in the segments, past those it took back, and whether it read further back than the
     * child before it: the steps after a child that changed are all made as before only when none does, see canResume().
     *
     * @param array<int, int>  $keptAt
     * @param array<int, true> $loose
     */
    private function noteKept(array &$keptAt, array &$loose, int $index, int $kept, int $read): void
    {
        $keptAt[$index] = $kept;
        if ($index > 0 && $read < $keptAt[$index - 1]) {
            $loose[$index] = true;
        } else {
            unset($loose[$index]);
        }
    }

    /**
     * Whether a step of the vertical layout before can be made again as it was made, and all those after it: in the same
     * state, see replayStep(), at the same row, and none of the steps after it reading further back than the one before
     * it, see noteKept(), whose segments are then all as they were.
     *
     * @param list<LineBufferInterface> $segments
     * @param array{int, int}           $pullGroup
     * @param array<int, true>          $loose
     */
    private function canResume(array $step, bool $last, array $segments, int $lineCount, ?AbstractWidget $previous, int $adjoiningMargin, int $carry, bool $first, array $pullGroup, array $loose, int $index, int $previousKept): bool
    {
        // At another row, not reaching rows before the first: those before are as many as the lines of the parts before,
        // see SegmentRope::linesBefore(), at least those this one took back; the steps after it reading no further back
        // than the one before them, see noteKept(), their segments as they were, checked below
        return ($step[4] === $lineCount || min($step[4], $lineCount) - self::replacedRows($step) >= 0) && \count($segments) + $step[9] >= $previousKept && $step[5]?->get() === $previous && $step[21] === $previous?->getRenderRevision() && $step[6] === $adjoiningMargin && $step[7] === $carry && $step[8] === $first && $step[22] === $pullGroup && $step[17] === $last
            && $step[10] === \array_slice($segments, \count($segments) - \count($step[10]))
            && (!$loose || max(array_keys($loose)) <= $index);
    }

    /**
     * A step of a vertical layout made again as the layout before made it, a child unchanged in the same state: the
     * same child before it, the same margins and pull carried, the segments it read the same, the rows before it clamping
     * what it takes back as much; moved down or up with the rows before it. Null when it cannot be, the step made anew.
     *
     * @param list<LineBufferInterface> $segments
     * @param array{int, int}           $pullGroup
     *
     * @return array<int, mixed>|null The step as made, kept for the layout after
     */
    private function replayStep(array $step, AbstractWidget $child, bool $last, array &$segments, int &$lineCount, ?AbstractWidget &$previous, int &$adjoiningMargin, int &$carry, bool &$first, array &$pullGroup, bool $tracked): ?array
    {
        if ($step[5]?->get() !== $previous || $step[21] !== $previous?->getRenderRevision() || $step[6] !== $adjoiningMargin || $step[7] !== $carry || $step[8] !== $first || $step[22] !== $pullGroup || $step[17] !== $last || $step[4] !== $lineCount && ($step[4] < $step[15] || $lineCount < $step[15])) {
            return null;
        }
        $count = \count($segments);
        if ($step[10] !== \array_slice($segments, $count - \count($step[10]))) {
            return null;
        }
        if ($tracked && null !== $step[16]) {
            [$row, $col] = $this->positionTracker->currentOffset();
            $rect = new WidgetRect($row + $lineCount + $step[16], $col, $step[18], $step[19]);
            // What it holds moved with it, tracked where it was drawn: else drawn again, see layoutVertical()
            if ($child instanceof ParentInterface && !$this->positionTracker->moveSubtree($child, $rect)) {
                return null;
            }
            $this->positionTracker->setWidgetRect($child, $rect);
        }
        array_splice($segments, $count + $step[9]);
        /** @var list<LineBufferInterface> $added */
        $added = $step[11];
        array_push($segments, ...$added);
        $step[3] = $count;
        $step[4] = $lineCount;
        $lineCount += (int) $step[12];
        $carry = $step[13];
        $adjoiningMargin = $step[14];
        $pullGroup = $step[23];
        if (!$step[20]) {
            $first = false;
            $previous = $child;
        }

        return $step;
    }

    /**
     * The top or bottom edge of a block, through its first or last child when it has no border nor padding there, like
     * margins collapsing: whether it has a border there, and whether its margin there is less than a cell made one.
     *
     * @return array{bool, bool}
     */
    private function edgeOf(AbstractWidget $widget, bool $top, int $depth = 0): array
    {
        $style = StyleBridge::resolve($this->widgetRenderer, $widget);
        $side = $top ? 0 : 2;
        $border = 0 !== (($top ? $style->getBorder()?->top : $style->getBorder()?->bottom) ?? 0);
        $hairline = (bool) ($style->getMarginHairline()[$side] ?? false);
        if ($border || $hairline || $depth >= 16 || !$widget instanceof ParentInterface || null !== $style->getDirection() || null !== $style->getGridTemplate() || null !== $style->getScroll() || 0 !== (($top ? $style->getPadding()?->top : $style->getPadding()?->bottom) ?? 0)) {
            return [$border, $hairline];
        }
        $edge = $this->inFlowChild(array_values($widget->all()), $top);

        return null !== $edge ? $this->edgeOf($edge, $top, $depth + 1) : [false, false];
    }

    /**
     * Whether a block drawn as nothing has its top and bottom margins collapse through it, like CSS: in the flow, holding
     * nothing in it, with no border, padding nor height of its own, not a formatting context of its own.
     */
    private function collapsesThrough(AbstractWidget $widget): bool
    {
        $style = StyleBridge::resolve($this->widgetRenderer, $widget);
        if (!$widget instanceof ContainerWidget || null === $style->getMarginPull() || $style->getHidden() || null !== $style->getFloat() || $style->getPosition()?->isOutOfFlow() || null !== $style->getMinRows() || null !== $style->getMinRowsPercent() || null !== $this->inFlowChild(array_values($widget->all()), true) || $this->widgetRenderer->establishesFormattingContext($widget)) {
            return false;
        }

        return 0 === ($style->getBorder()->top ?? 0) + ($style->getBorder()->bottom ?? 0) + ($style->getPadding()->top ?? 0) + ($style->getPadding()->bottom ?? 0);
    }

    /**
     * The negative top or bottom margin of a block, as rows it pulls by: with no border nor padding on that side, that of
     * its first or last child collapses into it, like CSS, the larger one winning.
     */
    private function collapsedPull(AbstractWidget $widget, bool $top, int $depth = 0): int
    {
        $style = StyleBridge::resolve($this->widgetRenderer, $widget);
        $pull = ($top ? $style->getMarginPull()?->top : $style->getMarginPull()?->bottom) ?? 0;
        // A block container whose content is in its flow: not a row, a grid, nor a scroll container
        if ($depth >= 16 || !$widget instanceof ParentInterface || null !== $style->getDirection() || null !== $style->getGridTemplate() || null !== $style->getScroll() || 0 !== (($top ? $style->getBorder()?->top : $style->getBorder()?->bottom) ?? 0) || 0 !== (($top ? $style->getPadding()?->top : $style->getPadding()?->bottom) ?? 0)) {
            return $pull;
        }
        if (null === $edge = $this->inFlowChild(array_values($widget->all()), $top)) {
            return $pull;
        }
        // Not through the lines of its ::before or ::after, which come between it and its child, which is pulled over them,
        // see Renderer: looked for only when that child pulls further
        $inner = $this->collapsedPull($edge, $top, $depth + 1);

        return $inner > $pull && !$this->widgetRenderer->drawsGeneratedLines($widget, $top) ? $inner : $pull;
    }

    /**
     * Takes the last lines out of the segments laid out so far.
     *
     * @param list<LineBufferInterface> $segments
     *
     * @return list<string>
     */
    private static function takeLastLines(array &$segments, int $count): array
    {
        $tail = [];
        while ($count > 0 && $segments) {
            $segment = array_pop($segments);
            $lines = $segment->toArray();
            $take = min($count, \count($lines));
            $tail = [...\array_slice($lines, \count($lines) - $take), ...$tail];
            if ($rest = \array_slice($lines, 0, \count($lines) - $take)) {
                $segments[] = ArrayLineBuffer::takenFrom($segment, $rest);
            }
            $count -= $take;
        }

        return $tail;
    }

    /**
     * Where each child comes in its parent, by object id, for the spaces between them in a row laid out reversed or
     * reordered.
     *
     * @param list<AbstractWidget> $children
     *
     * @return array<int, int>
     */
    private function sourceOrder(array $children): array
    {
        $first = reset($children);
        if (false === $first || !($parent = $first->getParent()) instanceof ParentInterface) {
            return [];
        }
        $order = [];
        foreach ($parent->all() as $index => $child) {
            $order[spl_object_id($child)] = $index;
        }

        return $order;
    }

    /**
     * Whether the text has a space between two children shown side by side, the one that comes later in it carrying
     * it: the one on the right, or on the left in a row reversed, like one written right to left.
     *
     * @param array<int, int> $source See sourceOrder()
     */
    private function spaceBetween(AbstractWidget $left, AbstractWidget $right, array $source): bool
    {
        $later = ($source[spl_object_id($left)] ?? 0) > ($source[spl_object_id($right)] ?? 0) ? $left : $right;

        return (bool) $this->widgetRenderer->resolveComputedStyle($later)->getSpaceBefore();
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
     * @param int|null         $minRows  The least height of the row, like a wrapped line stretched by align-content
     * @param bool             $inline   A line of inline content, whose auto margins are none, like CSS
     *
     * @phpstan-impure
     */
    private function layoutHorizontal(array $children, int $columns, int $rows, int $gap, ?VerticalAlign $verticalAlign = null, ?Justify $justify = null, ?int $minRows = null, bool $inline = false, bool $crossReverse = false): LineBufferInterface
    {
        if (!$count = \count($children)) {
            return new ArrayLineBuffer([]);
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
        // The columns between two children the text has a space between, like two words: before the second shown
        $before = [];
        $source = $this->sourceOrder($children);
        $shown = null;
        foreach ($children as $index => $child) {
            if ($this->widgetRenderer->resolveComputedStyle($child)->getHidden()) {
                continue;
            }
            if (null !== $shown && $this->spaceBetween($shown, $child, $source)) {
                $before[$index] = 1;
            }
            $shown = $child;
        }
        $gapColumns = $gap * max(0, $count - 1) + \count($before);
        $availableColumns = max(1, $columns - $gapColumns);
        // The cells of a table row take the widths of their columns, when they fit
        $tableWidths = [];
        foreach ($children as $index => $child) {
            if (!isset($this->cellWidths[$child])) {
                $tableWidths = [];
                break;
            }
            $tableWidths[$index] = $this->cellWidths[$child];
        }
        // Columns taken by cells spanning rows from above are left blank before the cells after them
        $tableOffsets = array_sum(array_map(fn (AbstractWidget $child) => $this->cellOffsets[$child] ?? 0, $tableWidths ? $children : []));
        // Resolve flex values for each child
        $flexValues = [];
        $percents = [];
        $autoMargins = [];
        $anyFlexSet = false;
        foreach ($children as $index => $child) {
            $childStyle = StyleBridge::resolve($this->widgetRenderer, $child);
            $flexValues[$index] = $childStyle->getFlex();
            if (!$inline && null !== $autoMargin = $childStyle->getAutoMargin()) {
                $autoMargins[$index] = $autoMargin;
            }
            if (null !== $percent = $childStyle->getWidthPercent()) {
                $percents[$index] = $childStyle;
            }
            if (null !== $childStyle->getFlex() || null !== $percent || null !== $childStyle->resolveFlexBasis($columns)) {
                $anyFlexSet = true;
            }
        }
        // Compute column widths based on flex values
        $childColumnCounts = $tableWidths && array_sum($tableWidths) + $tableOffsets <= $availableColumns ? $tableWidths : $this->computeFlexColumnWidths($children, $flexValues, $anyFlexSet, $percents, $availableColumns, $rows);
        // The spaces before each child (the gap included) and after the last one
        $spaces = self::distributeFreeColumns($justify, $autoMargins, $count, $availableColumns - array_sum($childColumnCounts), $gap);
        foreach ($before as $index => $columnsBefore) {
            $spaces[$index] += $columnsBefore;
        }
        if ($tableOffsets > 0 && $childColumnCounts === $tableWidths) {
            foreach ($children as $index => $child) {
                $spaces[$index] += $this->cellOffsets[$child] ?? 0;
            }
        }
        // Negative left and right margins: an item pulled over the one before it, never before the row; the columns it
        // frees go after the last item
        $pulled = false;
        $offset = 0;
        foreach (array_values($children) as $index => $child) {
            $pull = $this->leftPull($child) + ($index > 0 ? StyleBridge::resolve($this->widgetRenderer, $children[array_keys($children)[$index - 1]])?->getMarginPull()->right ?? 0 : 0);
            $key = array_keys($children)[$index];
            $pull = min($pull, $offset + $spaces[$key]);
            if ($pull > 0) {
                $spaces[$key] -= $pull;
                $spaces[$count] += $pull;
                $pulled = $pulled || $spaces[$key] < 0;
            }
            $offset += $spaces[$key] + $childColumnCounts[$key];
        }
        $childRenders = [];
        $childContexts = [];
        $cacheHits = [];
        $maxRows = 0;
        $hasPositionStack = $this->positionTracker->isActive();
        $colOffset = 0;
        $childColOffsets = [];
        foreach ($children as $index => $child) {
            $childColumns = $childColumnCounts[$index];
            $colOffset += $spaces[$index];
            $childColOffsets[$index] = $colOffset;
            // Push correct absolute position for this horizontal child
            // so descendants get proper coordinates during rendering
            if ($hasPositionStack) {
                [$absRow, $absCol] = $this->positionTracker->currentOffset();
                $this->positionTracker->push($absRow, $absCol + $colOffset);
            }
            // The item of a row takes percentages of the row, not of its share
            $context = (new RenderContext($childColumns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
            $revision = $child->getRenderRevision();
            $childLines = $this->widgetRenderer->renderLines($child, $context);
            $hadCachedRender = $this->widgetRenderer->wasRenderCached($child);
            $cacheHits[$index] = $hadCachedRender && $revision === $child->getRenderRevision();
            $childContexts[$index] = $context;
            $childRenders[$index] = $childLines;
            $maxRows = max($maxRows, \count($childLines));
            if ($hasPositionStack) {
                $this->positionTracker->pop();
            }
            $colOffset += $childColumns;
        }
        // A row of a table with cells spanning rows: as tall as its other cells, those cells drawn across the rows
        $rowWidget = $children ? reset($children)->getParent() : null;
        $spanning = [];
        if (null !== $rowWidget && isset($this->rowHeights[$rowWidget])) {
            // At least as tall as its other cells drawn, which can be taller than measured at the widths of the columns
            $maxRows = $this->rowHeights[$rowWidget];
            foreach ($children as $index => $child) {
                if (isset($this->spanningCells[$child])) {
                    $spanning[$index] = $this->spanningCells[$child];
                } else {
                    $maxRows = max($maxRows, \count($childRenders[$index]));
                }
            }
        }
        // Items without content in a row of a height, like the bar of a progress of Bootstrap holding hidden text only:
        // stretched to it below
        $maxRows = max($maxRows, $minRows ?? 0);
        if (null !== $minRows && array_any(array_keys($children), function (int|string $index) use ($children, $childRenders, $minRows): bool {
            if ($this->containsReplacedElement($children[$index])) {
                return false;
            }
            $style = StyleBridge::resolve($this->widgetRenderer, $children[$index]);
            // Its box taller than the row, its border too, like its content: drawn over what follows, not cut by the height
            // of the row; its margins aside
            $chrome = ($style->getMargin()->top ?? 0) + ($style->getMargin()->bottom ?? 0);
            $scroll = $style->getScroll();
            if ($children[$index] instanceof ContainerWidget && $scroll?->horizontal && $scroll->bar && ($scroll->alwaysSideways || ContainerState::of($children[$index])->getScrollWidth() > ContainerState::of($children[$index])->getClientWidth())) {
                ++$chrome;
            }

            return \count($childRenders[$index]) - $chrome > $minRows;
        })) {
            $maxRows = $minRows;
        }
        if (0 === $maxRows) {
            return new ArrayLineBuffer([]);
        }
        foreach ($spanning as $index => [$spanRows, $below]) {
            // At its column, like the items above: what it holds is tracked where it is drawn
            if ($hasPositionStack) {
                [$absRow, $absCol] = $this->positionTracker->currentOffset();
                $this->positionTracker->push($absRow, $absCol + $childColOffsets[$index]);
            }
            $revision = $children[$index]->getRenderRevision();
            $childRenders[$index] = $this->widgetRenderer->renderStretchedLines($children[$index], $childContexts[$index], $spanRows);
            $cacheHits[$index] = $this->widgetRenderer->wasRenderCached($children[$index]) && $revision === $children[$index]->getRenderRevision();
            if ($hasPositionStack) {
                $this->positionTracker->pop();
            }
            // Its lines past this row, in the rows below, at its column
            $from = $maxRows;
            foreach ($below as $belowRow) {
                $rowsBelow = $this->rowHeights[$belowRow] ?? 1;
                $part = [];
                for ($i = 0; $i < $rowsBelow; ++$i) {
                    $part[] = $from + $i < \count($childRenders[$index]) ? $childRenders[$index]->getLine($from + $i) : '';
                }
                $from += $rowsBelow;
                $carry = [$childColOffsets[$index], $childColumnCounts[$index], $part];
                $previous = $this->carried[$belowRow] ?? [];
                $key = spl_object_id($children[$index]);
                if (($previous[$key] ?? null) !== $carry) {
                    $previous[$key] = $carry;
                    $this->carried[$belowRow] = $previous;
                    $belowRow->clearRenderCache();
                }
            }
        }
        // Stretched, the shorter children are rendered again at the height of the row
        $stretched = [];
        $aligns = [];
        foreach ($children as $index => $child) {
            $childStyle = $this->widgetRenderer->resolveComputedStyle($child);
            $aligns[$index] = $childStyle->getAlignSelf() ?? $verticalAlign;
            // flex-start and flex-end in a line of a wrap-reverse row, its cross start at the bottom
            if ($crossReverse && $childStyle->isAlignSelfFlexRelative()) {
                $aligns[$index] = VerticalAlign::Top === $aligns[$index] ? VerticalAlign::Bottom : VerticalAlign::Top;
            }
        }
        $bordered = array_any($children, fn (AbstractWidget $child): bool => ($this->widgetRenderer->resolveComputedStyle($child)->getBorder()->top ?? 0) > 0);
        if ($bordered) {
            foreach ($children as $index => $child) {
                $border = $this->widgetRenderer->resolveComputedStyle($child)->getBorder();
                if (VerticalAlign::Stretch === $aligns[$index] && null !== $border && 0 === $border->top + $border->bottom && ($border->left > 0 || $border->right > 0) && ' ' === $border->pattern->getChars()[1][0] && ' ' === $border->pattern->getChars()[1][2]) {
                    $aligns[$index] = VerticalAlign::Center;
                }
            }
        }
        if (\in_array(VerticalAlign::Stretch, $aligns, true)) {
            foreach ($children as $index => $child) {
                if (VerticalAlign::Stretch !== $aligns[$index] || \count($childRenders[$index]) >= $maxRows || isset($spanning[$index]) || $this->setsHeight($child)) {
                    continue;
                }
                if ($hasPositionStack) {
                    [$absRow, $absCol] = $this->positionTracker->currentOffset();
                    $this->positionTracker->push($absRow, $absCol + $childColOffsets[$index]);
                }
                $revision = $child->getRenderRevision();
                $childRenders[$index] = $this->widgetRenderer->renderStretchedLines($child, $childContexts[$index], $maxRows);
                $hadCachedRender = $this->widgetRenderer->wasRenderCached($child);
                $stretched[$index] = true;
                $cacheHits[$index] = $hadCachedRender && $revision === $child->getRenderRevision();
                if ($hasPositionStack) {
                    $this->positionTracker->pop();
                }
            }
        }
        // Compute per-child vertical offset for cross-axis alignment (align-items).
        // This positions shorter children relative to the tallest, analogous to
        // CSS align-items on a flex row.
        $childOffsets = [];
        foreach ($children as $index => $child) {
            $childHeight = \count($childRenders[$index]);
            $childOffsets[$index] = match ($aligns[$index]) {
                VerticalAlign::Center => $maxRows - $childHeight >> 1,
                VerticalAlign::Bottom => $maxRows - $childHeight,
                default => 0,
            };
            // An item past a height it sets, see Renderer::overflowSetHeight(): its rows past it, drawn from the top of the
            // row when the row cuts it too, else from its own top
            $own = $this->overflowingItems[$child] ?? null;
            if (true === ($own[4] ?? null) && $own[0] === $child->getRenderRevision()) {
                if ($hasPositionStack) {
                    $cut = null !== $minRows && $childHeight > $maxRows;
                    $this->overflowingItems[$child] = [$own[0], $own[1], $cut ? $childOffsets[$index] : 0, $cut ? $maxRows : $childHeight, true, $own[5] ?? $childHeight];
                }
                continue;
            }
            if ($hasPositionStack) {
                unset($this->overflowingItems[$child]);
            }
            if (null !== $minRows && $childHeight > $maxRows && !$this->containsReplacedElement($child) && null !== $rowWidget && null === $this->widgetRenderer->resolveComputedStyle($rowWidget)->getTableRole() && ($hasPositionStack || !isset($this->overflowingItems[$child]))) {
                $this->overflowingItems[$child] = [$child->getRenderRevision(), $childRenders[$index], $childOffsets[$index], $maxRows];
            }
        }
        // Track widget positions for horizontal children, accounting for vertical offsets.
        if ($hasPositionStack) {
            [$absRow, $absCol] = $this->positionTracker->currentOffset();
            $colOffset = 0;
            foreach ($children as $index => $child) {
                $colOffset += $spaces[$index];
                $childAbsRow = $absRow + $childOffsets[$index];
                $childAbsCol = $absCol + $colOffset;
                $rect = new WidgetRect($childAbsRow, $childAbsCol, $childColumnCounts[$index], \count($childRenders[$index]));
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
                        if (isset($stretched[$index])) {
                            $this->widgetRenderer->renderStretchedLines($child, $childContexts[$index], $maxRows);
                        } else {
                            $this->widgetRenderer->renderLines($child, $childContexts[$index]);
                        }
                        $this->positionTracker->pop();
                    }
                }
                $this->positionTracker->setWidgetRect($child, $rect);
                $colOffset += $childColumnCounts[$index];
            }
        }
        $lines = [];
        // The parts of the row composed last, and what they made: the rows alike of tall items, like those of a tall
        // padding, are composed once
        $composedKey = null;
        $composed = '';
        // No item over another, none touching the one before it: each line the items one after the other, nothing to join
        $plain = !$pulled && !array_any(array_keys($children), static fn (int|string $index): bool => $index !== array_key_first($children) && 0 === $spaces[$index]);
        // The room of each item before it, its blank part past its rows, and its rows
        $pads = $blanks = $heights = [];
        foreach ($children as $index => $child) {
            $pads[$index] = str_repeat(' ', max(0, $spaces[$index]));
            $blanks[$index] = str_repeat(' ', $childColumnCounts[$index]);
            $heights[$index] = \count($childRenders[$index]);
        }
        $tail = str_repeat(' ', $spaces[$count]);
        // The columns the items and the spaces before them do not take
        $this->freeColumns = max(0, $columns - array_sum($childColumnCounts) - array_sum($spaces) + $spaces[$count]);
        // The box glyph each part of a line starts and ends with, '' for none; the lines with the cells spanning rows
        $firstGlyphs = $lastGlyphs = $spliced = [];
        for ($row = 0; $row < $maxRows; ++$row) {
            if ($plain) {
                $line = '';
                foreach ($children as $index => $child) {
                    $childRow = $row - $childOffsets[$index];
                    if ($childRow < 0 || $childRow >= $heights[$index]) {
                        $line .= $pads[$index].$blanks[$index];
                        continue;
                    }
                    $part = $childRenders[$index]->getLine($childRow);
                    $visibleLen = AnsiUtils::visibleWidth($part);
                    $cols = $childColumnCounts[$index];
                    $line .= $pads[$index].($visibleLen > $cols ? AnsiUtils::truncateToWidth($part, $cols, '') : ($visibleLen < $cols ? $part.str_repeat(' ', $cols - $visibleLen) : $part));
                }
                $lines[] = $this->withCarried($line.$tail, $row, $rowWidget, $spliced);
                continue;
            }
            $line = '';
            // Pulled over each other: the items drawn one after the other, their spaces letting those before them show
            $layers = $pulled ? [new Layer([''], 0, 0, false, $columns, 1)] : null;
            $column = 0;
            $previous = null;
            foreach ($children as $index => $child) {
                $line .= $pads[$index];
                $column += $spaces[$index];
                $childRow = $row - $childOffsets[$index];
                $cols = $childColumnCounts[$index];
                if ($childRow < 0 || $childRow >= $heights[$index]) {
                    $part = $blanks[$index];
                } else {
                    $part = $childRenders[$index]->getLine($childRow);
                    $visibleLen = AnsiUtils::visibleWidth($part);
                    if ($visibleLen > $cols) {
                        $part = AnsiUtils::truncateToWidth($part, $cols, '');
                    } elseif ($visibleLen < $cols) {
                        $part .= str_repeat(' ', $cols - $visibleLen);
                    }
                }
                if (null !== $layers) {
                    // Over the last column of the item before it, like a button of a group over the border of the one before
                    // it: their lines join, ┐ and ┌ making ┬
                    if (null !== $previous && $column < $previous[0] + $previous[1] && $column >= $previous[0] && '' !== $part) {
                        $part = self::joinFirstGlyph($part, AnsiUtils::stripAnsiCodes(AnsiUtils::sliceToWidth($previous[2], $column - $previous[0], 1)));
                    }
                    $layers[] = new Layer([$part], 0, $column, true);
                    $previous = [$column, $cols, $part];
                    $line .= $part;
                } else {
                    // Touching the item before it: the lines of boxes that touch join, ┐ and ─ making ┬; the glyphs at the
                    // edges of each part found once, the parts of an item repeating from row to row
                    if (0 === $spaces[$index] && null !== $previous && '' !== ($first = $firstGlyphs[$part] ??= self::edgeGlyph($part, true)) && '' !== ($last = $lastGlyphs[$previous] ??= self::edgeGlyph($previous, false)) && self::joins($last, $first)) {
                        self::joinTouching($line, $part);
                    }
                    $line .= $part;
                    $previous = $part;
                }
                $column += $cols;
            }
            if (null !== $layers) {
                $key = $line;
                if ($key !== $composedKey) {
                    $composedKey = $key;
                    $composed = Compositor::composite(...$layers)[0];
                }
                $line = $composed;
            } else {
                $line .= $tail;
            }
            $lines[] = $this->withCarried($line, $row, $rowWidget, $spliced);
        }

        return new ArrayLineBuffer($lines);
    }

    /**
     * A line of a row with the lines of the cells spanning rows from above, in the columns they take.
     *
     * @param array<int, array<string, array<string, string>>> $memo The lines spliced, by cell, line and piece
     */
    private function withCarried(string $line, int $row, ?AbstractWidget $rowWidget, array &$memo): string
    {
        foreach (null !== $rowWidget ? $this->carried[$rowWidget] ?? [] : [] as $index => [$x, $width, $part]) {
            // The same line with the same piece spliced once: the rows alike, like those of a tall padding, share it
            $source = $part[$row] ?? '';
            if (isset($memo[$index][$line][$source])) {
                $line = $memo[$index][$line][$source];
                continue;
            }
            $piece = AnsiUtils::sliceToWidth($source, 0, $width);
            $piece .= str_repeat(' ', max(0, $width - AnsiUtils::visibleWidth($piece)));
            // Slices are padded: the line keeps its width
            $line = $memo[$index][$line][$source] = AnsiUtils::sliceToWidth($line, 0, $x)."\x1b[0m".$piece."\x1b[0m".AnsiUtils::sliceToWidth($line, $x + $width, max(0, AnsiUtils::visibleWidth($line) - $x - $width));
        }

        return $line;
    }

    /**
     * The columns an item of a row is pulled left by: its negative left margin, or that of its first child when nothing
     * of its own is on its left, like the .page-link of a .page-item of a pagination of Bootstrap, over the one before.
     */
    private function leftPull(AbstractWidget $item): int
    {
        for ($depth = 0; $depth < 8; ++$depth) {
            $style = StyleBridge::resolve($this->widgetRenderer, $item);
            if (0 < $pull = $style->getMarginPull()->left ?? 0) {
                return $pull;
            }
            if (!$item instanceof ContainerWidget || null === ($first = $item->all()[0] ?? null) || ($style->getBorder()->left ?? 0) + ($style->getPadding()->left ?? 0) + ($style->getMargin()->left ?? 0) > 0 || Direction::Horizontal === $style->getDirection() && \count($item->all()) > 1) {
                return 0;
            }
            $item = $first;
        }

        return 0;
    }

    /**
     * Two lines one above the other, the lines of boxes in them joined where one goes on from the other, like └ over │
     * making ├ over │, or ─ over │ making ┬; null when none does.
     *
     * @return array{null, null}|array{string, string}
     */
    private static function joinStacked(string $upper, string $lower): array
    {
        if (!preg_match('/[─│┌┐└┘├┤┬┴┼╭╮╰╯]/u', $upper) || !preg_match('/[│├┤┼└┘┴╰╯┌┐╭╮┬]/u', $lower)) {
            return [null, null];
        }
        $glyphs = static function (string $line): array {
            $found = [];
            $column = 0;
            foreach (grapheme_str_split(AnsiUtils::stripAnsiCodes($line)) ?: [] as $glyph) {
                if (isset(self::BOX_ARMS[$glyph])) {
                    $found[$column] = $glyph;
                }
                $column += AnsiUtils::graphemeWidth($glyph);
            }

            return $found;
        };
        $top = $glyphs($upper);
        $bottom = $glyphs($lower);
        // Up is 0b1000, down 0b0100, left 0b0010, right 0b0001: an arm met by the other line's; a side arm met by
        // nothing next to it dropped, like the end of a line turning down, ─ making ┌ and not ┬
        $sides = static fn (array $line, int $column, int $arms): int => $arms & (0 !== ((self::BOX_ARMS[$line[$column - 1] ?? ' '] ?? 0) & 0b1) ? 0b1111 : 0b1101) & (0 !== ((self::BOX_ARMS[$line[$column + 1] ?? ' '] ?? 0) & 0b10) ? 0b1111 : 0b1110);
        $upperArms = [];
        $lowerArms = [];
        foreach (array_intersect_key($top, $bottom) as $column => $glyph) {
            $under = $bottom[$column];
            if (0 === (self::BOX_ARMS[$glyph] & 0b100) && 0 !== (self::BOX_ARMS[$under] & 0b1000)) {
                $upperArms[$column] = $sides($top, $column, self::BOX_ARMS[$glyph] | 0b100);
            }
            if (0 === (self::BOX_ARMS[$under] & 0b1000) && 0 !== (self::BOX_ARMS[$glyph] & 0b100)) {
                $lowerArms[$column] = $sides($bottom, $column, self::BOX_ARMS[$under] | 0b1000);
            }
        }
        if (!$upperArms && !$lowerArms) {
            return [null, null];
        }

        return [self::withArms($upper, $upperArms), self::withArms($lower, $lowerArms)];
    }

    /**
     * A line with the glyphs of boxes at some columns replaced by those of other arms.
     *
     * @param array<int, int> $arms
     */
    private static function withArms(string $line, array $arms): string
    {
        if (!$arms) {
            return $line;
        }
        $result = '';
        $column = 0;
        foreach (preg_split('/(\e\[[0-9;:]*m)/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ("\x1b" === $part[0]) {
                $result .= $part;
                continue;
            }
            foreach (grapheme_str_split($part) ?: [] as $glyph) {
                if (isset($arms[$column], self::BOX_ARMS[$glyph])) {
                    $glyph = self::glyphOf($arms[$column], $glyph);
                }
                $result .= $glyph;
                $column += AnsiUtils::graphemeWidth($glyph);
            }
        }

        return $result;
    }

    /**
     * The glyph of boxes of those arms: the one there when it has them already, like a rounded corner joined with
     * nothing new, else the first of them.
     */
    private static function glyphOf(int $arms, string $current): string
    {
        return (self::BOX_ARMS[$current] ?? null) === $arms ? $current : (array_search($arms, self::BOX_ARMS, true) ?: $current);
    }

    /**
     * The box glyph a part of a line starts or ends with, past its colors; '' for none.
     */
    private static function edgeGlyph(string $part, bool $first): string
    {
        if ($first) {
            $start = 0;
            while ("\x1b[" === substr($part, $start, 2) && 'm' === ($part[$start + 2 + $digits = strspn($part, '0123456789;:', $start + 2)] ?? '')) {
                $start += 3 + $digits;
            }
            $glyph = substr($part, $start, 3);
        } else {
            $end = \strlen($part);
            while ($end > 2 && 'm' === $part[$end - 1] && false !== ($escape = strrpos($part, "\x1b[", $end - \strlen($part) - 1)) && $escape + 2 + strspn($part, '0123456789;:', $escape + 2) === $end - 1) {
                $end = $escape;
            }
            $glyph = $end >= 3 ? substr($part, $end - 3, 3) : '';
        }

        return isset(self::BOX_ARMS[$glyph]) ? $glyph : '';
    }

    /**
     * Whether a box glyph and the one after it join, like ┐ and ─ making ┬, not │ and │.
     */
    private static function joins(string $left, string $right): bool
    {
        [$a, $b] = [self::BOX_ARMS[$left], self::BOX_ARMS[$right]];

        return 0 === ($a & 0b1) && 0 !== ($b & 0b10) && 0 !== ($a & 0b1100) || 0 === ($b & 0b10) && 0 !== ($a & 0b1) && 0 !== ($b & 0b1100);
    }

    /**
     * Two parts of a line side by side, the lines of boxes where they touch joined, like the ┐ of an item of a list
     * group of Bootstrap and the ─ of the next one, without a left border of its own, making ┬: changed in place, the
     * line not copied for each item of a row.
     */
    private static function joinTouching(string &$left, string &$right): void
    {
        // The glyph the right part starts with, past its colors, then the one the left ends with, before them: every
        // box glyph is three bytes, found without scanning the line, which grows with each item of the row
        $start = 0;
        while ("\x1b[" === substr($right, $start, 2) && 'm' === ($right[$start + 2 + $digits = strspn($right, '0123456789;:', $start + 2)] ?? '')) {
            $start += 3 + $digits;
        }
        if (!isset(self::BOX_ARMS[$first = substr($right, $start, 3)])) {
            return;
        }
        $end = \strlen($left);
        while ($end > 2 && 'm' === $left[$end - 1] && false !== ($escape = strrpos($left, "\x1b[", $end - \strlen($left) - 1)) && $escape + 2 + strspn($left, '0123456789;:', $escape + 2) === $end - 1) {
            $end = $escape;
        }
        if ($end < 3 || !isset(self::BOX_ARMS[$last = substr($left, $end - 3, 3)])) {
            return;
        }
        [$a, $b] = [self::BOX_ARMS[$last], self::BOX_ARMS[$first]];
        // Up and down 0b1100, left 0b0010, right 0b0001: a corner or an edge meeting a line that goes on from it
        if (0 === ($a & 0b1) && 0 !== ($b & 0b10) && 0 !== ($a & 0b1100)) {
            $left = substr($left, 0, $end - 3).(array_search($a | 0b1, self::BOX_ARMS, true) ?: $last).substr($left, $end);
        } elseif (0 === ($b & 0b10) && 0 !== ($a & 0b1) && 0 !== ($b & 0b1100)) {
            $right = substr($right, 0, $start).(array_search($b | 0b10, self::BOX_ARMS, true) ?: $first).substr($right, $start + 3);
        }
    }

    /**
     * The first glyph of a line drawn over a glyph, both lines of a box, joined, like ┐ and ┌ making ┬; the line as it is
     * when either is not a line of a box.
     */
    private static function joinFirstGlyph(string $line, string $under): string
    {
        if (!isset(self::BOX_ARMS[$under]) || !preg_match('/^((?:\e\[[0-9;:]*m)*)(.)/u', $line, $m) || !isset(self::BOX_ARMS[$m[2]])) {
            return $line;
        }
        $arms = self::BOX_ARMS[$under] | self::BOX_ARMS[$m[2]];

        return $m[1].self::glyphOf($arms, $m[2]).substr($line, \strlen($m[0]));
    }

    /**
     * Shares the free columns of a row like justify-content.
     *
     * Auto margins take the free columns first, like in CSS.
     *
     * @param array<int, AutoMargin> $autoMargins By child index
     *
     * @return array<int, int> The spaces before each child (by index), the gap included, then after the last one (at the count)
     */
    private static function distributeFreeColumns(?Justify $justify, array $autoMargins, int $count, int $free, int $gap): array
    {
        $spaces = array_fill(0, $count + 1, $gap);
        $spaces[0] = $spaces[$count] = 0;
        if ($free <= 0) {
            return $spaces;
        }
        // Weights of each slot: before the first child, between children, after the last one
        if ($autoMargins) {
            $weights = array_fill(0, $count + 1, 0);
            foreach ($autoMargins as $index => $autoMargin) {
                $weights[$index] += $autoMargin->hasLeft() ? 1 : 0;
                $weights[$index + 1] += $autoMargin->hasRight() ? 1 : 0;
            }
        } elseif (null === $justify || Justify::SpaceBetween === $justify && $count < 2) {
            // Nothing to share, or a single child: space-between falls back to the start, like CSS
            return $spaces;
        } else {
            $weights = match ($justify) {
                Justify::SpaceBetween => [0, ...array_fill(0, $count - 1, 1), 0],
                Justify::SpaceAround => [1, ...array_fill(0, $count - 1, 2), 1],
                Justify::SpaceEvenly => array_fill(0, $count + 1, 1),
            };
        }
        $totalWeight = array_sum($weights);
        // Largest remainder, ties going to the slots between children first
        $remainders = [];
        foreach ($weights as $slot => $weight) {
            $spaces[$slot] += intdiv($free * $weight, $totalWeight);
            $remainders[$slot] = $free * $weight % $totalWeight;
        }
        $left = $free - array_sum($spaces) + $gap * ($count - 1);
        uksort($remainders, static fn (int $a, int $b) => [$remainders[$b], 0 === $a || $count === $a, $a] <=> [$remainders[$a], 0 === $b || $count === $b, $b]);
        foreach (\array_slice(array_keys($remainders), 0, $left) as $slot) {
            ++$spaces[$slot];
        }

        return $spaces;
    }

    /**
     * The least width of an item of a flex row: its min-width, a percentage of it a share of the row, like CSS.
     */
    private function leastColumns(Style $style, int $rowColumns): ?int
    {
        $percent = $style->getMinWidthPercent();
        if (null === $percent) {
            return $style->getMinColumns();
        }

        return max($style->getMinColumns() ?? 0, (int) floor($rowColumns * $percent / 100));
    }

    /**
     * Compute column widths for horizontal children based on flex values.
     *
     * When no child has flex set, falls back to equal distribution.
     * flex: 0 children get their intrinsic width (measured by rendering).
     * flex: N children share remaining space proportionally.
     *
     * @param AbstractWidget[]           $children
     * @param array<int, int|float|null> $flexValues
     * @param array<int, Style>          $percents   The styles of the children with a percentage width, which comes before flex
     *
     * @return array<int, int>
     */
    private function computeFlexColumnWidths(array $children, array $flexValues, bool $anyFlexSet, array $percents, int $availableColumns, int $rows): array
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
        // The widths growing children start from, before their share of the free columns
        $bases = [];
        $totalFlexWeight = 0;
        $usedColumns = 0;
        // The columns of the margins of each child, around the width or the basis set, which size its box, like CSS
        $margins = [];
        $savedStack = $this->positionTracker->suppressStack();
        foreach ($children as $index => $child) {
            $basis = $this->widgetRenderer->resolveComputedStyle($child)->resolveFlexBasis($availableColumns);
            $margin = $this->widgetRenderer->resolveComputedStyle($child)->getMargin();
            $margins[$index] = max(0, $margin->left ?? 0) + max(0, $margin->right ?? 0);
            if (null !== $basis && $basis > 0 && !isset($percents[$index]) && (0 === $flexValues[$index] || null === $flexValues[$index])) {
                // A fixed starting width instead of the content, within its min-width and max-width, like CSS; a basis of 0
                // keeps the content, the closest to its min-content
                $childStyle = $this->widgetRenderer->resolveComputedStyle($child);
                $width = max(1, min($availableColumns, max(min($basis, $childStyle->getRowMaxColumns() ?? \PHP_INT_MAX), $this->leastColumns($childStyle, $availableColumns) ?? 0) + $margins[$index]));
                $intrinsicWidths[$index] = $width;
                $usedColumns += $width;
                continue;
            }
            if (null !== $basis && $basis > 0 && null !== $flexValues[$index] && $flexValues[$index] > 0) {
                $bases[$index] = min($availableColumns, $basis + $margins[$index]);
                $usedColumns += $bases[$index];
            } elseif (null === $basis && (!isset($percents[$index]) || $percents[$index]->isWidthPercentMax()) && null !== $flexValues[$index] && $flexValues[$index] > 0 && $this->widgetRenderer->resolveComputedStyle($child)->hasContentBasis()) {
                // A basis of auto: growing from its content, like flex: 1 1 auto, the buttons of a group of Bootstrap
                $bases[$index] = min($availableColumns, $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows));
                $usedColumns += $bases[$index];
            }
            if (isset($percents[$index]) && null !== ($flex = $flexValues[$index]) && $flex > 0 && !$percents[$index]->isWidthPercentMax()) {
                // Growing from that share, the width being the basis of a flex item, like the width: 1% and flex: 1 1 auto
                // of the field of an input group of Bootstrap; a flex-basis set comes first
                if (!isset($bases[$index])) {
                    $bases[$index] = min($availableColumns, (int) $percents[$index]->resolveWidthPercent($availableColumns) + $margins[$index]);
                    $usedColumns += $bases[$index];
                }
                $flexWeights[$index] = $flex;
                $totalFlexWeight += $flex;
            } elseif (isset($percents[$index]) && !($percents[$index]->isWidthPercentMax() && $flexValues[$index] > 0)) {
                // A share of the row, like a percentage width in CSS; a max-width one, the content up to that share, unless
                // it grows: up to that share then, see below
                $width = (int) $percents[$index]->resolveWidthPercent($availableColumns);
                if ($percents[$index]->isWidthPercentMax()) {
                    $width = max(1, min($width, $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows)));
                } else {
                    // Within its min-width and max-width, like CSS
                    $width = max(1, min($availableColumns, max(min($width, $percents[$index]->getRowMaxColumns() ?? \PHP_INT_MAX), $this->leastColumns($percents[$index], $availableColumns) ?? 0) + $margins[$index]));
                }
                $intrinsicWidths[$index] = $width;
                $usedColumns += $width;
            } elseif (0 === $flex = $flexValues[$index]) {
                // Intrinsic width: measure the child's natural content width
                // plus chrome (border/padding). This uses measureIntrinsicWidth()
                // instead of renderWidgetLines() because renderWidgetLines() pads lines
                // to the full allocated width via ChromeApplier.
                $width = $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows);
                $width = max($width, min($this->leastColumns($this->widgetRenderer->resolveComputedStyle($child), $availableColumns) ?? 0, $availableColumns));
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
        // The bases of growing children shrink with the intrinsic widths
        $intrinsicBudget = $availableColumns - \count(array_diff_key($flexWeights, $bases));
        $sized = $intrinsicWidths + $bases;
        if ($sized && array_sum($sized) > $intrinsicBudget) {
            $shrinks = [];
            $minimums = [];
            $automatic = [];
            foreach ($sized as $index => $width) {
                $childStyle = StyleBridge::resolve($this->widgetRenderer, $children[$index]);
                $shrinks[$index] = $childStyle->getShrink() ?? 1;
                if (null !== $minimum = $this->leastColumns($childStyle, $availableColumns)) {
                    $minimums[$index] = $minimum;
                } else {
                    // Its min-content, like the min-width: auto of CSS: a label is not broken in a word; all of it for
                    // one that does not shrink
                    $automatic[$index] = 0.0 === (float) $shrinks[$index] ? $width : min($width, $this->widgetRenderer->measureMinContentWidth($children[$index], $availableColumns, $rows));
                }
            }
            // The automatic minimums when they fit with the others, else the soft ones, a control ellipsized and an image
            // scaled down, not a word broken, like the links of the header of Cloudflare beside its search field, else the
            // min-widths alone
            if (array_sum($minimums + $automatic) > $intrinsicBudget) {
                foreach ($automatic as $index => $minimum) {
                    if (0.0 !== (float) $shrinks[$index]) {
                        $automatic[$index] = min($minimum, $this->widgetRenderer->measureSoftMinContentWidth($children[$index], $availableColumns, $rows));
                    }
                }
            }
            $floors = array_sum($minimums + $automatic) <= $intrinsicBudget ? $minimums + $automatic : $minimums;
            // Weighed by their sizes inside their margins, which they keep, like the scaled shrink factors of CSS
            $around = array_intersect_key($margins, $sized);
            if (array_sum($around) > 0 && $intrinsicBudget - array_sum($around) >= \count($sized)) {
                $inner = $innerFloors = [];
                foreach ($sized as $index => $width) {
                    $inner[$index] = max(1, $width - $around[$index]);
                }
                foreach ($floors as $index => $floor) {
                    // A min-width is that of the box already; the automatic minimum, a min-content, counts the margins
                    $innerFloors[$index] = isset($minimums[$index]) ? $floor : max(0, $floor - $around[$index]);
                }
                foreach (self::shrinkWithMinimums($inner, $intrinsicBudget - array_sum($around), $shrinks, $innerFloors) as $index => $width) {
                    $sized[$index] = $width + $around[$index];
                }
            } else {
                $sized = self::shrinkWithMinimums($sized, $intrinsicBudget, $shrinks, $floors);
            }
            $intrinsicWidths = array_intersect_key($sized, $intrinsicWidths);
            $bases = array_intersect_key($sized, $bases);
            $usedColumns = array_sum($sized);
        }
        // Second pass: distribute remaining space among flex children
        // The least and the most columns of the growing children, their min-width and max-width: one that its share takes
        // past them is frozen there, the others sharing the rest, like CSS
        $limits = [];
        foreach ($flexWeights as $index => $_) {
            $childStyle = StyleBridge::resolve($this->widgetRenderer, $children[$index]);
            $most = $childStyle->getRowMaxColumns();
            if (isset($percents[$index]) && $percents[$index]->isWidthPercentMax()) {
                $most = min($most ?? \PHP_INT_MAX, (int) $percents[$index]->resolveWidthPercent($availableColumns));
            }
            $least = null !== ($minimum = $this->leastColumns($childStyle, $availableColumns)) ? $minimum + $margins[$index] : null;
            // Growing beside other growing items only, its min-content, like the min-width: auto of CSS: a word not broken
            // at its share; beside items of their own width, see below
            if (null === $least && !$intrinsicWidths && \count($flexWeights) > 1) {
                $savedStack = $this->positionTracker->suppressStack();
                $least = min($this->widgetRenderer->measureMinContentWidth($children[$index], $availableColumns, $rows), null !== $most ? $most + $margins[$index] : \PHP_INT_MAX);
                $this->positionTracker->restoreStack($savedStack);
            }
            if (null !== $most || null !== $least) {
                $limits[$index] = [min($availableColumns, $least ?? 0), null !== $most ? max(1, $most + $margins[$index]) : \PHP_INT_MAX];
            }
        }
        $frozen = [];
        $initialColumns = max(0, $availableColumns - $usedColumns);
        while (true) {
            $growing = array_diff_key($flexWeights, $frozen);
            $totalFlexWeight = array_sum($growing);
            $remainingColumns = max(0, $initialColumns - array_sum(array_map(static fn (int|string $index, int $width): int => $width - ($bases[$index] ?? 0), array_keys($frozen), $frozen)));
            // Factors summing to less than 1 share that part of the free columns only, like CSS: flex: 0.5 alone takes half
            if ($totalFlexWeight > 0 && $totalFlexWeight < 1) {
                $remainingColumns = min($remainingColumns, (int) floor($initialColumns * $totalFlexWeight));
            }
            $result = [];
            $flexAllocated = 0;
            $flexColumnsUsed = 0;
            $flexCount = \count($growing);
            foreach ($children as $index => $child) {
                if (isset($intrinsicWidths[$index])) {
                    $result[$index] = $intrinsicWidths[$index];
                } elseif (isset($frozen[$index])) {
                    $result[$index] = $frozen[$index];
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
                    $result[$index] = max(1, ($bases[$index] ?? 0) + $allocated);
                    $flexColumnsUsed += $result[$index] - ($bases[$index] ?? 0);
                }
            }
            // The total violation: those under their minimum frozen when it adds, those over their maximum when it takes,
            // all of them when nothing
            $under = $over = [];
            foreach (array_intersect_key($limits, $growing) as $index => [$least, $most]) {
                $clamped = max($least, min($most, $result[$index]));
                if ($clamped > $result[$index]) {
                    $under[$index] = $clamped;
                } elseif ($clamped < $result[$index]) {
                    $over[$index] = $clamped;
                }
            }
            $violation = array_sum($under) - array_sum(array_intersect_key($result, $under)) + array_sum($over) - array_sum(array_intersect_key($result, $over));
            $violators = match (true) {
                $violation > 0 => $under,
                $violation < 0 => $over,
                default => $under + $over,
            };
            // Minimums the row cannot hold with the other children at their bases are left aside, the row never wider than
            // its columns
            if (!$violators || $violation > 0 && array_sum($intrinsicWidths) + array_sum($frozen) + array_sum($violators) + array_sum(array_map(static fn (int|string $index): int => max(1, $bases[$index] ?? 0), array_keys(array_diff_key($growing, $violators)))) > $availableColumns) {
                break;
            }
            $frozen += $violators;
        }
        // A growing child narrower than its longest word, like the min-width: auto of CSS: where a browser would overflow
        // the row, a terminal cannot, so its siblings give their columns past their own min-content, like the logo of a set
        // width of htmx beside its menu, not a link broken in a word
        if ($intrinsicWidths && $flexWeights) {
            $savedStack = $this->positionTracker->suppressStack();
            // The growing children first, their siblings measured only when one of them is too narrow
            $raised = $result;
            foreach ($flexWeights as $index => $_) {
                $childStyle = StyleBridge::resolve($this->widgetRenderer, $children[$index]);
                if (null === $this->leastColumns($childStyle, $availableColumns)) {
                    // Never past its max-width, which it could not use
                    $raised[$index] = max($result[$index], min($availableColumns, $childStyle->getMaxColumns() ?? \PHP_INT_MAX, $this->widgetRenderer->measureMinContentWidth($children[$index], $availableColumns, $rows)));
                }
            }
            if ($raised !== $result) {
                $floors = [];
                foreach ($result as $index => $width) {
                    $childStyle = StyleBridge::resolve($this->widgetRenderer, $children[$index]);
                    $floors[$index] = match (true) {
                        isset($flexWeights[$index]) => $raised[$index],
                        // One column at least, like every item of a row: a min-width of 0 does not drop it
                        null !== $minimum = $this->leastColumns($childStyle, $availableColumns) => min($width, max(1, $minimum)),
                        0.0 === (float) ($childStyle->getShrink() ?? 1) => $width,
                        default => min($width, $this->widgetRenderer->measureMinContentWidth($children[$index], $availableColumns, $rows)),
                    };
                }
                if (array_sum($floors) <= $availableColumns) {
                    $result = array_combine(array_keys($raised), self::shrinkAboveFloors(array_values($raised), array_values($floors), $availableColumns));
                }
            }
            $this->positionTracker->restoreStack($savedStack);
        }

        return $result;
    }

    /**
     * Shrinks like shrinkToFit(), but the entries that would go below their
     * minimum keep it, and the others absorb the rest, like CSS freezes the
     * flex items that violate their min-width.
     *
     * When the minimums alone do not fit, they are ignored.
     *
     * @param array<int, int>       $widths
     * @param array<int, int|float> $shrinks
     * @param array<int, int>       $minimums
     *
     * @return array<int, int>
     */
    private static function shrinkWithMinimums(array $widths, int $budget, array $shrinks, array $minimums): array
    {
        $frozen = [];
        while (true) {
            $free = array_diff_key($widths, $frozen);
            $freeBudget = $budget - array_sum($frozen);
            if (!$free || $freeBudget < \count($free)) {
                return $frozen && $freeBudget >= \count($free) ? array_replace($widths, $frozen) : self::shrinkToFit($widths, $budget, $shrinks);
            }
            $result = self::shrinkToFit($free, $freeBudget, $shrinks);
            $violations = array_filter($result, static fn (int $width, int $index) => $width < min($minimums[$index] ?? 0, $widths[$index]), \ARRAY_FILTER_USE_BOTH);
            if (!$violations) {
                return array_replace($widths, $frozen, $result);
            }
            foreach ($violations as $index => $_) {
                $frozen[$index] = min($minimums[$index], $widths[$index]);
            }
        }
    }
}
