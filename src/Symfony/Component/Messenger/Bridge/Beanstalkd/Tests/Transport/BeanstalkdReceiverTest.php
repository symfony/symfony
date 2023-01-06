<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdReceivedStamp;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdReceiver;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\Connection;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

final class BeanstalkdReceiverTest extends TestCase
{
    public function testItReturnsTheDecodedMessageToTheHandler()
    {
        $serializer = $this->createSerializer();

        $tube = 'foo bar';

        $beanstalkdEnvelope = $this->createBeanstalkdEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('get')->willReturn($beanstalkdEnvelope);
        $connection->expects($this->once())->method('getTube')->willReturn($tube);

        $receiver = new BeanstalkdReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        $this->assertCount(1, $actualEnvelopes);
        $this->assertEquals(new DummyMessage('Hi'), $actualEnvelopes[0]->getMessage());

        /** @var BeanstalkdReceivedStamp $receivedStamp */
        $receivedStamp = $actualEnvelopes[0]->last(BeanstalkdReceivedStamp::class);

        $this->assertInstanceOf(BeanstalkdReceivedStamp::class, $receivedStamp);
        $this->assertSame('1', $receivedStamp->getId());
        $this->assertSame($tube, $receivedStamp->getTube());
    }

    public function testItReturnsEmptyArrayIfThereAreNoMessages()
    {
        $serializer = $this->createSerializer();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('get')->willReturn(null);

        $receiver = new BeanstalkdReceiver($connection, $serializer);
        $actualEnvelopes = $receiver->get();
        $this->assertIsArray($actualEnvelopes);
        $this->assertCount(0, $actualEnvelopes);
    }

    public function testItRejectTheMessageIfThereIsAMessageDecodingFailedException()
    {
        $this->expectException(MessageDecodingFailedException::class);

        $serializer = $this->createMock(PhpSerializer::class);
        $serializer->expects($this->once())->method('decode')->willThrowException(new MessageDecodingFailedException());

        $beanstalkdEnvelope = $this->createBeanstalkdEnvelope();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('get')->willReturn($beanstalkdEnvelope);
        $connection->expects($this->once())->method('reject');

        $receiver = new BeanstalkdReceiver($connection, $serializer);
        $receiver->get();
    }

    private function createBeanstalkdEnvelope(): array
    {
        return [
            'id' => '1',
            'body' => '{"message": "Hi"}',
            'headers' => [
                'type' => DummyMessage::class,
            ],
        ];
    }

    private function createSerializer(): Serializer
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()])
        );

        return $serializer;
    }
}
