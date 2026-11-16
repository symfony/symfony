<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html\Element;

use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;

/**
 * HTML constraint validation, kept in widget states as the value changes.
 *
 * - "valid" or "invalid", for :valid and :invalid: required, minlength,
 *   maxlength, pattern, and the email, url, number and date and time types
 *   (with min and max)
 * - "user-valid" or "user-invalid" once the value was changed, for
 *   :user-valid and :user-invalid
 * - "in-range" or "out-of-range" for numbers with min or max
 *
 * Disabled and readonly controls are barred from validation: they match none of them.
 *
 * @experimental
 *
 * @internal
 */
final class ConstraintValidation
{
    /** The valid e-mail address of the HTML standard */
    private const EMAIL = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

    /**
     * @param \Closure(): string $value
     */
    public static function text(AbstractWidget $widget, \Dom\Element $element, string $type, \Closure $value): void
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
        self::watch($widget, $element, static function () use ($value, $type, $required, $minLength, $maxLength, $pattern, $multiple, $min, $max, $ranged): array {
            $text = $value();
            if ('' === $text) {
                return [!$required, $ranged ? true : null];
            }
            $length = mb_strlen($text);
            $valid = (null === $minLength || $length >= $minLength) && (null === $maxLength || $length <= $maxLength) && (null === $pattern || 1 === preg_match($pattern, $text)) && match ($type) {
                'email' => array_all($multiple ? array_map('trim', explode(',', $text)) : [$text], static fn (string $address) => 1 === preg_match(self::EMAIL, $address)),
                'url' => 1 === preg_match('~^[a-z][a-z0-9+.-]*:\S+$~i', $text),
                'number' => is_numeric($text),
                'color' => 1 === preg_match('/^#[0-9a-f]{6}$/i', $text),
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
        });
    }

    /**
     * @param \Closure(): bool $checked
     */
    public static function checkbox(AbstractWidget $widget, \Dom\Element $element, \Closure $checked): void
    {
        $required = $element->hasAttribute('required');
        self::watch($widget, $element, static fn (): array => [!$required || $checked(), null]);
    }

    /**
     * @param \Closure(): array{bool, ?bool} $check Whether the value is valid, and in range (null when not ranged)
     */
    private static function watch(AbstractWidget $widget, \Dom\Element $element, \Closure $check): void
    {
        if (HtmlBuilder::isDisabled($element) || $element->hasAttribute('readonly')) {
            return;
        }
        $update = static function (bool $changed) use ($widget, $check): void {
            [$valid, $inRange] = $check();
            $widget->setState('valid', $valid)->setState('invalid', !$valid);
            $widget->setState('user-valid', $changed && $valid)->setState('user-invalid', $changed && !$valid);
            $widget->setState('in-range', true === $inRange)->setState('out-of-range', false === $inRange);
        };
        $update(false);
        // Only a change by the user updates the states, and makes them user-*
        $widget->on(ChangeEvent::class, static fn () => $update(true));
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
