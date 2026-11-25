<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Form;

/** @internal */
final class ConstraintValidator
{
    private const EMAIL = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

    public static function control(\Dom\Element $element, string $value, bool $checked = false, ?array $selectedValues = null, ?bool $radioValid = null): array
    {
        $type = strtolower($element->getAttribute('type') ?? 'text');
        $textControl = 'textarea' === $element->localName || 'input' === $element->localName && \in_array($type, ['text', 'search', 'tel', 'url', 'email', 'password', 'number', 'date', 'time', 'datetime-local', 'month', 'week'], true);
        if (ControlState::isDisabled($element) || $textControl && $element->hasAttribute('readonly') || 'button' === $element->localName || 'input' === $element->localName && \in_array($type, ['hidden', 'button', 'submit', 'reset', 'image'], true)) {
            return [true, null];
        }
        if ('select' === $element->localName) {
            if (!$element->hasAttribute('required')) {
                return [true, null];
            }
            $list = $element->hasAttribute('multiple') || (int) ($element->getAttribute('size') ?? '0') > 1;
            $selectedValues ??= array_map(SelectOptions::value(...), SelectOptions::selected($element));

            return [$list ? [] !== $selectedValues : '' !== $value, null];
        }
        if ('input' === $element->localName && 'radio' === $type) {
            return [$radioValid ?? (!$element->hasAttribute('required') || $checked), null];
        }
        if ('input' === $element->localName && 'checkbox' === $type) {
            return [!$element->hasAttribute('required') || $checked, null];
        }

        return self::text($element, $type, $value);
    }

    public static function text(\Dom\Element $element, string $type, string $text): array
    {
        $required = $element->hasAttribute('required');
        $minLength = self::integer($element->getAttribute('minlength'));
        $maxLength = self::integer($element->getAttribute('maxlength'));
        $pattern = self::pattern($element->getAttribute('pattern'));
        $multiple = $element->hasAttribute('multiple');
        $temporal = \in_array($type, ['date', 'time', 'datetime-local', 'month', 'week'], true);
        // Dates and times compare as strings: their formats are zero-padded
        $min = $temporal ? self::temporal($type, $element->getAttribute('min') ?? '') : (is_numeric($min = $element->getAttribute('min')) ? (float) $min : null);
        $max = $temporal ? self::temporal($type, $element->getAttribute('max') ?? '') : (is_numeric($max = $element->getAttribute('max')) ? (float) $max : null);
        $ranged = ('number' === $type || $temporal) && (null !== $min || null !== $max);
        if ('' === $text) {
            return [!$required, $ranged ? true : null];
        }
        $length = preg_match_all('/./us', $text);
        $valid = (null === $minLength || $length >= $minLength) && (null === $maxLength || $length <= $maxLength) && (null === $pattern || 1 === preg_match($pattern, $text)) && match ($type) {
            'email' => array_all($multiple ? array_map('trim', explode(',', $text)) : [$text], static fn (string $address) => 1 === preg_match(self::EMAIL, $address)),
            'url' => 1 === preg_match('~^[a-z][a-z0-9+.-]*:\S+$~i', $text),
            'number' => is_numeric($text),
            'color' => null !== ControlValue::colorSwatch($text),
            'date', 'time', 'datetime-local', 'month', 'week' => null !== self::temporal($type, $text),
            default => true,
        };
        $inRange = null;
        $comparable = 'number' === $type ? is_numeric($text) ? (float) $text : null : self::temporal($type, $text);
        if ($ranged && null !== $comparable) {
            $inRange = (null === $min || $comparable >= $min) && (null === $max || $comparable <= $max);
            $valid = $valid && $inRange;
        }

        return [$valid, $inRange];
    }

    /**
     * Returns a date or time in its normalized form, comparable as a string, null when it is not one.
     */
    private static function temporal(string $type, string $value): ?string
    {
        $value = trim($value);
        $date = static fn (string $y, string $m, string $d): bool => checkdate((int) $m, (int) $d, (int) $y);
        $time = '(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,3})?)?';

        return match ($type) {
            'date' => preg_match('/^(\d{4,})-(\d{2})-(\d{2})$/', $value, $m) && $date($m[1], $m[2], $m[3]) ? $value : null,
            'month' => preg_match('/^\d{4,}-(0[1-9]|1[0-2])$/', $value) ? $value : null,
            'week' => preg_match('/^\d{4,}-W(0[1-9]|[1-4]\d|5[0-3])$/', $value) ? $value : null,
            'time' => preg_match('/^'.$time.'$/', $value) ? $value : null,
            // A space may separate the date and the time: normalized to T
            'datetime-local' => preg_match('/^(\d{4,})-(\d{2})-(\d{2})[T ]('.$time.')$/', $value, $m) && $date($m[1], $m[2], $m[3]) ? $m[1].'-'.$m[2].'-'.$m[3].'T'.$m[4] : null,
            default => null,
        };
    }

    private static function integer(?string $value): ?int
    {
        return null !== $value && ctype_digit(trim($value)) ? (int) trim($value) : null;
    }

    private static function pattern(?string $pattern): ?string
    {
        if (null === $pattern || '' === $pattern) {
            return null;
        }
        // Like the HTML attribute, the whole value must match; an invalid pattern is ignored
        $regex = '~^(?:'.str_replace('~', '\~', $pattern).')$~u';

        return false !== @preg_match($regex, '') ? $regex : null;
    }
}
