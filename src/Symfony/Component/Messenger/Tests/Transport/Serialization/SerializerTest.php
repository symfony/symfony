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
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\DecodedAwareTypeResolverInterface;
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\HeaderTypeResolver;
use Symfony\Component\Messenger\Transport\Serialization\TypeResolver\TypeResolverInterface;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface as SerializerComponentInterface;

class SerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new Serializer();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedWithStampsIsDecodable()
    {
        $serializer = new Serializer();

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']))
        ;

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedIsHavingTheBodyAndTypeHeader()
    {
        $serializer = new Serializer();

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

        $serializer = $this->getMockBuilder(SerializerComponent\SerializerInterface::class)->getMock();
        $serializer->expects($this->once())->method('serialize')->with($message, 'csv', ['foo' => 'bar', Serializer::MESSENGER_SERIALIZATION_CONTEXT => true])->willReturn('Yay');
        $serializer->expects($this->once())->method('deserialize')->with('Yay', DummyMessage::class, 'csv', ['foo' => 'bar', Serializer::MESSENGER_SERIALIZATION_CONTEXT => true])->willReturn($message);

        $encoder = new Serializer($serializer, 'csv', ['foo' => 'bar']);

        $encoded = $encoder->encode(new Envelope($message));
        $decoded = $encoder->decode($encoded);

        $this->assertSame('Yay', $encoded['body']);
        $this->assertSame($message, $decoded->getMessage());
    }

    public function testEncodedWithSymfonySerializerForStamps()
    {
        $serializer = new Serializer(
            $symfonySerializer = $this->createMock(SerializerComponentInterface::class)
        );

        $envelope = (new Envelope($message = new DummyMessage('test')))
            ->with($serializerStamp = new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with($validationStamp = new ValidationStamp(['foo', 'bar']));

        $symfonySerializer
            ->expects($this->exactly(3))
            ->method('serialize')
            ->withConsecutive(
                [$this->anything()],
                [$this->anything()],
                [$message, 'json', [
                    ObjectNormalizer::GROUPS => ['foo'],
                    Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
                ]]
            )
        ;

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.SerializerStamp::class, $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.ValidationStamp::class, $encoded['headers']);
    }

    public function testDecodeWithSymfonySerializerStamp()
    {
        $serializer = new Serializer(
            $symfonySerializer = $this->createMock(SerializerComponentInterface::class)
        );

        $symfonySerializer
            ->expects($this->exactly(2))
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
        $this->expectException(MessageDecodingFailedException::class);

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
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage($expectedMessage);

        $serializer = new Serializer();

        $serializer->decode($data);
    }

    public function getMissingKeyTests(): iterable
    {
        yield 'no_body' => [
            ['headers' => ['type' => 'bar']],
            'Encoded envelope should have at least a "body" and some "headers".',
        ];

        yield 'no_headers' => [
            ['body' => '{}'],
            'Encoded envelope should have at least a "body" and some "headers".',
        ];

        yield 'no_headers_type' => [
            ['body' => '{}', 'headers' => ['foo' => 'bar']],
            'Encoded envelope does not have a "type" header.',
        ];
    }

    public function testDecodingFailsWithBadClass()
    {
        $this->expectException(MessageDecodingFailedException::class);

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
        $this->assertStringNotContainsString('DummySymfonySerializerNonSendableStamp', print_r($encoded['headers'], true));
    }

    public function testDecodingFailedConstructorDeserialization()
    {
        $serializer = new Serializer();

        $this->expectException(MessageDecodingFailedException::class);

        $serializer->decode([
            'body' => '{}',
            'headers' => ['type' => DummySymfonySerializerInvalidConstructor::class],
        ]);
    }

    public function testDecodingStampFailedDeserialization()
    {
        $serializer = new Serializer();

        $this->expectException(MessageDecodingFailedException::class);

        $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => '[{}]',
            ],
        ]);
    }

    public function provideDecodingWithTypeResolver(): \Generator
    {
        $expectedInstance = new class('hello') {
            private $message;

            public function __construct(string $message)
            {
                $this->message = $message;
            }
        };

        $customTypeResolver = new class($expectedInstance) implements TypeResolverInterface {
            private $expectedInstance;

            public function __construct($expectedInstance)
            {
                $this->expectedInstance = $expectedInstance;
            }

            public function resolve(array $encodedEnvelope): string
            {
                if (('my-type-that-is-not-a-class' === $encodedEnvelope['headers']['type'] ?? null)
                    && 'my-app' === $encodedEnvelope['headers']['origin'] ?? null) {
                    return \get_class($this->expectedInstance);
                }

                throw new \Exception('Can\'t resolve type');
            }
        };

        $decodedAwareTypeResolver = new class($expectedInstance) implements DecodedAwareTypeResolverInterface {
            private $expectedInstance;

            public function __construct($expectedInstance)
            {
                $this->expectedInstance = $expectedInstance;
            }

            public function resolve(array $encodedEnvelope, array $body): string
            {
                if (\array_key_exists('message', $body)) {
                    return \get_class($this->expectedInstance);
                }

                throw new \Exception('Can\'t resolve type');
            }
        };

        yield 'implicit_header_type_resolver' => [[], [
            'headers' => ['type' => \get_class($expectedInstance)],
            'body' => '{"message":"hello"}',
        ], $expectedInstance, false];

        yield 'header_type_resolver_default_field' => [[Serializer::TYPE_RESOLVER => new HeaderTypeResolver()], [
            'headers' => ['type' => \get_class($expectedInstance)],
            'body' => '{"message":"hello"}',
        ], $expectedInstance, false];

        yield 'header_type_resolver_other_field' => [[Serializer::TYPE_RESOLVER => new HeaderTypeResolver('class')], [
            'headers' => ['class' => \get_class($expectedInstance)],
            'body' => '{"message":"hello"}',
        ], $expectedInstance, false];

        yield 'custom_type_resolver' => [[Serializer::TYPE_RESOLVER => $customTypeResolver], [
            'headers' => ['type' => 'my-type-that-is-not-a-class', 'origin' => 'my-app'],
            'body' => '{"message":"hello"}',
        ], $expectedInstance, false];

        yield 'decoded_aware_type_resolver' => [[Serializer::TYPE_RESOLVER => $decodedAwareTypeResolver], [
            'body' => '{"message":"hello"}',
        ], $expectedInstance, true];
    }

    /**
     * @dataProvider provideDecodingWithTypeResolver
     */
    public function testDecodingWithTypeResolver(array $serializerContext, array $encodedEnvelope, object $expectedInstance, bool $useFullSerializer)
    {
        $interfaces = SerializerComponentInterface::class;

        if ($useFullSerializer) {
            $interfaces = [
                $interfaces,
                SerializerComponent\Encoder\DecoderInterface::class,
                SerializerComponent\Normalizer\DenormalizerInterface::class,
            ];
        }

        $serializer = new Serializer(
            $symfonySerializer = $this->createMock($interfaces),
            'json',
            $serializerContext
        );

        $symfonySerializer
            ->method('deserialize')
            ->with('{"message":"hello"}', \get_class($expectedInstance), 'json', $serializerContext + [
                Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
            ])
            ->willReturn($expectedInstance);

        if ($useFullSerializer) {
            $symfonySerializer
                ->method('decode')
                ->with('{"message":"hello"}', 'json', $serializerContext + [
                        Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
                    ])
                ->willReturn(['message' => 'hello']);

            $symfonySerializer
                ->method('denormalize')
                ->with(['message' => 'hello'], \get_class($expectedInstance), 'json', $serializerContext + [
                        Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
                    ])
                ->willReturn($expectedInstance);
        }

        $envelope = $serializer->decode($encodedEnvelope);
        $message = $envelope->getMessage();

        $this->assertSame($expectedInstance, $message);
    }

    public function testUsingDecodedAwareTypeResolverWithoutSuitableSerializerShouldThrowException()
    {
        $decodedAwareTypeResolver = new class() implements DecodedAwareTypeResolverInterface {
            public function resolve(array $encodedEnvelope, array $body): string
            {
                throw new \Exception('Not implemented');
            }
        };

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage(sprintf(
            'A serializer implementing "%s" and "%s" is needed to use "%s".',
            SerializerComponent\Encoder\DecoderInterface::class,
            SerializerComponent\Normalizer\DenormalizerInterface::class,
            \get_class($decodedAwareTypeResolver)
        ));

        $serializer = new Serializer(
            $this->createMock(SerializerComponentInterface::class),
            'json',
            [Serializer::TYPE_RESOLVER => $decodedAwareTypeResolver]
        );

        $serializer->decode(['body' => '{"foo": "bar"}']);
    }

    public function testImproperTypeResolverShouldThrowException()
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage(sprintf(
            '"%s" must be an instance of "%s" or "%s", "%s" given.',
            Serializer::TYPE_RESOLVER,
            TypeResolverInterface::class,
            DecodedAwareTypeResolverInterface::class,
            \stdClass::class
        ));

        $serializer = new Serializer(
            $this->createMock(SerializerComponentInterface::class),
            'json',
            [Serializer::TYPE_RESOLVER => new \stdClass()]
        );

        $serializer->decode(['body' => '{"foo": "bar"}']);
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
