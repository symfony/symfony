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
    private const TAG_OPEN_REGEX_SEGMENT = '[a-z](?:[^\\\\<>]*+ | \\\\.)*';
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

        if (!preg_match('//u', $text)) {
            // everything below counts characters, which malformed UTF-8 has none of: the fold
            // would return null and empty the text. Invalid bytes become U+FFFD, which is what
            // a terminal displays for them anyway, and each takes one position like any character
            $substitute = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            try {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            } finally {
                mb_substitute_character($substitute);
            }
        }

        // a wide character uses two columns: a filler that follows it makes the pattern count both of them, and a line can't end between the two
        $wideChars = [];
        if (!str_contains($text, self::WIDE_CHAR_FILLER) && preg_match_all('/[^\x00-\x{10FF}]/u', $text, $chars)) {
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
        $tagPattern = \sprintf('(?<!\\\\)<(?:(?:%s)|/(?:%s)?)>', self::TAG_OPEN_REGEX_SEGMENT, self::TAG_CLOSE_REGEX_SEGMENT);

        // Fold each tag to one unused private-use codepoint so that the row pattern repeats a
        // small group: PCRE compiles a bounded repetition of a group by duplicating the group,
        // which hits the compiled-size limit around width 137 with the tag pattern inside
        $i = 0;
        do {
            $marker = mb_chr(0xF0000 + $i++, 'UTF-8');
        } while (str_contains($text, $marker));
        $tags = [];
        $text = preg_replace_callback('#'.$tagPattern.'#iux', static function ($m) use (&$tags, $marker) {
            $tags[] = $m[0];

            return $marker;
        }, $text);

        $hasUrls = !$this->allowCutUrls && preg_match('#'.self::URL_PATTERN.'#i', $text);
        // 65535 is the highest value a quantifier accepts, and a repeated group compiles up to about 1000 repetitions
        $width = min($width, $tags || $hasUrls ? 900 : 65535);
        // a URL takes its own width but is never cut: a word too long for a line is cut before the URL it contains
        $charPattern = $hasUrls ? '(?!'.self::URL_PATTERN.').' : '.';
        if ($tags) {
            // tags print nothing, so they take no column and stay with the characters next to them
            $rowPattern = "(?:(?:$marker*+.){1,$width}$marker*+|$marker++)";
            $cutPattern = "(?:(?:$marker*+$charPattern){1,$width}|$marker++)";
        } else {
            $rowPattern = ".{1,$width}";
            $cutPattern = $hasUrls ? "(?:$charPattern){1,$width}" : $rowPattern;
        }
        // a URL longer than the width gets a line of its own, together with the start of its word when that fits
        $urlPattern = $hasUrls ? "|$marker*+[^\\s<$marker]{0,$width}?$marker*+".self::URL_PATTERN.'[^\S\r\n]?' : '';
        if ($wideChars) {
            $rowPattern .= '(?!'.self::WIDE_CHAR_FILLER.')';
            $cutPattern .= '(?!'.self::WIDE_CHAR_FILLER.')';
        }
        // a line cut inside a word never ends between a backslash and the "<" or ">" it escapes,
        // or the escaped character would start the next line as a tag delimiter
        $pattern = \sprintf('#(?:((?>(%1$s)((?<=[^\S\r\n])[^\S\r\n]?|(?=\r?\n)|$|[^\S\r\n]))%3$s|(%2$s)(?!(?<=\\\\)[<>\x{F0000}-\x{10FFFF}]))(?:\r?\n)?|(?:\r?\n|$))#imux', $rowPattern, $cutPattern, $urlPattern);
        $output = rtrim(preg_replace($pattern, '\\1'.$break, $text), $break);
        $output = str_replace(' '.$break, $break, $output);
        if ($tags) {
            $parts = explode($marker, $output);
            $output = array_shift($parts);
            foreach ($parts as $k => $part) {
                $output .= $tags[$k].$part;
            }
        }

        return $wideChars ? str_replace(self::WIDE_CHAR_FILLER, '', $output) : $output;
    }
}
