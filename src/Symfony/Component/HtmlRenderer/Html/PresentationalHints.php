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

/** @internal */
final class PresentationalHints
{
    public static function declarations(string $tagName, array $attributes): string
    {
        $hints = [];
        $length = static fn (string $value): ?string => match (true) {
            1 === preg_match('/^\s*(\d{1,4})\s*$/', $value, $m) => $m[1].'px',
            1 === preg_match('/^\s*(\d{1,3}(?:\.\d+)?)%\s*$/', $value, $m) => $m[1].'%',
            default => null,
        };
        $cells = ['td', 'th'];
        if ('textarea' === $tagName && preg_match('/^\s*(\d{1,4})\s*$/', $attributes['cols'] ?? '', $m) && (int) $m[1] > 0) {
            $hints[] = 'width: '.$m[1].'ch';
        }
        if (\in_array($tagName, ['table', ...$cells], true) && isset($attributes['width']) && null !== $width = $length($attributes['width'])) {
            $hints[] = 'width: '.$width;
        }
        // The size of a frame, like browsers: CSS pixels or a percentage
        if ('iframe' === $tagName) {
            foreach (['width', 'height'] as $side) {
                if (isset($attributes[$side]) && null !== $size = $length($attributes[$side])) {
                    $hints[] = $side.': '.$size;
                }
            }
        }
        if (\in_array($tagName, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'caption', 'tr', 'thead', 'tbody', 'tfoot', ...$cells], true) && \in_array($align = strtolower(trim($attributes['align'] ?? '')), ['left', 'right', 'center', 'justify'], true)) {
            $hints[] = 'text-align: '.$align;
        }
        // A table centered, like the button of an email, or floated, like an image beside the text, by its align
        $align = strtolower(trim($attributes['align'] ?? ''));
        if ('table' === $tagName && 'center' === $align) {
            $hints[] = 'margin-left: auto';
            $hints[] = 'margin-right: auto';
        } elseif (\in_array($tagName, ['table', 'img'], true) && \in_array($align, ['left', 'right'], true)) {
            $hints[] = 'float: '.$align;
        }
        if (\in_array($tagName, $cells, true) && isset($attributes['nowrap'])) {
            $hints[] = 'white-space: nowrap';
        }
        // <font color>, like browsers: a color only
        if ('font' === $tagName && preg_match('/^\s*(#[0-9a-f]{3,8}|[a-z]{3,20})\s*$/i', $attributes['color'] ?? '', $m)) {
            $hints[] = 'color: '.$m[1];
        }
        // <font size>: 1 to 7, or more or less than 3, a keyword of font-size; <font face>: the families
        if ('font' === $tagName && preg_match('/^\s*([+-]?)(\d+)/', $attributes['size'] ?? '', $m)) {
            $size = max(1, min(7, '' === $m[1] ? (int) $m[2] : 3 + ('+' === $m[1] ? 1 : -1) * (int) $m[2]));
            $hints[] = 'font-size: '.['x-small', 'small', 'medium', 'large', 'x-large', 'xx-large', 'xxx-large'][$size - 1];
        }
        if ('font' === $tagName && preg_match('/^[\w\s,"\'-]+$/u', $face = trim($attributes['face'] ?? ''))) {
            $hints[] = 'font-family: '.$face;
        }
        // A color only: nothing else can go through
        if (\in_array($tagName, ['body', 'table', 'tr', ...$cells], true) && preg_match('/^\s*(#[0-9a-f]{3,8}|[a-z]{3,20})\s*$/i', $attributes['bgcolor'] ?? '', $m)) {
            $hints[] = 'background-color: '.$m[1];
        }
        if ('table' === $tagName && preg_match('/^\s*(\d{1,3})?\s*$/', $attributes['border'] ?? 'x', $m)) {
            $hints[] = '' === ($m[1] ?? '') || (int) $m[1] > 0 ? 'border: 1px solid' : 'border: none';
        }
        // A legend placed in the border of its fieldset by its align, like the HTML Standard
        if ('legend' === $tagName && \in_array($align, ['left', 'center', 'right'], true)) {
            $hints[] = 'justify-self: '.$align;
        }
        // A rule of a width, placed by its align, like <hr width="50%" align="left">
        if ('hr' === $tagName && null !== $width = $length($attributes['width'] ?? '')) {
            $hints[] = 'width: '.$width;
            $hints[] = match (strtolower(trim($attributes['align'] ?? ''))) {
                'left' => 'margin-right: auto',
                'right' => 'margin-left: auto',
                default => 'margin-left: auto; margin-right: auto',
            };
        }

        return implode('; ', $hints);
    }
}
