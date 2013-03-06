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
 * CSS selector comment handler.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class CommentHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(Reader $reader, TokenStream $stream)
    {
        if ('/*' !== $reader->getSubstring(2)) {
            return false;
        }

        $offset = $reader->getOffset('*/');

        if (false === $offset) {
            $reader->moveToEnd();
        } else {
            $reader->moveForward($offset + 2);
        }

        return true;
    }
}
