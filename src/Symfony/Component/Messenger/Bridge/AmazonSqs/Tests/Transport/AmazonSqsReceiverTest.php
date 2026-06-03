<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceivedStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceiver;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class AmazonSqsReceiverTest extends TestCase
{
    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = $this->createSerializer();

        $sqsEnvelop = $this->createSqsEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn([$sqsEnvelop]);

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get());
        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
    }

    public function testGetUsesFetchSizeWhenProvided()
    {
        $serializer = $this->createSerializer();

        $sqsEnvelope = $this->createSqsEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('get')->with(7)->willReturn([$sqsEnvelope]);

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get(7));

        $this->assertCount(1, $actualEnvelopes);
    }

    public function testItReturnsMultipleDecodedMessagesWhenAvailable()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn([
            $this->createSqsEnvelope(),
            [
                'id' => 2,
                'body' => '{"message": "Hello"}',
                'headers' => [
                    'type' => DummyMessage::class,
                ],
            ],
        ]);

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $actualEnvelopes = iterator_to_array($receiver->get(2));

        $this->assertCount(2, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());
        $this->assertEquals(new DummyMessage('Hello'), $actualEnvelopes[1]->getMessage());
    }

    public function testItReturnsSerializedEnvelopeWhenDecodingFails()
    {
        $serializer = $this->createStub(PhpSerializer::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException());

        $sqsEnvelop = $this->createSqsEnvelope();
        $connection = $this->createStub(Connection::class);
        $connection->method('get')->willReturn([$sqsEnvelop]);

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $envelopes = iterator_to_array($receiver->get());

        $this->assertCount(1, $envelopes);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelopes[0]->getMessage());
    }

    public function testKeepalive()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('keepalive')->with('123', 10);

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $receiver->keepalive(new Envelope(new DummyMessage('foo'), [new AmazonSqsReceivedStamp('123')]), 10);
    }

    public function testReject()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('reject')->with('123');

        $receiver = new AmazonSqsReceiver($connection, $serializer);
        $receiver->reject(new Envelope(new DummyMessage('foo'), [new AmazonSqsReceivedStamp('123')]));
    }

    private function createSqsEnvelope()
    {
        return [
            'id' => 1,
            'body' => '{"message": "Hi"}',
            'headers' => [
                'type' => DummyMessage::class,
            ],
        ];
    }

    private function createSerializer(): Serializer
    {
        return new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );
    }
}
