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

use Symfony\Component\Messenger\Bridge\Kafka\Stamp\KafkaMessageStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class KafkaSender implements SenderInterface
{
    public function __construct(
        private Connection $connection,
        private SerializerInterface $serializer = new PhpSerializer(),
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedMessage = $this->serializer->encode($envelope);
        $key = null;
        $partition = \RD_KAFKA_PARTITION_UA;
        $messageFlags = \RD_KAFKA_MSG_F_BLOCK;

        if ($messageStamp = $envelope->last(KafkaMessageStamp::class)) {
            $key = $messageStamp->key;
            $partition = $messageStamp->partition;
            $messageFlags = $messageStamp->messageFlags;
        }

        try {
            $this->connection->publish(
                $partition,
                $messageFlags,
                $encodedMessage['body'],
                $key,
                $encodedMessage['headers'] ?? [],
            );
        } catch (\RdKafka\Exception $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }
}
