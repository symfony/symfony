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
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $encoded = $serializer->encode($envelope);
        self::assertStringNotContainsString("\0", $encoded['body'], 'Does not contain the binary characters');
        self::assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        self::expectException(MessageDecodingFailedException::class);
        self::expectExceptionMessage('Encoded envelope should have at least a "body", or maybe you should implement your own serializer');

        $serializer = new PhpSerializer();

        $serializer->decode([]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        self::expectException(MessageDecodingFailedException::class);
        self::expectExceptionMessageMatches('/Could not decode/');

        $serializer = new PhpSerializer();

        $serializer->decode([
            'body' => '{"message": "bar"}',
        ]);
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        self::expectException(MessageDecodingFailedException::class);
        self::expectExceptionMessageMatches('/Could not decode/');

        $serializer = new PhpSerializer();

        $serializer->decode([
            'body' => 'x',
        ]);
    }

    public function testDecodingFailsWithBadClass()
    {
        self::expectException(MessageDecodingFailedException::class);
        self::expectExceptionMessageMatches('/class "ReceivedSt0mp" not found/');

        $serializer = new PhpSerializer();

        $serializer->decode([
            'body' => 'O:13:"ReceivedSt0mp":0:{}',
        ]);
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummyPhpSerializerNonSendableStamp(),
        ]);

        $encoded = $serializer->encode($envelope);
        self::assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        self::assertTrue((bool) preg_match('//u', $encoded['body']), 'Encodes non-UTF8 payloads');
        self::assertEquals($envelope, $serializer->decode($encoded));
    }
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
