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
final class ControlValue
{
    public static function initial(\Dom\Element $element): string
    {
        if ('textarea' === $element->localName) {
            return $element->textContent;
        }
        if ('select' === $element->localName) {
            $selected = SelectOptions::selected($element);

            return isset($selected[0]) ? SelectOptions::value($selected[0]) : '';
        }
        $type = 'input' === $element->localName ? strtolower($element->getAttribute('type') ?? 'text') : '';

        return match ($type) {
            'file' => '',
            'color' => strtolower(trim($element->getAttribute('value') ?? '#000000')),
            default => $element->getAttribute('value') ?? '',
        };
    }

    public static function displayLabel(\Dom\Element $element): ?string
    {
        if ('button' === $element->localName) {
            return $element->textContent;
        }
        if ('input' !== $element->localName) {
            return null;
        }
        $type = strtolower($element->getAttribute('type') ?? 'text');
        if (!\in_array($type, ['submit', 'reset', 'button', 'image'], true)) {
            return null;
        }

        return ('image' === $type ? $element->getAttribute('alt') : null) ?? $element->getAttribute('value') ?? match ($type) {
            'submit','image' => 'Submit',
            'reset' => 'Reset',
            default => '',
        };
    }

    public static function supportsPlaceholder(\Dom\Element $element): bool
    {
        return 'textarea' === $element->localName || 'input' === $element->localName && \in_array(strtolower($element->getAttribute('type') ?? 'text'), ['text', 'search', 'tel', 'url', 'email', 'password', 'number', 'file', 'color'], true);
    }

    public static function colorSwatch(string $value): ?string
    {
        return 1 === preg_match('/^#[0-9a-f]{6}$/iD', $value) ? strtolower($value) : null;
    }
}
