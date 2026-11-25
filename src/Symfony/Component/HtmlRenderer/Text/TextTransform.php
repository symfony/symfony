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
final class TextTransform
{
    public static function keywords(string $value): ?array
    {
        $parts = ValueTokenizer::split(strtolower(trim($value)));
        if (['none'] === $parts) {
            return [];
        }
        $case = $width = $kana = null;
        foreach ($parts as $part) {
            if (\in_array($part, ['uppercase', 'lowercase', 'capitalize'], true) && null === $case) {
                $case = $part;
            } elseif ('full-width' === $part && null === $width) {
                $width = $part;
            } elseif ('full-size-kana' === $part && null === $kana) {
                $kana = $part;
            } else {
                return null;
            }
        }

        return $parts ? array_values(array_filter([$case, $width, $kana])) : null;
    }

    public static function apply(string $text, array $keywords): string
    {
        foreach ($keywords as $keyword) {
            $text = match ($keyword) {
                'uppercase' => mb_strtoupper($text),
                'lowercase' => mb_strtolower($text),
                'capitalize' => preg_replace_callback('/(^|[\s\p{P}])(\p{Ll})/u', static fn (array $m) => $m[1].mb_strtoupper($m[2]), $text),
                'full-width' => preg_replace_callback('/[!-~]/', static fn (array $m) => mb_chr(\ord($m[0]) + 0xFEE0) ?: $m[0], $text),
                'full-size-kana' => self::fullSizeKana($text),
                default => $text,
            } ?? $text;
        }

        return $text;
    }

    public static function fullSizeKana(string $text): string
    {
        static $map = null;
        $map ??= array_combine(mb_str_split('ぁぃぅぇぉゕゖっゃゅょゎァィゥェォヵㇰヶㇱㇲッㇳㇴㇵㇶㇷㇸㇹㇺャュョㇻㇼㇽㇾㇿヮｧｨｩｪｫｯｬｭｮ𛄲𛅐𛅑𛅒𛅕𛅤𛅥𛅦𛅧'), mb_str_split('あいうえおかけつやゆよわアイウエオカクケシスツトヌハヒフヘホムヤユヨラリルレロワｱｲｳｴｵﾂﾔﾕﾖこゐゑをコヰヱヲン'));

        return strtr($text, $map);
    }
}
