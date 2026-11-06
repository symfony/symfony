<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

/**
 * Simple output wrapper for "tagged outputs" instead of wordwrap(). This solution is based on a StackOverflow
 * answer: https://stackoverflow.com/a/20434776/1476819 from user557597 (alias SLN).
 *
 *  (?:
 *       # -- Words/Characters
 *       (                       # (1 start)
 *            (?>                     # Atomic Group - Match words with valid breaks
 *                 .{1,16}                 #  1-N characters
 *                                         #  Followed by one of 4 prioritized, non-linebreak whitespace
 *                 (?:                     #  break types:
 *                      (?<= [^\S\r\n] )        # 1. - Behind a non-linebreak whitespace
 *                      [^\S\r\n]?              #      ( optionally accept an extra non-linebreak whitespace )
 *                   |  (?= \r? \n )            # 2. - Ahead a linebreak
 *                   |  $                       # 3. - EOS
 *                   |  [^\S\r\n]               # 4. - Accept an extra non-linebreak whitespace
 *                 )
 *            )                       # End atomic group
 *         |
 *            .{1,16}                 # No valid word breaks, just break on the N'th character
 *       )                       # (1 end)
 *       (?: \r? \n )?           # Optional linebreak after Words/Characters
 *    |
 *       # -- Or, Linebreak
 *       (?: \r? \n | $ )        # Stand alone linebreak or at EOS
 *  )
 *
 * @author Krisztián Ferenczi <ferenczi.krisztian@gmail.com>
 *
 * @see https://stackoverflow.com/a/20434776/1476819
 */
final class OutputWrapper
{
    private const TAG_OPEN_REGEX_SEGMENT = '[a-z](?:[^\\\\<>]++ | \\\\.)*+';
    private const TAG_CLOSE_REGEX_SEGMENT = '[a-z][^<>]*+';
    private const URL_PATTERN = 'https?://\S+';
    private const WIDE_CHAR_FILLER = "\u{FDD0}";

    public function __construct(
        private bool $allowCutUrls = false,
    ) {
    }

    public function wrap(string $text, int $width, string $break = "\n"): string
    {
        if (!$width) {
            return $text;
        }

        // the u modifier counts characters instead of bytes, but preg_replace() returns null
        // on malformed UTF-8, which would empty the text
        $modifiers = preg_match('//u', $text) ? 'imux' : 'imx';
        // a wide character uses two columns: a filler that follows it makes the pattern count both of them, and a line can't end between the two
        $wideChars = [];
        if ('imux' === $modifiers && !str_contains($text, self::WIDE_CHAR_FILLER) && preg_match_all('/[^\x00-\x{10FF}]/u', $text, $chars)) {
            foreach (array_unique($chars[0]) as $char) {
                if (2 === Helper::width($char)) {
                    $wideChars[$char] = $char.self::WIDE_CHAR_FILLER;
                }
            }
        }
        if ($wideChars) {
            $text = strtr($text, $wideChars);
        }

        // an escaped "<" never starts a tag, as in OutputFormatter
        $tagPattern = \sprintf('(?<!\\\\<)(?:(?:%s)|/(?:%s)?)>', self::TAG_OPEN_REGEX_SEGMENT, self::TAG_CLOSE_REGEX_SEGMENT);
        $hasTags = str_contains($text, '<') && preg_match("#<$tagPattern#$modifiers", $text);
        $hasEscapes = str_contains($text, '\\<') || str_contains($text, '\\>');
        $hasUrls = !$this->allowCutUrls && preg_match('#'.self::URL_PATTERN.'#i', $text);
        // PCRE compiles a bounded repetition of a group by copying the group, so tags, escapes and URLs are subroutine calls that keep each copy small
        // with them, the pattern still gets too large at about 1000 columns, and 65535 is the highest value a quantifier accepts
        $width = min($width, $hasTags || $hasEscapes || $hasUrls ? 900 : 65535);
        // an escaped "<" or ">" prints one character, so the backslash and the escaped character take one column together
        // a line never ends between the two, or the next line would start with a tag delimiter
        $char = $hasEscapes ? '(?&char)' : '.';
        $charPattern = $hasUrls ? "(?!(?&url))$char" : $char;
        if ($hasTags) {
            // tags print nothing, so they take no column and stay with the characters next to them
            $tag = '(?:<(?&tag))';
            $rowPattern = "(?:(?:$tag*+$char){1,$width}$tag*+|$tag++)";
            $cutPattern = "(?:(?:$tag*+$charPattern){1,$width}|$tag++)";
            $wordStartPattern = "$tag*+[^\\s<]{0,$width}?$tag*+";
        } else {
            $rowPattern = "$char{1,$width}";
            $cutPattern = $hasUrls ? "(?:$charPattern){1,$width}" : $rowPattern;
            $wordStartPattern = "[^\\s<]{0,$width}?";
        }
        // URLs are never cut. A URL longer than the width gets a line of its own, together with the start of its word when that fits
        $urlPattern = $hasUrls ? "|$wordStartPattern(?&url)[^\\S\\r\\n]?" : '';
        if ($wideChars) {
            $rowPattern .= '(?!'.self::WIDE_CHAR_FILLER.')';
            $cutPattern .= '(?!'.self::WIDE_CHAR_FILLER.')';
        }
        $pattern = \sprintf('#(?:((?>(%1$s)((?<=[^\S\r\n])[^\S\r\n]?|(?=\r?\n)|$|[^\S\r\n]))%3$s|(%2$s))(?:\r?\n)?|(?:\r?\n|$))(?(DEFINE)(?<tag>%5$s)(?<url>%6$s)(?<char>(?>\\\\[<>]|.)))#%4$s', $rowPattern, $cutPattern, $urlPattern, $modifiers, $tagPattern, self::URL_PATTERN);
        $output = rtrim(preg_replace($pattern, '\\1'.$break, $text), $break);

        $output = str_replace(' '.$break, $break, $output);

        return $wideChars ? str_replace(self::WIDE_CHAR_FILLER, '', $output) : $output;
    }
}
