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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\InteropSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

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

        $this->assertSame(DummyMessage::class, $serializer->getMessageType($encodedEnvelope));
        $this->assertEquals(new DummyMessage('Hello'), $serializer->decode($encodedEnvelope)->getMessage());
    }

    public function testDecodeResolvesTheMessageTypeWithTheGivenClosure()
    {
        $serializer = new InteropSerializer(Serializer::create(), static fn (array $encodedEnvelope) => str_contains($encodedEnvelope['body'], '"message"') ? DummyMessage::class : null);

        $this->assertSame(DummyMessage::class, $serializer->getMessageType(['body' => '{"message":"Hello"}']));
        $this->assertEquals(new DummyMessage('Hello'), $serializer->decode(['body' => '{"message":"Hello"}'])->getMessage());
        $this->assertNull($serializer->getMessageType(['body' => '{"other":"Hello"}']));
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

        $this->assertSame(DummyMessage::class, $serializer->getMessageType($encodedEnvelope));
        $this->assertEquals(new DummyMessage('Hello'), $serializer->decode($encodedEnvelope)->getMessage());
    }
}
