<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\ExternalMessage;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\ExternalMessageSerializer;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceiver;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class RedisReceiverTest extends TestCase
{
    /**
     * @dataProvider redisEnvelopeProvider
     */
    public function testItReturnsTheDecodedMessageToTheHandler(array $redisEnvelope, $expectedMessage, SerializerInterface $serializer)
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willReturn($redisEnvelope);

        $receiver = new RedisReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals($expectedMessage, $actualEnvelopes[0]->getMessage());
    }

    /**
     * @dataProvider rejectedRedisEnvelopeProvider
     */
    public function testItRejectTheMessageIfThereIsAMessageDecodingFailedException(array $redisEnvelope)
    {
        $this->expectException(MessageDecodingFailedException::class);

        $serializer = $this->createMock(PhpSerializer::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $connection = $this->createMock(Connection::class);
        $connection->method('get')->willReturn($redisEnvelope);
        $connection->expects($this->once())->method('reject');

        $receiver = new RedisReceiver($connection, $serializer);
        $receiver->get();
    }

    public static function redisEnvelopeProvider(): \Generator
    {
        yield [
            [
                'id' => 1,
                'data' => [
                    'message' => json_encode([
                        'body' => '{"message": "Hi"}',
                        'headers' => [
                            'type' => DummyMessage::class,
                        ],
                    ]),
                ],
            ],
            new DummyMessage('Hi'),
            new Serializer(
                new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
            ),
        ];

        yield [
            [
                'id' => 2,
                'data' => [
                    'message' => json_encode([
                        'foo' => 'fooValue',
                        'bar' => [
                            'baz' => 'bazValue',
                        ],
                    ]),
                ],
            ],
            (new ExternalMessage('fooValue'))->setBar(['baz' => 'bazValue']),
            new ExternalMessageSerializer(),
        ];
    }

    public static function rejectedRedisEnvelopeProvider(): \Generator
    {
        yield [
            [
                'id' => 1,
                'data' => [
                    'message' => json_encode([
                        'body' => '{"message": "Hi"}',
                        'headers' => [
                            'type' => DummyMessage::class,
                        ],
                    ]),
                ],
            ],
        ];

        yield [
            [
                'id' => 2,
                'data' => [
                    'message' => json_encode([
                        'foo' => 'fooValue',
                        'bar' => [
                            'baz' => 'bazValue',
                        ],
                    ]),
                ],
            ],
        ];
    }
}
