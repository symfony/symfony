<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class TolerateNoHandler implements MiddlewareInterface
{
    public function handle($message, callable $next)
    {
        try {
            return $next($message);
        } catch (NoHandlerForMessageException $e) {
            // We tolerate not having a handler for this message.
        }
    }
}
