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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\DispatchOnFailureTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Dispatches the failure message of a message that fails while it is handled synchronously.
 *
 * A message received from a transport is skipped: the worker decides when it
 * fails for good, and the DispatchOnFailureListener dispatches the failure
 * message then. A message that a synchronous transport sent to a failure
 * transport instead of throwing comes back with a SentToFailureTransportStamp,
 * which counts as a failure too.
 *
 * This middleware should run before the middleware that opens a transaction,
 * so that the failure message is dispatched once the transaction was rolled back.
 */
final class DispatchOnFailureMiddleware implements MiddlewareInterface
{
    use DispatchOnFailureTrait;

    public function __construct(
        private MessageBusInterface $bus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(ReceivedStamp::class) || null === $envelope->last(DispatchOnFailureStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            $result = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $this->dispatchFailureMessage($envelope, $e);

            throw $e;
        }

        if (null !== ($stamp = $result->last(SentToFailureTransportStamp::class)) && $stamp !== $envelope->last(SentToFailureTransportStamp::class)) {
            $this->dispatchFailureMessage($result, null);
        }

        return $result;
    }
}
