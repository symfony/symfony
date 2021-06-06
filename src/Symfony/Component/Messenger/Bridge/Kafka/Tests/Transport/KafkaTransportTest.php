<?php

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Transport;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Producer as KafkaProducer;
use Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures\TestMessage;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaMessageStamp;
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
    private $mockLogger;

    /** @var MockObject|SerializerInterface */
    private $mockSerializer;

    /** @var MockObject|KafkaConsumer */
    private $mockRdKafkaConsumer;

    /** @var MockObject|KafkaProducer */
    private $mockRdKafkaProducer;

    /** @var MockObject|RdKafkaFactory */
    private $mockRdKafkaFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = $this->createMock(LoggerInterface::class);

        $this->mockSerializer = $this->createMock(SerializerInterface::class);

        // RdKafka
        $this->mockRdKafkaFactory = $this->createMock(RdKafkaFactory::class);

        $this->mockRdKafkaConsumer = $this->createMock(KafkaConsumer::class);
        $this->mockRdKafkaFactory
            ->method('createConsumer')
            ->willReturn($this->mockRdKafkaConsumer);

        $this->mockRdKafkaProducer = $this->createMock(KafkaProducer::class);
        $this->mockRdKafkaFactory
            ->method('createProducer')
            ->willReturn($this->mockRdKafkaProducer);
    }

    public function testConstruct()
    {
        $transport = new KafkaTransport(
            $this->mockLogger,
            $this->mockSerializer,
            new RdKafkaFactory(),
            []
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testGet()
    {
        $this->mockRdKafkaConsumer
            ->method('subscribe')
            ->willReturn(true);

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
        $testMessage->timestamp = 1586861356;

        $this->mockRdKafkaConsumer
            ->method('consume')
            ->willReturn($testMessage);

        $this->mockSerializer->expects(static::once())
            ->method('decode')
            ->with([
                'body' => '{"data":null}',
                'headers' => [
                    'type' => TestMessage::class,
                    'Content-Type' => 'application/json',
                ],
                'key' => null,
                'partition' => 0,
                'offset' => 0,
                'timestamp' => 1586861356,
                'topic_name' => 'test',
            ])
            ->willReturn(new Envelope(new TestMessage()));

        $transport = new KafkaTransport(
            $this->mockLogger,
            $this->mockSerializer,
            $this->mockRdKafkaFactory,
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
        static::assertArrayHasKey(KafkaMessageStamp::class, $stamps);

        $kafkaMessageStamps = $stamps[KafkaMessageStamp::class];
        static::assertCount(1, $kafkaMessageStamps);

        /** @var KafkaMessageStamp $kafkaMessageStamp */
        $kafkaMessageStamp = $kafkaMessageStamps[0];
        static::assertSame($testMessage, $kafkaMessageStamp->getMessage());
    }
}
