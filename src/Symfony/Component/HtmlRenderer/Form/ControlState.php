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

use Symfony\Component\HtmlRenderer\Html\TreeScope;

/** @internal */
final class ControlState
{
    public static function formOwner(\Dom\Element $element): ?\Dom\Element
    {
        if (!$element->hasAttribute('form')) {
            return $element->closest('form');
        }
        $owner = TreeScope::getElementById($element, $element->getAttribute('form'));

        return null !== $owner && 'form' === strtolower($owner->localName) ? $owner : null;
    }

    public static function radioValidity(\Dom\Node|iterable $elements, ?\Closure $checked = null): \SplObjectStorage
    {
        $owners = new \SplObjectStorage();
        $groups = [];
        if ($elements instanceof \Dom\Document || $elements instanceof \Dom\DocumentFragment || $elements instanceof \Dom\Element) {
            $elements = $elements->querySelectorAll('input');
        } elseif ($elements instanceof \Dom\Node) {
            return new \SplObjectStorage();
        }
        foreach ($elements as $element) {
            if ('radio' !== strtolower($element->getAttribute('type') ?? '')) {
                continue;
            }
            $owner = self::formOwner($element) ?? TreeScope::root($element);
            $owners[$owner] = true;
            $name = $element->getAttribute('name') ?? '';
            $key = spl_object_id($owner).':'.('' === $name ? 'node:'.spl_object_id($element) : 'name:'.$name);
            $groups[$key] ??= ['required' => false, 'checked' => false, 'elements' => []];
            $groups[$key]['required'] = $groups[$key]['required'] || $element->hasAttribute('required');
            $groups[$key]['checked'] = $groups[$key]['checked'] || (null !== $checked ? $checked($element) : $element->hasAttribute('checked'));
            $groups[$key]['elements'][] = $element;
        }
        $result = new \SplObjectStorage();
        foreach ($groups as $group) {
            foreach ($group['elements'] as $element) {
                $result[$element] = !$group['required'] || $group['checked'];
            }
        }

        return $result;
    }

    public static function isInert(\Dom\Element $element): bool
    {
        for ($ancestor = $element; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if ($ancestor->hasAttribute('inert')) {
                return true;
            }
        }

        return false;
    }

    public static function isDisabled(\Dom\Element $element): bool
    {
        $tagName = strtolower($element->localName);
        if (!\in_array($tagName, ['button', 'input', 'select', 'textarea', 'fieldset', 'option', 'optgroup'], true)) {
            return false;
        }
        if ($element->hasAttribute('disabled')) {
            return true;
        }
        for ($child = $element, $ancestor = $element->parentElement; null !== $ancestor; $child = $ancestor, $ancestor = $ancestor->parentElement) {
            if ('fieldset' !== strtolower($ancestor->localName) || !$ancestor->hasAttribute('disabled')) {
                continue;
            }
            $legend = null;
            for ($candidate = $ancestor->firstElementChild; null !== $candidate; $candidate = $candidate->nextElementSibling) {
                if ('legend' === strtolower($candidate->localName)) {
                    $legend = $candidate;
                    break;
                }
            }
            if ($child !== $legend) {
                return true;
            }
        }

        return false;
    }
}
