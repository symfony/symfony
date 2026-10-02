<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\FlowContextMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\TrustStamp;

/**
 * Dispatches the messages named by the DispatchOnFailureStamps of failed envelopes.
 *
 * @internal
 */
final class FailureMessageDispatcher
{
    /**
     * Dispatches the failure message of a failed envelope, after the failure messages of the delayed messages whose handlers failed while it was handled.
     *
     * A delayed message handled in the process of the message that dispatched it is part of the handling of that message: its failure message is dispatched when that message fails for good.
     * A DispatchOnFailureStamp shared by several messages, as the steps of a chain share it, dispatches its failure message once, for the delayed message that failed.
     *
     * Each failure message gets a FailedMessageStamp holding the message that failed, an ErrorDetailsStamp describing its failure, and the bus name of that message unless it names its own bus.
     * It is dispatched without non-sendable stamps, and as untrusted when the message that failed is not trusted, since whoever created that message chose its failure message too.
     * It continues the flow of the message that failed, wherever it is dispatched from: it gets the propagated stamps of that message, as FlowContextMiddleware gives them to the messages dispatched while it is handled, and a CausationStamp holding its id.
     *
     * A failure to dispatch a failure message is logged and swallowed: it must not replace the original failure, and the caller then handles the failed envelope as if it had no failure message.
     *
     * @return bool Whether the failure message of the failed envelope was dispatched, possibly for a delayed message that shares its DispatchOnFailureStamp
     */
    public static function dispatch(MessageBusInterface $bus, ?LoggerInterface $logger, Envelope $failedEnvelope, \Throwable $throwable): bool
    {
        $dispatched = new \WeakMap();

        if ($throwable instanceof DelayedMessageHandlingException) {
            foreach ($throwable->getWrappedExceptions(HandlerFailedException::class) as $exception) {
                $envelope = $exception->getEnvelope();

                if (null !== ($stamp = $envelope->last(DispatchOnFailureStamp::class)) && !isset($dispatched[$stamp])) {
                    $dispatched[$stamp] = self::dispatchFailureMessage($bus, $logger, $envelope, $stamp, $exception);
                }
            }
        }

        if (null === $stamp = $failedEnvelope->last(DispatchOnFailureStamp::class)) {
            return false;
        }

        return $dispatched[$stamp] ??= self::dispatchFailureMessage($bus, $logger, $failedEnvelope, $stamp, $throwable);
    }

    private static function dispatchFailureMessage(MessageBusInterface $bus, ?LoggerInterface $logger, Envelope $failedEnvelope, DispatchOnFailureStamp $stamp, \Throwable $throwable): bool
    {
        $envelope = Envelope::wrap($stamp->getMessage())
            ->withoutStampsOfType(NonSendableStampInterface::class)
            ->with(new FailedMessageStamp($failedEnvelope->getMessage()), ErrorDetailsStamp::create($throwable));
        $envelope = FlowContextMiddleware::propagate($envelope, $failedEnvelope);

        if (null === $envelope->last(CausationStamp::class) && null !== $messageId = $failedEnvelope->last(MessageIdStamp::class)) {
            $envelope = $envelope->with(new CausationStamp($messageId->getId()));
        }

        if (null === $envelope->last(BusNameStamp::class) && null !== $busNameStamp = $failedEnvelope->last(BusNameStamp::class)) {
            $envelope = $envelope->with($busNameStamp);
        }

        if (!TrustStamp::isEnvelopeTrusted($failedEnvelope)) {
            $envelope = $envelope->with(TrustStamp::untrusted());
        }

        $context = ['class' => $failedEnvelope->getMessage()::class, 'failure_class' => $envelope->getMessage()::class];
        $logger?->info('Dispatching {failure_class} because message {class} failed.', $context);

        try {
            $bus->dispatch($envelope);
        } catch (\Throwable $e) {
            $logger?->error('Failed dispatching {failure_class} after message {class} failed.', $context + ['exception' => $e]);

            return false;
        }

        return true;
    }
}
