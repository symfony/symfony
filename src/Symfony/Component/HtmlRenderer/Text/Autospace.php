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

use Symfony\Component\HtmlRenderer\Css\ValueTokenizer;

/** @internal */
final class Autospace
{
    /** @return list<'alpha'|'numeric'>|null */
    public static function parse(string $value): ?array
    {
        $parts = ValueTokenizer::split(strtolower(trim($value)));
        if (['no-autospace'] === $parts) {
            return [];
        }
        if (['normal'] === $parts || ['auto'] === $parts) {
            return ['alpha', 'numeric'];
        }
        // ideograph-alpha || ideograph-numeric || punctuation, then insert or replace
        if (!$parts || \count($parts) !== \count(array_unique($parts)) || array_diff($parts, ['ideograph-alpha', 'ideograph-numeric', 'punctuation', 'insert', 'replace']) || \in_array('insert', $parts, true) && \in_array('replace', $parts, true)) {
            return null;
        }
        $kinds = [];
        foreach (['ideograph-alpha' => 'alpha', 'ideograph-numeric' => 'numeric'] as $keyword => $kind) {
            if (\in_array($keyword, $parts, true)) {
                $kinds[] = $kind;
            }
        }

        return $kinds || \in_array('punctuation', $parts, true) ? $kinds : null;
    }

    /**
     * @param list<'alpha'|'numeric'> $kinds
     * @param bool                    $test  Whether to return only the inserted boundary space
     */
    public static function apply(string $text, array $kinds, bool $test = false): string
    {
        $ideograph = '[\p{Han}\p{Hiragana}\p{Katakana}\p{Bopomofo}]';
        // Not the full-width forms, which are ideographic in width
        $other = '(?![\x{FF00}-\x{FFEF}])['.(\in_array('alpha', $kinds, true) ? '\p{Latin}\p{Greek}\p{Cyrillic}' : '').(\in_array('numeric', $kinds, true) ? '\p{Nd}' : '').']'; // codespell:ignore nd
        if ('(?![\x{FF00}-\x{FFEF}])[]' === $other) {
            return $test ? '' : $text;
        }
        $spaced = preg_replace('/(?<='.$ideograph.')(?='.$other.')|(?<='.$other.')(?='.$ideograph.')/u', ' ', $text) ?? $text;
        if ($test) {
            return $spaced === $text ? '' : ' ';
        }

        return $spaced;
    }
}
