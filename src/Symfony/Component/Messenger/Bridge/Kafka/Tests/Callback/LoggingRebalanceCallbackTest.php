<?php

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
use RdKafka\KafkaConsumer;
use RdKafka\TopicPartition;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\LoggingRebalanceCallback;

/**
 * @requires extension rdkafka
 */
final class LoggingRebalanceCallbackTest extends TestCase
{
    public function testInvokeWithAssignPartitions()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
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

        $callback = new LoggingRebalanceCallback($logger);
        $callback($consumer, \RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS, [$topicPartition]);
    }

    public function testInvokeWithRevokePartitions()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
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

        $callback = new LoggingRebalanceCallback($logger);
        $callback($consumer, \RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS, [$topicPartition]);
    }

    public function testInvokeWithUnknownReason()
    {
        $topic = 'topic1';
        $partition = 1;
        $offset = 2;
        $errorCode = 99;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
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

        $callback = new LoggingRebalanceCallback($logger);
        $callback($consumer, $errorCode, [$topicPartition]);
    }

    public function testInvokeWithUnknownReasonWithoutTopics()
    {
        $errorCode = 99;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Rebalancing error code 99',
                [
                    'error_code' => $errorCode,
                ],
            );

        $consumer = $this->createMock(KafkaConsumer::class);

        $callback = new LoggingRebalanceCallback($logger);
        $callback($consumer, $errorCode, []);
    }
}
