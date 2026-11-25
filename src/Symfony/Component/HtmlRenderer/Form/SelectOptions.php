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
final class SelectOptions
{
    public static function isListBox(\Dom\Element $select): bool
    {
        return $select->hasAttribute('multiple') || (int) ($select->getAttribute('size') ?? '0') > 1;
    }

    public static function options(\Dom\Element $select): array
    {
        $options = [];
        foreach ($select->querySelectorAll('option') as $option) {
            $group = 'optgroup' === strtolower($option->parentElement->localName ?? '') ? $option->parentElement : null;
            if (!$option->hasAttribute('disabled') && !$option->hasAttribute('hidden') && !$group?->hasAttribute('disabled')) {
                $options[] = $option;
            }
        }

        return $options;
    }

    public static function value(\Dom\Element $option): string
    {
        return $option->getAttribute('value') ?? trim(preg_replace('/[\t\r\n\f ]+/', ' ', $option->textContent) ?? $option->textContent);
    }

    public static function label(\Dom\Element $option): string
    {
        return $option->getAttribute('label') ?? trim(preg_replace('/[\t\r\n\f ]+/', ' ', $option->textContent) ?? $option->textContent);
    }

    public static function selected(\Dom\Element $select, ?array $values = null): array
    {
        $options = self::options($select);
        $selected = [];
        foreach ($options as $option) {
            if (null === $values ? $option->hasAttribute('selected') : \in_array(self::value($option), $values, true)) {
                $selected[] = $option;
                if (!$select->hasAttribute('multiple')) {
                    break;
                }
            }
        }
        if (!$selected && null === $values && !$select->hasAttribute('multiple') && $options) {
            $selected[] = $options[0];
        }

        return $selected;
    }
}
