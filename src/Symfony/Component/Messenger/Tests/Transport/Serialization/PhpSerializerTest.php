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
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class PhpSerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new PhpSerializer();
        $envelope = new Envelope(new DummyMessage());

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString("\0", $encoded['body'], 'Does not contain binary characters');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodedFailedIsEncodable()
    {
        $serializer = new PhpSerializer();

        $envelope = $serializer->encode(new Envelope(new DummyMessage()));
        $envelope['body'] = str_replace('NestedDummyMessage', 'NestedOupsyMessage', $envelope['body']);
        $envelope = $serializer->decode($envelope);
        $redecodedEnvelope = $serializer->decode($serializer->encode($envelope));

        $this->assertEquals($envelope->getMessage(), $redecodedEnvelope->getMessage());
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $envelope = new Envelope(new DummyMessage(), [new DummyPhpSerializerNonSendableStamp()]);
        $encoded = (new PhpSerializer())->encode($envelope);

        $this->assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = new PhpSerializer();
        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        $this->assertTrue((bool) preg_match('//u', $encoded['body']), 'Encodes non-UTF8 payloads');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Encoded envelope should have at least a "body", or maybe you should implement your own serializer');

        (new PhpSerializer())->decode([]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        (new PhpSerializer())->decode(['body' => '{"message": "bar"}']);
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        (new PhpSerializer())->decode(['body' => 'x']);
    }

    public function testDecodingFailsWithBadClass()
    {
        $serializer = new PhpSerializer();

        $envelope = $serializer->encode(new Envelope(new DummyMessage()));
        $envelope['body'] = str_replace('NestedDummyMessage', 'NestedOupsyMessage', $envelope['body']);

        $envelope = $serializer->decode($envelope);
        $stamp = $envelope->last(MessageDecodingFailedStamp::class);
        $message = $envelope->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedStamp::class, $stamp);
        $this->assertStringContainsString(NestedOupsyMessage::class, $stamp->getMessage());
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $message);
    }

    public function testDecodingFailsWithBadData()
    {
        $serializer = new PhpSerializer();

        $envelope = $serializer->encode(new Envelope(new DummyMessage()));
        $envelope['body'] = str_replace(
            's:7:\"message\";s:7:\"message\";',
            's:7:\"message\";'.serialize(5),
            $envelope['body'],
        );

        $envelope = $serializer->decode($envelope);
        $stamp = $envelope->last(MessageDecodingFailedStamp::class);
        $message = $envelope->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedStamp::class, $stamp);
        $this->assertStringContainsString('Cannot assign int', $stamp->getMessage());
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $message);
    }
}

class DummyMessage
{
    public function __construct(
        public readonly string $message = 'message',
        public readonly NestedDummyMessage $nested = new NestedDummyMessage(),
    ) {
    }
}

class NestedDummyMessage
{
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
