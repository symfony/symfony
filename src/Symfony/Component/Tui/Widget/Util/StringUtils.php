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

        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return false === $sanitized ? '' : $sanitized;
    }

    /**
     * Strip terminal-escape introducer bytes from untrusted text.
     *
     * Removes C0 controls (except TAB and LF), DEL, and the UTF-8 encoding
     * of C1 controls (`\xc2[\x80-\x9f]`). Prevents terminal-escape injection
     * by removing the introducer bytes (ESC, BEL, 8-bit CSI); any payload
     * bytes that followed survive as visible literal text.
     */
    public static function stripControlBytes(string $value): string
    {
        return preg_replace("/[\x00-\x08\x0b-\x1f\x7f]|\xc2[\x80-\x9f]/", '', $value) ?? '';
    }
}
