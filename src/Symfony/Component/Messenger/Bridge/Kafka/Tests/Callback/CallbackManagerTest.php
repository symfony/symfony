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
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\CallbackManager;
use Symfony\Component\Messenger\Bridge\Kafka\Callback\CallbackProcessorInterface;

/**
 * @requires extension rdkafka
 */
final class CallbackManagerTest extends TestCase
{
    private $manager;
    private $processor;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(CallbackProcessorInterface::class);
        $this->manager = new CallbackManager([
            $this->processor,
        ]);
    }

    public function testLog()
    {
        $kafka = new \stdClass();
        $level = 1;
        $facility = 'test';
        $error = 'test error message';

        $this->processor->expects(self::once())
            ->method('log')
            ->with($kafka, $level, $facility, $error);

        $this->manager->log($kafka, $level, $facility, $error);
    }

    public function testConsumerError()
    {
        $consumer = $this->createMock(KafkaConsumer::class);
        $this->processor->expects(self::once())
            ->method('consumerError')
            ->with($consumer, 1, 'test error message');

        $this->manager->consumerError($consumer, 1, 'test error message');
    }

    public function testProducerError()
    {
        $producer = $this->createMock(Producer::class);
        $this->processor->expects(self::once())
            ->method('producerError')
            ->with($producer, 1, 'test error message');

        $this->manager->producerError($producer, 1, 'test error message');
    }

    public function testStats()
    {
        $kafka = new \stdClass();
        $json = '{"test": "test"}';
        $jsonLength = 1;

        $this->processor->expects(self::once())
            ->method('stats')
            ->with($kafka, $json, $jsonLength);

        $this->manager->stats($kafka, $json, $jsonLength);
    }

    public function testRebalance()
    {
        $kafka = $this->createMock(KafkaConsumer::class);
        $err = 1;
        $partitions = [];

        $this->processor->expects(self::once())
            ->method('rebalance')
            ->with($kafka, $err, $partitions);

        $this->manager->rebalance($kafka, $err, $partitions);
    }

    public function testConsume()
    {
        $message = $this->createMock(Message::class);

        $this->processor->expects(self::once())
            ->method('consume')
            ->with($message);

        $this->manager->consume($message);
    }

    public function testOffsetCommit()
    {
        $kafka = new \stdClass();
        $err = 1;
        $partitions = [];

        $this->processor->expects(self::once())
            ->method('offsetCommit')
            ->with($kafka, $err, $partitions);

        $this->manager->offsetCommit($kafka, $err, $partitions);
    }

    public function testDeliveryReport()
    {
        $kafka = new \stdClass();
        $message = $this->createMock(Message::class);

        $this->processor->expects(self::once())
            ->method('deliveryReport')
            ->with($kafka, $message);

        $this->manager->deliveryReport($kafka, $message);
    }
}
