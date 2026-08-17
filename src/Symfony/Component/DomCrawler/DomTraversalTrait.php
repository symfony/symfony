<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

/**
 * Tree lookups expressed with the members both DOM APIs share.
 *
 * The classic and the native DOM are distinct class hierarchies, but they
 * expose the same names for traversal, so the lookups below work on either of
 * them. Only \Dom\Element has a selector engine, hence no selector is used here.
 *
 * @internal
 */
trait DomTraversalTrait
{
    /**
     * Returns the closest ancestor with the given tag name, the node itself excluded.
     */
    protected static function findAncestor(\DOMElement|\Dom\Element $node, string $localName): \DOMElement|\Dom\Element|null
    {
        $parent = $node->parentNode;

        while ($parent instanceof \DOMElement || $parent instanceof \Dom\Element) {
            if ($localName === $parent->localName) {
                return $parent;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    /**
     * Collects the descendant elements accepted by the given predicate, in document order.
     *
     * @param callable(\DOMElement|\Dom\Element): bool $accept
     *
     * @return list<\DOMElement|\Dom\Element>
     */
    protected static function collectDescendants(\DOMElement|\Dom\Element|\DOMDocument|\Dom\Document $root, callable $accept): array
    {
        $found = [];
        $walk = static function ($node) use (&$walk, &$found, $accept): void {
            foreach ($node->childNodes as $child) {
                if (!$child instanceof \DOMElement && !$child instanceof \Dom\Element) {
                    continue;
                }

                if ($accept($child)) {
                    $found[] = $child;
                }

                $walk($child);
            }
        };
        $walk($root);

        return $found;
    }
}
