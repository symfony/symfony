<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser\Shortcut;

use Symfony\Component\CssSelector\Node\ElementNode;
use Symfony\Component\CssSelector\Node\HashNode;
use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Parser\ParserInterface;
use Symfony\Component\CssSelector\Parser\Token;

/**
 * CSS selector hash parser shortcut.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class HashParser implements ParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function parse($source)
    {
        // matches "<selector>#<id>"
        if (preg_match('~^[ \t\r\n\f]*([a-zA-Z]*)#([a-zA-Z0-9_-]+)[ \t\r\n\f]*$~', $source, $matches)) {
            return array(new SelectorNode(new HashNode(new ElementNode($matches[1] ?: null), $matches[2])));
        }

        return array();
    }
}
