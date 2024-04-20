<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\TextSanitizer;

/**
 * @internal
 */
final class StringSanitizer
{
    private const LOWERCASE = [
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'abcdefghijklmnopqrstuvwxyz',
    ];

    private const REPLACEMENTS = [
        [
            // "&#34;" is shorter than "&quot;"
            '&quot;',

            // Fix several potential issues in how browsers interpret attributes values
            '+',
            '=',
            '@',
            '`',

            // Some DB engines will transform UTF8 full-width characters their classical version
            // if the data is saved in a non-UTF8 field
            '＜',
            '＞',
            '＋',
            '＝',
            '＠',
            '｀',
        ],
        [
            '&#34;',

            '&#43;',
            '&#61;',
            '&#64;',
            '&#96;',

            '&#xFF1C;',
            '&#xFF1E;',
            '&#xFF0B;',
            '&#xFF1D;',
            '&#xFF20;',
            '&#xFF40;',
        ],
    ];

    /**
     * Applies a transformation to lowercase following W3C HTML Standard.
     *
     * @see https://w3c.github.io/html-reference/terminology.html#case-insensitive
     */
    public static function htmlLower(string $string): string
    {
        return strtr($string, self::LOWERCASE[0], self::LOWERCASE[1]);
    }

    /**
     * Encodes the HTML entities in the given string for safe injection in a document's DOM.
     */
    public static function encodeHtmlEntities(string $string): string
    {
        return str_replace(
            self::REPLACEMENTS[0],
            self::REPLACEMENTS[1],
            htmlspecialchars($string, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8')
        );
    }
}
