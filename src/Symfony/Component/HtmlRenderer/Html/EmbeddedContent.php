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
final class EmbeddedContent
{
    public static function alternative(\Dom\Element $element, ?\Closure $attribute = null): string
    {
        if ('true' === strtolower(trim($element->getAttribute('aria-hidden') ?? ''))) {
            return '';
        }
        $attribute ??= $element->getAttribute(...);
        if ('img' === strtolower($element->localName)) {
            return $attribute('alt') ?? '';
        }
        $alternative = $attribute('aria-label') ?? $attribute('title');
        if (null === $alternative) {
            for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
                if ('title' === strtolower($child->localName)) {
                    return $child->textContent;
                }
            }
        }

        return $alternative ?? '';
    }
}
