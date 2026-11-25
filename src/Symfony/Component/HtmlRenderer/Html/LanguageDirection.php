<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

use Symfony\Component\HtmlRenderer\Css\Selector;

/** @internal */
final class LanguageDirection
{
    public static function resolve(array $attributes, string $language, string $direction, ?string $text = null): array
    {
        if (isset($attributes['lang'])) {
            $language = trim($attributes['lang']);
        }
        $own = strtolower(trim($attributes['dir'] ?? ''));
        if ('auto' === $own && null !== $text && class_exists(\IntlChar::class)) {
            foreach (mb_str_split($text) as $character) {
                $bidi = \IntlChar::charDirection($character);
                if (\in_array($bidi, [\IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT, \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT, \IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC], true)) {
                    $own = \IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT === $bidi ? 'ltr' : 'rtl';
                    break;
                }
            }
        }

        return [$language, \in_array($own, ['ltr', 'rtl'], true) ? $own : $direction];
    }

    public static function attributes(string $language, string $direction): array
    {
        $attributes = [];
        if ('' !== $language) {
            $attributes[Selector::LANG_ATTRIBUTE] = $language;
        }
        if ('rtl' === $direction) {
            $attributes[Selector::DIR_ATTRIBUTE] = 'rtl';
        }

        return $attributes;
    }
}
