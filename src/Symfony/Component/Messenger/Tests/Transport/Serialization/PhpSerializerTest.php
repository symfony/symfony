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
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class PhpSerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString("\0", $encoded['body'], 'Does not contain the binary characters');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        $serializer = $this->createPhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Encoded envelope should have at least a "body", or maybe you should implement your own serializer');

        $serializer->decode([]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        $serializer = $this->createPhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode([
            'body' => '{"message": "bar"}',
        ]);
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        $serializer = $this->createPhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode([
            'body' => 'x',
        ]);
    }

    public function testDecodingFailsWithBadClass()
    {
        $serializer = $this->createPhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/class "ReceivedSt0mp" not found/');

        $serializer->decode([
            'body' => 'O:13:"ReceivedSt0mp":0:{}',
        ]);
    }

    public function testDecodingFailsForPropertyTypeMismatch()
    {
        $serializer = $this->createPhpSerializer();
        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessage('true')));
        // Simulate a change of property type in the code base
        $encodedEnvelope['body'] = str_replace('s:4:\"true\"', 'b:1', $encodedEnvelope['body']);

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode($encodedEnvelope);
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummyPhpSerializerNonSendableStamp(),
        ]);

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = $this->createPhpSerializer();

        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        $this->assertTrue((bool) preg_match('//u', $encoded['body']), 'Encodes non-UTF8 payloads');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    protected function createPhpSerializer(): PhpSerializer
    {
        return new PhpSerializer();
    }
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
