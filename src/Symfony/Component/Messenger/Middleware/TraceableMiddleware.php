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
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Collects some data about a middleware.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class TraceableMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Stopwatch $stopwatch,
        private string $busName,
        private string $eventCategory = 'messenger.middleware',
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stack = new TraceableStack($stack, $this->stopwatch, $this->busName, $this->eventCategory);

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $stack->stop();
        }
    }
}

/**
 * @internal
 */
class TraceableStack implements StackInterface
{
    private ?string $currentEvent = null;

    public function __construct(
        private StackInterface $stack,
        private Stopwatch $stopwatch,
        private string $busName,
        private string $eventCategory,
    ) {
    }

    public function next(): MiddlewareInterface
    {
        if (null !== $this->currentEvent && $this->stopwatch->isStarted($this->currentEvent)) {
            $this->stopwatch->stop($this->currentEvent);
        }

        if ($this->stack === $nextMiddleware = $this->stack->next()) {
            $this->currentEvent = 'Tail';
        } else {
            $this->currentEvent = \sprintf('"%s"', get_debug_type($nextMiddleware));
        }
        $this->currentEvent .= \sprintf(' on "%s"', $this->busName);

        $this->stopwatch->start($this->currentEvent, $this->eventCategory);

        return $nextMiddleware;
    }

    public function stop(): void
    {
        if (null !== $this->currentEvent && $this->stopwatch->isStarted($this->currentEvent)) {
            $this->stopwatch->stop($this->currentEvent);
        }
        $this->currentEvent = null;
    }

    public function __clone()
    {
        $this->stack = clone $this->stack;
    }
}
