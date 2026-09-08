<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Stores new messages in an outbox transport instead of sending them to their target transport.
 *
 * Consuming the outbox transport forwards the stored messages to the target transport.
 * Redelivered messages went through the outbox already and are sent to the target directly.
 */
final class OutboxSender implements SenderInterface
{
    public function __construct(
        private SenderInterface $target,
        private SenderInterface $outbox,
        private string $targetName,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        if ($envelope->last(OutboxStamp::class)) {
            // the outbox served the delay and the retry history belongs to the relay
            foreach ([OutboxStamp::class, DelayStamp::class, RedeliveryStamp::class, ErrorDetailsStamp::class, SentToFailureTransportStamp::class] as $stampFqcn) {
                $envelope = $envelope->withoutAll($stampFqcn);
            }

            // the relay worker acks the returned envelope on the outbox transport,
            // so the message id given by the target must not shadow the outbox one
            $this->target->send($envelope);

            return $envelope;
        }

        if ($envelope->last(RedeliveryStamp::class)) {
            return $this->target->send($envelope);
        }

        return $this->outbox->send($envelope->with(new OutboxStamp($this->targetName)))->withoutAll(OutboxStamp::class);
    }
}
