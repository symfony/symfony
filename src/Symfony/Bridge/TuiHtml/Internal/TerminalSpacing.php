<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

/** @internal The native terminal profile inserts N-1 gaps and ignores negative spacing. */
final class TerminalSpacing
{
    public static function cells(float $pixels, float $cellWidth): int
    {
        return (int) floor(max(0.0, min(10000.0, $pixels / $cellWidth)) + 0.25);
    }

    /** @return array{text: string, boundaries: list<int>} */
    public static function expand(string $text, int $letters, int $words, bool $leadingSpace = false): array
    {
        $graphemes = grapheme_str_split($text) ?: [];
        $boundaries = [];
        $gaps = 0;
        $previousImage = false;
        foreach ($graphemes as $index => $grapheme) {
            $image = 1 === preg_match('/^[\x{100000}-\x{10FFFF}]$/u', $grapheme);
            if ($index > 0 && (!$image || !$previousImage)) {
                ++$gaps;
            }
            $previousImage = $image;
        }
        // Like the native RichText profile, word spacing also expands inserted letter-gap spaces.
        $extra = $gaps * $letters * (1 + $words) + substr_count($text, ' ') * $words + ($leadingSpace ? 1 + $words : 0);
        if (\strlen($text) + $extra > 8 * 1024 * 1024) {
            throw new \LengthException('Terminal text spacing exceeds the expanded text budget.');
        }
        $gap = $gaps > 0 ? str_repeat(' ', $letters * (1 + $words)) : '';
        $word = str_repeat(' ', 1 + $words);
        $output = $leadingSpace ? $word : '';
        $position = $leadingSpace ? \strlen($word) : 0;
        $previousImage = false;
        foreach ($graphemes as $index => $grapheme) {
            $image = 1 === preg_match('/^[\x{100000}-\x{10FFFF}]$/u', $grapheme);
            if ($index > 0 && (!$image || !$previousImage)) {
                $output .= $gap;
                $position += \strlen($gap);
            }
            $boundaries[] = $position;
            $output .= ' ' === $grapheme ? $word : $grapheme;
            $position += ' ' === $grapheme ? \strlen($word) : 1;
            $previousImage = $image;
        }
        $boundaries[] = $position;

        return ['text' => $output, 'boundaries' => $boundaries];
    }
}
