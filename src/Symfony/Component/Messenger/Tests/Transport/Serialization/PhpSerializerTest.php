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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageTyped;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class PhpSerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString("\0", $encoded['body'], 'Does not contain the binary characters');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    public function testDecodingFailsWithMissingBodyKey()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Encoded envelope should have at least a "body", or maybe you should implement your own serializer');

        $serializer->decode([]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode([
            'body' => '{"message": "bar"}',
        ]);
    }

    public function testDecodingFailsWithBadBase64Body()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode([
            'body' => 'x',
        ]);
    }

    public function testDecodingFailsWithBadClass()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/class "ReceivedSt0mp" not found/');

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
        $this->assertStringNotContainsString('DummyPhpSerializerNonSendableStamp', $encoded['body']);
    }

    public function testDecodingSkipsNonSendableStamps()
    {
        $serializer = new PhpSerializer();

        $envelope = $serializer->decode(['body' => addslashes(serialize(new Envelope(new DummyMessage('Hello'), [
            new BusNameStamp('a'),
            new DummyPhpSerializerNonSendableStamp(),
            new DelayStamp(1),
            new BusNameStamp('b'),
        ])))]);

        $this->assertEquals(new Envelope(new DummyMessage('Hello'), [new BusNameStamp('a'), new DelayStamp(1), new BusNameStamp('b')]), $envelope);
        $this->assertSame([BusNameStamp::class, DelayStamp::class], array_keys($envelope->all()));
    }

    public function testDecodingFailsWhenAStampIsFiledUnderAnotherClass()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Could not decode Envelope: Cannot unserialize '.Envelope::class);

        $serializer->decode(['body' => addslashes(self::serializeEnvelope([
            'stamps' => [BusNameStamp::class => [new BusNameStamp('a'), new DummyPhpSerializerNonSendableStamp()]],
            'message' => new DummyMessage('Hello'),
        ]))]);
    }

    public function testDecodingFailsWithMalformedEnvelopes()
    {
        $serializer = new PhpSerializer();

        foreach ([
            'stamps that are not an array' => ['stamps' => 'foo', 'message' => new DummyMessage('Hello')],
            'stamps that are not in arrays' => ['stamps' => [BusNameStamp::class => new BusNameStamp('a')], 'message' => new DummyMessage('Hello')],
            'an empty array of stamps' => ['stamps' => [BusNameStamp::class => []], 'message' => new DummyMessage('Hello')],
            'a stamp that is not a stamp' => ['stamps' => [DummyMessage::class => [new DummyMessage('Hello')]], 'message' => new DummyMessage('Hello')],
            'no message' => ['stamps' => []],
            'a message that is not an object' => ['stamps' => [], 'message' => 'Hello'],
        ] as $case => $properties) {
            try {
                $serializer->decode(['body' => addslashes(self::serializeEnvelope($properties))]);
                $this->fail(sprintf('Decoding an envelope with %s should fail.', $case));
            } catch (MessageDecodingFailedException $e) {
                $this->assertStringStartsWith('Could not decode Envelope: ', $e->getMessage(), $case);
            }
        }
    }

    public function testDecodingFailsWhenThePayloadIsNotAnEnvelope()
    {
        $serializer = new PhpSerializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Could not decode message into an Envelope.');

        $serializer->decode(['body' => addslashes(serialize(new DummyMessage('Hello')))]);
    }

    public function testNonUtf8IsBase64Encoded()
    {
        $serializer = new PhpSerializer();

        $envelope = new Envelope(new DummyMessage("\xE9"));

        $encoded = $serializer->encode($envelope);
        $this->assertTrue((bool) preg_match('//u', $encoded['body']), 'Encodes non-UTF8 payloads');
        $this->assertEquals($envelope, $serializer->decode($encoded));
    }

    /**
     * @requires PHP 7.4
     */
    public function testDecodingFailsForPropertyTypeMismatch()
    {
        $serializer = new PhpSerializer();
        $encodedEnvelope = $serializer->encode(new Envelope(new DummyMessageTyped('true')));
        // Simulate a change of property type in the code base
        $encodedEnvelope['body'] = str_replace('s:4:\"true\"', 'b:1', $encodedEnvelope['body']);

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessageMatches('/Could not decode/');

        $serializer->decode($encodedEnvelope);
    }

    private static function serializeEnvelope(array $properties): string
    {
        $serializedProperties = '';

        foreach ($properties as $name => $value) {
            $serializedProperties .= serialize("\0".Envelope::class."\0".$name).serialize($value);
        }

        return sprintf('O:%d:"%s":%d:{%s}', \strlen(Envelope::class), Envelope::class, \count($properties), $serializedProperties);
    }
}

class DummyPhpSerializerNonSendableStamp implements NonSendableStampInterface
{
}
