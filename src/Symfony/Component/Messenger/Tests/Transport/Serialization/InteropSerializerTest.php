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
}
