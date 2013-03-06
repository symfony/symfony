<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Token\Handler;

use Symfony\Component\CssSelector\Token\Reader;
use Symfony\Component\CssSelector\Token\Token;
use Symfony\Component\CssSelector\Token\TokenStream;

/**
 * CSS selector whitespace handler.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class WhitespaceHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(Reader $reader, TokenStream $stream)
    {
        $match = $reader->findPattern('~^[ \t\r\n\f]+~');

        if (false === $match) {
            return false;
        }

        $stream->push(new Token(Token::TYPE_WHITESPACE, $match[0], $reader->getPosition()));
        $reader->moveForward(strlen($match[0]));

        return true;
    }
}
