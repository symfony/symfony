<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** @internal */
final class ListOrdinals
{
    public static function of(\Dom\Element $element, array &$cache): int
    {
        if (null === $list = $element->parentElement) {
            return is_numeric($element->getAttribute('value')) ? CounterState::clamp((int) $element->getAttribute('value')) : 1;
        }
        $id = spl_object_id($list);
        if (!isset($cache[$id]) || $cache[$id][0] !== $list) {
            $items = [];
            for ($child = $list->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                if ('li' === $child->localName) {
                    $items[] = $child;
                }
            }
            $reversed = null !== $list->getAttribute('reversed');
            $ordinal = is_numeric($list->getAttribute('start')) ? CounterState::clamp((int) $list->getAttribute('start')) : ($reversed ? \count($items) : 1);
            // The items are kept with their ordinals, so that their ids stay theirs while the cascade runs
            $ordinals = new \SplObjectStorage();
            foreach ($items as $item) {
                if (is_numeric($item->getAttribute('value'))) {
                    $ordinal = CounterState::clamp((int) $item->getAttribute('value'));
                }
                $ordinals[$item] = $ordinal;
                $ordinal = CounterState::clamp($reversed ? $ordinal - 1 : $ordinal + 1);
            }
            // What is not an item counts after them all
            $cache[$id] = [$list, $ordinals, $ordinal];
        }

        return $cache[$id][1][$element] ?? $cache[$id][2];
    }
}
