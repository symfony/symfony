<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Util;

/**
 * General-purpose string utilities for terminal input handling.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class StringUtils
{
    /**
     * Check if the input data contains control characters (C0 controls + DEL).
     *
     * Only checks for ASCII control characters (0x00-0x1F and 0x7F).
     * Does NOT check for C1 control characters (U+0080-U+009F) at the byte
     * level, because bytes 0x80-0x9F are valid UTF-8 continuation bytes used
     * in multi-byte characters like emojis (e.g. 😀 = \xF0\x9F\x98\x80).
     */
    public static function hasControlChars(string $data): bool
    {
        for ($i = 0; $i < \strlen($data); ++$i) {
            $code = \ord($data[$i]);
            if ($code < 32 || 0x7F === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize a string by removing invalid UTF-8 byte sequences.
     */
    public static function sanitizeUtf8(string $value): string
    {
        if ('' === $value || false !== preg_match('//u', $value)) {
            return $value;
        }

        // Keep the well-formed multibyte sequences and drop every other non-ASCII byte. iconv() with //IGNORE would return false on a sequence cut at the end of the string, or on any invalid byte with musl.
        return preg_replace('/([\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|[\x80-\xFF]/', '$1', $value);
    }

    /**
     * Strip terminal-escape introducer bytes from untrusted text.
     *
     * Removes C0 controls (except TAB and LF), DEL, and the UTF-8 encoding
     * of C1 controls (`\xc2[\x80-\x9f]`). Prevents terminal-escape injection
     * by removing the introducer bytes (ESC, BEL, 8-bit CSI); any payload
     * bytes that followed survive as visible literal text.
     *
     * Pass well-formed UTF-8 (run it through {@see self::sanitizeUtf8()} first).
     * The replacement is single-pass, so on malformed input removing a control
     * byte between a lone 0xC2 and a following 0x80-0x9F byte would splice them
     * into a C1 introducer that then survives; sanitizeUtf8() drops such stray
     * bytes, which is why every caller here sanitizes first.
     */
    public static function stripControlBytes(string $value): string
    {
        return preg_replace("/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $value) ?? '';
    }

    /**
     * Decode the Ctrl+letter keys of a paste back to their control bytes.
     *
     * A tmux popup whose program enabled modifyOtherKeys mode 2, like a nested tmux client, sends the control bytes of a paste as Ctrl+letter keys: a newline arrives as ESC [ 27 ; 5 ; 106 ~, or as ESC [ 106 ; 5 u with extended-keys-format=csi-u.
     * Stripping the ESC would otherwise leave the rest of the sequence, like "[106;5u", in the text.
     */
    public static function decodeCtrlLetterKeys(string $value): string
    {
        if (!str_contains($value, "\x1b[")) {
            return $value;
        }

        return preg_replace_callback('/\x1b\[(?|(\d+);5u|27;5;(\d+)~)/', static function (array $match): string {
            $codepoint = (int) $match[1];

            return match (true) {
                $codepoint >= 97 && $codepoint <= 122 => \chr($codepoint - 96),
                $codepoint >= 65 && $codepoint <= 90 => \chr($codepoint - 64),
                default => $match[0],
            };
        }, $value) ?? $value;
    }
}
