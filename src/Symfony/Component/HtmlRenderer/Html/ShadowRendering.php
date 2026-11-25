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

use Symfony\Component\HtmlRenderer\Layout\Fragment;

/** @internal */
final class ShadowRendering
{
    public static function fragment(Fragment $fragment, \SplObjectStorage $owners): Fragment
    {
        return new Fragment(self::owner($fragment->node, $owners), $fragment->bounds, $fragment->style, $fragment->text, array_map(static fn (Fragment $child): Fragment => self::fragment($child, $owners), $fragment->children), $fragment->image, $fragment->transform, $fragment->backgrounds, $fragment->run, $fragment->pseudoElement, $fragment->topLayer, $fragment->scroll, $fragment->borderImage, self::anchor($fragment->anchor, $owners), $fragment->content);
    }

    public static function styles(\SplObjectStorage $styles, \SplObjectStorage $owners): \SplObjectStorage
    {
        $mapped = new \SplObjectStorage();
        foreach ($styles as $element) {
            $mapped[self::owner($element, $owners)] = $styles[$element];
        }

        return $mapped;
    }

    private static function anchor(?\Dom\Element $element, \SplObjectStorage $owners): ?\Dom\Element
    {
        $source = self::owner($element, $owners);
        if (null !== $source && !$source instanceof \Dom\Element) {
            throw new \LogicException('A composed anchor must preserve its source element identity.');
        }

        return $source;
    }

    private static function owner(?\Dom\Node $node, \SplObjectStorage $owners): ?\Dom\Node
    {
        if (null === $node) {
            return null;
        }
        if (!isset($owners[$node])) {
            throw new \LogicException('A composed rendering node has no active source owner.');
        }

        return $owners[$node];
    }
}
