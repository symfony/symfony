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
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaTransport;
use Symfony\Component\Messenger\Bridge\Kafka\Transport\KafkaTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @requires extension rdkafka
 */
class KafkaTransportFactoryTest extends TestCase
{
    private KafkaTransportFactory $factory;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->factory = new KafkaTransportFactory(new NullLogger());
    }

    public function testCreateTransport(): void
    {
        self::assertInstanceOf(
            KafkaTransport::class,
            $this->factory->createTransport(
                'kafka://',
                [
                    'producer' => [
                        'topic' => 'messages',
                    ],
                ],
                $this->serializer,
            ),
        );
    }

    public function testSupports(): void
    {
        self::assertTrue($this->factory->supports('kafka://', []));
        self::assertTrue($this->factory->supports('kafka://localhost:9092', []));
        self::assertFalse($this->factory->supports('plaintext://localhost:9092', []));
        self::assertFalse($this->factory->supports('kafka', []));
    }
}
