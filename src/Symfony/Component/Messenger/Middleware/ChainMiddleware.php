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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Dispatches the next message of a ChainStamp once the current message is handled.
 *
 * The next message is dispatched with a DispatchAfterCurrentBusStamp: it is handled after the current message, outside a transaction opened for it, and in a worker, after the handlers of the current message returned.
 * The remaining messages travel with it in a new ChainStamp, so a chain continues from where it stopped when a failed step is retried.
 * When an envelope carries several ChainStamps, their messages form one sequence, in stamp order.
 *
 * When the current message was received from a transport, a next message without a transport of its own, from the routing or from a TransportNamesStamp, is sent to that transport, so that each step is retried on its own.
 * It is handled in this process when that transport is not a sender, as the transport of a schedule.
 *
 * The next message is dispatched without non-sendable stamps, which a chain read from a transport could carry, and as untrusted when the current message is not trusted.
 * It gets the DispatchOnFailureStamp of the current message unless it carries its own, so that a chain dispatches its failure message once, whichever step fails.
 *
 * A message sent to a transport starts its next step where it is handled, whatever the position of this middleware in the stack.
 * The returned envelope keeps the chain stamps and the DispatchOnFailureStamp, so that a message retried because its next step could not be dispatched dispatches that step again, with the same failure message.
 *
 * A message handled by a batch handler cannot open a chain: the batch decides when it processes the message, which is after the handler returned.
 */
final class ChainMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private SendersLocatorInterface $sendersLocator,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $sentStamps = \count($envelope->all(SentStamp::class));
        $envelope = $stack->next()->handle($envelope, $stack);

        $messages = [];
        foreach ($envelope->all(ChainStamp::class) as $stamp) {
            $messages = [...$messages, ...$stamp->getMessages()];
        }

        if (!$messages) {
            return $envelope;
        }

        // sent by this dispatch, including a received message that a worker sends again, as an outbox relay does
        if (\count($envelope->all(SentStamp::class)) > $sentStamps) {
            return $envelope;
        }

        if ($noAutoAckStamp = $envelope->last(NoAutoAckStamp::class)) {
            throw new LogicException(\sprintf('A message handled by the batch handler "%s" cannot carry a "%s".', $noAutoAckStamp->getHandlerDescriptor()->getName(), ChainStamp::class));
        }

        $next = Envelope::wrap(array_shift($messages))
            ->withoutStampsOfType(NonSendableStampInterface::class)
            ->with(new DispatchAfterCurrentBusStamp());

        if ($messages) {
            $next = $next->with(new ChainStamp(...$messages));
        }

        if (null === $next->last(BusNameStamp::class) && null !== $busNameStamp = $envelope->last(BusNameStamp::class)) {
            $next = $next->with($busNameStamp);
        }

        if (null === $next->last(DispatchOnFailureStamp::class) && null !== $failureStamp = $envelope->last(DispatchOnFailureStamp::class)) {
            $next = $next->with($failureStamp);
        }

        if (!TrustStamp::isEnvelopeTrusted($envelope)) {
            $next = $next->with(TrustStamp::untrusted());
        }

        if (null !== $receivedStamp = $envelope->last(ReceivedStamp::class)) {
            $next = $this->sendToTransport($next, $receivedStamp->getTransportName());
        }

        $this->bus->dispatch($next);

        return $envelope;
    }

    /**
     * Sends the next message to the given transport when it has no transport of its own.
     */
    private function sendToTransport(Envelope $next, string $transportName): Envelope
    {
        if ($next->all(TransportNamesStamp::class) || $this->hasSenders($next)) {
            return $next;
        }

        $routed = $next->with(new TransportNamesStamp([$transportName]));

        try {
            return $this->hasSenders($routed) ? $routed : $next;
        } catch (RuntimeException) {
            // the transport is not a sender
            return $next;
        }
    }

    private function hasSenders(Envelope $envelope): bool
    {
        foreach ($this->sendersLocator->getSenders($envelope) as $sender) {
            return true;
        }

        return false;
    }
}
