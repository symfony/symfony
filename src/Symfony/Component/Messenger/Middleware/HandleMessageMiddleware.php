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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\Locator\HandlerLocatorInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    private $messageHandlerLocator;

    public function __construct(HandlerLocatorInterface $messageHandlerLocator, bool $allowNoHandler = false)
    {
        $this->messageHandlerLocator = $messageHandlerLocator;
        $this->allowNoHandler = $allowNoHandler;
    }

    public function handle(Envelope $envelope, callable $next): void
    {
        if ($handler = $this->messageHandlerLocator->getHandler($envelope, $this->allowNoHandler)) {
            $handler($envelope->getMessage());
        }

        $next($envelope);
    }
}
