<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class GeneratedContent
{
    public static function tokens(string $content): ?array
    {
        $content = trim($content);
        if (\in_array($content, ['none', 'normal', ''], true)) {
            return null;
        }
        preg_match_all('/"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\'|attr\(\s*([\w-]+)\s*\)|(open-quote|close-quote|no-open-quote|no-close-quote)|(counters?)\(\s*([\w-]+)\s*(?:,\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'))?\s*(?:,\s*([\w-]+)\s*)?\)|url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s"\']*))\s*\)/i', $content, $tokens, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);

        return $tokens;
    }

    public static function unescape(string $string): string
    {
        return preg_replace_callback('/\\\\([0-9a-fA-F]{1,6})\s?|\\\\(.)/s', static fn (array $m) => '' !== $m[1] ? self::character(min(\intval($m[1], 16), 0x10FFFF)) : $m[2], $string) ?? $string;
    }

    private static function character(int $code): string
    {
        if ($code < 0x80) {
            return \chr($code);
        }
        if ($code < 0x800) {
            return \chr(0xC0 | ($code >> 6)).\chr(0x80 | ($code & 0x3F));
        }
        if ($code < 0x10000) {
            return \chr(0xE0 | ($code >> 12)).\chr(0x80 | (($code >> 6) & 0x3F)).\chr(0x80 | ($code & 0x3F));
        }

        return \chr(0xF0 | ($code >> 18)).\chr(0x80 | (($code >> 12) & 0x3F)).\chr(0x80 | (($code >> 6) & 0x3F)).\chr(0x80 | ($code & 0x3F));
    }
}
