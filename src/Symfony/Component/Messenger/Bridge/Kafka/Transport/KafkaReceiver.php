<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

use Symfony\Component\Messenger\Bridge\Kafka\Stamp\KafkaReceivedMessageStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaReceiver implements ReceiverInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer = new PhpSerializer(),
    ) {
    }

    /** @psalm-return \Traversable<Envelope> */
    public function get(): iterable
    {
        yield from $this->getEnvelope();
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function ack(Envelope $envelope): void
    {
        /** @var KafkaReceivedMessageStamp $transportStamp */
        $transportStamp = $envelope->last(KafkaReceivedMessageStamp::class);

        $this->connection->ack($transportStamp->message);
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function reject(Envelope $envelope): void
    {
        // no reject method for kafka transport
    }

    /** @psalm-return iterable<Envelope> */
    private function getEnvelope(): iterable
    {
        try {
            $kafkaMessage = $this->connection->get();
        } catch (\RdKafka\Exception $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (\RD_KAFKA_RESP_ERR_NO_ERROR !== $kafkaMessage->err) {
            switch ($kafkaMessage->err) {
                case \RD_KAFKA_RESP_ERR__PARTITION_EOF: // No more messages
                case \RD_KAFKA_RESP_ERR__TIMED_OUT: // Attempt to connect again
                    return [];
                default:
                    throw new TransportException($kafkaMessage->errstr(), $kafkaMessage->err);
            }
        }

        yield $this->serializer->decode([
            'body' => $kafkaMessage->payload,
            'headers' => $kafkaMessage->headers,
        ])->with(new KafkaReceivedMessageStamp($kafkaMessage));
    }
}
