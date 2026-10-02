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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Failure\FailureMessageDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Dispatches the failure message of a message whose synchronous handling throws, then rethrows the exception.
 *
 * It also dispatches, first, the failure messages of the delayed messages that failed in their handlers, since they fail the message that dispatched them.
 * A message received from a transport is skipped: the worker decides when it fails for good, and the DispatchOnFailureListener dispatches the failure messages then.
 * A synchronous transport dispatches the message again with a ReceivedStamp: that inner dispatch is skipped too, so the failure message is dispatched once, after the retries of the transport.
 * A synchronous transport with a failure transport does not throw: it dispatches the failure message itself.
 *
 * This middleware must run before DispatchAfterCurrentBusMiddleware, which resumes the handling of a delayed message after itself.
 * The failure of a delayed message then reaches this middleware once, as the failure of the message that dispatched it.
 * The failure message is then dispatched on its own, once the delayed messages of the failed message were handled or dropped, so that its own delayed messages are handled.
 * It should run before the middleware that opens a transaction, so that the failure message is dispatched once the transaction was rolled back.
 */
final class DispatchOnFailureMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            FailureMessageDispatcher::dispatch($this->bus, $this->logger, $envelope, $e);

            throw $e;
        }
    }
}
