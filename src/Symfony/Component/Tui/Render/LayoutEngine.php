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
use Symfony\Component\Tui\Style\AutoMargin;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\GridPlacement;
use Symfony\Component\Tui\Style\GridTemplate;
use Symfony\Component\Tui\Style\Justify;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TableRole;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;
use Symfony\Component\Tui\Widget\HorizontalRuleWidget;
use Symfony\Component\Tui\Widget\ImageAlternativeWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\Tui\Widget\ReplacedElementInterface;
use Symfony\Component\Tui\Widget\TextWidget;
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
    /**
     * The most rows a grid item spans, which bounds the cost of placing items.
     */
    private const MAX_GRID_ROW_SPAN = 100;

    /** The lines of a box each glyph draws, as bits: up, down, left, right; the first glyph of the arms wins, see joinFirstGlyph() */
    /** @internal The lines of each glyph of a box, as bits: up, down, left and right */
    public const BOX_ARMS = ['─' => 0b0011, '│' => 0b1100, '┌' => 0b0101, '┐' => 0b0110, '└' => 0b1001, '┘' => 0b1010, '├' => 0b1101, '┤' => 0b1110, '┬' => 0b0111, '┴' => 0b1011, '┼' => 0b1111, '╭' => 0b0101, '╮' => 0b0110, '╰' => 0b1001, '╯' => 0b1010];

    /** @var \WeakMap<AbstractWidget, array{int, LineBufferInterface, int, int}> */
    private \WeakMap $overflowingItems;

    /** @return \WeakMap<AbstractWidget, array{int, LineBufferInterface, int, int}> */
    public function getOverflowingItems(): \WeakMap
    {
        return $this->overflowingItems;
    }

    public function __construct(
        private readonly WidgetRendererInterface $widgetRenderer,
        private readonly PositionTracker $positionTracker,
        private readonly FontRegistry $fontRegistry,
    ) {
        $this->overflowingItems = new \WeakMap();
        $this->cellWidths = new \WeakMap();
        $this->preparedTables = new \WeakMap();
        $this->cellOffsets = new \WeakMap();
        $this->cellColumnBackgrounds = new \WeakMap();
        $this->rowHeights = new \WeakMap();
        $this->spanningCells = new \WeakMap();
        $this->carried = new \WeakMap();
        $this->spanHeights = new \WeakMap();
        $this->cellHeights = new \WeakMap();
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
        if (null !== $last && $last[0] === $key && ($columns === $last[1] || (!$stretch && null !== $last[2] && $columns >= $last[2]))) {
            return;
        }
        // The width of its columns as wide as they are, when they fit and nothing stretched them; null otherwise
        $this->preparedTables[$table] = [$key, $columns, null];
        $tableRows = [];
        // The least widths the columns are given, like <col style="width: 10ch">
        $columnWidths = $this->columnElementWidths($table, $columns);
        $tableStyle = $this->widgetRenderer->resolveStyle($table);
        // table-layout: fixed needs a width to share: without one, the columns are sized by their cells
        $fixed = true === $tableStyle->getFixedLayout() && (null !== $tableStyle->getMaxColumns() || $stretch);
        foreach ($table instanceof ParentInterface ? $table->all() : [] as $child) {
            $role = $this->widgetRenderer->resolveStyle($child)->getTableRole();
            if (TableRole::Row === $role) {
                $tableRows[] = $child;
            } elseif (TableRole::RowGroup === $role && $child instanceof ParentInterface) {
                foreach ($child->all() as $row) {
                    if (TableRole::Row === $this->widgetRenderer->resolveStyle($row)->getTableRole()) {
                        $tableRows[] = $row;
                    }
                }
            }
        }
        if (!$tableRows) {
            return;
        }

        // The backgrounds of the <col> elements, under the cells of their columns
        $backgrounds = $this->columnElementBackgrounds($table);

        // The cells of each row, with their first column and span
        $savedStack = $this->positionTracker->suppressStack();
        $layout = [];
        $gap = $this->widgetRenderer->resolveStyle($tableRows[0])->getGap() ?? 0;
        $columnCount = 0;
        // No table shows more columns than it has room for, one each: spans are clamped to it, whatever colspan says
        $limit = max(1, $columns);
        // The columns taken in each row by cells of the rows above that span rows
        $taken = [];
        foreach ($tableRows as $rowIndex => $row) {
            $column = 0;
            foreach ($row instanceof ParentInterface ? $row->all() : [] as $cell) {
                $style = $this->widgetRenderer->resolveStyle($cell);
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
                for ($below = 1; $below < min($style->getRowSpan() ?? 1, \count($tableRows) - $rowIndex); ++$below) {
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
            $widths = $this->fixedTableWidths($columnWidths, array_map(static fn (array $entry) => \array_slice($entry, 0, 3), $firstRow), $columnCount, $columns, max($columnCount, $columns - $gap * ($columnCount - 1)), $gap);
            $this->assignCellWidths($table, $layout, $widths, $columnCount, $gap);
            $this->spanRows($table, $tableRows, $layout, $rows);

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
                $cellStyle = $this->widgetRenderer->resolveStyle($cell);
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
        $this->spanRows($table, $tableRows, $layout, $rows);
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
        foreach ($table instanceof ParentInterface ? $table->all() : [] as $child) {
            $role = $this->widgetRenderer->resolveStyle($child)->getTableRole();
            $tableRows = TableRole::Row === $role ? [$child] : (TableRole::RowGroup === $role && $child instanceof ParentInterface ? $child->all() : []);
            foreach ($tableRows as $row) {
                if (!$row instanceof ParentInterface || TableRole::Row !== $this->widgetRenderer->resolveStyle($row)->getTableRole()) {
                    continue;
                }
                $gap = $this->widgetRenderer->resolveStyle($row)->getGap() ?? 0;
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
        if (!isset($this->rowHeights[$row]) || !($carried = $this->carried[$row] ?? [])) {
            return null;
        }
        $lines = [];
        for ($i = 0; $i < $this->rowHeights[$row]; ++$i) {
            $line = str_repeat(' ', $columns);
            foreach ($carried as [$x, $width, $part]) {
                $piece = AnsiUtils::sliceToWidth($part[$i] ?? '', 0, $width);
                $line = AnsiUtils::sliceToWidth($line, 0, $x)."\e[0m".$piece."\e[0m".AnsiUtils::sliceToWidth($line, $x + $width, max(0, $columns - $x - $width));
            }
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Gives the cells spanning rows the height of the rows they span, like CSS: each row as tall as its other cells,
     * the last one taller when the cell needs more; a cell is then drawn across them, see layout().
     *
     * @param list<AbstractWidget>                                 $tableRows
     * @param list<array{AbstractWidget, int, int, int, int, int}> $layout    The cells, with their first column, span, width, the columns skipped before them and their row
     */
    private function spanRows(AbstractWidget $table, array $tableRows, array $layout, int $rows): void
    {
        $spans = [];
        foreach ($layout as [$cell, , , , , $rowIndex]) {
            $span = min($this->widgetRenderer->resolveStyle($cell)->getRowSpan() ?? 1, \count($tableRows) - $rowIndex);
            unset($this->spanningCells[$cell]);
            if ($span > 1) {
                $spans[] = [$cell, $rowIndex, $span];
            }
        }
        // Rows apart, like border-spacing, keep the cell in its first row
        if (($this->widgetRenderer->resolveStyle($table)->getGap() ?? 0) > 0) {
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
            $this->spanningCells[$cell] = [
                array_sum(array_map(static fn (int $h) => max(1, $h), \array_slice($heights, $rowIndex, $span))),
                \array_slice($tableRows, $rowIndex + 1, $span - 1),
            ];
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
            if (($this->cellHeights[$cell][0] ?? null) !== $key) {
                $this->cellHeights[$cell] = [$key, \count($this->widgetRenderer->renderWidgetLines($cell, new RenderContext($this->cellWidths[$cell], $rows, null, $this->fontRegistry)))];
            }

            return $this->cellHeights[$cell][1];
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
     * The least widths the <col> and <colgroup> elements of a table give its columns, in order.
     *
     * @return list<int>
     */
    public function columnElementWidths(AbstractWidget $table, int $columns): array
    {
        $widths = [];
        foreach ($table instanceof ParentInterface ? $table->all() : [] as $child) {
            if (TableRole::Column !== $this->widgetRenderer->resolveStyle($child)->getTableRole()) {
                continue;
            }
            $groupColumns = $child instanceof ParentInterface ? array_filter($child->all(), fn (AbstractWidget $col) => TableRole::Column === $this->widgetRenderer->resolveStyle($col)->getTableRole()) : [];
            foreach ($groupColumns ?: [$child] as $column) {
                $style = $this->widgetRenderer->resolveStyle($column);
                $least = $style->getMaxColumns() ?? $style->resolveWidthPercent($columns) ?? 0;
                // A group with columns spans theirs; one without spans its own
                array_push($widths, ...array_fill(0, min(1000, $style->getColumnSpan() ?? 1), $least));
            }
        }

        return $widths;
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
            $groupStyle = $this->widgetRenderer->resolveStyle($child);
            if (TableRole::Column !== $groupStyle->getTableRole()) {
                continue;
            }
            $groupColumns = $child instanceof ParentInterface ? array_filter($child->all(), fn (AbstractWidget $col) => TableRole::Column === $this->widgetRenderer->resolveStyle($col)->getTableRole()) : [];
            foreach ($groupColumns ?: [$child] as $column) {
                $style = $this->widgetRenderer->resolveStyle($column);
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
            $style = $this->widgetRenderer->resolveStyle($cell);
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
     * Gives each cell the width of the columns it spans, its row laid out again when it changes.
     *
     * @param list<array{AbstractWidget, int, int, int, int, int}> $layout The cells, with their first column, span, width, the columns skipped before them and their row
     * @param list<int>                                            $widths
     */
    private function assignCellWidths(AbstractWidget $table, array $layout, array $widths, int $columnCount, int $gap): void
    {
        foreach ($layout as [$cell, $column, $span, , $skipped]) {
            $span = min($span, $columnCount - $column);
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
     * Layout children based on direction.
     *
     * @param AbstractWidget[] $children
     */
    public function layout(array $children, int $columns, int $rows, int $gap, Direction $direction, ?string $gapLine = null, ?VerticalAlign $verticalAlign = null, ?Justify $justify = null, ?int $wrapRowGap = null, bool $reverse = false, bool $collapseMargins = false, ?Align $crossAlign = null, bool $flexColumn = false, ?int $contentRows = null, ?string $alignContent = null, bool $wrapReverse = false, bool $inlineFlow = false): LineBufferInterface
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

        return $this->layoutVertical($children, $columns, $rows, $gap, $gapLine, $collapseMargins, $crossAlign, $flexColumn, $flexColumn ? $contentRows : null, $justify);
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
     * Collapses the bottom margin of a child and the top margin of the next
     * one, like CSS in block layout: the larger one stays, the other goes.
     *
     * The previous child's margin rows are the last ones of its segment, painted like the parent: they are dropped.
     *
     * @param list<LineBufferInterface> $segments
     */
    private function collapseMargins(array &$segments, int &$lineCount, AbstractWidget $previous, AbstractWidget $child, int $columns, ?int $adjoiningMargin = null): void
    {
        // Their margins with those of their last and first children through them, like a <p> in a <li> with none: the
        // rows each draws there, which collapse like CSS
        $collapsed = min($adjoiningMargin ?? $this->collapsedMargin($previous, false, $columns), $this->collapsedMargin($child, true, $columns));
        if ($collapsed <= 0 || !$segments) {
            return;
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
                $segments[$last] = new ArrayLineBuffer(\array_slice($lines->toArray(), 0, \count($lines) - $taken));
            }
        }
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
    public function layoutGrid(array $children, int $columns, int $rows, GridTemplate $template, int $columnGap, int $rowGap, ?VerticalAlign $verticalAlign = null, bool $mirrored = false, ?string $justifyItems = null, ?int $contentRows = null, ?string $alignContent = null, bool $contentSized = false, ?int $maxRows = Renderer::MAX_LINES): LineBufferInterface
    {
        $children = array_values($this->ordered($children));
        if (!$children) {
            return new ArrayLineBuffer([]);
        }
        // A subgrid of rows: the rows of its parent it spans and their gap, like cards side by side whose titles, texts
        // and links line up; only while its parent is a grid, like for its columns below
        $rowTracks = null;
        if ($template->subgridRows && null !== ($box = $children[0]->getParent()) && null !== ($rowTracks = $this->subgridRowTracks[$box] ?? null)
            && null !== ($grandParent = $box->getParent()) && null !== $this->widgetRenderer->resolveStyle($grandParent)->getGridTemplate()) {
            // The gap of its parent, its rows on the lines of its parent
            $rowGap = $rowTracks[1];
        } else {
            $rowTracks = null;
        }
        $inheritedColumns = false;
        // A subgrid: the columns of its parent it spans, their widths and their gap, like the rows of a table of the
        // documentation of Tailwind, a grid of rows each a subgrid
        // Only while its parent is a grid, which a media query can change: a grid of its own otherwise
        if ($template->subgrid && null !== ($parent = $children[0]->getParent()) && null !== ($tracks = $this->subgridTracks[$parent] ?? null)
            && null !== ($grandParent = $parent->getParent()) && null !== $this->widgetRenderer->resolveStyle($grandParent)->getGridTemplate()) {
            $inheritedColumns = true;
            // Its own column-gap, else that of its parent
            $columnGap = null !== $this->widgetRenderer->resolveStyle($parent)->getGap() ? $columnGap : $tracks[1];
            $template = new GridTemplate(array_map(static fn (int $width): array => ['cells', (float) $width, 0], $tracks[0]), null, $template->rows, $template->areas, $template->autoRows, $template->dense, [], $template->rowLines, $template->columnFlow, null, $template->rowFractions, $template->autoRowCycle);
        }
        $count = max(1, $template->count($columns, $columnGap));
        $placed = $this->placeGridItems($children, $template, $count, $inheritedColumns);
        // auto-fit: the repeated tracks no item is in are dropped, those left sharing the room, like the .grid of Pico
        if ($template->autoFit && null !== $template->autoRepeat && null === $template->repeatLimit) {
            $used = max(0, ...array_map(static fn (array $item) => $item[2] + $item[4], $placed));
            if ($used < $count) {
                $template = $template->withRepeatLimit($used - \count($template->tracks));
                $count = max(1, $template->count($columns, $columnGap));
            }
        }
        // Column after column without grid-template-columns, every column is implicit, like CSS
        $explicit = $template->columnFlow && !$template->tracks && null === $template->autoRepeat && !$template->areas ? 0 : $count;
        $columnCount = max(1, $explicit, ...array_map(static fn (array $item) => $item[2] + $item[4], $placed));

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
            if (\count($spanned) === $rowSpan && null !== $this->widgetRenderer->resolveStyle($child)->getWritingMode()) {
                $inline[$index] = max(1, array_sum($spanned) + $rowGap * ($rowSpan - 1));
            }
        }

        // The tracks: the content of an auto track is its widest single-column item; written vertically in rows of a
        // set height, as many columns as its lines as long as them
        $savedStack = $this->positionTracker->suppressStack();
        $contentWidths = [];
        foreach ($placed as $index => [$child, , $column, , $span]) {
            // A subgrid: its items in the columns of this grid they are in
            if ($this->isSubgrid($child)) {
                foreach ($this->subgridWidths($child, $span, $columns, $rows) as $offset => $width) {
                    $contentWidths[$column + $offset] = max($contentWidths[$column + $offset] ?? 0, $width);
                }
                continue;
            }
            if (1 === $span) {
                $contentWidths[$column] = max($contentWidths[$column] ?? 0, isset($inline[$index])
                    ? min($columns, max(0, ...array_map(AnsiUtils::visibleWidth(...), $this->widgetRenderer->renderStretchedLines($child, new RenderContext($columns, $rows, null, $this->fontRegistry), $inline[$index])->toArray() ?: [''])))
                    : $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows));
            }
        }
        // The implicit columns first, sized by grid-auto-columns or their content; the explicit ones share the room left
        $implicit = [];
        for ($column = $explicit; $column < $columnCount; ++$column) {
            $track = $template->autoColumn($column - $explicit);
            $implicit[] = max('auto' === ($track[0] ?? 'auto') ? 0 : 1, match ($track[0] ?? 'auto') {
                'cells' => (int) $track[1],
                'percent' => intdiv((int) ($columns * $track[1]), 100),
                'length' => GridTemplate::minimum($track[2], $columns),
                default => max(GridTemplate::minimum($track[2] ?? 0, $columns), $contentWidths[$column] ?? 0),
            });
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
        $explicitRoom = max($explicit, $columns - array_sum($implicit) - $columnGap * \count($implicit));
        $widths = [...($explicit > 0 ? $template->resolve($explicitRoom, $columnGap, static fn (int $index) => $contentWidths[$index] ?? 0, $contentSized) : []), ...$implicit];
        $budget = max($columnCount, $columns - $columnGap * ($columnCount - 1));
        if (!$inheritedColumns && array_sum($widths) > $budget) {
            $widths = array_values(self::shrinkToFit($widths, $budget));
        }
        $geometry = [];
        foreach ($placed as $index => [$child, , $column, , $span]) {
            $geometry[$index] = [array_sum(\array_slice($widths, $column, $span)) + $columnGap * ($span - 1), array_sum(\array_slice($widths, 0, $column)) + $columnGap * $column];
            // The columns a subgrid spans, for its own layout
            if ($this->isSubgrid($child)) {
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
        $gridWidth = array_sum($widths) + $columnGap * (\count($widths) - 1);
        foreach ($byRow as $index => [$child, $row, , $rowSpan]) {
            for (; $measuredRow < $row; ++$measuredRow) {
                $rowsAbove += $heights[$measuredRow] + $rowGap;
            }
            // Nor past the columns drawn, like the items of huge gaps, from the right in a grid written from the right
            $drawnAt = $mirrored ? max(0, $gridWidth - $geometry[$index][1] - $geometry[$index][0]) : $geometry[$index][1];
            if ((null !== $maxRows && $rowsAbove > $maxRows) || $drawnAt >= Renderer::MAX_CLIPPED_COLUMNS) {
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
            $natural[$index] = \count(isset($inline[$index]) ? $this->widgetRenderer->renderStretchedLines($child, $context, $inline[$index]) : $this->widgetRenderer->renderWidgetLines($child, $context));
            if ($rowSubgrid) {
                // Drawn from the cache of another size or revision than it recorded them for: laid out again
                $recorded = $this->subgridRowHeights[$child] ?? null;
                if ([$child->getRenderRevision(), $geometry[$index][0]] !== [$recorded[0] ?? null, $recorded[1] ?? null]) {
                    $child->clearRenderCache();
                    $natural[$index] = \count($this->widgetRenderer->renderWidgetLines($child, $context));
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
            $boxStyle = $this->widgetRenderer->resolveStyle($box);
            $above = ($boxStyle->getPadding()?->top ?? 0) + ($boxStyle->getBorder()?->top ?? 0);
            $below = ($boxStyle->getPadding()?->bottom ?? 0) + ($boxStyle->getBorder()?->bottom ?? 0);
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
            $free = $contentRows - $total;
            $sum = array_sum($fractions);
            $given = 0;
            $last = array_key_last($fractions);
            foreach ($fractions as $row => $fraction) {
                $share = $row === $last ? $free - $given : (int) floor($free * $fraction / $sum);
                $heights[$row] += $share;
                $given += $share;
            }
            $top = 0;
            foreach ($heights as $row => $height) {
                $tops[$row] = $top;
                $top += $height + $rowGap;
            }
            $total = max(0, $top - $rowGap);
        }

        // align-content: the free rows of a set height shared between the rows, or by the auto rows when stretched
        if (null !== $contentRows && null !== $alignContent && $contentRows > $total && $heights) {
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
            $justify = $this->widgetRenderer->resolveStyle($child)->getJustifySelf() ?? $justifyItems ?? 'stretch';
            if ('stretch' !== $justify) {
                $saved = $this->positionTracker->suppressStack();
                $content = min($width, $this->widgetRenderer->measureIntrinsicWidth($child, $width, $rows));
                $this->positionTracker->restoreStack($saved);
                $free = $width - $content;
                $offset += match ($mirrored ? ['start' => 'end', 'end' => 'start'][$justify] ?? $justify : $justify) {
                    'center' => intdiv($free, 2),
                    'end' => $free,
                    default => 0,
                };
                $width = $content;
            }
            $areaHeight = array_sum(\array_slice($heights, $row, $rowSpan)) + $rowGap * ($rowSpan - 1);
            $align = $this->widgetRenderer->resolveStyle($child)->getAlignSelf() ?? ($stretch ? VerticalAlign::Stretch : $verticalAlign);
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
            $lines = $this->renderGridItem($child, $width, $rows, $inline[$index] ?? (VerticalAlign::Stretch === $align && $natural[$index] < $areaHeight ? $areaHeight : null), $absRow + $tops[$row], $absCol + $offset, $hasPositionStack);
            // Written from the right, text narrower than its area is at its right, where its lines start
            if ('stretch' === $justify && \in_array($this->widgetRenderer->resolveStyle($child)->getWritingMode(), ['vertical-rl', 'sideways-rl'], true) && ($drawn = max(0, ...array_map(AnsiUtils::visibleWidth(...), $lines->toArray() ?: ['']))) < $width) {
                // Its tracked box moved with it, for the pointer
                if ($hasPositionStack) {
                    $this->positionTracker->shiftContentPositions([$child], $width - $drawn, 0);
                }
                $offset += $width - $drawn;
                $width = $drawn;
            }
            $shift = match ($align) {
                VerticalAlign::Center => max(0, ($areaHeight - \count($lines)) >> 1),
                VerticalAlign::Bottom => max(0, $areaHeight - \count($lines)),
                default => 0,
            };
            // The order it is painted in over the items it overlaps: by its z-index, a grid item taking one positioned
            // or not, then in the order of the document
            $childStyle = $this->widgetRenderer->resolveStyle($child);
            $items[] = [$lines, $tops[$row] + $shift, $offset, $width, [$childStyle->getPosition()?->zIndex ?? $childStyle->getZIndex() ?? 0, $index]];
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
                $layers = [new Layer([''], width: $used, height: 1)];
                foreach ($parts as [, $offset, $part]) {
                    if (!self::blankWithoutBackground($part)) {
                        $layers[] = new Layer([$part], 0, $offset, true);
                    }
                }
                $text = Compositor::composite(...$layers)[0];
            }
            $output[] = $text;
            $widest = max($widest, $used);
        }

        return new ArrayLineBuffer($output, $widest);
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
        return [$side, (($child instanceof TextWidget && null === $style->getWritingMode()) || $container) && null === $style->getColumns()];
    }

    /**
     * Lays a block out with no float of the block around in it: no exclusion, no insets nor clearance, kept from a
     * layout beside floats until they change, see layoutFloats(); whether its own floats may go on past it.
     *
     * @internal
     */
    public static function clearFloatsAround(AbstractWidget $child, bool $floatsPast): void
    {
        if ($child instanceof ContainerWidget) {
            $child->setFloatExclusion([]);
            $child->setFloatsPast($floatsPast);
        } elseif ($child instanceof TextWidget) {
            $child->setLineInsets([]);
            $child->setClearance(0);
        }
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
     * Lays the children of a block container out around its floats, like CSS float: a floated child is at the left
     * or right, at its width or its content's; a block that starts beside floats is laid out in the room between
     * them, the rows it takes past them included; clear puts a block below them.
     *
     * @param AbstractWidget[]                                                                             $children
     * @param list<array{string, int, int, int, 4?: list<int>|null, 5?: int}>                              $exclusion      The floats of a block around, drawn by it: side, first row, rows, width, the columns its shape takes at each of its rows, and its columns from the side
     * @param list<array{string, int, int, int, list<int>|null, int, bool, LineBufferInterface, int}>|null $escaped        When not null, the block is as tall as its content, not as its floats, and gets the floats going on past it, with their lines, see ContainerWidget::setEscapedFloats()
     * @param int                                                                                          $collapsedAbove The rows at the top of the block of the margins collapsed above it, see ContainerWidget::setFloatExclusion()
     */
    public function layoutFloats(array $children, int $columns, int $rows, int $gap, array $exclusion = [], bool $collapseMargins = false, ?array &$escaped = null, int $collapsedAbove = 0): LineBufferInterface
    {
        $tracked = $this->positionTracker->isActive();
        [$absRow, $absCol] = $tracked ? $this->positionTracker->currentOffset() : [0, 0];
        // Each piece drawn: its lines, row, column, width, whether text flows in it, and the shape of a float; the floats
        // as well: side, first row, rows, width, the columns its shape takes at each of its rows, and its columns from the side
        $pieces = [];
        $floats = $exclusion;
        $y = 0;
        // The top of the last float placed, which the next one may not be above
        $floatTop = 0;
        // The last child in the flow and its piece: a float after it, out of the flow, placed under its margin, which
        // collapses with the one of the next block all the same, like CSS
        $previous = null;
        $marginOwner = null;
        $adjoiningMargin = 0;
        $marginPieces = [];
        // The bottom of the floats of the blocks laid out that go on past them
        $escapedBottom = 0;

        // The floats not ended above the last block in the flow, those its rows and the ones after it may be beside, the
        // margin it collapses with taken back at most: those looked at, not all those placed, like thousands of icons
        $live = [];
        $seen = 0;
        foreach ($children as $index => $child) {
            $floor = null !== $previous ? $pieces[$previous[1]][1] : 0;
            $live = array_filter([...$live, ...\array_slice($floats, $seen)], static fn (array $float): bool => $float[1] + $float[2] >= $floor);
            $seen = \count($floats);
            $style = $this->widgetRenderer->resolveStyle($child);
            if ($index > 0 && null === $style->getFloat()) {
                $y += $gap;
            }
            $clear = $style->getClear();
            // The text of a block beside floats flows around them, see below; a block holding blocks passes them on to
            // what it holds
            $container = $child instanceof ContainerWidget && !$child->isInlineFlow() && null === $style->getTableRole() && null === $style->getGridTemplate() && Direction::Horizontal !== $style->getDirection() && null === $style->getScroll();
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
                $lead = min($lead, $this->collapsedMargin($child, true, $columns, cleared: $cleared));
            }
            $marginAbove = null !== $clear && !$floated ? $this->collapsedMargin($child, true, $columns, cleared: $cleared) : 0;
            // Block layout, like CSS: the margins of two blocks one after the other collapse, beside floats or under them,
            // a cleared block's only as far as its border stays under the floats it clears
            if ($collapseMargins && null !== $previous && !$floated) {
                [$before, $at] = $previous;
                $lines = $pieces[$at][0];
                if ($marginOwner !== $before) {
                    $bottom = $this->collapsedMargin($before, false, $columns);
                    $beforeStyle = $this->widgetRenderer->resolveStyle($before);
                    $empty = $before instanceof ContainerWidget && null === $beforeStyle->getDirection() && null === $beforeStyle->getGridTemplate() && null === $beforeStyle->getScroll()
                        && \count($lines) === max($bottom, $beforeStyle->withResolvedBox($columns)->getMargin()?->top ?? 0);
                    $adjoiningMargin = $empty ? max($adjoiningMargin, $bottom) : $bottom;
                    $marginOwner = $before;
                    if (!$empty) {
                        $marginPieces = [];
                    }
                    $marginPieces[] = $at;
                }
                $collapsed = min($adjoiningMargin, $this->collapsedMargin($child, true, $columns, cleared: $cleared));
                if (null !== $clear && $clearedTo > $y - $collapsed + $marginAbove) {
                    $collapsed = max(0, min($collapsed, $y + $marginAbove - $clearedTo));
                }
                if ($collapsed > 0 && $pieces[$at][1] + \count($lines) === $y) {
                    $remaining = $collapsed;
                    while ($remaining > 0 && $marginPieces) {
                        $piece = $marginPieces[array_key_last($marginPieces)];
                        $lines = $pieces[$piece][0];
                        if ($pieces[$piece][1] + \count($lines) !== $y) {
                            break;
                        }
                        $taken = min($remaining, \count($lines));
                        $pieces[$piece][0] = new ArrayLineBuffer(\array_slice($lines->toArray(), 0, \count($lines) - $taken));
                        $y -= $taken;
                        $remaining -= $taken;
                        if ($taken === \count($lines)) {
                            array_pop($marginPieces);
                        }
                    }
                    $lead = $collapsed - $remaining;
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
                $percent = null === $style->getMaxColumns() && null !== $style->getWidthPercent() ? (int) $style->resolveWidthPercent($columns) + ($style->getMargin()?->left ?? 0) + ($style->getMargin()?->right ?? 0) : null;
                $saved = $this->positionTracker->suppressStack();
                $wanted = $percent ?? $style->getMaxColumns() ?? $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows);
                $this->positionTracker->restoreStack($saved);
                // Too wide for the room beside the floats of its row, like the next item of a menu of floats: below the
                // first of them to end, like CSS, not squeezed beside them
                // Not above a float before it, like CSS: the next item of a menu after one moved down goes beside it
                $top = max($y, $floatTop, null !== $clear ? $clearedTo : 0);
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
                $before = $index > 0 ? $this->widgetRenderer->resolveStyle($children[$index - 1]) : null;
                $bare = null !== $before && 0 === ($before->getMargin()?->bottom ?? 0) && 0 === ($before->getPadding()?->bottom ?? 0) && 0 === ($before->getBorder()?->bottom ?? 0)
                    // A run of text, not a block like a <p>, which the float goes under, like CSS
                    && !$this->widgetRenderer->isElementBox($children[$index - 1]);
                if ('right' === $side && $top === $y && null !== $last && $y > 0 && $bare && $children[$index - 1] instanceof TextWidget && !$pieces[$last][4] && $pieces[$last][1] + \count($pieces[$last][0]) === $y
                    && $x >= $pieces[$last][2] && $x > $pieces[$last][2] + AnsiUtils::visibleWidth(rtrim(AnsiUtils::stripAnsiCodes($pieces[$last][0]->getLine(\count($pieces[$last][0]) - 1))))
                    && self::floatsWidth($live, 'right', $y - 1, false) === $right) {
                    $top = $y - 1;
                    // Drawn over the room its line leaves, like the text flowing around a float
                    $pieces[$last][4] = true;
                }
                // A left float after the text of its line, like an icon floated after a word: at the start of the last line of
                // that text when there is room on it, the line moved right beside it, like CSS, not below it
                if ('left' === $side && $top === $y && null !== $last && $y > 0 && $bare && $children[$index - 1] instanceof TextWidget && !$pieces[$last][4] && $pieces[$last][1] + \count($pieces[$last][0]) === $y
                    && $x === $pieces[$last][2] && self::floatsWidth($live, 'left', $y - 1, false) === $left
                    && $width + 1 + AnsiUtils::visibleWidth(rtrim(AnsiUtils::stripAnsiCodes($lastLine = $pieces[$last][0]->getLine(\count($pieces[$last][0]) - 1)))) <= $pieces[$last][3]) {
                    $top = $y - 1;
                    $moved = $pieces[$last][0]->toArray();
                    $moved[\count($moved) - 1] = AnsiUtils::sliceToWidth(str_repeat(' ', $width + 1).$lastLine, 0, $pieces[$last][3]);
                    $pieces[$last][0] = new ArrayLineBuffer($moved);
                    $pieces[$last][4] = true;
                }
                $lines = $this->renderGridItem($child, $width, $rows, null, $absRow + $top, $absCol + $x, $tracked, null !== $percent ? $columns : null);
                $profile = null;
                if (null !== $shape = $style->getFloatShape()) {
                    $edges = static fn (?object $box): array => null === $box ? [0, 0, 0, 0] : [$box->top, $box->right, $box->bottom, $box->left];
                    $profile = $shape->profile($width, max(1, \count($lines)), $side, $edges($style->getMargin()), $edges($style->getBorder()), $edges($style->getPadding()), $style->getShapeMargin() ?? 0.0, $style->getShapeImageThreshold() ?? 0.0);
                }
                $pieces[] = [$lines, $top, $x, $width, false, null === $profile ? null : [$side, $profile]];
                // A percentage, the next one against it; its lines, for those going on past the block
                $floats[] = [$side, $top, max(1, \count($lines)), $width, $profile, 'left' === $side ? $left : $right, null !== $percent, $lines, $top];
                $floatTop = $top;
                continue;
            }

            // The text of a block beside floats flows around them: under them once they end, like CSS; a block holding
            // blocks passes them on to what it holds, the text of its paragraphs flowing around them too
            if (($child instanceof TextWidget || $container) && self::floatsBottom($live, null) > $y && array_any($live, static fn (array $float) => $float[1] + $float[2] > $y)) {
                $chromeTop = ($style->getMargin()?->top ?? 0) + ($style->getBorder()?->top ?? 0) + ($style->getPadding()?->top ?? 0);
                $chromeLeft = ($style->getMargin()?->left ?? 0) + ($style->getBorder()?->left ?? 0) + ($style->getPadding()?->left ?? 0);
                $chromeRight = ($style->getMargin()?->right ?? 0) + ($style->getBorder()?->right ?? 0) + ($style->getPadding()?->right ?? 0);
                // A ::before that clears, an empty block as tall as the rows of the floats it clears beside its content,
                // like CSS: its content under them, among the floats left
                // Its ::before under the margins collapsed above it, its own included, not that of its first child, like CSS:
                // none when that is past the floats
                // No more than the rows drawn
                $edge = ($style->getBorder()?->top ?? 0) + ($style->getPadding()?->top ?? 0);
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
                    if ($child instanceof ContainerWidget) {
                        // The margins collapsed above it through its top, which the ::before of its first child is under
                        $child->setFloatExclusion($inner, $clearance, 0 === $clearance && 0 === $edge ? $above - $chromeTop : 0);
                        $child->setFloatsPast(true);
                    }
                }
                $lines = $this->renderGridItem($child, $columns, $rows, null, $absRow + $y, $absCol, $tracked);
                $pieces[] = [$lines, $y, 0, $columns, true, null];
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
                $pieces[] = [$lines, $y, 0, $columns, true, null];
                $previous = [$child, array_key_last($pieces)];
                $y += \count($lines);
                continue;
            }
            $lines = $this->renderGridItem($child, $room, $rows, null, $absRow + $y, $absCol + $left, $tracked);
            // A cleared block: the floats over the rows of its margin beside them, see above
            $pieces[] = [$lines, $y, $left, $room, $movedPast && 0 === $left, null];
            $previous = [$child, array_key_last($pieces)];
            $escapedBottom = max($escapedBottom, $this->escapeFloats($child, $style, $y, $left, $room, $columns, $pieces, $floats));
            $y += \count($lines);
        }

        // As tall as its own floats, not those of the block around it; or as its content, its floats going on past it
        $own = \array_slice($floats, \count($exclusion));
        $total = null === $escaped ? max($y, self::floatsBottom($own, null)) : $y;
        // The margin under the last block, which may collapse with the next block or the one around: under the floats
        // going on past that block, not over them, as when the block held them
        if (null === $escaped && $collapseMargins && null !== $previous && $escapedBottom > $y - ($margin = $this->collapsedMargin($previous[0], false, $columns))) {
            $total = max($total, $escapedBottom + $margin);
        }
        if (null !== $escaped) {
            foreach ($own as $float) {
                [$side, $top, $height, $width] = $float;
                // All their lines, drawn again over the same rows by the block around: the rows of its margin under
                // them may collapse with the next block
                if ($top + $height > $y && isset($float[7], $float[8])) {
                    $escaped[] = [$side, $top, $height, $width, $float[4] ?? null, $float[5] ?? 0, $float[6] ?? false, $float[7], $float[8]];
                }
            }
        }
        // Drawn row by row, the pieces of a row from the left
        usort($pieces, static fn (array $a, array $b) => $a[2] <=> $b[2]);
        // By bands of rows between the tops and bottoms of the pieces, those of a band the same on each of its rows: the
        // rows of a block of many, like padding: 99999, not each looking at all of them
        $bounds = [0 => true, $total => true];
        foreach ($pieces as $piece) {
            $bounds[max(0, min($total, $piece[1]))] = true;
            $bounds[max(0, min($total, $piece[1] + \count($piece[0])))] = true;
        }
        ksort($bounds);
        $bounds = array_keys($bounds);
        // A line of a piece cut as the row before it was, like those of the padding of a box: cut once
        $cuts = [];
        $cut = static function (int $piece, string $line, int $width) use (&$cuts): string {
            if (($cuts[$piece][0] ?? null) !== $line || $cuts[$piece][1] !== $width) {
                $cuts[$piece] = [$line, $width, AnsiUtils::sliceToWidth($line, 0, $width)];
            }

            return $cuts[$piece][2];
        };
        // The pieces of each band, swept down: those starting at it added, those ending at it dropped, in the order of
        // their columns
        $starts = array_keys($pieces);
        usort($starts, static fn (int $a, int $b) => $pieces[$a][1] <=> $pieces[$b][1]);
        $next = 0;
        $starting = \count($starts);
        // Each live piece with the row under it
        $live = [];
        $active = [];
        $out = [];
        for ($band = 0, $bands = \count($bounds) - 1; $band < $bands; ++$band) {
            $changed = false;
            for (; $next < $starting && $pieces[$starts[$next]][1] <= $bounds[$band]; ++$next) {
                $live[$starts[$next]] = $pieces[$starts[$next]][1] + \count($pieces[$starts[$next]][0]);
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
                    if ($piece[4]) {
                        $flowing = [$index, $piece[0]->getLine($row - $piece[1])];
                    }
                }
                if (null !== $flowing) {
                    $text = $cut($flowing[0], $flowing[1], $columns);
                    // Under the text that comes over them where their shape leaves room, the floats shaped
                    $under = [];
                    $over = [];
                    foreach ($active as $index => [$lines, $top, $x, $width, $flow, $shape]) {
                        if ($flow) {
                            continue;
                        }
                        $at = $row - $top;
                        $line = $cut($index, $lines->getLine($at), $width);
                        if (null === $shape) {
                            $text = AnsiUtils::sliceToWidth($text, 0, $x)."\e[0m".$line."\e[0m".AnsiUtils::sliceToWidth($text, $x + $width, max(0, $columns - $x - $width));
                            continue;
                        }
                        $under[] = new Layer([$line], 0, $x);
                        if (0 < $taken = min($width, $shape[1][$at] ?? $width)) {
                            $over[] = 'left' === $shape[0] ? new Layer([AnsiUtils::sliceToWidth($line, 0, $taken)], 0, $x) : new Layer([AnsiUtils::sliceToWidth($line, $width - $taken, $taken)], 0, $x + $width - $taken);
                        }
                    }
                    if ($under) {
                        $text = Compositor::composite(new Layer([''], width: $columns, height: 1), ...$under, ...[new Layer([$text], transparent: true)], ...$over)[0] ?? $text;
                    }
                    $out[] = $text;
                    continue;
                }
                $text = '';
                $used = 0;
                foreach ($active as $index => [$lines, $top, $x, $width]) {
                    if ($x < $used) {
                        continue;
                    }
                    $text .= str_repeat(' ', $x - $used).$cut($index, $lines->getLine($row - $top), $width);
                    $used = $x + $width;
                }
                $out[] = $text;
            }
        }

        return new ArrayLineBuffer($out);
    }

    /**
     * Places the floats of a block that go on past it, see ContainerWidget::setEscapedFloats(): from its content box,
     * drawn over it again and past it, over its padding, border and margin too, and beside the blocks after it, like
     * CSS; the row under them, 0 when none. The only place they are moved from the box of the block to the one around.
     *
     * @param list<array{LineBufferInterface, int, int, int, bool, array{string, list<int>}|null}>                        $pieces See layoutFloats()
     * @param list<array{string, int, int, int, 4?: list<int>|null, 5?: int, 6?: bool, 7?: LineBufferInterface, 8?: int}> $floats See layoutFloats()
     */
    private function escapeFloats(AbstractWidget $child, Style $style, int $y, int $x, int $width, int $columns, array &$pieces, array &$floats): int
    {
        if (!$child instanceof ContainerWidget) {
            return 0;
        }
        // Those of the render just made, in its content box
        $escaped = $child->getEscapedFloats();
        if (!$escaped) {
            return 0;
        }
        // The rows of the block past its content, its padding, border and margin, drawn as text is: the floats over
        // them, like CSS paints floats over the borders of the blocks in the flow
        $pieces[array_key_last($pieces)][4] = true;
        $margin = $style->getMargin();
        $border = $style->getBorder();
        $padding = $style->getPadding();
        $base = $y + ($margin?->top ?? 0) + ($border?->top ?? 0) + ($padding?->top ?? 0);
        $start = $x + ($margin?->left ?? 0) + ($border?->left ?? 0) + ($padding?->left ?? 0);
        $end = $columns - $x - $width + ($margin?->right ?? 0) + ($border?->right ?? 0) + ($padding?->right ?? 0);
        $bottom = 0;
        foreach ($escaped as [$side, $top, $height, $floatWidth, $profile, $edge, $percent, $floatLines, $from]) {
            $edge += 'left' === $side ? $start : $end;
            $floatX = 'left' === $side ? $edge : $columns - $edge - $floatWidth;
            $pieces[] = [$floatLines, $base + $from, $floatX, $floatWidth, false, null === $profile ? null : [$side, \array_slice($profile, $from - $top)]];
            $floats[] = [$side, $base + $top, $height, $floatWidth, $profile, $edge, $percent, $floatLines, $base + $from];
            $bottom = max($bottom, $base + $top + $height);
        }

        return $bottom;
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
        $template = $this->widgetRenderer->resolveStyle($subgrid)->getGridTemplate() ?? new GridTemplate([]);
        $children = array_values(array_filter($subgrid->all(), function (AbstractWidget $child): bool {
            $style = $this->widgetRenderer->resolveStyle($child);

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
        return $widget instanceof ContainerWidget && true === $this->widgetRenderer->resolveStyle($widget)->getGridTemplate()?->subgrid;
    }

    /**
     * Whether a widget is a subgrid of rows, its rows those of its parent grid, like grid-template-rows: subgrid.
     */
    private function isRowSubgrid(AbstractWidget $widget): bool
    {
        return $widget instanceof ContainerWidget && true === $this->widgetRenderer->resolveStyle($widget)->getGridTemplate()?->subgridRows;
    }

    /**
     * Places the items of a grid: those with an area or lines first, then the others in the free cells, row after row.
     *
     * @param list<AbstractWidget> $children
     *
     * @return list<array{AbstractWidget, int, int, int, int}> Each item: its first row, first column, rows and columns
     */
    private function placeGridItems(array $children, GridTemplate $template, int $count, bool $subgrid = false): array
    {
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
            $style = $this->widgetRenderer->resolveStyle($child);
            $placement = $style->getGridPlacement() ?? new GridPlacement(columnSpan: $style->getColumnSpan() ?? 1);
            $area = null !== $placement->area ? ($template->areas[$placement->area] ?? null) : null;
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
                    $column = min(max(0, $column), $subgrid ? $count - $columnSpan : 1000);
                }
            }
            $items[$order] = [$child, null === $row ? null : min(1000, $row), $column, min(self::MAX_GRID_ROW_SPAN, $rowSpan), $columnSpan];
        }

        if (!$template->columnFlow && !$subgrid && $items) {
            $count = max($count, ...array_map(static fn (array $item): int => ($item[2] ?? 0) + $item[4], $items));
        }

        // Fixed first: areas and items with both lines; then the ones with a row (a column, column after column); then the others, in order
        $placed = [];
        if ($template->columnFlow) {
            foreach ([true, false] as $both) {
                foreach ($items as $order => [$child, $row, $column, $rowSpan, $columnSpan]) {
                    if (null === $column || isset($placed[$order]) || ($both && null === $row)) {
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
        foreach ($template->columnFlow ? [] : [true, false] as $both) {
            foreach ($items as $order => [$child, $row, $column, $rowSpan, $columnSpan]) {
                if (null === $row || isset($placed[$order]) || ($both && null === $column)) {
                    continue;
                }
                $row = max(0, $row);
                if (null === $column) {
                    for ($column = 0; $column + $columnSpan <= $count && !$free($row, $column, $rowSpan, $columnSpan); ++$column) {
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
                if (null === $row) {
                    $cursorRow = $at + $rowSpan;
                }
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
            if (null === $column) {
                $cursorColumn = $at + $columnSpan;
            }
        }
        ksort($placed);

        // [child, row, column, rows, columns]
        return array_values($placed);
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
        $lines = null !== $stretchRows ? $this->widgetRenderer->renderStretchedLines($child, $context, $stretchRows) : $this->widgetRenderer->renderWidgetLines($child, $context);
        $cached = $this->widgetRenderer->wasRenderCached($child);
        if ($track) {
            $this->positionTracker->pop();
            $rect = new WidgetRect($absRow, $absCol, $width, \count($lines));
            // A cached item was not walked: its descendants move with it, or are tracked again
            if ($cached && $revision === $child->getRenderRevision() && $child instanceof ParentInterface && !$this->positionTracker->moveSubtree($child, $rect)) {
                $child->clearRenderCache();
                $this->positionTracker->push($absRow, $absCol);
                $lines = null !== $stretchRows ? $this->widgetRenderer->renderStretchedLines($child, $context, $stretchRows) : $this->widgetRenderer->renderWidgetLines($child, $context);
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
            $style = $this->widgetRenderer->resolveStyle($child);
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
            $width = match (true) {
                // A flex-basis set is the size it takes in the line, over its width, like CSS: at least its longest word
                null !== $style->getFlexBasisPercent() => max((int) floor($columns * $style->getFlexBasisPercent() / 100), $least()),
                null !== $style->getFlexBasis() => max($style->getFlexBasis(), $least()),
                null !== $style->getWidthPercent() => (int) $style->resolveWidthPercent($columns),
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
                $aligns[$i] = $inlineFlow || !array_any($line, fn (AbstractWidget $child) => ($this->widgetRenderer->resolveStyle($child)->getFlex() ?? 0) > 0 || null !== $this->widgetRenderer->resolveStyle($child)->getAutoMargin());
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
            $heights = array_map(fn (array $line) => \count($this->layoutHorizontal($line, $columns, $rows, $gap, $verticalAlign, $justify)), $lines);
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
            return $this->layoutHorizontal($children, $columns, $rows, $gap, $verticalAlign, $justify, null, $inlineFlow);
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
            $buffer = $this->layoutHorizontal($line, $columns, $rows, $gap, $verticalAlign, $justify, $minRows[$i], $inlineFlow);
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
            $shift = ($aligns[$i] ?? false) && $free > 0 ? (Align::Center === $lineAlign ? $free >> 1 : $free) : 0;
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
        $orders = [];
        foreach ($children as $index => $child) {
            $orders[$index] = $this->widgetRenderer->resolveStyle($child)->getOrder() ?? 0;
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
     * Shift all lines by prepending spaces.
     */
    public function shiftLines(LineBufferInterface $lines, int $offset): LineBufferInterface
    {
        $prefix = str_repeat(' ', $offset);
        $result = [];
        foreach ($lines as $line) {
            $result[] = $prefix.$line;
        }

        return new ArrayLineBuffer($result);
    }

    /**
     * Layout children vertically with gap and fill support.
     *
     * @param AbstractWidget[] $children
     */
    private function layoutVertical(array $children, int $columns, int $rows, int $gap, ?string $gapLine = null, bool $collapseMargins = false, ?Align $crossAlign = null, bool $flexColumn = false, ?int $contentRows = null, ?Justify $justify = null): LineBufferInterface
    {
        // In a flex column, an item not stretched across, by align-self or align-items, is as wide as its content, placed
        // at the start, the center or the end: its width and offset
        $placed = [];
        foreach ($flexColumn ? $children : [] as $index => $child) {
            $self = $this->widgetRenderer->resolveStyle($child)->getAlignSelf();
            $placement = null !== $self ? match ($self) {
                VerticalAlign::Top => 'start', VerticalAlign::Center => 'center', VerticalAlign::Bottom => 'end', default => null,
            } : match ($crossAlign) {
                Align::Left => 'start', Align::Center => 'center', Align::Right => 'end', default => null,
            };
            if (null === $placement || ($child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded())) {
                continue;
            }
            // A percentage width is of the column, not of the content, like the full-width bar of the header of GitHub
            $style = $this->widgetRenderer->resolveStyle($child);
            $percent = null === $style->getMaxColumns() && null !== $style->getWidthPercent() && !$style->isWidthPercentMax() ? (int) $style->resolveWidthPercent($columns) + ($style->getMargin()?->left ?? 0) + ($style->getMargin()?->right ?? 0) : null;
            // No narrower than its min-width
            $percent = null !== $percent ? max($percent, ($style->getMinColumns() ?? 0) + ($style->getMargin()?->left ?? 0) + ($style->getMargin()?->right ?? 0)) : null;
            $width = max(1, min($columns, $percent ?? $this->widgetRenderer->measureIntrinsicWidth($child, $columns, $rows)));
            $placed[$index] = [$width, match ($placement) {
                'start' => 0,
                'center' => ($columns - $width) >> 1,
                'end' => $columns - $width,
            }];
        }

        $previous = null;
        $adjoiningMargin = 0;
        $carry = 0;
        if (!$children) {
            return new ArrayLineBuffer([]);
        }

        $segments = [];
        $lineCount = 0;
        $gapLine ??= str_repeat(' ', max(1, $columns));
        $gapLines = new ArrayLineBuffer($gap > 0 ? array_fill(0, $gap, $gapLine) : []);
        $first = true;

        $totalGapRows = $gap * max(0, \count($children) - 1);
        $remainingRows = $rows - $totalGapRows;

        $fillChildren = [];
        $nonFillRenders = [];
        $nonFillCacheHits = [];
        $savedStack = $this->positionTracker->suppressStack();

        foreach ($children as $index => $child) {
            if ($child instanceof VerticallyExpandableInterface && $child->isVerticallyExpanded()) {
                $fillChildren[$index] = $child;
            } else {
                $childColumns = $placed[$index][0] ?? $columns;
                // An item of a flex column narrower than it takes percentages of the column
                $context = (new RenderContext($childColumns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                $revision = $child->getRenderRevision();
                $childLines = $this->widgetRenderer->renderWidgetLines($child, $context);
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
                $flex = $this->widgetRenderer->resolveStyle($children[$index])->getFlex();
                if (null !== $flex && $flex > 0) {
                    $grow[$index] = (float) $flex;
                }
            }
            // Too tall: the scroll containers give way, like the min-height: 0 of a flex item that is not visible
            // in CSS, a list of messages scrolling above the field it leaves on the screen
            if ($contentRows < $used) {
                $scrollers = array_values(array_filter($shown, fn (int $index) => true === $this->widgetRenderer->resolveStyle($children[$index])->getScroll()?->vertical));
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
                $sum = array_sum($grow);
                $given = 0;
                $last = array_key_last($grow);
                $savedStack = $this->positionTracker->suppressStack();
                foreach ($grow as $index => $weight) {
                    $share = $index === $last ? $free - $given : (int) floor($free * $weight / $sum);
                    $given += $share;
                    $grown[$index] = \count($nonFillRenders[$index]) + $share;
                    $context = (new RenderContext($placed[$index][0] ?? $columns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                    $nonFillRenders[$index] = $this->widgetRenderer->renderStretchedLines($children[$index], $context, $grown[$index]);
                    $nonFillCacheHits[$index] = false;
                }
                $this->positionTracker->restoreStack($savedStack);
                $used = $contentRows;
            }
            $autos = [];
            foreach ($shown as $slot => $index) {
                $sides = $this->widgetRenderer->resolveStyle($children[$index])->getAutoMarginRows();
                if (null !== $sides && null !== $auto = AutoMargin::fromSides($sides[0], $sides[1])) {
                    $autos[$slot] = $auto;
                }
            }
            if ($count > 0 && $contentRows > $used && ($autos || null !== $justify)) {
                $spaces = self::distributeFreeColumns($justify, $autos, $count, $contentRows - $used, $gap);
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
        foreach ($children as $index => $child) {
            $cacheHit = false;
            if (isset($fillChildren[$index])) {
                $childFillRows = $baseFillRows + ($fillIndex < $extraRows ? 1 : 0);
                ++$fillIndex;

                if (!$first && \count($gapLines) > 0) {
                    $segments[] = $gapLines;
                    $lineCount += \count($gapLines);
                }
                if ($collapseMargins && null !== $previous) {
                    $this->collapseMargins($segments, $lineCount, $previous, $child, $columns, $adjoiningMargin);
                }

                // Stretched to its share: a set height, which its own column shares, like a card body of flex: 1
                $context = new RenderContext($columns, $childFillRows, null, $this->fontRegistry, fillRows: true);
                $revision = $child->getRenderRevision();

                if ($hasPositionStack) {
                    [$parentAbsRow, $parentAbsCol] = $this->positionTracker->currentOffset();
                    $this->positionTracker->push($parentAbsRow + $lineCount, $parentAbsCol);
                }
                $childLines = $this->widgetRenderer->renderWidgetLines($child, $context);
                $cacheHit = $this->widgetRenderer->wasRenderCached($child) && $revision === $child->getRenderRevision();
                if ($hasPositionStack) {
                    $this->positionTracker->pop();
                }

                if (\count($childLines) < $childFillRows) {
                    $childLines = new ConcatenatedLineBuffer([
                        $childLines,
                        new ArrayLineBuffer(array_fill(0, $childFillRows - \count($childLines), '')),
                    ]);
                }
            } else {
                $context = (new RenderContext($placed[$index][0] ?? $columns, $rows, null, $this->fontRegistry))->withContainingColumns($columns);
                $childLines = $nonFillRenders[$index] ?? (isset($grown[$index]) ? $this->widgetRenderer->renderStretchedLines($child, $context, $grown[$index]) : $this->widgetRenderer->renderWidgetLines($child, $context));
                $cacheHit = $nonFillCacheHits[$index] ?? false;

                if (0 === \count($childLines)) {
                    continue;
                }

                if (!$first && \count($gapLines) > 0) {
                    $segments[] = $gapLines;
                    $lineCount += \count($gapLines);
                }
                if ($collapseMargins && null !== $previous) {
                    $this->collapseMargins($segments, $lineCount, $previous, $child, $columns, $adjoiningMargin);
                }
                if (($freeRowsBefore[$index] ?? 0) > 0) {
                    $segments[] = new ArrayLineBuffer(array_fill(0, $freeRowsBefore[$index], $gapLine));
                    $lineCount += $freeRowsBefore[$index];
                }
            }

            // Negative margins: the child is drawn over the last rows before it, the larger pull when margins collapse
            $tail = [];
            if (null !== $previous) {
                $before = $collapseMargins ? $this->collapsedPull($previous, false) : $this->widgetRenderer->resolveStyle($previous)->getMarginPull()->bottom ?? 0;
                $after = $collapseMargins ? $this->collapsedPull($child, true) : $this->widgetRenderer->resolveStyle($child)->getMarginPull()->top ?? 0;
                // A pull of less than a cell joins a border with the one it is pulled over: none when there is none,
                // like the links of a menu of PHP.net, each over a dotted bottom border, the next one not over it
                if (1 === $before && $this->edgeOf($previous, false)[1] && !$this->edgeOf($child, true)[0]) {
                    $before = 0;
                }
                if (1 === $after && $this->edgeOf($child, true)[1] && !$this->edgeOf($previous, false)[0]) {
                    $after = 0;
                }
                // The rows a shorter child pulled before it did not cover: what follows it starts at its end
                $pull = min(($collapseMargins ? max($before, $after) : $before + $after) + $carry, $lineCount);
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

                $rect = new WidgetRect(
                    $childAbsRow,
                    $childAbsCol,
                    $placed[$index][0] ?? $columns,
                    \count($childLines),
                );
                $positionsTracked = isset($fillChildren[$index]) && !$cacheHit;
                if ($cacheHit && $child instanceof ParentInterface) {
                    $positionsTracked = $this->positionTracker->moveSubtree($child, $rect);
                }

                $this->positionTracker->setWidgetRect($child, $rect);

                if ($child instanceof ParentInterface && !$positionsTracked) {
                    $child->clearRenderCache();
                    $this->positionTracker->push($childAbsRow, $childAbsCol);
                    $childLines = isset($grown[$index]) ? $this->widgetRenderer->renderStretchedLines($child, $context, $grown[$index]) : $this->widgetRenderer->renderWidgetLines($child, $context);
                    $this->positionTracker->pop();
                    $this->positionTracker->setWidgetRect($child, new WidgetRect($childAbsRow, $childAbsCol, $placed[$index][0] ?? $columns, \count($childLines)));
                }
            }

            if (0 < $offset = $placed[$index][1] ?? 0) {
                $childLines = $this->shiftLines($childLines, $offset);
            }
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
                for ($from = 0; $from < $overlap; $from += 1_024) {
                    $rows = min(1_024, $overlap - $from);
                    $under = \array_slice($tail, $from, $rows);
                    $over = array_map(self::joinGlyphs(...), \array_slice($lines, $from, max(0, min($rows, $joined - $from))), \array_slice($under, 0, max(0, min($rows, $joined - $from))));
                    array_push($composited, ...Compositor::composite(new Layer($under, width: $columns, height: $rows), new Layer($over, transparent: true, seeThrough: $seeThrough)));
                }
                $segments[] = new ArrayLineBuffer($composited);
                $childLines = new ArrayLineBuffer(\array_slice($lines, $overlap));
                $lineCount += $overlap;
            } elseif ($segments && \count($childLines) > 0 && \count($last = $segments[array_key_last($segments)]) > 0) {
                // Right under the box before it, its sides go on from that box's bottom, like the items of a list group
                // of Bootstrap without a top border: their lines join, └ over │ making ├
                [$above, $below] = self::joinStacked($last->getLine(\count($last) - 1), $childLines->getLine(0));
                if (null !== $above) {
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
                $childStyle = $this->widgetRenderer->resolveStyle($child);
                $empty = $child instanceof ContainerWidget && null === $childStyle->getDirection() && null === $childStyle->getGridTemplate() && null === $childStyle->getScroll()
                    && \count($childLines) === max($bottom, $childStyle->withResolvedBox($columns)->getMargin()?->top ?? 0);
                $adjoiningMargin = $empty ? max($adjoiningMargin, $bottom) : $bottom;
            }
            $previous = $child;
        }

        return new ConcatenatedLineBuffer($segments);
    }

    /**
     * The top or bottom edge of a block, through its first or last child when it has no border nor padding there, like
     * margins collapsing: whether it has a border there, and whether its margin there is less than a cell made one.
     *
     * @return array{bool, bool}
     */
    private function edgeOf(AbstractWidget $widget, bool $top, int $depth = 0): array
    {
        $style = $this->widgetRenderer->resolveStyle($widget);
        $side = $top ? 0 : 2;
        $border = 0 !== (($top ? $style->getBorder()?->top : $style->getBorder()?->bottom) ?? 0);
        $hairline = (bool) ($style->getMarginHairline()[$side] ?? false);
        if ($border || $hairline || $depth >= 16 || !$widget instanceof ParentInterface || null !== $style->getDirection() || null !== $style->getGridTemplate() || null !== $style->getScroll()
            || 0 !== (($top ? $style->getPadding()?->top : $style->getPadding()?->bottom) ?? 0)) {
            return [$border, $hairline];
        }
        $children = array_values(array_filter($widget->all(), fn (AbstractWidget $child) => !$this->widgetRenderer->resolveStyle($child)->getHidden() && !$this->widgetRenderer->resolveStyle($child)->getPosition()?->isOutOfFlow()));

        return $children ? $this->edgeOf($top ? $children[0] : $children[array_key_last($children)], $top, $depth + 1) : [false, false];
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
        $style = $this->widgetRenderer->resolveStyle($widget);
        $margin = max(0, ($top ? $style->withResolvedBox($columns)->getMargin()?->top : $style->withResolvedBox($columns)->getMargin()?->bottom) ?? 0);
        $cleared ??= self::hasClearance($widget);
        // A block container whose content is in its flow: not a row, a grid, nor a scroll container
        if ($depth >= 16 || ($top && $cleared) || !$widget instanceof ParentInterface || null !== $style->getDirection() || null !== $style->getGridTemplate() || null !== $style->getScroll()
            || 0 !== (($top ? $style->getBorder()?->top : $style->getBorder()?->bottom) ?? 0) || 0 !== (($top ? $style->getPadding()?->top : $style->getPadding()?->bottom) ?? 0)) {
            return $margin;
        }
        $children = array_values(array_filter($widget->all(), fn (AbstractWidget $child) => !$this->widgetRenderer->resolveStyle($child)->getHidden() && !$this->widgetRenderer->resolveStyle($child)->getPosition()?->isOutOfFlow()));
        if (!$children) {
            // A box of no rows of its own, its top and bottom margins collapsed through it, the larger on each side,
            // see ChromeApplier::apply()
            $box = $style->withResolvedBox($columns);
            // Not a widget that draws itself, like the summary of a <details> closed
            if ($widget instanceof ContainerWidget && null === $style->getMinRows() && null === $style->getMinRowsPercent() && 0 === ($box->getBorder()?->top ?? 0) + ($box->getBorder()?->bottom ?? 0) + ($box->getPadding()?->top ?? 0) + ($box->getPadding()?->bottom ?? 0)) {
                return max(0, $box->getMargin()?->top ?? 0, $box->getMargin()?->bottom ?? 0);
            }

            return $margin;
        }

        // Not through a first child whose ::before cleared floats, its margin kept apart, see
        // Renderer::collapseParentMargins()
        if ($top && self::hasClearance($children[0])) {
            return $margin;
        }

        return max($margin, $this->collapsedMargin($top ? $children[0] : $children[array_key_last($children)], $top, $columns, $depth + 1));
    }

    /**
     * Whether the ::before of a block or text cleared floats in its last layout.
     *
     * @internal
     */
    public static function hasClearance(AbstractWidget $widget): bool
    {
        return ($widget instanceof ContainerWidget || $widget instanceof TextWidget) && $widget->getClearance() > 0;
    }

    /**
     * The negative top or bottom margin of a block, as rows it pulls by: with no border nor padding on that side, that of
     * its first or last child collapses into it, like CSS, the larger one winning.
     */
    private function collapsedPull(AbstractWidget $widget, bool $top, int $depth = 0): int
    {
        $style = $this->widgetRenderer->resolveStyle($widget);
        $pull = ($top ? $style->getMarginPull()?->top : $style->getMarginPull()?->bottom) ?? 0;
        // A block container whose content is in its flow: not a row, a grid, nor a scroll container
        if ($depth >= 16 || !$widget instanceof ParentInterface || null !== $style->getDirection() || null !== $style->getGridTemplate() || null !== $style->getScroll()
            || 0 !== (($top ? $style->getBorder()?->top : $style->getBorder()?->bottom) ?? 0) || 0 !== (($top ? $style->getPadding()?->top : $style->getPadding()?->bottom) ?? 0)) {
            return $pull;
        }
        $children = array_values(array_filter($widget->all(), fn (AbstractWidget $child) => !$this->widgetRenderer->resolveStyle($child)->getHidden() && !$this->widgetRenderer->resolveStyle($child)->getPosition()?->isOutOfFlow()));
        if (!$children) {
            return $pull;
        }

        return max($pull, $this->collapsedPull($top ? $children[0] : $children[array_key_last($children)], $top, $depth + 1));
    }

    /**
     * The rows the children of a container pull into its bottom border by their negative bottom margins, like the
     * links of the tabs of Bootstrap over the line under them: the largest of the items of a row, that of the last
     * child of a column, through the blocks around them with nothing under them.
     *
     * @internal
     */
    public function bottomPullInto(ParentInterface $container, bool $row): int
    {
        $children = array_values(array_filter($container->all(), fn (AbstractWidget $child) => !$this->widgetRenderer->resolveStyle($child)->getHidden() && !$this->widgetRenderer->resolveStyle($child)->getPosition()?->isOutOfFlow()));
        if (!$children) {
            return 0;
        }

        return $row ? max(array_map(fn (AbstractWidget $child) => $this->collapsedPull($child, false), $children)) : $this->collapsedPull($children[array_key_last($children)], false);
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
            $lines = array_pop($segments)->toArray();
            $take = min($count, \count($lines));
            $tail = [...\array_slice($lines, \count($lines) - $take), ...$tail];
            if ($rest = \array_slice($lines, 0, \count($lines) - $take)) {
                $segments[] = new ArrayLineBuffer($rest);
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

        return (bool) $this->widgetRenderer->resolveStyle($later)->getSpaceBefore();
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
     */
    /**
     * @param bool $inline A line of inline content, whose auto margins are none, like CSS
     */
    private function layoutHorizontal(array $children, int $columns, int $rows, int $gap, ?VerticalAlign $verticalAlign = null, ?Justify $justify = null, ?int $minRows = null, bool $inline = false): LineBufferInterface
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
            if ($this->widgetRenderer->resolveStyle($child)->getHidden()) {
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
            $childStyle = $this->widgetRenderer->resolveStyle($child);
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
        $childColumnCounts = $tableWidths && array_sum($tableWidths) + $tableOffsets <= $availableColumns ? $tableWidths : $this->computeFlexColumnWidths(
            $children,
            $flexValues,
            $anyFlexSet,
            $percents,
            $availableColumns,
            $rows,
        );

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
            $pull = $this->leftPull($child)
                + ($index > 0 ? $this->widgetRenderer->resolveStyle($children[array_keys($children)[$index - 1]])->getMarginPull()->right ?? 0 : 0);
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
            $childLines = $this->widgetRenderer->renderWidgetLines($child, $context);
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
            $style = $this->widgetRenderer->resolveStyle($children[$index]);
            $chrome = ($style->getBorder()?->top ?? 0) + ($style->getBorder()?->bottom ?? 0);
            $scroll = $style->getScroll();
            if ($children[$index] instanceof ContainerWidget && $scroll?->horizontal && $scroll->bar
                && ($scroll->alwaysSideways || $children[$index]->getScrollWidth() > $children[$index]->getClientWidth())) {
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
            $childRenders[$index] = $this->widgetRenderer->renderStretchedLines($children[$index], $childContexts[$index], $spanRows);
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
            $aligns[$index] = $this->widgetRenderer->resolveStyle($child)->getAlignSelf() ?? $verticalAlign;
        }
        $bordered = array_any($children, fn (AbstractWidget $child): bool => ($this->widgetRenderer->resolveStyle($child)->getBorder()?->top ?? 0) > 0);
        if ($bordered) {
            foreach ($children as $index => $child) {
                $border = $this->widgetRenderer->resolveStyle($child)->getBorder();
                if (VerticalAlign::Stretch === $aligns[$index] && null !== $border && 0 === $border->top + $border->bottom
                    && ($border->left > 0 || $border->right > 0) && ' ' === $border->pattern->getChars()[1][0] && ' ' === $border->pattern->getChars()[1][2]) {
                    $aligns[$index] = VerticalAlign::Center;
                }
            }
        }
        if (\in_array(VerticalAlign::Stretch, $aligns, true)) {
            foreach ($children as $index => $child) {
                if (VerticalAlign::Stretch !== $aligns[$index] || \count($childRenders[$index]) >= $maxRows || isset($spanning[$index])) {
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
                VerticalAlign::Center => ($maxRows - $childHeight) >> 1,
                VerticalAlign::Bottom => $maxRows - $childHeight,
                default => 0,
            };
            if ($hasPositionStack) {
                unset($this->overflowingItems[$child]);
            }
            if (null !== $minRows && $childHeight > $maxRows && !$this->containsReplacedElement($child) && null !== $rowWidget && null === $this->widgetRenderer->resolveStyle($rowWidget)->getTableRole()
                && ($hasPositionStack || !isset($this->overflowingItems[$child]))) {
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
                        if (isset($stretched[$index])) {
                            $this->widgetRenderer->renderStretchedLines($child, $childContexts[$index], $maxRows);
                        } else {
                            $this->widgetRenderer->renderWidgetLines($child, $childContexts[$index]);
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
            $layers = $pulled ? [new Layer([''], width: $columns, height: 1)] : null;
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
                    if (0 === $spaces[$index] && null !== $previous
                        && '' !== ($first = $firstGlyphs[$part] ??= self::edgeGlyph($part, true))
                        && '' !== ($last = $lastGlyphs[$previous] ??= self::edgeGlyph($previous, false))
                        && self::joins($last, $first)) {
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
            $line = $memo[$index][$line][$source] = AnsiUtils::sliceToWidth($line, 0, $x)."\e[0m".$piece."\e[0m".AnsiUtils::sliceToWidth($line, $x + $width, max(0, AnsiUtils::visibleWidth($line) - $x - $width));
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
            $style = $this->widgetRenderer->resolveStyle($item);
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
        foreach (preg_split('/(\e\[[0-9;:]*m)/', $over, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ("\e" === $part[0]) {
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
     * Two lines one above the other, the lines of boxes in them joined where one goes on from the other, like └ over │
     * making ├ over │, or ─ over │ making ┬; null when none does.
     *
     * @return array{?string, ?string}
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
        $sides = static fn (array $line, int $column, int $arms): int => $arms
            & (0 !== ((self::BOX_ARMS[$line[$column - 1] ?? ' '] ?? 0) & 0b0001) ? 0b1111 : 0b1101)
            & (0 !== ((self::BOX_ARMS[$line[$column + 1] ?? ' '] ?? 0) & 0b0010) ? 0b1111 : 0b1110);
        $upperArms = [];
        $lowerArms = [];
        foreach (array_intersect_key($top, $bottom) as $column => $glyph) {
            $under = $bottom[$column];
            if (0 === (self::BOX_ARMS[$glyph] & 0b0100) && 0 !== (self::BOX_ARMS[$under] & 0b1000)) {
                $upperArms[$column] = $sides($top, $column, self::BOX_ARMS[$glyph] | 0b0100);
            }
            if (0 === (self::BOX_ARMS[$under] & 0b1000) && 0 !== (self::BOX_ARMS[$glyph] & 0b0100)) {
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
            if ("\e" === $part[0]) {
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
     * The first glyph of a line drawn over a glyph, both lines of a box, joined, like ┐ and ┌ making ┬; the line as it is
     * when either is not a line of a box.
     */
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
            while ("\e[" === substr($part, $start, 2) && 'm' === ($part[$start + 2 + ($digits = strspn($part, '0123456789;:', $start + 2))] ?? '')) {
                $start += 3 + $digits;
            }
            $glyph = substr($part, $start, 3);
        } else {
            $end = \strlen($part);
            while ($end > 2 && 'm' === $part[$end - 1] && false !== ($escape = strrpos($part, "\e[", $end - \strlen($part) - 1)) && $escape + 2 + strspn($part, '0123456789;:', $escape + 2) === $end - 1) {
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

        return (0 === ($a & 0b0001) && 0 !== ($b & 0b0010) && 0 !== ($a & 0b1100)) || (0 === ($b & 0b0010) && 0 !== ($a & 0b0001) && 0 !== ($b & 0b1100));
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
        while ("\e[" === substr($right, $start, 2) && 'm' === ($right[$start + 2 + ($digits = strspn($right, '0123456789;:', $start + 2))] ?? '')) {
            $start += 3 + $digits;
        }
        if (!isset(self::BOX_ARMS[$first = substr($right, $start, 3)])) {
            return;
        }
        $end = \strlen($left);
        while ($end > 2 && 'm' === $left[$end - 1] && false !== ($escape = strrpos($left, "\e[", $end - \strlen($left) - 1)) && $escape + 2 + strspn($left, '0123456789;:', $escape + 2) === $end - 1) {
            $end = $escape;
        }
        if ($end < 3 || !isset(self::BOX_ARMS[$last = substr($left, $end - 3, 3)])) {
            return;
        }
        [$a, $b] = [self::BOX_ARMS[$last], self::BOX_ARMS[$first]];
        // Up and down 0b1100, left 0b0010, right 0b0001: a corner or an edge meeting a line that goes on from it
        if (0 === ($a & 0b0001) && 0 !== ($b & 0b0010) && 0 !== ($a & 0b1100)) {
            $left = substr($left, 0, $end - 3).(array_search($a | 0b0001, self::BOX_ARMS, true) ?: $last).substr($left, $end);
        } elseif (0 === ($b & 0b0010) && 0 !== ($a & 0b0001) && 0 !== ($b & 0b1100)) {
            $right = substr($right, 0, $start).(array_search($b | 0b0010, self::BOX_ARMS, true) ?: $first).substr($right, $start + 3);
        }
    }

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
        } elseif (null === $justify || (Justify::SpaceBetween === $justify && $count < 2)) {
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
     * Compute column widths for horizontal children based on flex values.
     *
     * When no child has flex set, falls back to equal distribution.
     * flex: 0 children get their intrinsic width (measured by rendering).
     * flex: N children share remaining space proportionally.
     *
     * @param AbstractWidget[]  $children
     * @param array<int, ?int>  $flexValues
     * @param array<int, Style> $percents   The styles of the children with a percentage width, which comes before flex
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
        $savedStack = $this->positionTracker->suppressStack();

        foreach ($children as $index => $child) {
            $basis = $this->widgetRenderer->resolveStyle($child)->resolveFlexBasis($availableColumns);
            if (null !== $basis && $basis > 0 && !isset($percents[$index]) && (0 === $flexValues[$index] || null === $flexValues[$index])) {
                // A fixed starting width instead of the content; a basis of 0 keeps the content, the closest to its min-content
                $width = max(1, min($availableColumns, max($basis, $this->widgetRenderer->resolveStyle($child)->getMinColumns() ?? 0)));
                $intrinsicWidths[$index] = $width;
                $usedColumns += $width;
                continue;
            }
            if (null !== $basis && $basis > 0 && null !== $flexValues[$index] && $flexValues[$index] > 0) {
                $bases[$index] = min($availableColumns, $basis);
                $usedColumns += $bases[$index];
            } elseif (null === $basis && !isset($percents[$index]) && null !== $flexValues[$index] && $flexValues[$index] > 0 && $this->widgetRenderer->resolveStyle($child)->hasContentBasis()) {
                // A basis of auto: growing from its content, like flex: 1 1 auto, the buttons of a group of Bootstrap
                $bases[$index] = min($availableColumns, $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows));
                $usedColumns += $bases[$index];
            }

            if (isset($percents[$index]) && null !== ($flex = $flexValues[$index]) && $flex > 0 && !$percents[$index]->isWidthPercentMax()) {
                // Growing from that share, the width being the basis of a flex item, like the width: 1% and flex: 1 1 auto
                // of the field of an input group of Bootstrap; a flex-basis set comes first
                if (!isset($bases[$index])) {
                    $bases[$index] = min($availableColumns, (int) $percents[$index]->resolveWidthPercent($availableColumns));
                    $usedColumns += $bases[$index];
                }
                $flexWeights[$index] = $flex;
                $totalFlexWeight += $flex;
            } elseif (isset($percents[$index])) {
                // A share of the row, like a percentage width in CSS; a max-width one, the content up to that share
                $width = (int) $percents[$index]->resolveWidthPercent($availableColumns);
                if ($percents[$index]->isWidthPercentMax()) {
                    $width = max(1, min($width, $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows)));
                }
                $intrinsicWidths[$index] = $width;
                $usedColumns += $width;
            } elseif (0 === $flex = $flexValues[$index]) {
                // Intrinsic width: measure the child's natural content width
                // plus chrome (border/padding). This uses measureIntrinsicWidth()
                // instead of renderWidgetLines() because renderWidgetLines() pads lines
                // to the full allocated width via ChromeApplier.
                $width = $this->widgetRenderer->measureIntrinsicWidth($child, $availableColumns, $rows);
                $width = max($width, min($this->widgetRenderer->resolveStyle($child)->getMinColumns() ?? 0, $availableColumns));
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
                $childStyle = $this->widgetRenderer->resolveStyle($children[$index]);
                $shrinks[$index] = $childStyle->getShrink() ?? 1;
                if (null !== $minimum = $childStyle->getMinColumns()) {
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
            $sized = self::shrinkWithMinimums($sized, $intrinsicBudget, $shrinks, array_sum($minimums + $automatic) <= $intrinsicBudget ? $minimums + $automatic : $minimums);
            $intrinsicWidths = array_intersect_key($sized, $intrinsicWidths);
            $bases = array_intersect_key($sized, $bases);
            $usedColumns = array_sum($sized);
        }

        // Second pass: distribute remaining space among flex children
        $remainingColumns = max(0, $availableColumns - $usedColumns);
        // Factors summing to less than 1 share that part of the free columns only, like CSS: flex: 0.5 alone takes half
        if ($totalFlexWeight > 0 && $totalFlexWeight < 1) {
            $remainingColumns = (int) floor($remainingColumns * $totalFlexWeight);
        }
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
                $result[$index] = max(1, ($bases[$index] ?? 0) + $allocated);
                $flexColumnsUsed += $result[$index] - ($bases[$index] ?? 0);
            }
        }

        // A growing child narrower than its longest word, like the min-width: auto of CSS: where a browser would overflow
        // the row, a terminal cannot, so its siblings give their columns past their own min-content, like the logo of a set
        // width of htmx beside its menu, not a link broken in a word
        if ($intrinsicWidths && $flexWeights) {
            $savedStack = $this->positionTracker->suppressStack();
            // The growing children first, their siblings measured only when one of them is too narrow
            $raised = $result;
            foreach ($flexWeights as $index => $_) {
                $childStyle = $this->widgetRenderer->resolveStyle($children[$index]);
                if (null === $childStyle->getMinColumns()) {
                    // Never past its max-width, which it could not use
                    $raised[$index] = max($result[$index], min($availableColumns, $childStyle->getMaxColumns() ?? \PHP_INT_MAX, $this->widgetRenderer->measureMinContentWidth($children[$index], $availableColumns, $rows)));
                }
            }
            if ($raised !== $result) {
                $floors = [];
                foreach ($result as $index => $width) {
                    $childStyle = $this->widgetRenderer->resolveStyle($children[$index]);
                    $floors[$index] = match (true) {
                        isset($flexWeights[$index]) => $raised[$index],
                        null !== $minimum = $childStyle->getMinColumns() => min($width, $minimum),
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
     * @param array<int, int> $widths
     * @param array<int, int> $shrinks
     * @param array<int, int> $minimums
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
}
