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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ChainStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Dispatches the next message of a ChainStamp once the current message is handled.
 *
 * The next message is dispatched with a DispatchAfterCurrentBusStamp: it is
 * handled after the current message, outside a transaction opened for it,
 * and in a worker, after the handlers of the current message returned.
 * The remaining messages travel with it in a new ChainStamp, so a chain
 * continues from where it stopped when a failed step is retried.
 *
 * When an envelope carries several ChainStamps, their messages form one
 * sequence, in stamp order.
 *
 * A message sent to a transport starts its next step where it is handled,
 * whatever the position of this middleware in the stack.
 *
 * The chain stamps leave the returned envelope once the next step is dispatched,
 * so a stack that reaches this middleware twice for the same message, as a
 * synchronous transport does, starts that step once.
 *
 * A message handled by a batch handler cannot open a chain: the batch decides
 * when it processes the message, which is after the handler returned.
 */
final class ChainMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $messages = [];
        foreach ($envelope->all(ChainStamp::class) as $stamp) {
            $messages = [...$messages, ...$stamp->getMessages()];
        }

        if (!$messages) {
            return $envelope;
        }

        if ($envelope->last(SentStamp::class) && !$envelope->last(ReceivedStamp::class)) {
            return $envelope;
        }

        if ($noAutoAckStamp = $envelope->last(NoAutoAckStamp::class)) {
            throw new LogicException(\sprintf('A message handled by the batch handler "%s" cannot carry a "%s".', $noAutoAckStamp->getHandlerDescriptor()->getName(), ChainStamp::class));
        }

        $next = Envelope::wrap(array_shift($messages), [new DispatchAfterCurrentBusStamp()]);

        if ($messages) {
            $next = $next->with(new ChainStamp(...$messages));
        }

        if (null === $next->last(BusNameStamp::class) && null !== $busNameStamp = $envelope->last(BusNameStamp::class)) {
            $next = $next->with($busNameStamp);
        }

        $this->bus->dispatch($next);

        return $envelope->withoutAll(ChainStamp::class);
    }
}
