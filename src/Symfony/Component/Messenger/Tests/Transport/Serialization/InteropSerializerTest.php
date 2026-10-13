<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\InteropSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Transport\Serialization\StampHeaderMapperInterface;

class InteropSerializerTest extends TestCase
{
    public function testEncodeSendsNoStamps()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new BusNameStamp('command.bus')]));

        $this->assertSame([], array_filter(array_keys($encoded['headers']), static fn ($name) => str_starts_with($name, 'X-Message-Stamp-')));
        $this->assertEquals(new Envelope(new DummyMessage('Hello')), $serializer->decode($encoded)->withoutAll(SerializedMessageStamp::class));
    }

    public function testEncodeKeepsStampsOfReceivedMessagesForRetries()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new ReceivedStamp('async'), new RedeliveryStamp(2)]));

        $this->assertSame(2, RedeliveryStamp::getRetryCountFromEnvelope($serializer->decode($encoded)));
    }

    public function testEncodeSendsNoStampsOfReceivedMessagesRelayedToAnotherTransport()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new ReceivedStamp('outbox'), new BusNameStamp('command.bus')]));

        $this->assertSame([], array_filter(array_keys($encoded['headers']), static fn ($name) => str_starts_with($name, 'X-Message-Stamp-')));
    }

    public function testDecodeIgnoresUnknownStamps()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $envelope = $serializer->decode([
            'body' => '{"message":"Hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-App\Stamp\UnknownStamp' => '[{}]',
                'X-Message-Stamp-'.RedeliveryStamp::class => '[{"retryCount":1}]',
            ],
        ]);

        $this->assertEquals(new DummyMessage('Hello'), $envelope->getMessage());
        $this->assertSame(1, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
    }

    public function testDecodeIgnoresTheStampsOfMessagesThatAreNotRetried()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $envelope = $serializer->decode([
            'body' => '{"message":"Hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.BusNameStamp::class => '[{"busName":"app_a.bus"}]',
            ],
        ]);

        $this->assertEquals(new DummyMessage('Hello'), $envelope->getMessage());
        $this->assertNull($envelope->last(BusNameStamp::class));
    }

    public function testDecodeUsesTheGivenMessageTypeWhenTheTypeHeaderIsMissing()
    {
        $serializer = new InteropSerializer(Serializer::create(), DummyMessage::class);
        $encodedEnvelope = ['body' => '{"message":"Hello"}', 'headers' => []];

        $this->assertEquals(new DummyMessage('Hello'), $serializer->decode($encodedEnvelope)->getMessage());
    }

    public function testDecodeResolvesTheMessageTypeWithTheGivenClosure()
    {
        $serializer = new InteropSerializer(Serializer::create(), static fn (array $encodedEnvelope) => str_contains($encodedEnvelope['body'], '"message"') ? DummyMessage::class : null);

        $this->assertEquals(new DummyMessage('Hello'), $serializer->decode(['body' => '{"message":"Hello"}'])->getMessage());
        $this->assertInstanceOf(MessageDecodingFailedException::class, $serializer->decode(['body' => '{"other":"Hello"}'])->getMessage());
    }

    public function testDecodeFailsWithAClearMessageWhenTheGivenClosureCannotTellTheMessageType()
    {
        $serializer = new InteropSerializer(Serializer::create(), static fn () => null);

        $failure = $serializer->decode(['body' => '{"message":"Hello"}', 'headers' => []])->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame('Encoded envelope does not have a "type" header and the message type closure returned null.', $failure->getMessage());

        $envelope = $serializer->decode(['body' => '{"message":"Hello"}', 'headers' => ['X-Message-Stamp-'.RedeliveryStamp::class => '[{"retryCount":2}]']]);

        $this->assertSame('Encoded envelope does not have a "type" header and the message type closure returned null.', $envelope->getMessage()->getMessage());
        $this->assertSame(2, RedeliveryStamp::getRetryCountFromEnvelope($envelope));
    }

    public function testTheTypeHeaderWinsOverTheGivenMessageType()
    {
        $serializer = new InteropSerializer(Serializer::create(), static fn () => throw new \LogicException('The type header must be used.'));
        $encodedEnvelope = ['body' => '{"message":"Hello"}', 'headers' => ['type' => DummyMessage::class]];

        $this->assertEquals(new DummyMessage('Hello'), $serializer->decode($encodedEnvelope)->getMessage());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testGetMessageTypeUsesTheTypeHeaderThenTheGivenMessageType()
    {
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/messenger 8.2: The "%s::getMessageType()" method is deprecated, "%s" does not need it when it signs every message.', InteropSerializer::class, SigningSerializer::class));

        $this->assertSame(DummyMessage::class, (new InteropSerializer(Serializer::create(), DummyMessage::class))->getMessageType(['body' => '{"message":"Hello"}', 'headers' => []]));

        $serializer = new InteropSerializer(Serializer::create(), static fn (array $encodedEnvelope) => str_contains($encodedEnvelope['body'], '"message"') ? DummyMessage::class : null);
        $this->assertSame(DummyMessage::class, $serializer->getMessageType(['body' => '{"message":"Hello"}']));
        $this->assertNull($serializer->getMessageType(['body' => '{"other":"Hello"}']));

        $serializer = new InteropSerializer(Serializer::create(), static fn () => throw new \LogicException('The type header must be used.'));
        $this->assertSame(DummyMessage::class, $serializer->getMessageType(['body' => '{"message":"Hello"}', 'headers' => ['type' => DummyMessage::class]]));
    }

    public function testIdentityStampsTravelAsPlainHeaders()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new MessageIdStamp('message-id'), new CorrelationStamp('correlation-id'), new CausationStamp('causation-id')]));

        $this->assertSame(['type' => DummyMessage::class, 'Content-Type' => 'application/json', 'message_id' => 'message-id', 'correlation_id' => 'correlation-id', 'causation_id' => 'causation-id'], $encoded['headers']);

        $envelope = $serializer->decode($encoded);

        $this->assertEquals([new MessageIdStamp('message-id')], $envelope->all(MessageIdStamp::class));
        $this->assertEquals([new CorrelationStamp('correlation-id')], $envelope->all(CorrelationStamp::class));
        $this->assertEquals([new CausationStamp('causation-id')], $envelope->all(CausationStamp::class));
    }

    public function testRetriesKeepOneStampOfEachMappedClass()
    {
        $serializer = new InteropSerializer(Serializer::create());

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new ReceivedStamp('async'), new RedeliveryStamp(1), new MessageIdStamp('message-id')]));

        $this->assertSame('message-id', $encoded['headers']['message_id']);
        $this->assertArrayHasKey('X-Message-Stamp-'.MessageIdStamp::class, $encoded['headers']);
        $this->assertEquals([new MessageIdStamp('message-id')], $serializer->decode($encoded)->all(MessageIdStamp::class));
    }

    public function testTheHeadersOfTheInnerSerializerWinOverTheHeadersOfMappers()
    {
        $serializer = new InteropSerializer(Serializer::create(), null, [self::createMapper(['type' => 'App\Message', 'Content-Type' => 'text/plain', 'X-Message-Stamp-'.RedeliveryStamp::class => '[{"retryCount":9}]', 'tenant' => 'acme'])]);

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));

        $this->assertSame(['type' => DummyMessage::class, 'Content-Type' => 'application/json', 'tenant' => 'acme'], $encoded['headers']);

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello'), [new ReceivedStamp('async'), new RedeliveryStamp(2)]));

        $this->assertSame(2, RedeliveryStamp::getRetryCountFromEnvelope($serializer->decode($encoded)));
    }

    public function testTheFirstMapperWins()
    {
        $serializer = new InteropSerializer(Serializer::create(), null, [
            self::createMapper(['message_id' => 'first'], [new MessageIdStamp('first')]),
            self::createMapper(['message_id' => 'second'], [new MessageIdStamp('second')]),
        ]);

        $this->assertSame('first', $serializer->encode(new Envelope(new DummyMessage('Hello')))['headers']['message_id']);
        $this->assertEquals([new MessageIdStamp('first')], $serializer->decode(['body' => '{"message":"Hello"}', 'headers' => ['type' => DummyMessage::class]])->all(MessageIdStamp::class));
    }

    public function testMappersReadTheHeadersAsReceived()
    {
        $received = new \ArrayObject();
        $serializer = new InteropSerializer(Serializer::create(), DummyMessage::class, [self::createMapper([], [], $received)]);
        $headers = ['X-Message-Stamp-'.BusNameStamp::class => '[{"busName":"app_a.bus"}]', 'tenant' => 'acme'];

        $serializer->decode(['body' => '{"message":"Hello"}', 'headers' => $headers]);

        $this->assertSame([$headers], $received->getArrayCopy());
    }

    #[DataProvider('provideDecodingFailures')]
    public function testDecodingFailuresKeepTheMappedStamps(InteropSerializer $serializer, array $encodedEnvelope)
    {
        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertEquals([new MessageIdStamp('message-id')], $envelope->all(MessageIdStamp::class));
    }

    public static function provideDecodingFailures(): iterable
    {
        yield 'invalid body' => [new InteropSerializer(Serializer::create()), ['body' => '{"message":', 'headers' => ['type' => DummyMessage::class, 'message_id' => 'message-id']]];
        yield 'no type header' => [new InteropSerializer(Serializer::create()), ['body' => '{"message":"Hello"}', 'headers' => ['message_id' => 'message-id']]];
        yield 'unknown type' => [new InteropSerializer(Serializer::create(), static fn () => null), ['body' => '{"message":"Hello"}', 'headers' => ['message_id' => 'message-id']]];
    }

    public function testMappersDoNotSeeNonSendableStamps()
    {
        $seen = new \ArrayObject();
        $serializer = new InteropSerializer(Serializer::create(), null, [new class($seen) implements StampHeaderMapperInterface {
            public function __construct(
                private \ArrayObject $seen,
            ) {
            }

            public function toHeaders(Envelope $envelope): array
            {
                $this->seen->exchangeArray(array_keys($envelope->all()));

                return [];
            }

            public function toStamps(array $headers): array
            {
                return [];
            }
        }]);

        $serializer->encode(new Envelope(new DummyMessage('Hello'), [new ReceivedStamp('async'), TrustStamp::trusted(), new MessageIdStamp('message-id')]));

        $this->assertSame([MessageIdStamp::class], $seen->getArrayCopy());
    }

    public function testDecodeIgnoresNonSendableStampsReturnedByMappers()
    {
        $serializer = new InteropSerializer(Serializer::create(), null, [self::createMapper([], [new ReceivedStamp('forged'), TrustStamp::trusted(), new MessageIdStamp('message-id')])]);

        $envelope = $serializer->decode(['body' => '{"message":"Hello"}', 'headers' => ['type' => DummyMessage::class]]);

        $this->assertNull($envelope->last(ReceivedStamp::class));
        $this->assertNull($envelope->last(TrustStamp::class));
        $this->assertEquals([new MessageIdStamp('message-id')], $envelope->all(MessageIdStamp::class));
    }

    public function testNoMapperDisablesMapping()
    {
        $serializer = new InteropSerializer(Serializer::create(), null, []);

        $this->assertArrayNotHasKey('message_id', $serializer->encode(new Envelope(new DummyMessage('Hello'), [new MessageIdStamp('message-id')]))['headers']);
        $this->assertNull($serializer->decode(['body' => '{"message":"Hello"}', 'headers' => ['type' => DummyMessage::class, 'message_id' => 'message-id']])->last(MessageIdStamp::class));
    }

    private static function createMapper(array $headers = [], array $stamps = [], ?\ArrayObject $received = null): StampHeaderMapperInterface
    {
        return new class($headers, $stamps, $received) implements StampHeaderMapperInterface {
            public function __construct(
                private array $headers,
                private array $stamps,
                private ?\ArrayObject $received,
            ) {
            }

            public function toHeaders(Envelope $envelope): array
            {
                return $this->headers;
            }

            public function toStamps(array $headers): array
            {
                $this->received?->append($headers);

                return $this->stamps;
            }
        };
    }
}
