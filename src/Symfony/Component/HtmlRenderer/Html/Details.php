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
final class Details
{
    public static function summary(\Dom\Element $details): ?\Dom\Element
    {
        for ($child = $details->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            if ('summary' === $child->localName) {
                return $child;
            }
        }

        return null;
    }
}
