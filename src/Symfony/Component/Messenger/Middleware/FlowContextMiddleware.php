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
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Carries the context of the message being handled over to the messages dispatched while handling it.
 *
 * A nested dispatch inherits the PropagatedStampInterface stamps of the message being handled, except for the stamp classes it already carries.
 * A received message is never enriched, but the messages its handler dispatches inherit its propagated stamps.
 * One instance shared by several buses propagates across them.
 *
 * With identity stamps enabled, each message also gets a MessageIdStamp.
 * A message that opens a flow gets a CorrelationStamp built from that id, which the rest of the flow inherits.
 * A message dispatched while another one is handled gets a CausationStamp holding the id of that other message.
 * Stamps already carried by the message are kept as they are.
 *
 * This middleware must run after DecodeFailedMessageMiddleware, so that a replayed decoding failure keeps the identity and the stamps of the message it decodes to.
 * It must run before DispatchAfterCurrentBusMiddleware, so that a message handled after the current bus is the context of the messages its handler dispatches.
 *
 * @see DecodeFailedMessageMiddleware
 * @see DispatchAfterCurrentBusMiddleware
 */
final class FlowContextMiddleware implements MiddlewareInterface
{
    private ?\Closure $generateId = null;

    /**
     * @var list<Envelope>
     */
    private array $handling = [];

    /**
     * @param bool                                    $identityStamps Whether to add a MessageIdStamp, a CorrelationStamp and a CausationStamp to the messages
     * @param (\Closure(): (string|\Stringable))|null $generateId     Generates the message ids when identity stamps are enabled, 32 random hexadecimal characters by default
     */
    public function __construct(bool $identityStamps = false, ?\Closure $generateId = null)
    {
        if ($identityStamps) {
            $this->generateId = $generateId ?? static fn (): string => bin2hex(random_bytes(16));
        }
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $handled = $this->handling ? end($this->handling) : null;

        if (null !== $this->generateId) {
            if (null === $messageId = $envelope->last(MessageIdStamp::class)) {
                $envelope = $envelope->with($messageId = new MessageIdStamp(($this->generateId)()));
            }

            if (null === $handled) {
                if (null === $envelope->last(CorrelationStamp::class)) {
                    $envelope = $envelope->with(new CorrelationStamp($messageId->getId()));
                }
            } elseif (null === $envelope->last(CausationStamp::class) && null !== ($causedBy = $handled->last(MessageIdStamp::class)?->getId()) && $causedBy !== $messageId->getId()) {
                // a message dispatched again while it is handled, as the sync transport does, is not its own cause
                $envelope = $envelope->with(new CausationStamp($causedBy));
            }
        }

        if (null !== $handled && null === $envelope->last(ReceivedStamp::class)) {
            foreach ($handled->all() as $class => $stamps) {
                if (is_subclass_of($class, PropagatedStampInterface::class) && null === $envelope->last($class)) {
                    $envelope = $envelope->with(...$stamps);
                }
            }
        }

        if (null !== $envelope->last(DispatchAfterCurrentBusStamp::class)) {
            $stack = $this->trackOnResume($stack);

            return $stack->next()->handle($envelope, $stack);
        }

        return $this->track($envelope, $stack, $stack->next());
    }

    private function track(Envelope $envelope, StackInterface $stack, MiddlewareInterface $next): Envelope
    {
        $this->handling[] = $envelope;

        try {
            return $next->handle($envelope, $stack);
        } finally {
            array_pop($this->handling);
        }
    }

    /**
     * Tracks the envelope each time the rest of the stack starts handling it, including when DispatchAfterCurrentBusMiddleware resumes it after this middleware returned.
     */
    private function trackOnResume(StackInterface $stack): StackInterface
    {
        return new class($stack, $this->track(...)) implements StackInterface, MiddlewareInterface {
            private MiddlewareInterface $next;
            private bool $tracking = false;

            public function __construct(
                private StackInterface $stack,
                private \Closure $track,
            ) {
            }

            public function next(): MiddlewareInterface
            {
                $next = $this->stack->next();

                if ($this->tracking) {
                    return $next;
                }

                $this->next = $next;

                return $this;
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->tracking = true;

                try {
                    return ($this->track)($envelope, $stack, $this->next);
                } finally {
                    $this->tracking = false;
                }
            }
        };
    }
}
