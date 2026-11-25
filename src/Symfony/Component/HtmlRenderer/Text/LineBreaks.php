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

/**
 * @internal
 */
final class LineBreaks
{
    private const IDEOGRAPHS = '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Bopomofo}]/u';

    public static function tokens(string $text, string $wordBreak = 'normal'): array
    {
        if ('break-all' !== $wordBreak && ('keep-all' === $wordBreak || !self::isIdeographic($text)) && !str_contains($text, "\u{00A0}") && !str_contains($text, "\u{202F}") && !str_contains($text, "\u{2060}")) {
            return preg_split('/(?<=[ \t\r\n])(?=[^ \t\r\n])/u', $text, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (false === preg_match_all('/\X/u', $text, $matches)) {
            throw new \InvalidArgumentException('Text must be valid UTF-8.');
        }
        $graphemes = $matches[0];
        $tokens = [];
        $current = '';
        for ($index = 0, $count = \count($graphemes); $index < $count; ++$index) {
            $grapheme = $graphemes[$index];
            $current .= $grapheme;
            if ($index + 1 >= $count) {
                continue;
            }
            $next = $graphemes[$index + 1];
            $space = \in_array($grapheme, [' ', "\t", "\r", "\n", "\r\n"], true);
            $nextSpace = \in_array($next, [' ', "\t", "\r", "\n", "\r\n"], true);
            $joined = \in_array($grapheme, ["\u{00A0}", "\u{202F}", "\u{2060}"], true) || \in_array($next, ["\u{00A0}", "\u{202F}", "\u{2060}"], true);
            if (!$joined && ($space && !$nextSpace || !$space && !$nextSpace && ('break-all' === $wordBreak || 'keep-all' !== $wordBreak && (self::isIdeographic($grapheme) || self::isIdeographic($next))))) {
                $tokens[] = $current;
                $current = '';
            }
        }
        if ('' !== $current) {
            $tokens[] = $current;
        }

        return $tokens;
    }

    public static function isIdeographic(string $text): bool
    {
        return (bool) preg_match(self::IDEOGRAPHS, $text);
    }
}
