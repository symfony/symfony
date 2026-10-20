<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Tests\Transport;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Amqp\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpPriorityStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpReceivedStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpSender;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\BatchSendFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[RequiresPhpExtension('amqp')]
class AmqpSenderTest extends TestCase
{
    public function testItSendsTheEncodedMessage()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers']);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageUsingARoutingKey()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new AmqpStamp('rk'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsTheEncodedMessageWithoutHeaders()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...'];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], []);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsWithDelay()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new DelayStamp(1000));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 1000);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItSendsWithPriority()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with(new AmqpPriorityStamp(255));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturnOnConsecutiveCalls($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $this->callback(static fn (AmqpStamp $stamp) => 255 === $stamp->getAttributes()['priority']));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testContentTypeHeaderIsMovedToAttribute()
    {
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class, 'Content-Type' => 'application/json']];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        unset($encoded['headers']['Content-Type']);
        $stamp = new AmqpStamp(null, \AMQP_NOPARAM, ['content_type' => 'application/json']);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testContentTypeHeaderDoesNotOverwriteAttribute()
    {
        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp = new AmqpStamp('rk', \AMQP_NOPARAM, ['content_type' => 'custom']));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class, 'Content-Type' => 'application/json']];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        unset($encoded['headers']['Content-Type']);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItThrowsATransportExceptionIfItCannotSendTheMessage()
    {
        $this->expectException(TransportException::class);
        $envelope = new Envelope(new DummyMessage('Oy'));
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'])->willThrowException(new \AMQPException());

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testTransportMessageIdStampIsCreatedIfMessageIdIsSet()
    {
        $id = '01946fcb-4bcb-7aa7-9727-dac1c0374443';
        $stamp = new AmqpStamp(null, \AMQP_NOPARAM, ['message_id' => $id]);

        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $returnedEnvelope = $sender->send($envelope);

        $transportMessageIdStamp = $returnedEnvelope->last(TransportMessageIdStamp::class);
        $this->assertSame($id, $transportMessageIdStamp->getId());
    }

    public function testTransportMessageIdStampIsNotCreatedIfMessageIdIsNotSet()
    {
        $stamp = new AmqpStamp(null, \AMQP_NOPARAM, []);

        $envelope = (new Envelope(new DummyMessage('Oy')))->with($stamp);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())->method('encode')->with($envelope)->willReturn($encoded);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sender = new AmqpSender($connection, $serializer);
        $returnedEnvelope = $sender->send($envelope);

        $transportMessageIdStamp = $returnedEnvelope->last(TransportMessageIdStamp::class);
        $this->assertNull($transportMessageIdStamp);
    }

    public function testIdentityStampsSetTheNativeProperties()
    {
        $envelope = new Envelope(new DummyMessage('Oy'), [new MessageIdStamp('message-id'), new CorrelationStamp('correlation-id'), new CausationStamp('causation-id')]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, new AmqpStamp(null, \AMQP_NOPARAM, ['message_id' => 'message-id', 'correlation_id' => 'correlation-id']));

        $sent = (new AmqpSender($connection, $serializer))->send($envelope);

        $this->assertSame('message-id', $sent->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testAmqpStampAttributesWinOverIdentityStamps()
    {
        $stamp = new AmqpStamp('rk', \AMQP_NOPARAM, ['message_id' => 'amqp-message-id', 'correlation_id' => 'amqp-correlation-id']);
        $envelope = new Envelope(new DummyMessage('Oy'), [$stamp, new MessageIdStamp('message-id'), new CorrelationStamp('correlation-id')]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, $stamp);

        $sent = (new AmqpSender($connection, $serializer))->send($envelope);

        $this->assertSame('amqp-message-id', $sent->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testIdentityStampsLongerThanAmqpAllowsDoNotSetTheNativeProperties()
    {
        $envelope = new Envelope(new DummyMessage('Oy'), [new MessageIdStamp($messageId = str_repeat('m', 255)), new CorrelationStamp(str_repeat('c', 256))]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')->with($encoded['body'], $encoded['headers'], 0, new AmqpStamp(null, \AMQP_NOPARAM, ['message_id' => $messageId]));

        (new AmqpSender($connection, $serializer))->send($envelope);
    }

    public function testRetryKeepsTheIdentityOfTheMessage()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getCorrelationId')->willReturn('correlation-id');

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(1),
            new MessageIdStamp('message-id'),
            new CorrelationStamp('correlation-id'),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                $this->assertTrue($stamp->isRetryAttempt());
                $this->assertSame('message-id', $stamp->getAttributes()['message_id']);
                $this->assertSame('correlation-id', $stamp->getAttributes()['correlation_id']);

                return true;
            }));

        $sent = (new AmqpSender($connection, $serializer))->send($envelope);

        $this->assertSame('message-id', $sent->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testDoNotUseRetryRoutingKeyWhenSendingToFailureTransport()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(0),
            new SentToFailureTransportStamp('async'),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                // The routing key must NOT be the original queue name
                $this->assertNotSame('original_queue', $stamp->getRoutingKey());
                $this->assertFalse($stamp->isRetryAttempt());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItRetriesToTheOriginalQueueOfTheTransportTheMessageWasReceivedFrom()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(1),
            new DelayStamp(1000),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['other_queue', 'original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 1000, $this->callback(function (AmqpStamp $stamp) {
                $this->assertSame('original_queue', $stamp->getRoutingKey());
                $this->assertTrue($stamp->isRetryAttempt());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItKeepsTheReceivedRoutingKeyWhenSendingToTheTransportTheMessageWasReceivedFrom()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');

        $envelope = new Envelope(new DummyMessage('Oy'), [new AmqpReceivedStamp($amqpEnvelope, 'original_queue')]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                $this->assertSame('original_routing_key', $stamp->getRoutingKey());
                $this->assertFalse($stamp->isRetryAttempt());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItDoesNotReuseTheReceivedRoutingKeyWhenSendingToAnotherTransport()
    {
        $this->assertRoutingKeyWhenSendingToAnotherTransport(null);
        $this->assertRoutingKeyWhenSendingToAnotherTransport(null, [new RedeliveryStamp(1)]);
        $this->assertRoutingKeyWhenSendingToAnotherTransport(null, [new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testAnExplicitRoutingKeyIsUsedWhenSendingAReceivedMessageToAnotherTransport()
    {
        $this->assertRoutingKeyWhenSendingToAnotherTransport('explicit_routing_key', [new AmqpStamp('explicit_routing_key')]);
        $this->assertRoutingKeyWhenSendingToAnotherTransport('explicit_routing_key', [new AmqpStamp('explicit_routing_key'), new RedeliveryStamp(1)]);
        $this->assertRoutingKeyWhenSendingToAnotherTransport('explicit_routing_key', [new AmqpStamp('explicit_routing_key'), new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testItDoesNotReuseTheReceivedExpirationWhenSendingToAnotherTransport()
    {
        $this->assertExpirationWhenSending(null, ['other_queue']);
        $this->assertExpirationWhenSending(null, ['other_queue'], [new RedeliveryStamp(1)]);
        $this->assertExpirationWhenSending(null, ['other_queue'], [new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testAnExplicitExpirationIsUsedWhenSendingAReceivedMessageToAnotherTransport()
    {
        $amqpStamp = new AmqpStamp(null, \AMQP_NOPARAM, ['expiration' => '300000']);

        $this->assertExpirationWhenSending('300000', ['other_queue'], [$amqpStamp]);
        $this->assertExpirationWhenSending('300000', ['other_queue'], [$amqpStamp, new RedeliveryStamp(0), new SentToFailureTransportStamp('async')]);
    }

    public function testItKeepsTheReceivedExpirationWhenSendingToTheTransportTheMessageWasReceivedFrom()
    {
        $this->assertExpirationWhenSending('60000', ['original_queue']);
        $this->assertExpirationWhenSending('60000', ['original_queue'], [new RedeliveryStamp(1)]);
    }

    public function testItDoesNotRepublishAReceivedZeroPriorityOnRetry()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getPriority')->willReturn(0);

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(0),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                $this->assertSame('original_queue', $stamp->getRoutingKey());
                $this->assertTrue($stamp->isRetryAttempt());
                $this->assertArrayNotHasKey('priority', $stamp->getAttributes());

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testItRepublishesAnExplicitZeroPriorityOnRetry()
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getPriority')->willReturn(5);

        $envelope = new Envelope(new DummyMessage('Oy'), [
            new AmqpPriorityStamp(0),
            new AmqpReceivedStamp($amqpEnvelope, 'original_queue'),
            new RedeliveryStamp(0),
        ]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['original_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) {
                $this->assertTrue($stamp->isRetryAttempt());
                $this->assertSame(0, $stamp->getAttributes()['priority']);

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    public function testSendBatch()
    {
        $envelopes = [
            'a' => new Envelope(new DummyMessage('a'), [new DelayStamp(500)]),
            'b' => new Envelope(new DummyMessage('b'), [$stamp = new AmqpStamp('rk', \AMQP_NOPARAM, ['message_id' => 'id-b'])]),
        ];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('publish');
        $connection->expects($this->once())->method('publishBatch')->with([
            'a' => ['body a', ['type' => DummyMessage::class], 500, null],
            'b' => ['body b', ['type' => DummyMessage::class], 0, $stamp],
        ])->willReturn([]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturnCallback(static fn (Envelope $envelope) => ['body' => 'body '.$envelope->getMessage()->getMessage(), 'headers' => ['type' => DummyMessage::class]]);

        $sent = (new AmqpSender($connection, $serializer))->sendBatch($envelopes);

        $this->assertSame(['a', 'b'], array_keys($sent));
        $this->assertSame($envelopes['a'], $sent['a']);
        $this->assertSame('id-b', $sent['b']->last(TransportMessageIdStamp::class)?->getId());
    }

    public function testSendBatchTellsWhichMessagesWereSent()
    {
        $envelopes = ['a' => new Envelope(new DummyMessage('a')), 'b' => new Envelope(new DummyMessage('b'))];

        $connection = $this->createStub(Connection::class);
        $connection->method('publishBatch')->willReturn(['b' => $exception = new TransportException('Nacked.')]);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        try {
            (new AmqpSender($connection, $serializer))->sendBatch($envelopes);
            $this->fail('An exception should have been thrown.');
        } catch (BatchSendFailedException $e) {
            $this->assertSame(['a' => $envelopes['a']], $e->getEnvelopes());
            $this->assertSame(['b' => $exception], $e->getExceptions());
        }
    }

    public function testItConvertsAmqpExceptionDuringSendBatchIntoTransportException()
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('publishBatch')->willThrowException(new \AMQPException('Could not connect to the AMQP server.'));

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => '...']);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Could not connect to the AMQP server.');

        (new AmqpSender($connection, $serializer))->sendBatch([new Envelope(new DummyMessage('a'))]);
    }

    private function assertRoutingKeyWhenSendingToAnotherTransport(?string $expectedRoutingKey, array $stamps = []): void
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getRoutingKey')->willReturn('original_routing_key');
        $amqpEnvelope->method('getContentType')->willReturn('application/x-original');

        $envelope = new Envelope(new DummyMessage('Oy'), [new AmqpReceivedStamp($amqpEnvelope, 'original_queue'), ...$stamps]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn(['other_queue']);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) use ($expectedRoutingKey) {
                $this->assertSame($expectedRoutingKey, $stamp->getRoutingKey());
                $this->assertFalse($stamp->isRetryAttempt());
                $this->assertSame('application/x-original', $stamp->getAttributes()['content_type']);

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }

    private function assertExpirationWhenSending(?string $expectedExpiration, array $queueNames, array $stamps = []): void
    {
        $amqpEnvelope = $this->createStub(\AMQPEnvelope::class);
        $amqpEnvelope->method('getExpiration')->willReturn('60000');

        $envelope = new Envelope(new DummyMessage('Oy'), [new AmqpReceivedStamp($amqpEnvelope, 'original_queue'), ...$stamps]);
        $encoded = ['body' => '...', 'headers' => ['type' => DummyMessage::class]];

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn($encoded);

        $connection = $this->createMock(Connection::class);
        $connection->method('getQueueNames')->willReturn($queueNames);
        $connection->expects($this->once())->method('publish')
            ->with($encoded['body'], $encoded['headers'], 0, $this->callback(function (AmqpStamp $stamp) use ($expectedExpiration) {
                $this->assertSame($expectedExpiration, $stamp->getAttributes()['expiration'] ?? null);

                return true;
            }));

        $sender = new AmqpSender($connection, $serializer);
        $sender->send($envelope);
    }
}
