<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Text;

/** @internal */
final class HighlightRanges
{
    public static function declarations(array $declarations): array
    {
        return array_diff_key($declarations, array_fill_keys(['content', 'counter-increment', 'counter-reset', 'counter-set', 'quotes', 'display', 'position'], true));
    }

    public static function register(array $highlights, string $name, ?array $texts, bool $matchCase = false): array
    {
        if (!preg_match('/^-?[a-zA-Z_][\\w-]*$/', $name)) {
            throw new \Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException(\sprintf('The highlight name "%s" is not a CSS identifier.', $name));
        }
        unset($highlights[$name]);
        $texts = array_values(array_filter($texts ?? [], static fn (string $text) => '' !== trim($text)));
        if ($texts) {
            $highlights[$name] = [$texts, $matchCase];
        }

        return $highlights;
    }

    public static function pattern(array $texts, bool $matchCase = false): string
    {
        $words = array_map(static fn (string $text) => str_replace(' ', '\\s+', preg_quote(trim((string) preg_replace('/\\s+/u', ' ', $text)), '/')), $texts);

        return '/'.implode('|', $words).'/u'.($matchCase ? '' : 'i');
    }

    /** @param list<array{object, string, int}> $texts */
    public static function pieces(string $source, array $texts, array $highlights): \SplObjectStorage
    {
        $ranges = [];
        foreach ($highlights as $highlight) {
            [$pattern, $style] = $highlight;
            if (!preg_match_all($pattern, $source, $matches, \PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach (\array_slice($matches[1] ?? $matches[0], 0, $highlight[2] ?? null) as [$match, $offset]) {
                if ('' !== $match && $offset >= 0 && (\count($ranges) < 2000 || isset($highlight[2]))) {
                    $ranges[] = [$offset, $offset + \strlen($match), $style];
                }
            }
        }
        $pieces = new \SplObjectStorage();
        foreach ($texts as [$node, $text, $start]) {
            $length = \strlen($text);
            $over = array_filter($ranges, static fn (array $range) => $range[0] < $start + $length && $range[1] > $start);
            if (!$over) {
                continue;
            }
            $cuts = [0, $length];
            foreach ($over as [$from, $to]) {
                $cuts[] = max(0, $from - $start);
                $cuts[] = min($length, $to - $start);
            }
            $cuts = array_values(array_unique($cuts));
            sort($cuts);
            $list = [];
            for ($i = 1, $count = \count($cuts); $i < $count; ++$i) {
                [$from, $to] = [$cuts[$i - 1], $cuts[$i]];
                $marks = array_values(array_map(static fn (array $range) => $range[2], array_filter($over, static fn (array $range) => $range[0] - $start <= $from && $range[1] - $start >= $to)));
                $list[] = [substr($text, $from, $to - $from), $marks];
            }
            $pieces[$node] = $list;
        }

        return $pieces;
    }
}
