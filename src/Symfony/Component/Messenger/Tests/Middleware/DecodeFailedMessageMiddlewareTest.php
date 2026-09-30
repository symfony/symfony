<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;

class DecodeFailedMessageMiddlewareTest extends TestCase
{
    public function testItDecodesSerializedEnvelope()
    {
        $decodedEnvelope = new Envelope(new DummyMessage('decoded'));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => 'body', 'headers' => ['type' => DummyMessage::class]])
            ->willReturn($decodedEnvelope);

        $locator = new InMemoryLocator(['async' => $serializer]);
        $middleware = new DecodeFailedMessageMiddleware($locator);

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $ack = static fn (): bool => true;
        $envelope = MessageDecodingFailedException::wrap([
            'body' => 'body',
            'headers' => ['type' => DummyMessage::class],
        ], 'Could not decode.')
            ->with(new ReceivedStamp('async'), new AckStamp($ack));

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertInstanceOf(DummyMessage::class, $nextMiddleware->envelope?->getMessage());
        $this->assertNotNull($nextMiddleware->envelope?->last(AckStamp::class));
    }

    public function testItUsesOriginalTransportNameWhenRetryingFromFailureTransport()
    {
        $decodedEnvelope = new Envelope(new DummyMessage('decoded'));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => 'body', 'headers' => []])
            ->willReturn($decodedEnvelope);

        // 'async' is the original transport, 'failed' is the failure transport
        $locator = new InMemoryLocator(['async' => $serializer]);
        $middleware = new DecodeFailedMessageMiddleware($locator);

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.')
            ->with(
                new ReceivedStamp('failed'),
                new SentToFailureTransportStamp('async'),
            );

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertInstanceOf(DummyMessage::class, $nextMiddleware->envelope?->getMessage());
        $this->assertNotNull($nextMiddleware->envelope->last(SentToFailureTransportStamp::class));
    }

    public function testItRemovesTheFailureTransportStampOfARedispatchedFailure()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope(new DummyMessage('decoded')));

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['async' => $serializer]));

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.')
            ->with(new SentToFailureTransportStamp('async'), new RedeliveryStamp(1));

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertInstanceOf(DummyMessage::class, $nextMiddleware->envelope?->getMessage());
        $this->assertSame([], $nextMiddleware->envelope->all(SentToFailureTransportStamp::class));
        $this->assertNotNull($nextMiddleware->envelope->last(RedeliveryStamp::class));
    }

    public function testItDoesNotDuplicateTheStampsKeptByTheFailedEnvelope()
    {
        $serializer = Serializer::create();
        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new BusNameStamp('bus'), new RedeliveryStamp(1)]));

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['async' => $serializer]));

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $envelope = MessageDecodingFailedException::wrap($encodedEnvelope, 'Could not decode.')
            ->with(
                new BusNameStamp('bus'),
                new RedeliveryStamp(1),
                new SentToFailureTransportStamp('async'),
                new RedeliveryStamp(0),
                new ReceivedStamp('failed'),
            );

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $envelope = $nextMiddleware->envelope;
        $this->assertInstanceOf(DummyMessage::class, $envelope?->getMessage());
        $this->assertCount(1, $envelope->all(BusNameStamp::class));
        $this->assertSame([1, 0], array_map(static fn (RedeliveryStamp $stamp): int => $stamp->getRetryCount(), $envelope->all(RedeliveryStamp::class)));
        $this->assertCount(1, $envelope->all(SentToFailureTransportStamp::class));
        $this->assertCount(1, $envelope->all(SerializedMessageStamp::class));
    }

    public function testItKeepsOnlyTheLocalStampsOfAnUnverifiedFailureThatDecodesToASignedMessage()
    {
        $envelope = $this->handleUnverifiedFailure(new DummyMessage('decoded'), 'the_bus');

        $this->assertInstanceOf(DummyMessage::class, $envelope->getMessage());
        $this->assertSame(['the_bus'], array_map(static fn (BusNameStamp $stamp): string => $stamp->getBusName(), $envelope->all(BusNameStamp::class)));
        $this->assertSame([], $envelope->all(RedeliveryStamp::class));
        $this->assertSame('async', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertSame([42], array_map(static fn (TransportMessageIdStamp $stamp): mixed => $stamp->getId(), $envelope->all(TransportMessageIdStamp::class)));
        $this->assertSame('async', $envelope->last(ReceivedStamp::class)?->getTransportName());
        $this->assertCount(1, $envelope->all(AckStamp::class));
    }

    #[DataProvider('provideUnverifiedFailuresOfSignedMessages')]
    public function testItRejectsAnUnverifiedFailureOnAnotherBusThanTheSignedMessageItDecodesTo(object $decodedMessage, array $decodedStamps, SerializerInterface $failureSerializer)
    {
        $this->expectException(InvalidMessageSignatureException::class);
        $this->expectExceptionMessage('the message belongs to the "the_bus" bus');

        $this->handleUnverifiedFailure($decodedMessage, 'failed_bus', $decodedStamps, $failureSerializer);
    }

    public static function provideUnverifiedFailuresOfSignedMessages(): iterable
    {
        yield 'message type that requires a signature' => [new DummyMessage('decoded'), [], new SigningSerializer(new PhpSerializer(), 'signing-key', [DummyMessage::class])];
        yield 'verified message' => [new \stdClass(), [TrustStamp::trusted()], new SigningSerializer(new PhpSerializer(), 'signing-key', [DummyMessage::class])];
        yield 'verified message, from a failure transport that does not sign' => [new \stdClass(), [TrustStamp::trusted()], new PhpSerializer()];
    }

    #[DataProvider('provideFailureSerializers')]
    public function testItKeepsTheStampsOfAnUnverifiedFailureThatDecodesToAMessageWithoutSignature(SerializerInterface $failureSerializer)
    {
        $envelope = $this->handleUnverifiedFailure(new \stdClass(), 'failed_bus', [], $failureSerializer);

        $this->assertInstanceOf(\stdClass::class, $envelope->getMessage());
        $this->assertSame(['failed_bus'], array_map(static fn (BusNameStamp $stamp): string => $stamp->getBusName(), $envelope->all(BusNameStamp::class)));
        $this->assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
        $this->assertSame(['failed_id', 42], array_map(static fn (TransportMessageIdStamp $stamp): mixed => $stamp->getId(), $envelope->all(TransportMessageIdStamp::class)));
        $this->assertSame('async', $envelope->last(ReceivedStamp::class)?->getTransportName());
        $this->assertCount(1, $envelope->all(AckStamp::class));
    }

    #[DataProvider('provideFailureSerializers')]
    public function testItKeepsOnlyTheLocalStampsOfAnUnverifiedFailureThatDecodesToAVerifiedMessage(SerializerInterface $failureSerializer)
    {
        $envelope = $this->handleUnverifiedFailure(new \stdClass(), 'the_bus', [TrustStamp::trusted()], $failureSerializer);

        $this->assertSame(['the_bus'], array_map(static fn (BusNameStamp $stamp): string => $stamp->getBusName(), $envelope->all(BusNameStamp::class)));
        $this->assertSame([], $envelope->all(RedeliveryStamp::class));
        $this->assertSame('async', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertSame([42], array_map(static fn (TransportMessageIdStamp $stamp): mixed => $stamp->getId(), $envelope->all(TransportMessageIdStamp::class)));
        $this->assertSame('async', $envelope->last(ReceivedStamp::class)?->getTransportName());
        $this->assertCount(1, $envelope->all(AckStamp::class));
    }

    public static function provideFailureSerializers(): iterable
    {
        yield 'from a failure transport that signs message types' => [new SigningSerializer(new PhpSerializer(), 'signing-key', [DummyMessage::class])];
        yield 'from a failure transport that does not sign' => [new PhpSerializer()];
    }

    #[DataProvider('provideTrustStamps')]
    public function testTheDecodedMessageIsTrustedOnlyWhenTheFailureThatCarriedItWasTrustedToo(?bool $failureVerified, ?bool $messageVerified, ?bool $expected)
    {
        $createStamps = static fn (?bool $verified): array => match ($verified) {
            null => [],
            false => [TrustStamp::untrusted()],
            true => [TrustStamp::trusted()],
        };

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope(new DummyMessage('decoded'), $createStamps($messageVerified)));

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['async' => $serializer]));

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $failure = MessageDecodingFailedException::wrap(['body' => 'body'], 'Could not decode.')->with(new ReceivedStamp('async'), ...$createStamps($failureVerified));
        $middleware->handle($failure, new StackMiddleware($nextMiddleware));

        $this->assertLessThanOrEqual(1, \count($nextMiddleware->envelope->all(TrustStamp::class)));
        $this->assertSame($expected, $nextMiddleware->envelope->last(TrustStamp::class)?->isTrusted());
    }

    public static function provideTrustStamps(): iterable
    {
        yield 'both verified' => [true, true, true];
        yield 'message verified, failure not' => [false, true, false];
        yield 'message verified, failure not checked' => [null, true, false];
        yield 'failure verified, message not' => [true, false, false];
        yield 'failure verified, message not checked' => [true, null, false];
        yield 'none checked' => [null, null, null];
    }

    public function testItThrowsWhenNoReceivedStampAndNoSentToFailureStamp()
    {
        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator([]));

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.');

        $this->expectException(\Symfony\Component\Messenger\Exception\LogicException::class);
        $this->expectExceptionMessage('ReceivedStamp');
        $middleware->handle($envelope, new StackMiddleware());
    }

    public function testItThrowsWhenDecodingStillFails()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException('boom'));

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['transport' => $serializer]));

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.')
            ->with(new ReceivedStamp('transport'));

        $this->expectException(MessageDecodingFailedException::class);
        $middleware->handle($envelope, new StackMiddleware());
    }

    public function testItThrowsTheDecodingFailureWhenDecodingStillFails()
    {
        $failed = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.', 0, new \RuntimeException('Class not found.'));

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn($failed);

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['transport' => $serializer]));

        try {
            $middleware->handle($failed->with(new ReceivedStamp('transport')), new StackMiddleware());
            $this->fail('An exception should have been thrown.');
        } catch (MessageDecodingFailedException $e) {
            $this->assertSame($failed->getMessage(), $e);
        }
    }

    public function testItThrowsTheUnrecoverableCauseWhenDecodingStillFails()
    {
        $cause = new UnrecoverableMessageHandlingException('Invalid signature.');
        $failed = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Invalid signature.', 0, $cause);

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn($failed);

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['transport' => $serializer]));

        try {
            $middleware->handle($failed->with(new ReceivedStamp('transport')), new StackMiddleware());
            $this->fail('An exception should have been thrown.');
        } catch (UnrecoverableMessageHandlingException $e) {
            $this->assertSame($cause, $e);
        }
    }

    public function testItIgnoresRegularMessages()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('decode');

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['async' => $serializer]));

        $envelope = new Envelope(new DummyMessage('ok'));

        $middleware->handle($envelope, new StackMiddleware());
    }

    private function handleUnverifiedFailure(object $decodedMessage, string $failureBusName, array $decodedStamps = [], ?SerializerInterface $failureSerializer = null): Envelope
    {
        $phpSerializer = new PhpSerializer();
        $encodedFailure = $phpSerializer->encode(new Envelope(new MessageDecodingFailedException('Could not retrieve the claim.', 0, null, ['body' => 'claim']), [new BusNameStamp($failureBusName), new RedeliveryStamp(1), new TransportMessageIdStamp('failed_id'), new SentToFailureTransportStamp('async')]));
        $failure = ($failureSerializer ?? new SigningSerializer($phpSerializer, 'signing-key', [DummyMessage::class]))->decode($encodedFailure);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure->getMessage());

        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope($decodedMessage, [new BusNameStamp('the_bus'), ...$decodedStamps]));

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['async' => $serializer]));

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $middleware->handle($failure->with(new TransportMessageIdStamp(42), new ReceivedStamp('async'), new AckStamp(static fn () => null)), new StackMiddleware($nextMiddleware));

        return $nextMiddleware->envelope;
    }
}

class InMemoryLocator implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class(\sprintf('Service "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
