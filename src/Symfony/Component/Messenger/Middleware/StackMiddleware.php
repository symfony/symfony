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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class StackMiddleware implements MiddlewareInterface, StackInterface
{
    private $stack;
    private $offset = 0;

    /**
     * @param iterable|MiddlewareInterface[]|MiddlewareInterface|null $middlewareIterator
     */
    public function __construct($middlewareIterator = null)
    {
        $this->stack = new MiddlewareStack();

        if (null === $middlewareIterator) {
            return;
        }

        if ($middlewareIterator instanceof \Iterator) {
            $this->stack->iterator = $middlewareIterator;
        } elseif ($middlewareIterator instanceof MiddlewareInterface) {
            $this->stack->stack[] = $middlewareIterator;
        } elseif (!is_iterable($middlewareIterator)) {
            throw new \TypeError(sprintf('Argument 1 passed to "%s()" must be iterable of "%s", "%s" given.', __METHOD__, MiddlewareInterface::class, get_debug_type($middlewareIterator)));
        } else {
            $this->stack->iterator = (function () use ($middlewareIterator) {
                yield from $middlewareIterator;
            })();
        }
    }

    public function next(): MiddlewareInterface
    {
        if (null === $next = $this->stack->next($this->offset)) {
            return $this;
        }

        ++$this->offset;

        return $next;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $envelope;
    }
}

/**
 * @internal
 */
class MiddlewareStack
{
    public $iterator;
    public $stack = [];

    public function next(int $offset): ?MiddlewareInterface
    {
        if (isset($this->stack[$offset])) {
            return $this->stack[$offset];
        }

        if (null === $this->iterator) {
            return null;
        }

        $this->iterator->next();

        if (!$this->iterator->valid()) {
            return $this->iterator = null;
        }

        return $this->stack[] = $this->iterator->current();
    }
}
