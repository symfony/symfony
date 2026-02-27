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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\ExternalMessage;
use Symfony\Component\Messenger\Bridge\Redis\Tests\Fixtures\ExternalMessageSerializer;
use Symfony\Component\Messenger\Bridge\Redis\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceivedStamp;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisReceiver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class RedisReceiverTest extends TestCase
{
    #[DataProvider('redisEnvelopeProvider')]
    public function testItReturnsTheDecodedMessageToTheHandler(array $redisEnvelope, $expectedMessage, SerializerInterface $serializer)
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($redisEnvelope);

        $receiver = new RedisReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        $this->assertCount(1, $actualEnvelopes);
        /** @var Envelope $actualEnvelope */
        $actualEnvelope = $actualEnvelopes[0];
        $this->assertEquals($expectedMessage, $actualEnvelope->getMessage());

        /** @var TransportMessageIdStamp $transportMessageIdStamp */
        $transportMessageIdStamp = $actualEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNotNull($transportMessageIdStamp);
        $this->assertSame($redisEnvelope['id'], $transportMessageIdStamp->getId());
    }

    #[DataProvider('rejectedRedisEnvelopeProvider')]
    public function testItRejectTheMessageIfThereIsAMessageDecodingFailedException(array $redisEnvelope)
    {
        $serializer = $this->createStub(PhpSerializer::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn($redisEnvelope);

        $receiver = new RedisReceiver($connection, $serializer);
        $envelopes = $receiver->get();

        $this->assertCount(1, $envelopes);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelopes[0]->getMessage());
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

    public function testKeepalive()
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('keepalive')->with('redisid-123');

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));
        $receiver->keepalive(new Envelope(new DummyMessage('foo'), [new RedisReceivedStamp('redisid-123')]));
    }

    public function testAllReturnsAllMessages()
    {
        $messages = [
            [
                'id' => '1',
                'data' => [
                    'message' => json_encode([
                        'body' => '{"message": "Hi"}',
                        'headers' => [
                            'type' => DummyMessage::class,
                        ],
                    ]),
                ],
            ],
            [
                'id' => '2',
                'data' => [
                    'message' => json_encode([
                        'body' => '{"message": "Hello"}',
                        'headers' => [
                            'type' => DummyMessage::class,
                        ],
                    ]),
                ],
            ],
        ];

        $connection = $this->createStub(Connection::class);
        $connection->method('findAll')->willReturn($messages);

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));

        $envelopes = iterator_to_array($receiver->all());
        $this->assertCount(2, $envelopes);

        $this->assertEquals(new DummyMessage('Hi'), $envelopes[0]->getMessage());
        $this->assertEquals(new DummyMessage('Hello'), $envelopes[1]->getMessage());

        $this->assertNotNull($envelopes[0]->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelopes[0]->last(RedisReceivedStamp::class));
        $this->assertNotNull($envelopes[1]->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelopes[1]->last(RedisReceivedStamp::class));
    }

    public function testAllWithLimit()
    {
        $messages = [
            [
                'id' => '1',
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

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('findAll')->with(1)->willReturn($messages);

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));

        $envelopes = iterator_to_array($receiver->all(1));
        $this->assertCount(1, $envelopes);
    }

    public function testAllSkipsInvalidMessages()
    {
        $messages = [
            [
                'id' => '1',
                'data' => null,
            ],
            [
                'id' => '2',
                'data' => [
                    'message' => 'invalid-json',
                ],
            ],
            [
                'id' => '3',
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

        $connection = $this->createStub(Connection::class);
        $connection->method('findAll')->willReturn($messages);

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));

        $envelopes = iterator_to_array($receiver->all());
        $this->assertCount(1, $envelopes);
        $this->assertEquals(new DummyMessage('Hi'), $envelopes[0]->getMessage());
    }

    public function testFindReturnsMessageById()
    {
        $message = [
            'id' => '123',
            'data' => [
                'message' => json_encode([
                    'body' => '{"message": "Hi"}',
                    'headers' => [
                        'type' => DummyMessage::class,
                    ],
                ]),
            ],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('find')->with('123')->willReturn($message);

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));

        $envelope = $receiver->find('123');
        $this->assertNotNull($envelope);
        $this->assertEquals(new DummyMessage('Hi'), $envelope->getMessage());
        $this->assertNotNull($envelope->last(TransportMessageIdStamp::class));
        $this->assertNotNull($envelope->last(RedisReceivedStamp::class));
    }

    public function testFindReturnsNullForNonExistentMessage()
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('find')->with('999')->willReturn(null);

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));

        $envelope = $receiver->find('999');
        $this->assertNull($envelope);
    }

    public function testFindReturnsNullForInvalidJson()
    {
        $message = [
            'id' => '123',
            'data' => [
                'message' => 'invalid-json',
            ],
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('find')->with('123')->willReturn($message);

        $receiver = new RedisReceiver($connection, new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        ));

        $envelope = $receiver->find('123');
        $this->assertNull($envelope);
    }
}
