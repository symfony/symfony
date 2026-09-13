<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DispatchOnFailureStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\FailedMessageStamp;

/**
 * @internal
 */
trait DispatchOnFailureTrait
{
    private MessageBusInterface $bus;
    private ?LoggerInterface $logger;

    /**
     * Dispatches the message named by the DispatchOnFailureStamp of a failed envelope.
     *
     * The failure message gets the message that failed, the error details recorded
     * on the failed envelope, or created from the throwable when there are none,
     * and the bus name of the failed envelope unless it names its own bus.
     *
     * A failure to dispatch it is logged and swallowed: it must not replace the
     * original failure, nor stop what the caller does next about it.
     */
    private function dispatchFailureMessage(Envelope $failedEnvelope, ?\Throwable $throwable): void
    {
        $envelope = Envelope::wrap($failedEnvelope->last(DispatchOnFailureStamp::class)->getMessage(), [new FailedMessageStamp($failedEnvelope->getMessage())]);

        if (null !== $errorDetailsStamp = $failedEnvelope->last(ErrorDetailsStamp::class)) {
            $envelope = $envelope->with($errorDetailsStamp);
        } elseif (null !== $throwable) {
            $envelope = $envelope->with(ErrorDetailsStamp::create($throwable));
        }

        if (null === $envelope->last(BusNameStamp::class) && null !== $busNameStamp = $failedEnvelope->last(BusNameStamp::class)) {
            $envelope = $envelope->with($busNameStamp);
        }

        try {
            $this->bus->dispatch($envelope);
        } catch (\Throwable $e) {
            $this->logger?->error('Failed dispatching {failure_class} after message {class} failed.', [
                'class' => $failedEnvelope->getMessage()::class,
                'failure_class' => $envelope->getMessage()::class,
                'exception' => $e,
            ]);
        }
    }
}
