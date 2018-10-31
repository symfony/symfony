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
    private $busName;
    private $stopwatch;
    private $eventCategory;

    public function __construct(string $busName, Stopwatch $stopwatch, string $eventCategory = 'messenger.middleware')
    {
        $this->busName = $busName;
        $this->stopwatch = $stopwatch;
        $this->eventCategory = $eventCategory;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stack = new TraceableStack($stack, $this->busName, $this->stopwatch, $this->eventCategory);

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
    private $stack;
    private $busName;
    private $stopwatch;
    private $eventCategory;
    private $currentEvent;

    public function __construct(StackInterface $stack, string $busName, Stopwatch $stopwatch, string $eventCategory)
    {
        $this->stack = $stack;
        $this->busName = $busName;
        $this->stopwatch = $stopwatch;
        $this->eventCategory = $eventCategory;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): MiddlewareInterface
    {
        if (null !== $this->currentEvent) {
            $this->stopwatch->stop($this->currentEvent);
        }

        if ($this->stack === $nextMiddleware = $this->stack->next()) {
            $this->currentEvent = 'Tail';
        } else {
            $class = \get_class($nextMiddleware);
            $this->currentEvent = sprintf('"%s"', 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class);
        }
        $this->currentEvent .= sprintf(' on "%s"', $this->busName);

        $this->stopwatch->start($this->currentEvent, $this->eventCategory);

        return $nextMiddleware;
    }

    public function stop()
    {
        if (null !== $this->currentEvent && $this->stopwatch->isStarted($this->currentEvent)) {
            $this->stopwatch->stop($this->currentEvent);
        }
        $this->currentEvent = null;
    }
}
