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
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.2
 */
class HandleMessageMiddleware implements MiddlewareInterface
{
    private $handlersLocator;
    private $allowNoHandlers;

    public function __construct(HandlersLocatorInterface $handlersLocator, bool $allowNoHandlers = false)
    {
        $this->handlersLocator = $handlersLocator;
        $this->allowNoHandlers = $allowNoHandlers;
    }

    /**
     * {@inheritdoc}
     *
     * @throws NoHandlerForMessageException When no handler is found and $allowNoHandlers is false
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $handler = null;
        $message = $envelope->getMessage();
        foreach ($this->handlersLocator->getHandlers($envelope) as $alias => $handler) {
            $envelope = $envelope->with(HandledStamp::fromCallable($handler, $handler($message), \is_string($alias) ? $alias : null));
        }
        if (null === $handler && !$this->allowNoHandlers) {
            throw new NoHandlerForMessageException(sprintf('No handler for message "%s".', \get_class($envelope->getMessage())));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
