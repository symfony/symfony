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

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Core\Exception\Http\ServerException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceivedStamp;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceiver;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransport;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AmazonSqsTransportTest extends TestCase
{
    public function testItIsATransport()
    {
        $transport = $this->getTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testReceivesMessages()
    {
        $transport = $this->getTransport(
            $serializer = $this->createMock(SerializerInterface::class),
            $connection = $this->createStub(Connection::class)
        );

        $decodedMessage = new DummyMessage('Decoded.');

        $sqsEnvelope = [
            'id' => '5',
            'body' => 'body',
            'headers' => ['my' => 'header'],
        ];

        $serializer->expects($this->once())->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
        $connection->method('get')->willReturn($sqsEnvelope);

        $envelopes = iterator_to_array($transport->get());
        $this->assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    public function testTransportIsAMessageCountAware()
    {
        $transport = $this->getTransport();

        $this->assertInstanceOf(MessageCountAwareInterface::class, $transport);
    }

    public function testItCanGetMessagesViaTheReceiver()
    {
        $receiver = $this->createMock(AmazonSqsReceiver::class);
        $transport = $this->getTransport(null, null, $receiver);
        $envelopes = [new Envelope(new \stdClass()), new Envelope(new \stdClass())];
        $receiver->expects($this->once())->method('get')->willReturn($envelopes);
        $this->assertSame($envelopes, $transport->get());
    }

    public function testItCanAcknowledgeAMessageViaTheReceiver()
    {
        $receiver = $this->createMock(AmazonSqsReceiver::class);
        $transport = $this->getTransport(null, null, $receiver);
        $envelope = new Envelope(new \stdClass());
        $receiver->expects($this->once())->method('ack')->with($envelope);
        $transport->ack($envelope);
    }

    public function testItCanRejectAMessageViaTheReceiver()
    {
        $receiver = $this->createMock(AmazonSqsReceiver::class);
        $transport = $this->getTransport(null, null, $receiver);
        $envelope = new Envelope(new \stdClass());
        $receiver->expects($this->once())->method('reject')->with($envelope);
        $transport->reject($envelope);
    }

    public function testItCanGetMessageCountViaTheReceiver()
    {
        $receiver = $this->createMock(AmazonSqsReceiver::class);
        $transport = $this->getTransport(null, null, $receiver);
        $messageCount = 15;
        $receiver->expects($this->once())->method('getMessageCount')->willReturn($messageCount);
        $this->assertSame($messageCount, $transport->getMessageCount());
    }

    public function testItCanSendAMessageViaTheSender()
    {
        $sender = $this->createMock(SenderInterface::class);
        $transport = $this->getTransport(null, null, null, $sender);
        $envelope = new Envelope(new \stdClass());
        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);
        $this->assertSame($envelope, $transport->send($envelope));
    }

    public function testItSendsAMessageViaTheSenderWithRedeliveryStamp()
    {
        $sender = $this->createMock(SenderInterface::class);
        $transport = $this->getTransport(null, null, null, $sender);
        $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp(1)]);
        $sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);
        $this->assertSame($envelope, $transport->send($envelope));
    }

    public function testItDoesNotSendRedeliveredMessageWhenNotHandlingRetries()
    {
        $sender = $this->createMock(SenderInterface::class);
        $transport = $this->getTransport(null, null, null, $sender, false);

        $envelope = new Envelope(new \stdClass(), [new RedeliveryStamp(1)]);
        $sender->expects($this->never())->method('send')->with($envelope)->willReturn($envelope);
        $this->assertSame($envelope, $transport->send($envelope));
    }

    public function testItCanSetUpTheConnection()
    {
        $connection = $this->createMock(Connection::class);
        $transport = $this->getTransport(null, $connection);
        $connection->expects($this->once())->method('setup');
        $transport->setup();
    }

    public function testItConvertsHttpExceptionDuringSetupIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $transport = $this->getTransport(null, $connection);
        $connection
            ->expects($this->once())
            ->method('setup')
            ->willThrowException($this->createHttpException());

        $this->expectException(TransportException::class);

        $transport->setup();
    }

    public function testItCanResetTheConnection()
    {
        $connection = $this->createMock(Connection::class);
        $transport = $this->getTransport(null, $connection);
        $connection->expects($this->once())->method('reset');
        $transport->reset();
    }

    public function testItConvertsHttpExceptionDuringResetIntoTransportException()
    {
        $connection = $this->createMock(Connection::class);
        $transport = $this->getTransport(null, $connection);
        $connection
            ->expects($this->once())
            ->method('reset')
            ->willThrowException($this->createHttpException());

        $this->expectException(TransportException::class);

        $transport->reset();
    }

    public function testKeepalive()
    {
        $transport = $this->getTransport(
            null,
            $connection = $this->createMock(Connection::class),
        );

        $connection->expects($this->once())->method('keepalive')->with('123', 10);
        $transport->keepalive(new Envelope(new DummyMessage('foo'), [new AmazonSqsReceivedStamp('123')]), 10);
    }

    public function testKeepaliveWhenASqsExceptionOccurs()
    {
        $transport = $this->getTransport(
            null,
            $connection = $this->createMock(Connection::class),
        );

        $exception = $this->createHttpException();
        $connection->expects($this->once())->method('keepalive')->with('123')->willThrowException($exception);

        $this->expectExceptionObject(new TransportException($exception->getMessage(), 0, $exception));
        $transport->keepalive(new Envelope(new DummyMessage('foo'), [new AmazonSqsReceivedStamp('123')]));
    }

    private function getTransport(?SerializerInterface $serializer = null, ?Connection $connection = null, ?ReceiverInterface $receiver = null, ?SenderInterface $sender = null, bool $handleRetries = true)
    {
        $serializer ??= $this->createStub(SerializerInterface::class);
        $connection ??= $this->createStub(Connection::class);

        return new AmazonSqsTransport($connection, $serializer, $receiver, $sender, $handleRetries);
    }

    private function createHttpException(): HttpException
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getInfo')->willReturnCallback(static function (?string $type = null) {
            $info = [
                'http_code' => 500,
                'url' => 'https://symfony.com',
            ];

            if (null === $type) {
                return $info;
            }

            return $info[$type] ?? null;
        });

        return new ServerException($response);
    }
}
