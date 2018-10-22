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
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\Locator\HandlerLocatorInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    private $messageHandlerLocator;
    private $allowNoHandlers;

    public function __construct(HandlerLocatorInterface $messageHandlerLocator, bool $allowNoHandlers = false)
    {
        $this->messageHandlerLocator = $messageHandlerLocator;
        $this->allowNoHandlers = $allowNoHandlers;
    }

    /**
     * {@inheritdoc}
     *
     * @throws NoHandlerForMessageException When no handler is found and $allowNoHandlers is false
     */
    public function handle(Envelope $envelope, callable $next): void
    {
        if (null !== $handler = $this->messageHandlerLocator->getHandler($envelope)) {
            $handler($envelope->getMessage());
            $next($envelope);
        } elseif (!$this->allowNoHandlers) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', \get_class($envelope->getMessage())));
        }
    }
}
