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
final class TreeScope
{
    public static function root(\Dom\Node $node): \Dom\Node
    {
        return $node->getRootNode();
    }

    public static function getElementById(\Dom\Node $origin, string $id): ?\Dom\Element
    {
        if ('' === $id) {
            return null;
        }
        $root = self::root($origin);
        if ($root instanceof \Dom\Document) {
            return $root->getElementById($id);
        }
        if (!$root instanceof \Dom\Element && !$root instanceof \Dom\DocumentFragment) {
            return null;
        }
        if ($root instanceof \Dom\Element && $root->getAttribute('id') === $id) {
            return $root;
        }
        foreach ($root->querySelectorAll('[id]') as $element) {
            if ($element->getAttribute('id') === $id) {
                return $element;
            }
        }

        return null;
    }
}
