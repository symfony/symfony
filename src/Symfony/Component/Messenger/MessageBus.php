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

use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Matthias Noback <matthiasnoback@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental in 4.2
 */
class MessageBus implements MessageBusInterface
{
    private $middlewareAggregate;

    /**
     * @param MiddlewareInterface[]|iterable $middlewareHandlers
     */
    public function __construct(iterable $middlewareHandlers = array())
    {
        if ($middlewareHandlers instanceof \IteratorAggregate) {
            $this->middlewareAggregate = $middlewareHandlers;
        } elseif (\is_array($middlewareHandlers)) {
            $this->middlewareAggregate = new \ArrayObject($middlewareHandlers);
        } else {
            $this->middlewareAggregate = new class() {
                public $aggregate;
                public $iterator;

                public function getIterator()
                {
                    return $this->aggregate = new \ArrayObject(iterator_to_array($this->iterator, false));
                }
            };
            $this->middlewareAggregate->aggregate = &$this->middlewareAggregate;
            $this->middlewareAggregate->iterator = $middlewareHandlers;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message): Envelope
    {
        if (!\is_object($message)) {
            throw new \TypeError(sprintf('Invalid argument provided to "%s()": expected object, but got %s.', __METHOD__, \gettype($message)));
        }
        $envelope = $message instanceof Envelope ? $message : new Envelope($message);
        $middlewareIterator = $this->middlewareAggregate->getIterator();

        while ($middlewareIterator instanceof \IteratorAggregate) {
            $middlewareIterator = $middlewareIterator->getIterator();
        }
        $middlewareIterator->rewind();

        if (!$middlewareIterator->valid()) {
            return $envelope;
        }
        $stack = new StackMiddleware($middlewareIterator);

        return $middlewareIterator->current()->handle($envelope, $stack);
    }
}
