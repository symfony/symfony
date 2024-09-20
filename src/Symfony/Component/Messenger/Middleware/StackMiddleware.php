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
    private MiddlewareStack $stack;
    private int $offset = 0;

    /**
     * @param iterable<mixed, MiddlewareInterface>|MiddlewareInterface|null $middlewareIterator
     */
    public function __construct(iterable|MiddlewareInterface|null $middlewareIterator = null)
    {
        $this->stack = new MiddlewareStack();

        if (null === $middlewareIterator) {
            return;
        }

        if ($middlewareIterator instanceof \Iterator) {
            $this->stack->iterator = $middlewareIterator;
        } elseif ($middlewareIterator instanceof MiddlewareInterface) {
            $this->stack->stack[] = $middlewareIterator;
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
    /** @var \Iterator<mixed, MiddlewareInterface>|null */
    public ?\Iterator $iterator = null;
    public array $stack = [];

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
