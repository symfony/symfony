<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Transport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RdKafka\Conf as KafkaConf;
use RdKafka\Producer as KafkaProducer;
use RdKafka\ProducerTopic;
use Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures\TestMessage;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaMessageSendStamp;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaSender;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\RdKafkaFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 *
 * @requires extension rdkafka
 */
class KafkaSenderTest extends TestCase
{
    /** @var MockObject|SerializerInterface */
    private $serializer;

    /** @var MockObject|KafkaProducer */
    private $rdKafkaProducer;

    /** @var MockObject|RdKafkaFactory */
    private $rdKafkaFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->rdKafkaFactory = $this->createMock(RdKafkaFactory::class);

        $this->rdKafkaProducer = $this->createMock(KafkaProducer::class);
        $this->rdKafkaFactory
            ->method('createProducer')
            ->willReturn($this->rdKafkaProducer);
    }

    public function testConstruct()
    {
        $sender = new KafkaSender(
            $this->createMock(LoggerInterface::class),
            $this->serializer,
            $this->rdKafkaFactory,
            new KafkaConf(),
            []
        );

        static::assertInstanceOf(SenderInterface::class, $sender);
    }

    public function testSend()
    {
        $sender = new KafkaSender(
            $this->createMock(LoggerInterface::class),
            $this->serializer,
            $this->rdKafkaFactory,
            new KafkaConf(),
            [
                'topic_name' => 'test_topic_kafka_sender_test',
                'flush_timeout' => 10000,
                'flush_retries' => 10,
                'conf' => [],
            ]
        );

        $this->serializer->expects(static::once())
            ->method('encode')
            ->willReturn([
                'body' => '{"data":"my_test_data"}',
                'headers' => [
                    'type' => TestMessage::class,
                    'Content-Type' => 'application/json',
                ],
            ]);

        $mockProducerTopic = $this->createMock(ProducerTopic::class);
        $this->rdKafkaProducer->expects(static::once())
            ->method('newTopic')
            ->with('test_topic_kafka_sender_test')
            ->willReturn($mockProducerTopic);

        $mockProducerTopic->expects(static::once())
            ->method('producev')
            ->with(
                5,
                \RD_KAFKA_MSG_F_BLOCK,
                '{"data":"my_test_data"}',
                'test_key_123',
                [
                    'type' => TestMessage::class,
                    'Content-Type' => 'application/json',
                ],
                1681790400
            );

        $sender->send(new Envelope(
            new TestMessage('my_test_data'),
            [
                new KafkaMessageSendStamp([
                    'partition' => 5,
                    'msgflags' => \RD_KAFKA_MSG_F_BLOCK,
                    'key' => 'test_key_123',
                    'timestamp_ms' => 1681790400,
                ]),
            ]
        ));
    }
}
