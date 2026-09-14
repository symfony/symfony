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
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Copies the propagated stamps of the message being handled onto the messages dispatched while handling it.
 *
 * A nested dispatch inherits the PropagatedStampInterface stamps of the message on top of the
 * handling stack, except for the stamp classes it already carries. A received message is never
 * enriched, but the messages its handler dispatches inherit its propagated stamps. One instance
 * shared by several buses propagates across them.
 */
final class PropagateStampsMiddleware implements MiddlewareInterface
{
    /**
     * @var list<Envelope>
     */
    private array $stack = [];

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($this->stack && null === $envelope->last(ReceivedStamp::class)) {
            foreach (end($this->stack)->all() as $class => $stamps) {
                if (is_subclass_of($class, PropagatedStampInterface::class) && null === $envelope->last($class)) {
                    $envelope = $envelope->with(...$stamps);
                }
            }
        }

        $this->stack[] = $envelope;

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            array_pop($this->stack);
        }
    }
}
