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
final class IdAttributes
{
    public static function mark(\Dom\Element $root): void
    {
        // The root too, like <html id> in a document
        foreach ([$root, ...$root->querySelectorAll('[id]')] as $element) {
            if (null !== ($id = $element->getAttributeNode('id')) && '' !== $id->value && !$id->isId()) {
                $element->setIdAttribute('id', true);
            }
        }
    }
}
