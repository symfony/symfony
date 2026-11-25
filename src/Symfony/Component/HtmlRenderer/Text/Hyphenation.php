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

use Symfony\Component\HtmlRenderer\Hyphenation\LanguageHyphenator;

/**
 * @internal
 */
final class Hyphenation
{
    public static function limits(string $value): ?array
    {
        $parts = preg_split('/\s+/', strtolower(trim($value))) ?: [];
        if (!$parts || \count($parts) > 3 || array_filter($parts, static fn (string $part) => 'auto' !== $part && !preg_match('/^\d{1,3}$/', $part))) {
            return null;
        }
        if (['auto'] === $parts) {
            return null;
        }
        // The after limit is the before one when missing; auto, and a missing before limit, is no limit
        $limits = array_map(static fn (string $part) => 'auto' === $part ? 0 : (int) $part, $parts);

        return [$limits[0], $limits[1] ?? 0, $limits[2] ?? $limits[1] ?? 0];
    }

    public static function character(string $value): ?string
    {
        $value = trim($value);
        if ('auto' === strtolower($value)) {
            return '-';
        }

        return preg_match('/^(?:"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\')$/s', $value, $m, \PREG_UNMATCHED_AS_NULL) ? \Symfony\Component\HtmlRenderer\Css\GeneratedContent::unescape($m[1] ?? $m[2] ?? '') : null;
    }

    public static function prepare(string $text, ?\Closure $hyphenate = null, bool $enabled = true, ?array $limits = null): string
    {
        if ($enabled && null !== $hyphenator = $hyphenate) {
            $text = preg_replace_callback('/\S+/u', static fn (array $token): string => preg_match('~(?:^|\W)(?:[a-z][a-z0-9+.-]{0,31}://|www\.|(?:mailto|tel|urn|data):)|\w@\w~iu', $token[0]) ? $token[0] : preg_replace_callback('/[\p{L}\p{M}\x{00AD}]+/u', static function (array $m) use ($hyphenator): string {
                if (\strlen($m[0]) > 4 * LanguageHyphenator::MAX_LENGTH || str_contains($m[0], '­') || [] === $breaks = $hyphenator($m[0])) {
                    return $m[0];
                }
                $breaks = array_flip($breaks);
                $word = '';
                foreach (mb_str_split($m[0]) as $i => $character) {
                    $word .= (isset($breaks[$i]) ? '­' : '').$character;
                }

                return $word;
            }, $token[0]) ?? $token[0], $text) ?? $text;
        }
        if (!$enabled) {
            $text = str_replace('­', '', $text);
        } elseif (null !== $limits) {
            // hyphenate-limit-chars: the soft hyphens of words too short, or too close to their ends, do not break them
            $text = preg_replace_callback('/[^\s\x{00AD}]*\x{00AD}\S*/u', static fn (array $m) => self::limitHyphens($m[0], ...$limits), $text) ?? $text;
        }

        return $text;
    }

    /**
     * Keeps the soft hyphens of a word with at least $word characters, $before before them and $after after them.
     */
    private static function limitHyphens(string $text, int $word, int $before, int $after): string
    {
        preg_match_all('/\X/u', $text, $matches);
        $graphemes = $matches[0];
        $total = \count(array_filter($graphemes, static fn (string $g) => '­' !== $g));
        $out = '';
        $seen = 0;
        foreach ($graphemes as $grapheme) {
            if ('­' !== $grapheme) {
                ++$seen;
                $out .= $grapheme;
            } elseif ($total >= $word && $seen >= $before && $total - $seen >= $after) {
                $out .= $grapheme;
            }
        }

        return $out;
    }
}
