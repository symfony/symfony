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
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class SerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = Serializer::create();

        $decodedEnvelope = $serializer->decode($serializer->encode(new Envelope(new DummyMessage('Hello'))));

        $this->assertEquals(new DummyMessage('Hello'), $decodedEnvelope->getMessage());
        $this->assertEquals(new SerializedMessageStamp('{"message":"Hello"}'), $decodedEnvelope->last(SerializedMessageStamp::class));
    }

    public function testEncodedWithStampsIsDecodable()
    {
        $serializer = Serializer::create();

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']))
            ->with(new SerializedMessageStamp('{"message":"Hello"}'))
        ;

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testSerializedMessageStampIsUsedForEncoding()
    {
        $serializer = Serializer::create();

        $encoded = $serializer->encode(
            new Envelope(new DummyMessage(''), [new SerializedMessageStamp('{"message":"Hello"}')])
        );

        $this->assertSame('{"message":"Hello"}', $encoded['body'] ?? null);
    }

    public function testEncodedIsHavingTheBodyAndTypeHeader()
    {
        $serializer = Serializer::create();

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertSame(DummyMessage::class, $encoded['headers']['type']);
        $this->assertSame('application/json', $encoded['headers']['Content-Type']);
    }

    public function testUsesTheCustomFormatAndContext()
    {
        $message = new DummyMessage('Foo');
        $decodedMessage = ['message' => 'foo'];
        $context = ['foo' => 'bar', Serializer::MESSENGER_SERIALIZATION_CONTEXT => true];

        // TODO: On PhpUnit 10, replace by $this->createMockForIntersectionOfInterfaces([...])
        $serializer = $this->createMock(IntersectionSerializer::class);
        $serializer
            ->expects($this->once())
            ->method('serialize')
            ->with($message, 'csv', $context)
            ->willReturn('Yay');
        $serializer
            ->expects($this->once())
            ->method('decode')
            ->with('Yay', 'csv', $context)
            ->willReturn($decodedMessage);
        $serializer
            ->expects($this->once())
            ->method('denormalize')
            ->with($decodedMessage, DummyMessage::class, 'csv', $context)
            ->willReturn($message);

        $encoder = new Serializer($serializer, 'csv', ['foo' => 'bar']);

        $encoded = $encoder->encode(new Envelope($message));
        $this->assertSame('Yay', $encoded['body']);

        $decoded = $encoder->decode($encoded);
        $this->assertSame($message, $decoded->getMessage());
    }

    public function testEncodedWithSymfonySerializerForStamps()
    {
        $message = new DummyMessage('test');
        $envelope = (new Envelope($message))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']));

        // TODO: On PhpUnit 10, replace by $this->createMockForIntersectionOfInterfaces([...])
        $serializer = $this->createMock(IntersectionSerializer::class);
        $serializer
            ->expects($this->exactly(3))
            ->method('serialize')
            ->willReturnCallback(function (...$args) use ($message) {
                static $constraints = null;
                $constraints ??= [
                    $this->anything(),
                    $this->anything(),
                    $this->equalTo([
                        $message,
                        'json',
                        [
                            ObjectNormalizer::GROUPS => ['foo'],
                            Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
                        ],
                    ]),
                ];

                array_shift($constraints)->evaluate($args);

                return '{}';
            });

        $encoded = (new Serializer($serializer))->encode($envelope);

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.SerializerStamp::class, $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.ValidationStamp::class, $encoded['headers']);
    }

    public function testDecodeWithSymfonySerializerStamp()
    {
        // TODO: On PhpUnit 10, replace by $this->createMockForIntersectionOfInterfaces([...])
        $serializer = $this->createMock(IntersectionSerializer::class);
        $serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                '[{"context":{"groups":["foo"]}}]',
                SerializerStamp::class.'[]',
                'json',
                [Serializer::MESSENGER_SERIALIZATION_CONTEXT => true],
            )
            ->willReturn([new SerializerStamp(['groups' => ['foo']])]);
        $serializer
            ->expects($this->once())
            ->method('decode')
            ->with(
                '{}',
                'json',
                [ObjectNormalizer::GROUPS => ['foo'], Serializer::MESSENGER_SERIALIZATION_CONTEXT => true],
            )
            ->willReturn(['message' => 'message']);
        $serializer
            ->expects($this->once())
            ->method('denormalize')
            ->with(
                ['message' => 'message'],
                DummyMessage::class,
                'json',
                [ObjectNormalizer::GROUPS => ['foo'], Serializer::MESSENGER_SERIALIZATION_CONTEXT => true],
            )
            ->willReturn(new DummyMessage('message'));

        (new Serializer($serializer))->decode([
            'body' => '{}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => '[{"context":{"groups":["foo"]}}]',
            ],
        ]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        $this->expectException(MessageDecodingFailedException::class);

        $serializer = Serializer::create();

        $serializer->decode([
            'body' => '{foo',
            'headers' => ['type' => 'stdClass'],
        ]);
    }

    /**
     * @dataProvider getMissingKeyTests
     */
    public function testDecodingFailsWithMissingKeys(array $data, string $expectedMessage)
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage($expectedMessage);

        $serializer = Serializer::create();

        $serializer->decode($data);
    }

    public static function getMissingKeyTests(): iterable
    {
        yield 'no_body' => [
            ['headers' => ['type' => 'bar']],
            'Encoded envelope should have at least a "body" and some "headers", or maybe you should implement your own serializer.',
        ];

        yield 'no_headers' => [
            ['body' => '{}'],
            'Encoded envelope should have at least a "body" and some "headers", or maybe you should implement your own serializer.',
        ];

        yield 'no_headers_type' => [
            ['body' => '{}', 'headers' => ['foo' => 'bar']],
            'Encoded envelope does not have a "type" header.',
        ];
    }

    public function testDecodingFailsWithBadClass()
    {
        $envelope = ['body' => '{}', 'headers' => ['type' => 'NonExistentClass']];
        $envelope = Serializer::create()->decode($envelope);

        $stamp = $envelope->last(MessageDecodingFailedStamp::class);
        $message = $envelope->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedStamp::class, $stamp);
        $this->assertInstanceOf(\stdClass::class, $message);
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $serializer = Serializer::create();

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummySymfonySerializerNonSendableStamp(),
        ]);

        $encoded = $serializer->encode($envelope);
        $this->assertStringNotContainsString('DummySymfonySerializerNonSendableStamp', print_r($encoded['headers'], true));
    }

    public function testDecodingFailedConstructorDeserialization()
    {
        $envelope = [
            'body' => '{}',
            'headers' => ['type' => DummySymfonySerializerInvalidConstructor::class],
        ];
        $envelope = Serializer::create()->decode($envelope);

        $stamp = $envelope->last(MessageDecodingFailedStamp::class);
        $message = $envelope->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedStamp::class, $stamp);
        $this->assertInstanceOf(\stdClass::class, $message);
    }

    public function testDecodingStampFailedDeserialization()
    {
        $serializer = Serializer::create();

        $this->expectException(MessageDecodingFailedException::class);

        $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => '[{}]',
            ],
        ]);
    }
}
class DummySymfonySerializerNonSendableStamp implements NonSendableStampInterface
{
}
class DummySymfonySerializerInvalidConstructor
{
    public function __construct(string $missingArgument)
    {
    }
}

abstract class IntersectionSerializer implements DecoderInterface, DenormalizerInterface, SerializerInterface
{
}
