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
        $this->connection = self::createMock(Connection::class);
        // Mocking the concrete receiver class because mocking multiple interfaces is deprecated
        $this->receiver = self::createMock(AmazonSqsReceiver::class);
        $this->sender = self::createMock(SenderInterface::class);

        $this->transport = new AmazonSqsTransport($this->connection, null, $this->receiver, $this->sender);
    }

    public function testItIsATransport()
    {
        $transport = $this->getTransport();

        self::assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testReceivesMessages()
    {
        $transport = $this->getTransport(
            $serializer = self::createMock(SerializerInterface::class),
            $connection = self::createMock(Connection::class)
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
        self::assertSame($decodedMessage, $envelopes[0]->getMessage());
    }

    public function testTransportIsAMessageCountAware()
    {
        $transport = $this->getTransport();

        self::assertInstanceOf(MessageCountAwareInterface::class, $transport);
    }

    public function testItCanGetMessagesViaTheReceiver()
    {
        $envelopes = [new Envelope(new \stdClass()), new Envelope(new \stdClass())];
        $this->receiver->expects(self::once())->method('get')->willReturn($envelopes);
        self::assertSame($envelopes, $this->transport->get());
    }

    public function testItCanAcknowledgeAMessageViaTheReceiver()
    {
        $envelope = new Envelope(new \stdClass());
        $this->receiver->expects(self::once())->method('ack')->with($envelope);
        $this->transport->ack($envelope);
    }

    public function testItCanRejectAMessageViaTheReceiver()
    {
        $envelope = new Envelope(new \stdClass());
        $this->receiver->expects(self::once())->method('reject')->with($envelope);
        $this->transport->reject($envelope);
    }

    public function testItCanGetMessageCountViaTheReceiver()
    {
        $messageCount = 15;
        $this->receiver->expects(self::once())->method('getMessageCount')->willReturn($messageCount);
        self::assertSame($messageCount, $this->transport->getMessageCount());
    }

    public function testItCanSendAMessageViaTheSender()
    {
        $envelope = new Envelope(new \stdClass());
        $this->sender->expects(self::once())->method('send')->with($envelope)->willReturn($envelope);
        self::assertSame($envelope, $this->transport->send($envelope));
    }

    public function testItCanSetUpTheConnection()
    {
        $this->connection->expects(self::once())->method('setup');
        $this->transport->setup();
    }

    public function testItConvertsHttpExceptionDuringSetupIntoTransportException()
    {
        $this->connection
            ->expects(self::once())
            ->method('setup')
            ->willThrowException($this->createHttpException());

        self::expectException(TransportException::class);

        $this->transport->setup();
    }

    public function testItCanResetTheConnection()
    {
        $this->connection->expects(self::once())->method('reset');
        $this->transport->reset();
    }

    public function testItConvertsHttpExceptionDuringResetIntoTransportException()
    {
        $this->connection
            ->expects(self::once())
            ->method('reset')
            ->willThrowException($this->createHttpException());

        self::expectException(TransportException::class);

        $this->transport->reset();
    }

    private function getTransport(SerializerInterface $serializer = null, Connection $connection = null)
    {
        $serializer = $serializer ?? self::createMock(SerializerInterface::class);
        $connection = $connection ?? self::createMock(Connection::class);

        return new AmazonSqsTransport($connection, $serializer);
    }

    private function createHttpException(): HttpException
    {
        $response = self::createMock(ResponseInterface::class);
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
