<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html\Element;

use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Widget\AbstractWidget;

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
    /**
     * @param \Closure(): string $value
     */
    public static function text(AbstractWidget $widget, \Dom\Element $element, string $type, \Closure $value): void
    {
        self::watch($widget, $element, static fn (): array => \Symfony\Component\HtmlRenderer\Form\ConstraintValidator::text($element, $type, $value()));
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
}
