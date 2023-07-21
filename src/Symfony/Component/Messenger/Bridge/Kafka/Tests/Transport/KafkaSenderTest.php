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

use PHPUnit\Framework\TestCase;
use RdKafka\Exception;
use Symfony\Component\Messenger\Bridge\Kafka\Stamp\KafkaMessageStamp;
use Symfony\Component\Messenger\Bridge\Kafka\Tests\Fixtures\FakeMessage;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaSender;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer as SymfonySerializer;

/**
 * @requires extension rdkafka
 */
class KafkaSenderTest extends TestCase
{
    private SerializerInterface $serializer;
    private Connection $connection;
    private KafkaSender $kafkaSender;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->serializer = new Serializer(
            new SymfonySerializer\Serializer([new SymfonySerializer\Normalizer\ObjectNormalizer()], ['json' => new SymfonySerializer\Encoder\JsonEncoder()]),
        );
        $this->kafkaSender = new KafkaSender(
            $this->connection,
            $this->serializer,
        );
    }

    public function testSend(): void
    {
        $envelope = new Envelope(new FakeMessage('Hello'));
        $this->connection
            ->expects($this->once())
            ->method('publish')
            ->with(
                \RD_KAFKA_PARTITION_UA,
                \RD_KAFKA_MSG_F_BLOCK,
                '{"message":"Hello"}',
                null,
                ['type' => FakeMessage::class, 'Content-Type' => 'application/json'],
            );

        self::assertSame($envelope, $this->kafkaSender->send($envelope));
    }

    public function testSendWithStamp(): void
    {
        $partition = 1;
        $messageFlags = 0;
        $key = 'message-key';
        $envelope = new Envelope(new FakeMessage('Hello'), [
            new KafkaMessageStamp(
                $partition,
                $messageFlags,
                $key
            ),
        ]);
        $this->connection
            ->expects($this->once())
            ->method('publish')
            ->with(
                $partition,
                $messageFlags,
                '{"message":"Hello"}',
                $key,
                ['type' => FakeMessage::class, 'Content-Type' => 'application/json'],
            );

        self::assertSame($envelope, $this->kafkaSender->send($envelope));
    }

    public function testExceptionConnection(): void
    {
        $envelope = new Envelope(new FakeMessage('Hello'));
        $this->connection
            ->expects($this->once())
            ->method('publish')
            ->with(
                \RD_KAFKA_PARTITION_UA,
                \RD_KAFKA_MSG_F_BLOCK,
                '{"message":"Hello"}',
                null,
                ['type' => FakeMessage::class, 'Content-Type' => 'application/json'],
            )
            ->willThrowException(new Exception('Connection exception', 1));

        self::expectException(TransportException::class);
        self::expectExceptionMessage('Connection exception');
        self::expectExceptionCode(0);

        $this->kafkaSender->send($envelope);
    }
}
