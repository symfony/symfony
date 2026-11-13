<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * The columns of a grid, like CSS grid-template-columns.
 *
 * A track is a number of columns, a percentage of the grid, a fraction of
 * the room left (fr), or the width of its content (auto); minmax() gives a
 * fraction or the content a minimum. A repeated track can fill the room, like
 * repeat(auto-fill, minmax(20ch, 1fr)): as many tracks as their minimum allows.
 *
 * @experimental
 */
final class GridTemplate
{
    /**
     * @param list<array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}> $tracks          Each track: its kind, its size (cells, percent or fraction) and its minimum, in cells or of the width of the grid; a length track is that minimum, like calc(100% - 2ch)
     * @param array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}|null  $autoRepeat      A track repeated to fill the room, after the others
     * @param list<int|null>                                                                $rows            The least height of each explicit row, null for its content
     * @param array<string, array{int, int, int, int}>                                      $areas           The named areas: first row, first column, last row and last column, from 0
     * @param int|null                                                                      $autoRows        The least height of the rows past the explicit ones, like grid-auto-rows (null for their content)
     * @param bool                                                                          $dense           Whether items fill the free cells left before them, like grid-auto-flow: dense
     * @param array<string, list<int>>                                                      $columnLines     The named lines between the columns, like [name] in grid-template-columns: their numbers from 1
     * @param array<string, list<int>>                                                      $rowLines        The named lines between the explicit rows
     * @param bool                                                                          $columnFlow      Whether items are placed column after column, filling the explicit rows, like grid-auto-flow: column
     * @param array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}|null  $autoColumns     The size of the columns past the explicit ones, like grid-auto-columns (null for their content)
     * @param list<float>                                                                   $rowFractions    The fraction of the free height of the grid each explicit row takes, like 1fr, 0 for the others
     * @param list<int|null>                                                                $autoRowCycle    The heights of grid-auto-rows when it has several, which the implicit rows take in turn
     * @param list<array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}> $autoColumnCycle The sizes of grid-auto-columns when it has several, which the implicit columns take in turn
     * @param int|null                                                                      $repeatAt        The number of tracks before the repeat that fills the room, null at the end
     * @param array<string, list<int>>                                                      $repeatLines     The named lines inside the repeat that fills the room: 0 before its track, 1 after it, in each repetition
     * @param bool                                                                          $autoFit         Whether the repeat that fills the room drops its tracks no item is in, like auto-fit, the others sharing their room
     * @param int|null                                                                      $repeatLimit     The most repetitions of the repeat that fills the room, the tracks items are in for auto-fit
     * @param bool                                                                          $subgrid         Whether its columns are those of its parent grid it spans, like grid-template-columns: subgrid
     * @param bool                                                                          $subgridRows     Whether its rows are those of its parent grid it spans, like grid-template-rows: subgrid
     */
    public function __construct(public readonly array $tracks, public readonly ?array $autoRepeat = null, public readonly array $rows = [], public readonly array $areas = [], public readonly ?int $autoRows = null, public readonly bool $dense = false, public readonly array $columnLines = [], public readonly array $rowLines = [], public readonly bool $columnFlow = false, public readonly ?array $autoColumns = null, public readonly array $rowFractions = [], public readonly array $autoRowCycle = [], public readonly array $autoColumnCycle = [], public readonly ?int $repeatAt = null, public readonly array $repeatLines = [], public readonly bool $autoFit = false, public readonly ?int $repeatLimit = null, public readonly bool $subgrid = false, public readonly bool $subgridRows = false)
    {
    }

    /**
     * Returns the template with at most that many repetitions of the repeat that fills the room, like auto-fit drops
     * the tracks no item is in.
     */
    public function withRepeatLimit(?int $limit): self
    {
        return new self($this->tracks, $this->autoRepeat, $this->rows, $this->areas, $this->autoRows, $this->dense, $this->columnLines, $this->rowLines, $this->columnFlow, $this->autoColumns, $this->rowFractions, $this->autoRowCycle, $this->autoColumnCycle, $this->repeatAt, $this->repeatLines, $this->autoFit, null !== $limit ? max(1, $limit) : null, $this->subgrid, $this->subgridRows);
    }

    /**
     * The least height of an implicit row, from 0 for the first past the explicit ones, like grid-auto-rows: its
     * values repeat.
     */
    public function autoRow(int $index): ?int
    {
        return $this->autoRowCycle ? $this->autoRowCycle[$index % \count($this->autoRowCycle)] : $this->autoRows;
    }

    /**
     * The size of an implicit column, from 0 for the first past the explicit ones, like grid-auto-columns: its
     * values repeat.
     *
     * @return array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}|null
     */
    public function autoColumn(int $index): ?array
    {
        return $this->autoColumnCycle ? $this->autoColumnCycle[$index % \count($this->autoColumnCycle)] : $this->autoColumns;
    }

    /**
     * Returns the number of a named line, from 1, like a grid item names it: for a start, the line "name-start", else
     * "name"; for an end, "name-end", else "name". The edges of a named area are such lines. "name N" is the Nth line
     * of the name, counted from the end when N is negative. Null when there is none.
     *
     * @param int|null $count The number of columns, for the lines of a repeat that fills the room
     */
    public function line(string $name, bool $start, bool $column, ?int $count = null): ?int
    {
        $nth = 1;
        if (preg_match('/^(\S+)\s+(-?\d+)$/', trim($name), $m)) {
            [$name, $nth] = [$m[1], (int) $m[2]];
            if (0 === $nth) {
                return null;
            }
        }
        $lines = $this->lines($column, $count);
        foreach ([$name.($start ? '-start' : '-end'), $name] as $candidate) {
            if (isset($lines[$candidate])) {
                $numbers = $lines[$candidate];

                return $numbers[$nth > 0 ? $nth - 1 : \count($numbers) + $nth] ?? null;
            }
            if (1 !== $nth) {
                continue;
            }
            // The edges of an area: area-start and area-end
            foreach (['-start' => true, '-end' => false] as $suffix => $isStart) {
                if (str_ends_with($candidate, $suffix) && null !== $area = $this->areas[substr($candidate, 0, -\strlen($suffix))] ?? null) {
                    return $isStart ? ($column ? $area[1] : $area[0]) + 1 : ($column ? $area[3] : $area[2]) + 2;
                }
            }
        }

        return null;
    }

    /**
     * The named lines of the columns or the rows, their numbers from 1 in order; those of a repeat that fills the room
     * once the number of columns is known.
     *
     * @return array<string, list<int>>
     */
    public function lines(bool $column, ?int $count = null): array
    {
        if (!$column) {
            return $this->rowLines;
        }
        if (null === $this->autoRepeat || null === $count) {
            return $this->columnLines;
        }
        $at = $this->repeatAt ?? \count($this->tracks);
        $repeats = max(1, $count - \count($this->tracks));
        $lines = [];
        // The lines after the repeat move by the repetitions past the first
        foreach ($this->columnLines as $name => $numbers) {
            $lines[$name] = array_map(static fn (int $number) => $number > $at + 1 ? $number + $repeats - 1 : $number, $numbers);
        }
        foreach ($this->repeatLines as $name => $offsets) {
            for ($i = 0; $i < $repeats; ++$i) {
                foreach ($offsets as $offset) {
                    $lines[$name][] = $at + 1 + $i + $offset;
                }
            }
        }
        foreach ($lines as $name => $numbers) {
            $numbers = array_values(array_unique($numbers));
            sort($numbers);
            $lines[$name] = $numbers;
        }

        return $lines;
    }

    /**
     * Returns how many tracks the grid has for that room.
     */
    public function count(int $columns, int $gap): int
    {
        return \count($this->expand($columns, $gap));
    }

    /**
     * Returns the width of each track for that room.
     *
     * @param \Closure(int): int $contentWidth The width of the content of an auto track, by its index
     * @param bool               $contentSized Sized by its content, the room being indefinite: the auto tracks do not
     *                                         stretch, the fr ones are as wide as their content needs, by their weight
     *
     * @return list<int>
     */
    public function resolve(int $columns, int $gap, \Closure $contentWidth, bool $contentSized = false): array
    {
        $tracks = $this->expand($columns, $gap);
        $room = max(0, $columns - $gap * max(0, \count($tracks) - 1));
        if ($contentSized) {
            // The width of one fr: the most any fr track needs for its content
            $unit = 0.0;
            foreach ($tracks as $index => [$kind, $size]) {
                if ('fr' === $kind && $size > 0) {
                    $unit = max($unit, $contentWidth($index) / $size);
                }
            }

            return array_values(array_map(static fn (array $track, int $index) => max('auto' === $track[0] ? 0 : 1, match ($track[0]) {
                'cells' => (int) $track[1],
                // A percentage of a width not known yet: sized by its content, like CSS
                'percent' => $contentWidth($index),
                'auto' => max(self::minimum($track[2], $columns), $contentWidth($index)),
                'fr' => max(self::minimum($track[2], $columns), (int) ceil($unit * $track[1] - 1.0E-9)),
                'length' => self::minimum($track[2], $columns),
            }), $tracks, array_keys($tracks)));
        }
        $widths = [];
        $fractions = 0.0;
        foreach ($tracks as $index => [$kind, $size, $minimum]) {
            $widths[$index] = match ($kind) {
                'cells' => (int) $size,
                'percent' => intdiv($columns * (int) round($size), 100),
                'auto' => max(self::minimum($minimum, $columns), $contentWidth($index)),
                'fr', 'length' => self::minimum($minimum, $columns),
            };
            if ('fr' === $kind) {
                $fractions += $size;
            }
        }
        // The room left goes to the fractions, by their weight, the rounding to the first ones
        $left = $room - array_sum($widths);
        if ($fractions > 0 && $left > 0) {
            $given = 0;
            $shares = [];
            foreach ($tracks as $index => [$kind, $size]) {
                if ('fr' === $kind) {
                    $shares[$index] = (int) floor($left * $size / $fractions);
                    $given += $shares[$index];
                }
            }
            foreach (array_keys($shares) as $index) {
                if ($given >= $left) {
                    break;
                }
                ++$shares[$index];
                ++$given;
            }
            foreach ($shares as $index => $share) {
                $widths[$index] += $share;
            }
        } elseif ($left > 0 && $autos = array_keys(array_filter($tracks, static fn (array $track) => 'auto' === $track[0] && 0.0 === (float) $track[1]))) {
            // No fractions: the auto tracks stretch over the room left, like justify-content: normal; not those of
            // min-content, max-content or fit-content, like the empty ones around the label of a button of GitHub
            foreach ($autos as $i => $index) {
                $widths[$index] += intdiv($left, \count($autos)) + ($i < $left % \count($autos) ? 1 : 0);
            }
        }

        // An auto track empty, like the one of the icon of a button without one, takes no column; the others one at least
        return array_values(array_map(static fn (int $width, array $track) => max('auto' === $track[0] ? 0 : 1, $width), $widths, $tracks));
    }

    /**
     * The minimum of a track in cells, for the width of the grid.
     */
    public static function minimum(int|PercentLength $minimum, int $columns): int
    {
        return $minimum instanceof PercentLength ? max(0, min(10000, (int) floor($minimum->at($columns)))) : $minimum;
    }

    /**
     * @return list<array{'cells'|'percent'|'fr'|'auto'|'length', float, int|PercentLength}>
     */
    private function expand(int $columns, int $gap): array
    {
        if (null === $this->autoRepeat) {
            if ($this->tracks) {
                return $this->tracks;
            }
            // Without tracks, the areas say how many columns there are, sized by their content
            $areaColumns = array_reduce($this->areas, static fn (int $carry, array $area) => max($carry, $area[3] + 1), 0);

            return $areaColumns > 0 ? array_fill(0, $areaColumns, ['auto', 0.0, 0]) : [['fr', 1.0, 0]];
        }
        // As many repeated tracks as fit at their minimum, at least one
        [$kind, $size, $minimum] = $this->autoRepeat;
        $width = max(1, match ($kind) {
            'cells' => (int) $size,
            'percent' => intdiv($columns * (int) round($size), 100),
            default => self::minimum($minimum, $columns),
        });
        $used = array_sum(array_map(static fn (array $track) => 'cells' === $track[0] ? (int) $track[1] : self::minimum($track[2], $columns), $this->tracks)) + $gap * \count($this->tracks);
        $count = max(1, intdiv(max(0, $columns - $used) + $gap, $width + $gap));
        $repeated = array_fill(0, min($count, $this->repeatLimit ?? 1000, 1000), $this->autoRepeat);
        $at = min($this->repeatAt ?? \count($this->tracks), \count($this->tracks));

        return [...\array_slice($this->tracks, 0, $at), ...$repeated, ...\array_slice($this->tracks, $at)];
    }
}
