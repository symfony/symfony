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
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\OutboxStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SenderStampInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Stores new messages in an outbox transport instead of sending them to their target transport.
 *
 * Consuming the outbox transport forwards the stored messages to the target transport.
 * Redelivered messages went through the outbox already and are sent to the target directly.
 * When the outbox transport sends batches, the new messages of a batch are stored with as few requests as it allows.
 */
final class OutboxSender implements BatchSenderInterface
{
    /**
     * @param string      $outboxName           The name of the outbox transport
     * @param string|null $failureTransportName The name of the failure transport of the outbox transport
     */
    public function __construct(
        private SenderInterface $target,
        private SenderInterface $outbox,
        private string $targetName,
        private string $outboxName,
        private ?string $failureTransportName = null,
    ) {
    }

    /**
     * Tells whether a received message that carries an OutboxStamp was stored by this outbox.
     *
     * @internal
     */
    public function canForward(Envelope $envelope): bool
    {
        $transportName = $envelope->last(ReceivedStamp::class)?->getTransportName();

        if (null === $transportName) {
            return false;
        }

        if ($this->outboxName === $transportName) {
            return true;
        }

        // retrying the failure transport gives back the messages that the outbox failed to forward
        return $this->failureTransportName === $transportName && $this->outboxName === $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName();
    }

    public function send(Envelope $envelope): Envelope
    {
        if ($outboxStamp = $envelope->last(OutboxStamp::class)) {
            // the outbox served the delay and the retry history belongs to the relay
            foreach ([OutboxStamp::class, DelayStamp::class, RedeliveryStamp::class, ErrorDetailsStamp::class, SentToFailureTransportStamp::class] as $stampFqcn) {
                $envelope = $envelope->withoutAll($stampFqcn);
            }

            // the relay worker acks the returned envelope on the outbox transport,
            // so the message id given by the target must not shadow the outbox one
            $this->target->send(self::restoreSenderStamps($envelope, $outboxStamp));

            return $envelope;
        }

        if ($envelope->last(RedeliveryStamp::class)) {
            return $this->target->send($envelope);
        }

        return $this->outbox->send($this->stampForOutbox($envelope))->withoutAll(OutboxStamp::class);
    }

    public function sendBatch(array $envelopes): array
    {
        $sent = $exceptions = [];
        $new = array_filter($envelopes, static fn (Envelope $envelope) => !$envelope->last(OutboxStamp::class) && !$envelope->last(RedeliveryStamp::class));

        // storing first keeps an exception other than BatchSendFailedException meaning that no envelope was sent
        if ($new && $this->outbox instanceof BatchSenderInterface) {
            try {
                $sent = $this->outbox->sendBatch(array_map($this->stampForOutbox(...), $new));
            } catch (BatchSendFailedException $e) {
                $sent = $e->getEnvelopes();
                $exceptions = $e->getExceptions();
            }

            $sent = array_map(static fn (Envelope $envelope) => $envelope->withoutAll(OutboxStamp::class), $sent);
        }

        foreach (array_diff_key($envelopes, $sent, $exceptions) as $key => $envelope) {
            try {
                $sent[$key] = $this->send($envelope);
            } catch (\Throwable $e) {
                $exceptions += array_fill_keys(array_keys(array_diff_key($envelopes, $sent, $exceptions)), $e);
                break;
            }
        }

        if ($exceptions) {
            throw new BatchSendFailedException($sent, $exceptions);
        }

        return $sent;
    }

    private function stampForOutbox(Envelope $envelope): Envelope
    {
        $senderStamps = [];

        // the serializer of the outbox drops non-sendable stamps
        foreach ($envelope->all() as $class => $stamps) {
            if (is_a($class, SenderStampInterface::class, true)) {
                $senderStamps[$class] = base64_encode(serialize($stamps));
            }
        }

        return $envelope->with(new OutboxStamp($this->targetName, $senderStamps));
    }

    private static function restoreSenderStamps(Envelope $envelope, OutboxStamp $outboxStamp): Envelope
    {
        foreach ($outboxStamp->getSenderStamps() as $class => $serializedStamps) {
            $stamps = null;

            // nothing but the sender stamp class named by the key can be unserialized
            if (is_a($class, SenderStampInterface::class, true) && \is_string($serializedStamps) && false !== $serializedStamps = base64_decode($serializedStamps, true)) {
                $stamps = @unserialize($serializedStamps, ['allowed_classes' => [$class]]);
            }

            if (!\is_array($stamps) || !$stamps || array_any($stamps, static fn ($stamp) => !$stamp instanceof $class)) {
                throw new UnrecoverableMessageHandlingException(\sprintf('The outbox stamp of the message carries invalid "%s" stamps.', $class));
            }

            $envelope = $envelope->withoutAll($class)->with(...array_values($stamps));
        }

        return $envelope;
    }
}
