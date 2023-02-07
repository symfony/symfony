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

use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
final class KafkaSender implements SenderInterface
{
    private ?KafkaProducer $producer;

    public function __construct(
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private RdKafkaFactory $rdKafkaFactory,
        private KafkaConf $conf,
        private array $properties
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $producer = $this->getProducer();
        $topic = $producer->newTopic($this->properties['topic_name']);

        $encodedMessage = $this->serializer->encode($envelope);
        $attributes = [];

        /** @var KafkaMessageSendStamp|null $kafkaMessageSendStamp */
        if ($kafkaMessageSendStamp = $envelope->last(KafkaMessageSendStamp::class)) {
            $attributes = $kafkaMessageSendStamp->getAttributes();
        }

        $topic->producev(
            $attributes['partition'] ?? \RD_KAFKA_PARTITION_UA,
            $attributes['msgflags'] ?? 0,
            $encodedMessage['body'],
            $attributes['key'] ?? null,
            $encodedMessage['headers'] ?? $attributes['headers'] ?? null,
            $attributes['timestamp_ms'] ?? null
        );

        $code = \RD_KAFKA_RESP_ERR_NO_ERROR;
        for ($flushTry = 0; $flushTry <= $this->properties['flush_retries']; ++$flushTry) {
            $code = $producer->flush($this->properties['flush_timeout']);
            if (\RD_KAFKA_RESP_ERR_NO_ERROR === $code) {
                break;
            }
            $this->logger->info(sprintf('Kafka flush #%s didn\'t succeed.', $flushTry));
            sleep(1);
        }

        if (\RD_KAFKA_RESP_ERR_NO_ERROR !== $code) {
            throw new TransportException('Kafka producer response error: '.$code, $code);
        }

        return $envelope;
    }

    private function getProducer(): KafkaProducer
    {
        return $this->producer ?? $this->producer = $this->rdKafkaFactory->createProducer($this->conf);
    }
}
