<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware\Enhancers;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Collects some data about a middleware.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class TraceableMiddleware implements MiddlewareInterface
{
    private $inner;
    private $stopwatch;
    private $busName;
    private $eventCategory;

    public function __construct(MiddlewareInterface $inner, Stopwatch $stopwatch, string $busName = null, string $eventCategory = 'messenger.middleware')
    {
        $this->inner = $inner;
        $this->stopwatch = $stopwatch;
        $this->busName = $busName;
        $this->eventCategory = $eventCategory;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Envelope $envelope, callable $next): void
    {
        $class = \get_class($this->inner);
        $eventName = 'c' === $class[0] && 0 === strpos($class, "class@anonymous\0") ? get_parent_class($class).'@anonymous' : $class;

        if ($this->busName) {
            $eventName .= " (bus: {$this->busName})";
        }

        $this->stopwatch->start($eventName, $this->eventCategory);

        try {
            $this->inner->handle($envelope, function (Envelope $envelope) use ($next, $eventName) {
                $this->stopwatch->stop($eventName);
                $next($envelope);
                $this->stopwatch->start($eventName, $this->eventCategory);
            });
        } finally {
            if ($this->stopwatch->isStarted($eventName)) {
                $this->stopwatch->stop($eventName);
            }
        }
    }
}
