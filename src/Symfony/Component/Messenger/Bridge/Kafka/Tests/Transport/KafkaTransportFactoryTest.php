<?php

namespace Symfony\Component\Messenger\Bridge\Kafka\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @requires extension rdkafka
 */
class KafkaTransportFactoryTest extends TestCase
{
    /** @var KafkaTransportFactory */
    private $factory;

    /** @var SerializerInterface */
    private $serializerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new KafkaTransportFactory(new NullLogger());
        $this->serializerMock = $this->createMock(SerializerInterface::class);
    }

    public function testSupports()
    {
        static::assertTrue($this->factory->supports('kafka://my-local-kafka:9092', []));
        static::assertTrue($this->factory->supports('kafka+ssl://my-staging-kafka:9093', []));
        static::assertTrue($this->factory->supports('kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093,kafka+ssl://prod-kafka-01:9093', []));
    }

    public function testCreateTransport()
    {
        $transport = $this->factory->createTransport(
            'kafka://my-local-kafka:9092',
            [
                'conf' => [],
                'consumer' => [
                    'topics' => [
                        'test',
                    ],
                    'receive_timeout' => 10000,
                    'conf' => [],
                ],
            ],
            $this->serializerMock
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testCreateTransportFromDsn()
    {
        $transport = $this->factory->createTransport(
            'kafka://kafka1,kafka2:9092?consumer[topics][0]=test&consumer[receive_timeout]=10000',
            [],
            $this->serializerMock
        );

        static::assertInstanceOf(TransportInterface::class, $transport);
    }
}
