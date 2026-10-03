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

use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class MessageBus implements MessageBusInterface
{
    private \IteratorAggregate $middlewareAggregate;

    /**
     * @param iterable<mixed, MiddlewareInterface> $middlewareHandlers
     * @param list<class-string>                   $messageTypes       The types of the messages the application can dispatch on this bus, any type when empty
     * @param bool                                 $unwrapExceptions   Whether the application gets the exception of the failing handler instead of a HandlerFailedException
     */
    public function __construct(
        iterable $middlewareHandlers = [],
        private array $messageTypes = [],
        private bool $unwrapExceptions = false,
    ) {
        if ($middlewareHandlers instanceof \IteratorAggregate) {
            $this->middlewareAggregate = $middlewareHandlers;
        } elseif (\is_array($middlewareHandlers)) {
            $this->middlewareAggregate = new \ArrayObject($middlewareHandlers);
        } else {
            // $this->middlewareAggregate should be an instance of IteratorAggregate.
            // When $middlewareHandlers is an Iterator, we wrap it to ensure it is lazy-loaded and can be rewound.
            $this->middlewareAggregate = new class($middlewareHandlers) implements \IteratorAggregate {
                private \ArrayObject $cachedIterator;

                public function __construct(
                    private \Traversable $middlewareHandlers,
                ) {
                }

                public function getIterator(): \Traversable
                {
                    return $this->cachedIterator ??= new \ArrayObject(iterator_to_array($this->middlewareHandlers, false));
                }
            };
        }
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        // a received message is left alone: a decoding failure reaches the bus as a placeholder, and the worker needs the HandlerFailedException
        $fromApplication = !$envelope->last(ReceivedStamp::class);

        if ($fromApplication && $this->messageTypes && !$this->isDispatchable($envelope->getMessage())) {
            throw new InvalidArgumentException(\sprintf('This bus only dispatches messages of type "%s", "%s" given.', implode('" or "', $this->messageTypes), get_debug_type($envelope->getMessage())));
        }

        $middlewareIterator = $this->middlewareAggregate->getIterator();

        while ($middlewareIterator instanceof \IteratorAggregate) {
            $middlewareIterator = $middlewareIterator->getIterator();
        }
        $middlewareIterator->rewind();

        if (!$middlewareIterator->valid()) {
            return $envelope;
        }
        $stack = new StackMiddleware($middlewareIterator);

        try {
            return $middlewareIterator->current()->handle($envelope, $stack);
        } catch (HandlerFailedException $e) {
            if ($fromApplication && $this->unwrapExceptions && 1 === \count($exceptions = $e->getWrappedExceptions())) {
                throw reset($exceptions);
            }

            throw $e;
        }
    }

    private function isDispatchable(object $message): bool
    {
        foreach ($this->messageTypes as $type) {
            if ($message instanceof $type) {
                return true;
            }
        }

        return false;
    }
}
