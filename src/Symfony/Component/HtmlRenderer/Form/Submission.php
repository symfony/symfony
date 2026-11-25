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

/** @internal Shared HTML submission rules; control state and application data mapping belong to callers. */
final class Submission
{
    public static function isSubmitButton(\Dom\Element $control): bool
    {
        $type = strtolower($control->getAttribute('type') ?? '');

        return match (strtolower($control->localName)) {
            'button' => !\in_array($type, ['button', 'reset'], true),
            'input' => \in_array($type, ['submit', 'image'], true),
            default => false,
        };
    }

    /**
     * @param string|list<string> $value The caller's current value, or an empty list for an unchecked/unsuccessful control
     *
     * @return array<string|int, string|list<string>>
     */
    public static function values(\Dom\Element $control, string|array $value, ?\Dom\Element $submitter = null): array
    {
        $tag = strtolower($control->localName);
        $type = \in_array($tag, ['input', 'button'], true) ? strtolower($control->getAttribute('type') ?? '') : '';
        if (!\in_array($tag, ['input', 'button', 'textarea', 'select'], true) || \in_array($type, ['button', 'reset'], true) || self::isSubmitButton($control) && $control !== $submitter) {
            return [];
        }
        $name = $control->getAttribute('name') ?? '';
        if ('input' === $tag && 'image' === $type) {
            $prefix = '' === $name ? '' : $name.'.';

            // Programmatic/keyboard submission has no captured image coordinate.
            return [$prefix.'x' => '0', $prefix.'y' => '0'];
        }

        return '' !== $name && [] !== $value ? [$name => $value] : [];
    }

    /**
     * @return array{string, 'get'|'post'|'dialog'}
     */
    public static function settings(\Dom\Element $form, ?\Dom\Element $submitter = null, bool $trimMethodWhitespace = false): array
    {
        $action = $submitter?->getAttribute('formaction') ?? $form->getAttribute('action') ?? '';
        $method = $submitter?->getAttribute('formmethod') ?? $form->getAttribute('method') ?? '';
        $method = strtolower($trimMethodWhitespace ? trim($method) : $method);

        return [$action, \in_array($method, ['post', 'dialog'], true) ? $method : 'get'];
    }
}
