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
use RdKafka\KafkaConsumer;
use RdKafka\TopicPartition;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
final class KafkaReceiver implements ReceiverInterface
{
    private ?KafkaConsumer $consumer;
    private $subscribed = false;

    public function __construct(
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private RdKafkaFactory $rdKafkaFactory,
        private KafkaConf $conf,
        private array $properties
    ) {
        $this->conf->setRebalanceCb($this->createRebalanceCb($this->logger));
    }

    public function get(): iterable
    {
        $message = $this->getSubscribedConsumer()->consume($this->properties['receive_timeout']);

        switch ($message->err) {
            case \RD_KAFKA_RESP_ERR_NO_ERROR:
                $this->logger->debug(sprintf(
                    'Kafka: Message %s %s %s received ',
                    $message->topic_name,
                    $message->partition,
                    $message->offset
                ));

                $envelope = $this->serializer->decode([
                    'body' => $message->payload,
                    'headers' => $message->headers,
                ]);

                return [$envelope->with(new KafkaMessageReceivedStamp($message))];
            case \RD_KAFKA_RESP_ERR__PARTITION_EOF:
                $this->logger->debug('Kafka: Partition EOF reached. Waiting for next message ...');
                break;
            case \RD_KAFKA_RESP_ERR__TIMED_OUT:
                $this->logger->debug('Kafka: Consumer timeout.');
                break;
            case \RD_KAFKA_RESP_ERR__TRANSPORT:
                $this->logger->debug('Kafka: Broker transport failure.');
                break;
            default:
                throw new TransportException($message->errstr(), $message->err);
        }

        return [];
    }

    public function ack(Envelope $envelope): void
    {
        $consumer = $this->getConsumer();

        /** @var ?KafkaMessageReceivedStamp $transportStamp */
        $transportStamp = $envelope->last(KafkaMessageReceivedStamp::class);

        if (!$transportStamp) {
            throw new TransportException('Kafka message could not be acked because KafkaMessageReceivedStamp is missing.');
        }

        $message = $transportStamp->getMessage();

        if ($this->properties['commit_async']) {
            $consumer->commitAsync($message);

            $this->logger->debug(sprintf(
                'Offset topic=%s partition=%s offset=%s to be committed asynchronously.',
                $message->topic_name,
                $message->partition,
                $message->offset
            ));
        } else {
            $consumer->commit($message);

            $this->logger->debug(sprintf(
                'Offset topic=%s partition=%s offset=%s successfully committed.',
                $message->topic_name,
                $message->partition,
                $message->offset
            ));
        }
    }

    public function reject(Envelope $envelope): void
    {
        // Do nothing.
    }

    private function getSubscribedConsumer(): KafkaConsumer
    {
        $consumer = $this->getConsumer();

        if (false === $this->subscribed) {
            $this->logger->debug(sprintf('Partition assignment for topics %s ...', implode(', ', $this->properties['topics'])));
            $consumer->subscribe($this->properties['topics']);

            $this->subscribed = true;
        }

        return $consumer;
    }

    private function getConsumer(): KafkaConsumer
    {
        return $this->consumer ?? $this->consumer = $this->rdKafkaFactory->createConsumer($this->conf);
    }

    private function createRebalanceCb(LoggerInterface $logger): callable
    {
        return function (KafkaConsumer $kafkaConsumer, $err, array $topicPartitions = null) use ($logger) {
            /** @var TopicPartition[] $topicPartitions */
            $topicPartitions = $topicPartitions ?? [];

            switch ($err) {
                case \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    foreach ($topicPartitions as $topicPartition) {
                        $logger->info(sprintf('Assign: %s %s %s', $topicPartition->getTopic(), $topicPartition->getPartition(), $topicPartition->getOffset()));
                    }
                    $kafkaConsumer->assign($topicPartitions);
                    break;

                case \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    foreach ($topicPartitions as $topicPartition) {
                        $logger->info(sprintf('Assign: %s %s %s', $topicPartition->getTopic(), $topicPartition->getPartition(), $topicPartition->getOffset()));
                    }
                    $kafkaConsumer->assign(null);
                    break;

                default:
                    throw new TransportException('Kafka consumer response error: '.$err, $err);
            }
        };
    }
}
