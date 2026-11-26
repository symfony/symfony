<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\ValueTokenizer;

/**
 * @internal
 */
final class GridGeometry
{
    public static function tracks(string $source, float $room, float $gap, \Closure $length, ?int $itemCount = null, int $depth = 0, ?array &$lineNames = null): array
    {
        if ($depth > 16) {
            return [];
        }
        $tracks = [];
        $lineNames = [];
        $source = (string) preg_replace_callback('/\[([^\]]*)\]/', static fn (array $match): string => ' ['.implode(',', preg_split('/\s+/', trim($match[1]), -1, \PREG_SPLIT_NO_EMPTY) ?: []).'] ', $source);
        $tokens = ValueTokenizer::split($source);
        foreach ($tokens as $tokenIndex => $token) {
            if (str_starts_with($token, '[') && str_ends_with($token, ']')) {
                foreach (explode(',', substr($token, 1, -1)) as $name) {
                    if (preg_match('/^-?[a-z_][\w-]*$/i', $name) && !\in_array($name, ['auto', 'span'], true) && \count($lineNames) < 100) {
                        $lineNames[$name][] = \count($tracks) + 1;
                    }
                }
                continue;
            }
            if (preg_match('/^repeat\(\s*(\d+|auto-fill|auto-fit)\s*,\s*(.+)\)$/is', $token, $match)) {
                $repeated = self::tracks($match[2], $room, $gap, $length, $itemCount, $depth + 1, $repeatedLines);
                if (!$repeated) {
                    return [];
                }
                if (ctype_digit($match[1])) {
                    $count = min(1000, (int) $match[1]);
                } else {
                    $minimum = array_sum(array_map(static fn (array $track): float => 'fixed' === $track['kind'] ? $track['size'] : max(0.0, $track['minimum']), $repeated)) + $gap * max(0, \count($repeated) - 1);
                    $used = array_sum(array_map(static fn (array $track): float => 'fixed' === $track['kind'] ? $track['size'] : max(0.0, $track['minimum']), $tracks)) + $gap * \count($tracks);
                    $suffix = self::tracks(implode(' ', \array_slice($tokens, $tokenIndex + 1)), $room, $gap, $length, $itemCount, $depth + 1);
                    $used += array_sum(array_map(static fn (array $track): float => 'fixed' === $track['kind'] ? $track['size'] : max(0.0, $track['minimum']), $suffix)) + $gap * \count($suffix);
                    $count = $minimum + $gap > 0 ? max(1, min(1000, (int) floor((max(0.0, $room - $used) + $gap) / ($minimum + $gap)))) : 1;
                    if ('auto-fit' === $match[1] && null !== $itemCount) {
                        $count = max(1, min($count, (int) ceil($itemCount / \count($repeated))));
                    }
                }
                for ($i = 0; $i < $count && \count($tracks) < 1000; ++$i) {
                    foreach ($repeatedLines as $name => $numbers) {
                        foreach ($numbers as $number) {
                            $lineNames[$name][] = \count($tracks) + $number;
                        }
                    }
                    array_push($tracks, ...\array_slice($repeated, 0, 1000 - \count($tracks)));
                }
            } else {
                $track = self::track($token, $length);
                if (null === $track || \count($tracks) >= 1000) {
                    return [];
                }
                $tracks[] = $track;
            }
        }

        $lineNames = array_map(static fn (array $numbers): array => array_values(array_unique($numbers)), $lineNames);

        return $tracks;
    }

    public static function resolve(array $tracks, float $room, float $gap, array $content, array $minimum, bool $stretch = true): array
    {
        $room = max(0.0, $room - $gap * max(0, \count($tracks) - 1));
        $sizes = $fractions = [];
        foreach ($tracks as $index => $track) {
            $floor = $track['minimum'] < 0 ? ($minimum[$index] ?? 0.0) : $track['minimum'];
            $sizes[$index] = match ($track['kind']) {
                'fixed' => max($floor, $track['size']),
                'fr' => max(0.0, $floor),
                default => max($floor, min($track['maximum'] ?? \INF, 'min-content' === ($track['content'] ?? '') ? ($minimum[$index] ?? 0.0) : ($content[$index] ?? 0.0))),
            };
            if ('fr' === $track['kind'] && $track['size'] > 0) {
                $fractions[$index] = $track['size'];
            }
        }
        if (array_sum($sizes) > $room) {
            $automatic = [];
            foreach ($tracks as $index => $track) {
                if ('auto' === $track['kind'] && !isset($track['content'])) {
                    $floor = $track['minimum'] < 0 ? ($minimum[$index] ?? 0.0) : $track['minimum'];
                    $automatic[$index] = ['basis' => $floor, 'minimum' => $floor, 'maximum' => $sizes[$index], 'grow' => 1.0, 'shrink' => 0.0];
                }
            }
            if ($automatic) {
                $free = $room - array_sum(array_diff_key($sizes, $automatic));
                $fitted = FlexGeometry::resolve(array_values($automatic), $free, 0.0);
                foreach (array_keys($automatic) as $offset => $index) {
                    $sizes[$index] = $fitted[$offset];
                }
            }
        }
        $left = $room - array_sum($sizes);
        if ($fractions && $left > 0) {
            foreach (self::flexibleTracks($sizes, $fractions, $room) as $index => $size) {
                $sizes[$index] = $size;
            }
        } elseif (!$fractions && $stretch && $left > 0) {
            $automatic = array_keys(array_filter($tracks, static fn (array $track): bool => 'auto' === $track['kind'] && $track['stretch']));
            foreach ($automatic as $index) {
                $sizes[$index] += $left / \count($automatic);
            }
        }

        return $sizes;
    }

    /**
     * The unfrozen fractional tracks, keeping the other tracks at their current sizes.
     *
     * @param array<int, float|int> $sizes
     * @param array<int, float>     $fractions Nonnegative fraction weights, keyed by track
     *
     * @return array<int, float>
     */
    public static function flexibleTracks(array $sizes, array $fractions, float $room): array
    {
        if (!$fractions) {
            return [];
        }
        $flexible = $fractions;
        $free = $room - array_sum(array_diff_key($sizes, $fractions));
        do {
            $space = $free - array_sum(array_intersect_key($sizes, array_diff_key($fractions, $flexible)));
            $sum = array_sum($flexible);
            $frozen = $sum > 0 ? array_filter($flexible, static fn (float $fraction, int $index): bool => $sizes[$index] > $space * $fraction / $sum, \ARRAY_FILTER_USE_BOTH) : $flexible;
            $flexible = array_diff_key($flexible, $frozen);
        } while ($frozen && $flexible);
        foreach ($flexible as $index => $fraction) {
            $flexible[$index] = $space * $fraction / $sum;
        }

        return $flexible;
    }

    /**
     * @param-out int $columnLead
     * @param-out int $rowLead
     *
     * @return list<array{int, int, int, int}>
     */
    public static function place(array $styles, int $columns, bool $dense = false, bool $columnFlow = false, int $explicitRows = 1, ?int &$columnLead = null, ?int &$rowLead = null, bool $subgridColumns = false, bool $subgridRows = false): array
    {
        $items = [];
        foreach ($styles as $style) {
            [$column, $columnSpan] = self::axis($style, 'grid-column', $columns);
            [$row, $rowSpan] = self::axis($style, 'grid-row', $explicitRows);
            $items[] = [$row, $column, $rowSpan, $columnSpan];
        }

        return self::placeResolved($items, $columns, $dense, $columnFlow, $explicitRows, $columnLead, $rowLead, $subgridColumns, $subgridRows);
    }

    /**
     * @param list<array{?int, ?int, int, int}> $items Resolved row, column and spans, before leading implicit tracks
     *
     * @param-out int $columnLead
     * @param-out int $rowLead
     *
     * @return list<array{int, int, int, int}>
     *
     * @internal
     */
    public static function placeResolved(array $items, int $columns, bool $dense = false, bool $columnFlow = false, int $explicitRows = 1, ?int &$columnLead = null, ?int &$rowLead = null, bool $subgridColumns = false, bool $subgridRows = false): array
    {
        $columnLead = $rowLead = 0;
        if ($columnFlow) {
            $transposed = array_map(static fn (array $item): array => [$item[1], $item[0], $item[3], $item[2]], $items);

            return array_map(static fn (array $item): array => [$item[1], $item[0], $item[3], $item[2]], self::placeResolved($transposed, max(1, $explicitRows), $dense, false, $columns, $rowLead, $columnLead, $subgridRows, $subgridColumns));
        }
        foreach ($items as &$item) {
            if ($subgridColumns) {
                [$item[1], $item[3]] = self::clampPlacement($item[1], $item[3], $columns);
            }
            if ($subgridRows) {
                [$item[0], $item[2]] = self::clampPlacement($item[0], $item[2], $explicitRows);
            }
        }
        unset($item);
        if ($items) {
            $columnLead = max(0, -min(0, ...array_map(static fn (array $item): int => $item[1] ?? 0, $items)));
            $rowLead = max(0, -min(0, ...array_map(static fn (array $item): int => $item[0] ?? 0, $items)));
            $columns += $columnLead;
            foreach ($items as &$item) {
                if (null !== $item[0]) {
                    $item[0] += $rowLead;
                }
                if (null !== $item[1]) {
                    $item[1] += $columnLead;
                }
                $columns = min(1000, max($columns, ($item[1] ?? 0) + $item[3]));
            }
            unset($item);
        }
        $occupied = $placed = [];
        $reserve = static function (int $index, int $row, int $column, int $rowSpan, int $columnSpan) use (&$occupied, &$placed): void {
            if ($row + $rowSpan > 1000 || $column + $columnSpan > 1000) {
                throw new \LengthException('A grid cannot generate more than 1000 tracks along either axis.');
            }
            for ($r = $row; $r < $row + $rowSpan; ++$r) {
                for ($c = $column; $c < $column + $columnSpan; ++$c) {
                    $occupied[$r][$c] = true;
                }
            }
            $placed[$index] = [$row, $column, $rowSpan, $columnSpan];
        };
        foreach ($items as $index => [$row, $column, $rowSpan, $columnSpan]) {
            if (null !== $row && null !== $column) {
                $reserve($index, $row, $column, $rowSpan, $columnSpan);
            }
        }
        $rowEnds = [];
        foreach ($items as $index => [$row, $column, $rowSpan, $columnSpan]) {
            if (isset($placed[$index]) || null === $row) {
                continue;
            }
            for ($column = $dense ? 0 : ($rowEnds[$row] ?? 0); $column < 1000 && !self::isFree($occupied, $row, $column, $rowSpan, $columnSpan); ++$column) {
            }
            $rowEnds[$row] = $column + $columnSpan;
            $reserve($index, $row, $column, $rowSpan, $columnSpan);
        }
        $cursorRow = $cursorColumn = 0;
        foreach ($items as $index => [$row, $column, $rowSpan, $columnSpan]) {
            if (isset($placed[$index])) {
                continue;
            }
            $r = $dense ? 0 : $cursorRow;
            $c = $dense ? 0 : $cursorColumn;
            if (null !== $column) {
                if (!$dense && $column < $c) {
                    ++$r;
                }
                $c = $column;
            }
            for ($attempt = 0; $attempt < 1000000; ++$attempt) {
                if ($r + $rowSpan > 1000) {
                    throw new \LengthException('A grid cannot generate more than 1000 tracks along either axis.');
                }
                if (null === $column && $c + $columnSpan > $columns) {
                    $c = 0;
                    ++$r;
                }
                if (self::isFree($occupied, $r, $c, $rowSpan, $columnSpan)) {
                    break;
                }
                null === $column ? ++$c : ++$r;
            }
            $reserve($index, $r, $c, $rowSpan, $columnSpan);
            $cursorRow = $r;
            $cursorColumn = $c + $columnSpan;
        }
        ksort($placed);

        return array_values($placed);
    }

    public static function areas(string $value): ?array
    {
        if ('none' === strtolower(trim($value))) {
            return [];
        }
        if (!preg_match_all('/"([^"]*)"|\'([^\']*)\'/', $value, $m, \PREG_SET_ORDER) || '' !== trim((string) preg_replace('/"[^"]*"|\'[^\']*\'/', '', $value))) {
            return null;
        }
        $cells = [];
        $width = null;
        foreach ($m as $index => $match) {
            $text = '' !== ($match[1] ?? '') ? $match[1] : $match[2] ?? '';
            $names = preg_split('/\s+/', trim($text)) ?: [];
            if (null !== $width && \count($names) !== $width) {
                return null;
            }
            $width = \count($names);
            foreach ($names as $column => $name) {
                if (!preg_match('/^\.+$/', $name)) {
                    $cells[$name][] = [$index, $column];
                }
            }
        }
        $areas = [];
        foreach ($cells as $name => $positions) {
            $rows = array_column($positions, 0);
            $columns = array_column($positions, 1);
            $area = [min($rows), min($columns), max($rows), max($columns)];
            // A name must fill a rectangle
            if (\count($positions) !== ($area[2] - $area[0] + 1) * ($area[3] - $area[1] + 1)) {
                return null;
            }
            $areas[$name] = $area;
        }

        return $areas;
    }

    public static function namedStyles(array $styles, array $areas, array $columnLines = [], array $rowLines = [], int $columns = 1, int $rows = 1): array
    {
        $names = ['grid-row' => $rowLines, 'grid-column' => $columnLines];
        foreach ($areas as $name => [$firstRow, $firstColumn, $lastRow, $lastColumn]) {
            $names['grid-row'][$name.'-start'][] = $firstRow + 1;
            $names['grid-row'][$name.'-end'][] = $lastRow + 2;
            $names['grid-column'][$name.'-start'][] = $firstColumn + 1;
            $names['grid-column'][$name.'-end'][] = $lastColumn + 2;
        }
        foreach ($styles as &$style) {
            $area = $style['grid-area'] ?? null;
            if (null !== $area && isset($areas[$area])) {
                [$firstRow, $firstColumn, $lastRow, $lastColumn] = $areas[$area];
                $style += ['grid-row' => ($firstRow + 1).' / '.($lastRow + 2), 'grid-column' => ($firstColumn + 1).' / '.($lastColumn + 2)];
            } elseif (null !== $area && str_contains($area, '/')) {
                $parts = array_map('trim', explode('/', $area));
                foreach (['grid-row-start', 'grid-column-start', 'grid-row-end', 'grid-column-end'] as $index => $property) {
                    if (isset($parts[$index]) && !isset($style[$property])) {
                        $style[$property] = $parts[$index];
                    }
                }
            }
            foreach ($names as $property => $lines) {
                $parts = array_map('trim', explode('/', $style[$property] ?? 'auto'));
                $first = $style[$property.'-start'] ?? $parts[0];
                $last = $style[$property.'-end'] ?? $parts[1] ?? (preg_match('/^-?[a-z_][\w-]*$/i', $first) && 'auto' !== $first ? $first : 'auto');
                if (null === $parsed = self::parseLines($first.' / '.$last)) {
                    continue;
                }
                [$from, $to, $span, $firstName, $lastName] = $parsed;
                $from = null !== $firstName && !str_starts_with($firstName, 'span ') ? self::namedLine($firstName, true, $lines) : $from;
                $to = null !== $lastName && !str_starts_with($lastName, 'span ') ? self::namedLine($lastName, false, $lines) : $to;
                foreach ([[$firstName, true], [$lastName, false]] as [$name, $start]) {
                    if (null === $name || !preg_match('/^span (\S+) (\d+)$/', $name, $match) || null === ($anchor = $start ? $to : $from)) {
                        continue;
                    }
                    if ($anchor < 0) {
                        $anchor = max(1, ('grid-column' === $property ? $columns : $rows) + 2 + $anchor);
                    }
                    $found = array_values(array_filter($lines[$match[1]] ?? [], static fn (int $line): bool => $start ? $line < $anchor : $line > $anchor));
                    if ($start) {
                        $found = array_reverse($found);
                    }
                    $target = $found[(int) $match[2] - 1] ?? null;
                    $start ? $from = $target : $to = $target;
                }
                $style[$property.'-start'] = null !== $from ? (string) $from : (str_starts_with($first, 'span ') && null === $firstName ? 'span '.$span : 'auto');
                $style[$property.'-end'] = null !== $to ? (string) $to : (str_starts_with($last, 'span ') && null === $lastName ? 'span '.$span : 'auto');
            }
        }
        unset($style);

        return $styles;
    }

    public static function namedLine(string $name, bool $start, array $lines): ?int
    {
        $nth = 1;
        if (preg_match('/^(\S+)\s+(-?\d+)$/', trim($name), $match)) {
            [$name, $nth] = [$match[1], (int) $match[2]];
            if (0 === $nth) {
                return null;
            }
        }
        foreach ([$name.($start ? '-start' : '-end'), $name] as $candidate) {
            if (isset($lines[$candidate])) {
                $numbers = $lines[$candidate];

                return $numbers[$nth > 0 ? $nth - 1 : \count($numbers) + $nth] ?? null;
            }
        }

        return null;
    }

    public static function parseLines(string $value): ?array
    {
        $sides = array_map('trim', explode('/', strtolower(trim($value))));
        if (\count($sides) > 2 || \in_array('', $sides, true)) {
            return null;
        }
        $parsed = [];
        foreach ($sides as $side) {
            $line = match (true) {
                'auto' === $side => [null, null, null],
                1 === preg_match('/^-?[1-9]\d*$/', $side) => [max(-1000, min(1000, (int) $side)), null, null],
                1 === preg_match('/^span\s+([1-9]\d*)$/', $side, $m) => [null, min(1000, (int) $m[1]), null],
                1 === preg_match('/^-?[a-z_][\w-]*$/', $side) && 'span' !== $side => [null, null, $side],
                // To the Nth line of a name, like span col or span 2 col: LayoutEngine counts them from the other line
                1 === preg_match('/^span\s+(?:([1-9]\d*)\s+)?(-?[a-z_][\w-]*)$/', $side, $m) && !\in_array($m[2], ['span', 'auto'], true) => [null, null, 'span '.$m[2].' '.min(1000, (int) ($m[1] ?: 1))],
                // The Nth line of a name, like a 2 or 2 a: GridTemplate::line() counts them
                1 === preg_match('/^(?:(-?[1-9]\d*)\s+(-?[a-z_][\w-]*)|(-?[a-z_][\w-]*)\s+(-?[1-9]\d*))$/', $side, $m) && !\in_array($m[2] ?: $m[3], ['span', 'auto'], true) => [null, null, ($m[2] ?: $m[3]).' '.max(-1000, min(1000, (int) ($m[1] ?: $m[4])))],
                default => null,
            };
            if (null === $line) {
                return null;
            }
            $parsed[] = $line;
        }
        [$start, $startSpan, $startName] = $parsed[0];
        // A name alone is both lines; the Nth line of a name alone, the start only
        [$end, $endSpan, $endName] = $parsed[1] ?? [null, null, 1 === \count($parsed) && !str_contains($startName ?? ' ', ' ') ? $startName : null];

        return [$start, $end, max(1, min(1000, $startSpan ?? $endSpan ?? 1)), $startName, $endName];
    }

    private static function track(string $token, \Closure $length): ?array
    {
        $token = strtolower(trim($token));
        $track = ['kind' => 'auto', 'size' => 0.0, 'minimum' => -1.0, 'maximum' => null, 'stretch' => true];
        if (preg_match('/^minmax\((.*)\)$/s', $token, $match)) {
            $arguments = Selector::splitTopLevel($match[1]);
            if (2 !== \count($arguments)) {
                return null;
            }
            $minimum = self::track($arguments[0], $length);
            $maximum = self::track($arguments[1], $length);
            if (null === $minimum || null === $maximum || 'fr' === $minimum['kind']) {
                return null;
            }
            $maximum['minimum'] = 'fixed' === $minimum['kind'] ? $minimum['size'] : ('fr' === $maximum['kind'] ? -1.0 : 0.0);
            if ('fixed' === $maximum['kind']) {
                $maximum['size'] = max($maximum['size'], $maximum['minimum']);
            }

            return $maximum;
        }
        if ('auto' === $token) {
            return $track;
        }
        if (\in_array($token, ['min-content', 'max-content'], true)) {
            return array_replace($track, ['stretch' => false, 'content' => $token]);
        }
        if (preg_match('/^fit-content\((.*)\)$/s', $token, $match)) {
            $maximum = $length($match[1]);

            return null === $maximum ? null : array_replace($track, ['stretch' => false, 'maximum' => max(0.0, $maximum)]);
        }
        if (preg_match('/^(\d*\.?\d+)fr$/', $token, $match)) {
            return array_replace($track, ['kind' => 'fr', 'size' => (float) $match[1], 'minimum' => -1.0]);
        }
        $size = $length($token);

        return null === $size || $size < 0 ? null : array_replace($track, ['kind' => 'fixed', 'size' => $size, 'minimum' => 0.0]);
    }

    private static function axis(array $style, string $property, int $count): array
    {
        $parts = explode('/', $style[$property] ?? 'auto');
        $start = trim($style[$property.'-start'] ?? $parts[0]);
        $end = trim($style[$property.'-end'] ?? $parts[1] ?? 'auto');
        $span = 1;
        foreach ([$start, $end] as $line) {
            if (preg_match('/^span\s+(\d+)$/', $line, $match)) {
                $span = min(100, max(1, (int) $match[1]));
            }
        }
        $number = static fn (string $line): ?int => preg_match('/^-?\d+$/', $line) && 0 !== (int) $line ? max(-1000, min(1001, (int) $line)) : null;
        [$from, $span] = self::resolveLines($number($start), $number($end), $span, $count);

        return [$from, min(100, $span)];
    }

    /** @return array{?int, int} */
    public static function clampPlacement(?int $start, int $span, int $tracks): array
    {
        $span = min(max(1, $tracks), $span);

        return [null === $start ? null : min(max(0, $start), max(0, $tracks - $span)), $span];
    }

    /**
     * Resolves numbered lines before implicit leading tracks are inserted.
     *
     * @return array{?int, int}
     */
    public static function resolveLines(?int $start, ?int $end, int $span, int $tracks): array
    {
        $index = static fn (?int $line): ?int => null === $line ? null : ($line > 0 ? $line - 1 : $tracks + 1 + $line);
        $from = $index($start);
        $to = $index($end);
        if (null !== $from && null !== $to) {
            return [min($from, $to), max(1, abs($to - $from))];
        }
        if (null === $from && null !== $to) {
            $from = max(0, $to - $span);
        }

        return [$from, $span];
    }

    private static function isFree(array $occupied, int $row, int $column, int $rowSpan, int $columnSpan): bool
    {
        for ($r = $row; $r < $row + $rowSpan; ++$r) {
            for ($c = $column; $c < $column + $columnSpan; ++$c) {
                if (isset($occupied[$r][$c])) {
                    return false;
                }
            }
        }

        return true;
    }
}
