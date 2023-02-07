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
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures\TestMessage;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaMessageReceivedStamp;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaTransport;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\RdKafkaFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 *
 * @requires extension rdkafka
 */
class KafkaTransportTest extends TestCase
{
    /** @var MockObject|LoggerInterface */
    private $logger;

    /** @var MockObject|SerializerInterface */
    private $serializer;

    /** @var MockObject|KafkaConsumer */
    private $rdKafkaConsumer;

    /** @var MockObject|KafkaProducer */
    private $rdKafkaProducer;

    /** @var MockObject|RdKafkaFactory */
    private $rdKafkaFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->serializer = $this->createMock(SerializerInterface::class);

        // RdKafka
        $this->rdKafkaFactory = $this->createMock(RdKafkaFactory::class);

        $this->rdKafkaConsumer = $this->createMock(KafkaConsumer::class);
        $this->rdKafkaFactory
            ->method('createConsumer')
            ->willReturn($this->rdKafkaConsumer);

        $this->rdKafkaProducer = $this->createMock(KafkaProducer::class);
        $this->rdKafkaFactory
            ->method('createProducer')
            ->willReturn($this->rdKafkaProducer);
    }

    public function testConstruct()
    {
        $transport = new KafkaTransport(
            $this->logger,
            $this->serializer,
            new RdKafkaFactory(),
            []
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testGet()
    {
        $this->rdKafkaConsumer->method('subscribe');

        $testMessage = new Message();
        $testMessage->err = \RD_KAFKA_RESP_ERR_NO_ERROR;
        $testMessage->topic_name = 'test';
        $testMessage->partition = 0;
        $testMessage->headers = [
            'type' => TestMessage::class,
            'Content-Type' => 'application/json',
        ];
        $testMessage->payload = '{"data":null}';
        $testMessage->offset = 0;
        $testMessage->timestamp = 1681790400;

        $this->rdKafkaConsumer
            ->method('consume')
            ->willReturn($testMessage);

        $this->serializer->expects(static::once())
            ->method('decode')
            ->with([
                'body' => '{"data":null}',
                'headers' => [
                    'type' => TestMessage::class,
                    'Content-Type' => 'application/json',
                ],
            ])
            ->willReturn(new Envelope(new TestMessage()));

        $transport = new KafkaTransport(
            $this->logger,
            $this->serializer,
            $this->rdKafkaFactory,
            [
                'conf' => [],
                'consumer' => [
                    'topics' => [
                        'test',
                    ],
                    'receive_timeout' => 10000,
                    'conf' => [],
                ],
            ]
        );

        $receivedMessages = $transport->get();
        static::assertArrayHasKey(0, $receivedMessages);

        /** @var Envelope $receivedMessage */
        $receivedMessage = $receivedMessages[0];
        static::assertInstanceOf(Envelope::class, $receivedMessage);
        static::assertInstanceOf(TestMessage::class, $receivedMessage->getMessage());

        $stamps = $receivedMessage->all();
        static::assertCount(1, $stamps);
        static::assertArrayHasKey(KafkaMessageReceivedStamp::class, $stamps);

        $kafkaMessageReceivedStamps = $stamps[KafkaMessageReceivedStamp::class];
        static::assertCount(1, $kafkaMessageReceivedStamps);

        /** @var KafkaMessageReceivedStamp $kafkaMessageReceivedStamp */
        $kafkaMessageReceivedStamp = $kafkaMessageReceivedStamps[0];
        static::assertSame($testMessage, $kafkaMessageReceivedStamp->getMessage());
    }
}
