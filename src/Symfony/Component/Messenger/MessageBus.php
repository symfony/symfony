<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 */
class MessageBus implements MessageBusInterface
{
    private $middlewareHandlers;

    /**
     * @var MiddlewareInterface[]|null
     */
    private $indexedMiddlewareHandlers;

    /**
     * @param MiddlewareInterface[]|iterable $middlewareHandlers
     */
    public function __construct(iterable $middlewareHandlers = array())
    {
        $this->middlewareHandlers = $middlewareHandlers;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message): void
    {
        if (!\is_object($message)) {
            throw new InvalidArgumentException(sprintf('Invalid type for message argument. Expected object, but got "%s".', \gettype($message)));
        }

        $this->callableForNextMiddleware(0)($message instanceof Envelope ? $message : new Envelope($message));
    }

    private function callableForNextMiddleware(int $index): callable
    {
        if (null === $this->indexedMiddlewareHandlers) {
            $this->indexedMiddlewareHandlers = \is_array($this->middlewareHandlers) ? array_values($this->middlewareHandlers) : iterator_to_array($this->middlewareHandlers, false);
        }

        if (!isset($this->indexedMiddlewareHandlers[$index])) {
            return static function () {};
        }

        return function (Envelope $envelope) use ($index) {
            $this->indexedMiddlewareHandlers[$index]->handle($envelope, $this->callableForNextMiddleware($index + 1));
        };
    }
}
