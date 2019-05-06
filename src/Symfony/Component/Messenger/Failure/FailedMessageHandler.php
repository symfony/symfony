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
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Handles a FailedMessage by redelivering via the original transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @experimental in 4.3
 */
class FailedMessageHandler
{
    private $messageBus;
    private $logger;

    public function __construct(MessageBusInterface $messageBus, LoggerInterface $logger = null)
    {
        $this->messageBus = $messageBus;
        $this->logger = $logger ?: new NullLogger();
    }

    public function __invoke(FailedMessage $failedMessage)
    {
        $failedEnvelope = $failedMessage->getFailedEnvelope();
        $envelope = $failedMessage->getFailedEnvelope()
            ->withoutAll(DelayStamp::class)
            ->withoutAll(RedeliveryStamp::class)
            ->withoutAll(ReceivedStamp::class);

        if ($failedMessage->isStrategyRetry()) {
            // ReceivedStamp is needed to fake that the message was received in the original way
            $receivedStamp = $failedEnvelope->last(ReceivedStamp::class);
            if (null === $receivedStamp) {
                throw new UnrecoverableMessageHandlingException('Cannot retry failed message: the original envelope lacks a ReceivedStamp.');
            }

            // fake that the message was just received from the transport
            // this will cause it to be retried immediately
            $envelope = $envelope->with($receivedStamp);
        } else {
            // SentStamp is needed so message is resent to only original
            // sender for this failed message
            /** @var SentStamp $sentStamp */
            $sentStamp = $failedEnvelope->last(SentStamp::class);
            if (null === $sentStamp) {
                throw new UnrecoverableMessageHandlingException('Cannot resend failed message: the original envelope lacks a SentStamp.');
            }

            $envelope = $envelope->with(new RedeliveryStamp(0, $sentStamp->getSenderAlias() ?: $sentStamp->getSenderClass()));
        }

        $this->messageBus->dispatch($envelope);
    }
}
