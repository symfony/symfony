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
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\IgbinarySerializer;

class IgbinarySerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = $this->createIgbinarySerializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $encoded = $serializer->encode($envelope);
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Encoded envelope should have at least a "body", or maybe you should implement your own serializer');

        $serializer = $this->createIgbinarySerializer();

        $serializer->decode([]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer = $this->createIgbinarySerializer();

        $serializer->decode([
            'body' => '{"message": "bar"}',
        ]);
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer = $this->createIgbinarySerializer();

        $serializer->decode([
            'body' => 'x',
        ]);
    }

    public function testDecodingFailsWithBadClass()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/class "ReceivedSt0mp" not found/');

        $serializer = $this->createIgbinarySerializer();

        $serializer->decode([
            // we need to represent serialized binary here in tests, so we use the help of base64
            'body' => base64_decode('AAAAAhcNUmVjZWl2ZWRTdDBtcBQA'),
        ]);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = $this->createIgbinarySerializer();

        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    protected function createIgbinarySerializer(): IgbinarySerializer
    {
        return new IgbinarySerializer();
    }
}
