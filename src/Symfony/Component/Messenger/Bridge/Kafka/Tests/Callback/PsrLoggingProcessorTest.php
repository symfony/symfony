<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Callback;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use RdKafka\TopicPartition;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\PsrLoggingProcessor;

/**
 * @requires extension rdkafka
 */
final class PsrLoggingProcessorTest extends TestCase
{
    private $logger;
    private $processor;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new PsrLoggingProcessor($this->logger);
    }

    public function testConsumerError()
    {
        $this->logger->expects(self::once())
            ->method('error')
            ->with('test error message', ['error_code' => 1]);

        $consumer = $this->createMock(KafkaConsumer::class);

        $this->processor->consumerError($consumer, 1, 'test error message');
    }

    public function testProducerError()
    {
        $this->logger->expects(self::once())
            ->method('error')
            ->with('test error message', ['error_code' => 1]);

        $producer = $this->createMock(Producer::class);

        $this->processor->producerError($producer, 1, 'test error message');
    }

    public function getLogLevels(): iterable
    {
        yield [0, LogLevel::EMERGENCY];
        yield [1, LogLevel::ALERT];
        yield [2, LogLevel::CRITICAL];
        yield [3, LogLevel::ERROR];
        yield [4, LogLevel::WARNING];
        yield [5, LogLevel::NOTICE];
        yield [6, LogLevel::INFO];
        yield [7, LogLevel::DEBUG];
        yield [8, LogLevel::DEBUG];
    }

    /**
     * @dataProvider getLogLevels
     */
    public function testLog(int $level, $expectedLevel)
    {
        $this->logger->expects(self::once())
            ->method('log')
            ->with($expectedLevel, 'test error message', ['facility' => 'facility-value']);

        $consumer = $this->createMock(KafkaConsumer::class);

        $this->processor->log($consumer, $level, 'facility-value', 'test error message');
    }

    public function testRebalanceWithAssignPartitions()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Rebalancing topic1 1 2 as the assignment changed',
                [
                    'topic' => $topic,
                    'partition' => $partition,
                    'offset' => $offset,
                    'error_code' => \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS,
                ],
            );

        $topicPartition = new TopicPartition($topic, $partition, $offset);

        $consumer = $this->createMock(KafkaConsumer::class);
        $consumer->expects($this->once())
            ->method('assign')
            ->with([$topicPartition]);

        $this->processor->rebalance($consumer, \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS, [$topicPartition]);
    }

    public function testRebalanceWithRevokePartitions()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Rebalancing topic1 1 2 as the assignment was revoked',
                [
                    'topic' => $topic,
                    'partition' => $partition,
                    'offset' => $offset,
                    'error_code' => \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS,
                ],
            );

        $consumer = $this->createMock(KafkaConsumer::class);
        $topicPartition = new TopicPartition($topic, $partition, $offset);

        $this->processor->rebalance($consumer, \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS, [$topicPartition]);
    }

    public function testRebalanceWithUnknownReason()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;
        $errorCode = 99;

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Rebalancing topic1 1 2 due to error code 99',
                [
                    'topic' => $topic,
                    'partition' => $partition,
                    'offset' => $offset,
                    'error_code' => $errorCode,
                ],
            );

        $consumer = $this->createMock(KafkaConsumer::class);
        $topicPartition = new TopicPartition($topic, $partition, $offset);

        $this->processor->rebalance($consumer, $errorCode, [$topicPartition]);
    }

    public function testConsumeWithNoError()
    {
        $partition = 1;
        $payload = 'test payload';

        $message = new Message();
        $message->err = \RD_KAFKA_RESP_ERR_NO_ERROR;
        $message->partition = $partition;
        $message->payload = $payload;

        $this->logger->expects(self::once())
            ->method('debug')
            ->with(
                sprintf(
                    'Message consumed from Kafka on partition %s: %s',
                    $partition,
                    $payload,
                )
            );

        $this->processor->consume($message);
    }

    public function testConsumeWithPartitionEofError()
    {
        $partition = 1;
        $payload = 'test payload';

        $message = new Message();
        $message->err = \RD_KAFKA_RESP_ERR__PARTITION_EOF;
        $message->partition = $partition;
        $message->payload = $payload;

        $this->logger->expects(self::once())
            ->method('info')
            ->with('No more messages; Waiting for more');

        $this->processor->consume($message);
    }

    public function testConsumeWithTimedOutError()
    {
        $partition = 1;
        $payload = 'test payload';

        $message = new Message();
        $message->err = \RD_KAFKA_RESP_ERR__TIMED_OUT;
        $message->partition = $partition;
        $message->payload = $payload;

        $this->logger->expects(self::once())
            ->method('debug')
            ->with('Timed out waiting for message');

        $this->processor->consume($message);
    }

    public function testConsumeWithTransportError()
    {
        $partition = 1;
        $payload = 'test payload';

        $message = new Message();
        $message->err = \RD_KAFKA_RESP_ERR__TRANSPORT;
        $message->partition = $partition;
        $message->payload = $payload;

        $this->logger->expects(self::once())
            ->method('warning')
            ->with('Kafka Broker transport failure');

        $this->processor->consume($message);
    }

    public function testConsumeWithGenericError()
    {
        $partition = 1;
        $payload = 'test payload';

        $message = new Message();
        $message->err = \RD_KAFKA_RESP_ERR__RESOLVE;
        $message->partition = $partition;
        $message->payload = $payload;

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Error occurred while consuming message from Kafka: Local: Host resolution failure');

        $this->processor->consume($message);
    }

    public function testOffsetCommit()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;

        $kafka = new \stdClass();
        $err = 1;

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Offset topic=topic1 partition=1 offset=2 code=1 successfully committed.',
                [
                    'topic' => $topic,
                    'partition' => $partition,
                    'offset' => $offset,
                    'error_code' => $err,
                ],
            );

        $topicPartition = new TopicPartition($topic, $partition, $offset);

        $this->processor->offsetCommit($kafka, $err, [$topicPartition]);
    }
}
