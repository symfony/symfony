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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsReceiver;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransport;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AmazonSqsTransportTest extends TestCase
{
    /**
     * @var MockObject|Connection
     */
    private $connection;

    /**
     * @var MockObject|ReceiverInterface
     */
    private $receiver;

    /**
     * @var MockObject|SenderInterface|MessageCountAwareInterface
     */
    private $sender;

    /**
     * @var AmazonSqsTransport
     */
    private $transport;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        // Mocking the concrete receiver class because mocking multiple interfaces is deprecated
        $this->receiver = $this->createMock(AmazonSqsReceiver::class);
        $this->sender = $this->createMock(SenderInterface::class);

        $this->transport = new AmazonSqsTransport($this->connection, null, $this->receiver, $this->sender);
    }

    public function testItIsATransport()
    {
        $transport = $this->getTransport();

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testReceivesMessages()
    {
        $transport = $this->getTransport(
            $serializer = $this->createMock(SerializerInterface::class),
            $connection = $this->createMock(Connection::class)
        );

        $decodedMessage = new DummyMessage('Decoded.');

        $sqsEnvelope = [
            'id' => '5',
            'body' => 'body',
            'headers' => ['my' => 'header'],
        ];

        $serializer->method('decode')->with(['body' => 'body', 'headers' => ['my' => 'header']])->willReturn(new Envelope($decodedMessage));
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
        $envelopes = [new Envelope(new \stdClass()), new Envelope(new \stdClass())];
        $this->receiver->expects($this->once())->method('get')->willReturn($envelopes);
        $this->assertSame($envelopes, $this->transport->get());
    }

    public function testItCanAcknowledgeAMessageViaTheReceiver()
    {
        $envelope = new Envelope(new \stdClass());
        $this->receiver->expects($this->once())->method('ack')->with($envelope);
        $this->transport->ack($envelope);
    }

    public function testItCanRejectAMessageViaTheReceiver()
    {
        $envelope = new Envelope(new \stdClass());
        $this->receiver->expects($this->once())->method('reject')->with($envelope);
        $this->transport->reject($envelope);
    }

    public function testItCanGetMessageCountViaTheReceiver()
    {
        $messageCount = 15;
        $this->receiver->expects($this->once())->method('getMessageCount')->willReturn($messageCount);
        $this->assertSame($messageCount, $this->transport->getMessageCount());
    }

    public function testItCanSendAMessageViaTheSender()
    {
        $envelope = new Envelope(new \stdClass());
        $this->sender->expects($this->once())->method('send')->with($envelope)->willReturn($envelope);
        $this->assertSame($envelope, $this->transport->send($envelope));
    }

    public function testItCanSetUpTheConnection()
    {
        $this->connection->expects($this->once())->method('setup');
        $this->transport->setup();
    }

    public function testItConvertsHttpExceptionDuringSetupIntoTransportException()
    {
        $this->connection
            ->expects($this->once())
            ->method('setup')
            ->willThrowException($this->createHttpException());

        $this->expectException(TransportException::class);

        $this->transport->setup();
    }

    public function testItCanResetTheConnection()
    {
        $this->connection->expects($this->once())->method('reset');
        $this->transport->reset();
    }

    public function testItConvertsHttpExceptionDuringResetIntoTransportException()
    {
        $this->connection
            ->expects($this->once())
            ->method('reset')
            ->willThrowException($this->createHttpException());

        $this->expectException(TransportException::class);

        $this->transport->reset();
    }

    private function getTransport(SerializerInterface $serializer = null, Connection $connection = null)
    {
        $serializer ??= $this->createMock(SerializerInterface::class);
        $connection ??= $this->createMock(Connection::class);

        return new AmazonSqsTransport($connection, $serializer);
    }

    private function createHttpException(): HttpException
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')->willReturnCallback(static function (string $type = null) {
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
