<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Failure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Failure\FailedMessageFilter;
use Symfony\Component\Messenger\Failure\FailedMessageRepository;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;

class FailedMessageRepositoryTest extends TestCase
{
    public function testGetTransportNames()
    {
        $repository = new FailedMessageRepository(new ServiceLocator([
            'failure_receiver' => fn () => $this->createStub(ListableReceiverInterface::class),
            'failure_receiver_another' => fn () => $this->createStub(ListableReceiverInterface::class),
        ]), 'failure_receiver');

        $this->assertSame(['failure_receiver', 'failure_receiver_another'], $repository->getTransportNames());
        $this->assertSame('failure_receiver', $repository->getGlobalTransportName());
    }

    public function testTheGlobalTransportIsUsedWhenNoneIsGiven()
    {
        $envelope = new Envelope(new DummyMessage('a'));
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('find')->with(42)->willReturn($envelope);

        $repository = new FailedMessageRepository(new ServiceLocator([
            'global' => static fn () => $receiver,
            'other' => fn () => $this->createStub(ListableReceiverInterface::class),
        ]), 'global');

        $this->assertSame($envelope, $repository->find(42));
    }

    public function testAnUnknownTransportIsRejected()
    {
        $repository = new FailedMessageRepository(new ServiceLocator([
            'global' => fn () => $this->createStub(ListableReceiverInterface::class),
        ]), 'global');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "nope" failure transport was not found. Available transports are: "global".');

        $repository->getReceiver('nope');
    }

    public function testAMissingGlobalTransportIsRejected()
    {
        $repository = new FailedMessageRepository(new ServiceLocator([
            'global' => fn () => $this->createStub(ListableReceiverInterface::class),
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No default failure transport is defined. Available transports are: "global".');

        $repository->getReceiver();
    }

    public function testAllAppliesTheFilter()
    {
        $matching = new Envelope(new DummyMessage('a'), [new TransportMessageIdStamp(1)]);
        $other = new Envelope(new \stdClass(), [new TransportMessageIdStamp(2)]);

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(null)->willReturn([$matching, $other]);

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => static fn () => $receiver]), 'global');

        $envelopes = iterator_to_array($repository->all(null, new FailedMessageFilter(DummyMessage::class)), false);

        $this->assertSame([$matching], $envelopes);
    }

    public function testAllPassesTheLimitToTheReceiver()
    {
        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('all')->with(10)->willReturn([]);

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => static fn () => $receiver]), 'global');

        $this->assertSame([], iterator_to_array($repository->all(null, null, 10), false));
    }

    public function testAllRejectsANonListableTransportEagerly()
    {
        $repository = new FailedMessageRepository(new ServiceLocator([
            'global' => fn () => $this->createStub(ReceiverInterface::class),
        ]), 'global');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "global" failure transport does not support listing messages.');

        // the exception must not wait for the first iteration
        $repository->all();
    }

    public function testCountReturnsNullWhenTheTransportCannotCount()
    {
        $counting = $this->createMock(CountableListableReceiver::class);
        $counting->expects($this->once())->method('getMessageCount')->willReturn(3);

        $repository = new FailedMessageRepository(new ServiceLocator([
            'counting' => static fn () => $counting,
            'plain' => fn () => $this->createStub(ListableReceiverInterface::class),
        ]), 'counting');

        $this->assertSame(3, $repository->count());
        $this->assertNull($repository->count('plain'));
    }

    public function testSupportsListing()
    {
        $repository = new FailedMessageRepository(new ServiceLocator([
            'listable' => fn () => $this->createStub(ListableReceiverInterface::class),
            'plain' => fn () => $this->createStub(ReceiverInterface::class),
        ]), 'listable');

        $this->assertTrue($repository->supportsListing());
        $this->assertFalse($repository->supportsListing('plain'));
    }

    public function testRemoveRejectsTheEnvelope()
    {
        $envelope = new Envelope(new DummyMessage('a'));

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('reject')->with($envelope);

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => static fn () => $receiver]), 'global');
        $repository->remove($envelope);
    }

    public function testGetMessageId()
    {
        $this->assertSame(15, FailedMessageRepository::getMessageId(new Envelope(new DummyMessage('a'), [new TransportMessageIdStamp(15)])));
        $this->assertNull(FailedMessageRepository::getMessageId(new Envelope(new DummyMessage('a'))));
    }

    public function testPrepareForRedispatchStripsTheTransportStamps()
    {
        $envelope = new Envelope(new DummyMessage('a'), [
            new TransportMessageIdStamp(15),
            new SentToFailureTransportStamp('async'),
            new ReceivedStamp('failed'),
            new RedeliveryStamp(1),
            new BusNameStamp('the_bus'),
        ]);

        $prepared = FailedMessageRepository::prepareForRedispatch($envelope);

        $this->assertSame([], $prepared->all(TransportMessageIdStamp::class));
        $this->assertSame([], $prepared->all(SentToFailureTransportStamp::class));
        $this->assertSame([], $prepared->all(ReceivedStamp::class));
        // sendable stamps are kept, so the redispatched message keeps its history
        $this->assertCount(1, $prepared->all(RedeliveryStamp::class));
        $this->assertCount(1, $prepared->all(BusNameStamp::class));
    }

    public function testPrepareForRedispatchKeepsTellingWhetherTheEnvelopeIsTrusted()
    {
        $trust = TrustStamp::trusted();

        $this->assertSame($trust, FailedMessageRepository::prepareForRedispatch(new Envelope(new DummyMessage('a'), [new ReceivedStamp('failed'), $trust]))->last(TrustStamp::class));
        $this->assertFalse(FailedMessageRepository::prepareForRedispatch(new Envelope(new DummyMessage('a'), [new ReceivedStamp('failed')]))->last(TrustStamp::class)?->isTrusted());
        $this->assertFalse(FailedMessageRepository::prepareForRedispatch(new Envelope(new DummyMessage('a')))->last(TrustStamp::class)?->isTrusted());
    }

    #[DataProvider('provideFailureTransportSerializers')]
    public function testRedispatchToATransportThatSignsEverythingIsAcceptedOnlyWhenTheFailureTransportVerifiedTheMessage(SerializerInterface $failureSerializer, bool $expectAccepted)
    {
        $serializer = new SigningSerializer(new PhpSerializer(), 'signing-key', ['*']);
        $envelope = $failureSerializer->decode($failureSerializer->encode(new Envelope(new DummyMessage('a'), [new SentToFailureTransportStamp('async')])))->with(new ReceivedStamp('failed'));

        $async = new InMemoryTransport($serializer);
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['async']], new ServiceLocator(['async' => static fn () => $async]))),
        ]);

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => fn () => $this->createStub(ListableReceiverInterface::class)]), 'global', null, $bus);
        $repository->redispatch($envelope);

        $this->assertCount(1, $sent = $async->getSent());
        $this->assertInstanceOf($expectAccepted ? DummyMessage::class : MessageDecodingFailedException::class, $sent[0]->getMessage());
    }

    public static function provideFailureTransportSerializers(): iterable
    {
        yield 'signing every message' => [new SigningSerializer(new PhpSerializer(), 'signing-key', ['*']), true];
        yield 'signing the message type' => [new SigningSerializer(new PhpSerializer(), 'signing-key', [DummyMessage::class]), true];
        yield 'signing other message types' => [new SigningSerializer(new PhpSerializer(), 'signing-key', [\stdClass::class]), false];
        yield 'not signing' => [new PhpSerializer(), false];
    }

    public function testRedispatchDispatchesTheStrippedEnvelopeThenAcksTheOriginal()
    {
        $envelope = new Envelope(new DummyMessage('a'), [
            new TransportMessageIdStamp(15),
            new SentToFailureTransportStamp('async'),
        ]);

        $dispatched = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')->willReturnCallback(static function (Envelope $e) use (&$dispatched) {
            $dispatched = $e;

            return $e;
        });

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('ack')->with($envelope);
        $receiver->expects($this->never())->method('reject');

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => static fn () => $receiver]), 'global', messageBus: $bus);
        $repository->redispatch($envelope);

        $this->assertSame([], $dispatched->all(SentToFailureTransportStamp::class));
        $this->assertSame($envelope->getMessage(), $dispatched->getMessage());
    }

    public function testRedispatchDecodesADecodingFailureWithTheSerializerOfItsOriginalTransport()
    {
        $serializer = new PhpSerializer();
        $envelope = MessageDecodingFailedException::wrap($serializer->encode(new Envelope(new DummyMessage('a'))), 'Could not decode.')
            ->with(new SentToFailureTransportStamp('async'), new TransportMessageIdStamp(15));

        $async = new InMemoryTransport();
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['async' => static fn () => $serializer])),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['async']], new ServiceLocator(['async' => static fn () => $async]))),
        ]);

        $receiver = $this->createMock(ListableReceiverInterface::class);
        $receiver->expects($this->once())->method('ack')->with($envelope);

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => static fn () => $receiver]), 'global', null, $bus);
        $repository->redispatch($envelope);

        $this->assertCount(1, $sent = $async->getSent());
        $this->assertEquals(new DummyMessage('a'), $sent[0]->getMessage());
        $this->assertSame([], $sent[0]->all(SentToFailureTransportStamp::class));
    }

    public function testRedispatchDoesNotPutTheStampsOfAnUnverifiedFailureOnTheSignedMessageItDecodesTo()
    {
        $phpSerializer = new PhpSerializer();
        $encodedFailure = $phpSerializer->encode(new Envelope(new MessageDecodingFailedException('Could not retrieve the claim.', 0, null, ['body' => 'claim']), [new SentToFailureTransportStamp('async'), new RedeliveryStamp(3)]));
        $envelope = (new SigningSerializer($phpSerializer, 'signing-key', [DummyMessage::class]))->decode($encodedFailure)->with(new TransportMessageIdStamp(15));

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope(new DummyMessage('a'), [new BusNameStamp('the_bus')]));

        $async = new InMemoryTransport();
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['async' => static fn () => $serializer])),
            new SendMessageMiddleware(new SendersLocator([DummyMessage::class => ['async']], new ServiceLocator(['async' => static fn () => $async]))),
        ]);

        $repository = new FailedMessageRepository(new ServiceLocator(['global' => fn () => $this->createStub(ListableReceiverInterface::class)]), 'global', null, $bus);
        $repository->redispatch($envelope);

        $this->assertCount(1, $sent = $async->getSent());
        $this->assertSame(['the_bus'], array_map(static fn (BusNameStamp $stamp): string => $stamp->getBusName(), $sent[0]->all(BusNameStamp::class)));
        $this->assertSame([], $sent[0]->all(RedeliveryStamp::class));
    }

    public function testRedispatchWithoutABusIsRejected()
    {
        $repository = new FailedMessageRepository(new ServiceLocator([
            'global' => fn () => $this->createStub(ListableReceiverInterface::class),
        ]), 'global');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot redispatch a failed message without a message bus.');

        $repository->redispatch(new Envelope(new DummyMessage('a')));
    }
}

interface CountableListableReceiver extends ListableReceiverInterface, MessageCountAwareInterface
{
}
