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
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface as SerializerComponentInterface;

class SerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new Serializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        self::assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedWithStampsIsDecodable()
    {
        $serializer = new Serializer();

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']))
        ;

        self::assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedIsHavingTheBodyAndTypeHeader()
    {
        $serializer = new Serializer();

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));

        self::assertArrayHasKey('body', $encoded);
        self::assertArrayHasKey('headers', $encoded);
        self::assertArrayHasKey('type', $encoded['headers']);
        self::assertSame(DummyMessage::class, $encoded['headers']['type']);
        self::assertSame('application/json', $encoded['headers']['Content-Type']);
    }

    public function testUsesTheCustomFormatAndContext()
    {
        $message = new DummyMessage('Foo');

        $serializer = self::createMock(SerializerComponent\SerializerInterface::class);
        $serializer->expects(self::once())->method('serialize')->with($message, 'csv', ['foo' => 'bar', Serializer::MESSENGER_SERIALIZATION_CONTEXT => true])->willReturn('Yay');
        $serializer->expects(self::once())->method('deserialize')->with('Yay', DummyMessage::class, 'csv', ['foo' => 'bar', Serializer::MESSENGER_SERIALIZATION_CONTEXT => true])->willReturn($message);

        $encoder = new Serializer($serializer, 'csv', ['foo' => 'bar']);

        $encoded = $encoder->encode(new Envelope($message));
        $decoded = $encoder->decode($encoded);

        self::assertSame('Yay', $encoded['body']);
        self::assertSame($message, $decoded->getMessage());
    }

    public function testEncodedWithSymfonySerializerForStamps()
    {
        $serializer = new Serializer(
            $symfonySerializer = self::createMock(SerializerComponentInterface::class)
        );

        $envelope = (new Envelope($message = new DummyMessage('test')))
            ->with($serializerStamp = new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with($validationStamp = new ValidationStamp(['foo', 'bar']));

        $symfonySerializer
            ->expects(self::exactly(3))
            ->method('serialize')
            ->withConsecutive(
                [self::anything()],
                [self::anything()],
                [$message, 'json', [
                    ObjectNormalizer::GROUPS => ['foo'],
                    Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
                ]]
            )
        ;

        $encoded = $serializer->encode($envelope);

        self::assertArrayHasKey('body', $encoded);
        self::assertArrayHasKey('headers', $encoded);
        self::assertArrayHasKey('type', $encoded['headers']);
        self::assertArrayHasKey('X-Message-Stamp-'.SerializerStamp::class, $encoded['headers']);
        self::assertArrayHasKey('X-Message-Stamp-'.ValidationStamp::class, $encoded['headers']);
    }

    public function testDecodeWithSymfonySerializerStamp()
    {
        $serializer = new Serializer(
            $symfonySerializer = self::createMock(SerializerComponentInterface::class)
        );

        $symfonySerializer
            ->expects(self::exactly(2))
            ->method('deserialize')
            ->withConsecutive(
                ['[{"context":{"groups":["foo"]}}]', SerializerStamp::class.'[]', 'json', [Serializer::MESSENGER_SERIALIZATION_CONTEXT => true]],
                ['{}', DummyMessage::class, 'json', [
                    ObjectNormalizer::GROUPS => ['foo'],
                    Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
                ]]
            )
            ->willReturnOnConsecutiveCalls(
                [new SerializerStamp(['groups' => ['foo']])],
                new DummyMessage('test')
            )
        ;

        $serializer->decode([
            'body' => '{}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => '[{"context":{"groups":["foo"]}}]',
            ],
        ]);
    }

    public function testDecodingFailsWithBadFormat()
    {
        self::expectException(MessageDecodingFailedException::class);

        $serializer = new Serializer();

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
        self::expectException(MessageDecodingFailedException::class);
        self::expectExceptionMessage($expectedMessage);

        $serializer = new Serializer();

        $serializer->decode($data);
    }

    public function getMissingKeyTests(): iterable
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
        self::expectException(MessageDecodingFailedException::class);

        $serializer = new Serializer();

        $serializer->decode([
            'body' => '{}',
            'headers' => ['type' => 'NonExistentClass'],
        ]);
    }

    public function testEncodedSkipsNonEncodeableStamps()
    {
        $serializer = new Serializer();

        $envelope = new Envelope(new DummyMessage('Hello'), [
            new DummySymfonySerializerNonSendableStamp(),
        ]);

        $encoded = $serializer->encode($envelope);
        self::assertStringNotContainsString('DummySymfonySerializerNonSendableStamp', print_r($encoded['headers'], true));
    }

    public function testDecodingFailedConstructorDeserialization()
    {
        $serializer = new Serializer();

        self::expectException(MessageDecodingFailedException::class);

        $serializer->decode([
            'body' => '{}',
            'headers' => ['type' => DummySymfonySerializerInvalidConstructor::class],
        ]);
    }

    public function testDecodingStampFailedDeserialization()
    {
        $serializer = new Serializer();

        self::expectException(MessageDecodingFailedException::class);

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
