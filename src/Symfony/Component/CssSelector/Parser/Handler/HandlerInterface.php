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
 * CSS selector handler interface.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
interface HandlerInterface
{
    /**
     * @param Reader      $reader
     * @param TokenStream $stream
     *
     * @return boolean
     */
    public function handle(Reader $reader, TokenStream $stream);
}
