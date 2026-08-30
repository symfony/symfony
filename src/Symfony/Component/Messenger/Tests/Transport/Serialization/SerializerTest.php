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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithInterfaceWithSerializedTypeName;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithSerializedTypeName;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;
use Symfony\Component\Serializer\SerializerInterface as SerializerComponentInterface;

class SerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new Serializer();

        $decodedEnvelope = $serializer->decode($serializer->encode(new Envelope(new DummyMessage('Hello'))));

        $this->assertEquals(new DummyMessage('Hello'), $decodedEnvelope->getMessage());
        $this->assertEquals(new SerializedMessageStamp('{"message":"Hello"}'), $decodedEnvelope->last(SerializedMessageStamp::class));
    }

    public function testEncodedWithStampsIsDecodable()
    {
        $serializer = new Serializer();

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']))
            ->with(new DeduplicateStamp('someKey', 42, true))
            ->with(new SerializedMessageStamp('{"message":"Hello"}'))
        ;

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testSerializedMessageStampIsUsedForEncoding()
    {
        $serializer = new Serializer();

        $encoded = $serializer->encode(
            new Envelope(new DummyMessage(''), [new SerializedMessageStamp('{"message":"Hello"}')])
        );

        $this->assertSame('{"message":"Hello"}', $encoded['body'] ?? null);
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

    public function testGetMessageType()
    {
        $serializer = new Serializer();

        $this->assertSame(DummyMessage::class, $serializer->getMessageType($serializer->encode(new Envelope(new DummyMessage('Hello')))));
        $this->assertNull($serializer->getMessageType(['body' => '{}']));
        $this->assertNull($serializer->getMessageType(['body' => '{}', 'headers' => []]));
    }

    public function testGetMessageTypeResolvesLogicalTypeViaTypeToClassMap()
    {
        $serializer = new Serializer(typeToClassMap: ['dummy.message' => DummyMessageWithSerializedTypeName::class]);

        $encoded = $serializer->encode(new Envelope(new DummyMessageWithSerializedTypeName('Hello')));

        $this->assertSame('dummy.message', $encoded['headers']['type']);
        $this->assertSame(DummyMessageWithSerializedTypeName::class, $serializer->getMessageType($encoded));
    }

    public function testUsesTheCustomFormatAndContext()
    {
        $message = new DummyMessage('Foo');

        $serializer = $this->createMock(SerializerComponentInterface::class);
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
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']));

        $series = [
            [$this->anything()],
            [$this->anything()],
            [$message, 'json', [
                ObjectNormalizer::GROUPS => ['foo'],
                Serializer::MESSENGER_SERIALIZATION_CONTEXT => true,
            ]],
        ];

        $symfonySerializer
            ->expects($this->exactly(3))
            ->method('serialize')
            ->willReturnCallback(function (...$args) use (&$series) {
                $expectedArgs = array_shift($series);

                if ($expectedArgs[0] instanceof Constraint) {
                    $expectedArgs[0]->evaluate($args);
                } else {
                    $this->assertSame($expectedArgs, $args);
                }

                return '{}';
            })
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

        $series = [
            [
                ['[{"context":{"groups":["foo"]}}]', SerializerStamp::class.'[]', 'json', [Serializer::MESSENGER_SERIALIZATION_CONTEXT => true]],
                [new SerializerStamp(['groups' => ['foo']])],
            ],
            [
                ['{}', DummyMessage::class, 'json', [ObjectNormalizer::GROUPS => ['foo'], Serializer::MESSENGER_SERIALIZATION_CONTEXT => true]],
                new DummyMessage('test'),
            ],
        ];

        $symfonySerializer
            ->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturnCallback(function (...$args) use (&$series) {
                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;

        $serializer->decode([
            'body' => '{}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => '[{"context":{"groups":["foo"]}}]',
            ],
        ]);
    }

    public function testStampsIgnoreTheAttributeSelectionOfTheContext()
    {
        if (!class_exists(AttributeLoader::class)) {
            $this->markTestSkipped('The "AttributeLoader" class from symfony/serializer 6.4 is required.');
        }

        $symfonySerializer = new SymfonySerializer([new ArrayDenormalizer(), new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()))], [new JsonEncoder()]);
        $serializer = new Serializer($symfonySerializer, 'json', [
            AbstractNormalizer::GROUPS => ['dummy'],
            AbstractNormalizer::ATTRIBUTES => ['message'],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['busName'],
        ]);

        $encoded = $serializer->encode(new Envelope(new DummySymfonySerializerGroupedMessage('Hello'), [new BusNameStamp('the_bus')]));

        $this->assertSame('{"message":"Hello"}', $encoded['body']);
        $this->assertSame('[{"busName":"the_bus"}]', $encoded['headers']['X-Message-Stamp-'.BusNameStamp::class]);
        $this->assertEquals([new BusNameStamp('the_bus')], $serializer->decode($encoded)->all(BusNameStamp::class));
    }

    public function testDecodingFailsWithBadFormat()
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode([
            'body' => '{foo',
            'headers' => ['type' => 'stdClass'],
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    #[DataProvider('getMissingKeyTests')]
    public function testDecodingFailsWithMissingKeys(array $data)
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode($data);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public static function getMissingKeyTests(): iterable
    {
        yield 'no_body' => [['headers' => ['type' => 'bar']]];
        yield 'no_headers' => [['body' => '{}']];
        yield 'no_headers_type' => [['body' => '{}', 'headers' => ['foo' => 'bar']]];
    }

    public function testDecodingFailsWithBadClass()
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode([
            'body' => '{}',
            'headers' => ['type' => 'NonExistentClass'],
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
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

        $envelope = $serializer->decode([
            'body' => '{}',
            'headers' => ['type' => DummySymfonySerializerInvalidConstructor::class],
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testDecodingStampFailedDeserialization()
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => '[{}]',
            ],
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public function testEncodeUsesTypeToClassMapForType()
    {
        $serializer = new Serializer(typeToClassMap: ['custom.type' => DummyMessage::class]);

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));

        $this->assertSame('custom.type', $encoded['headers']['type']);
    }

    public function testDecodeUsesTypeToClassMapForDeserialization()
    {
        $serializer = new Serializer(typeToClassMap: ['custom.type' => DummyMessage::class]);

        $decodedEnvelope = $serializer->decode([
            'body' => '{"message":"Hello"}',
            'headers' => ['type' => 'custom.type'],
        ]);

        $this->assertInstanceOf(DummyMessage::class, $decodedEnvelope->getMessage());
        $this->assertSame('Hello', $decodedEnvelope->getMessage()->getMessage());
    }

    public function testTypeToClassMapAliasesDecodeToSameClassWhileEncodeUsesCanonicalName()
    {
        $serializer = new Serializer(typeToClassMap: [
            'legacy.type' => DummyMessage::class,
            'current.type' => DummyMessage::class,
        ]);

        foreach (['legacy.type', 'current.type'] as $type) {
            $decoded = $serializer->decode(['body' => '{"message":"Hello"}', 'headers' => ['type' => $type]]);
            $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
        }

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));
        $this->assertSame('current.type', $encoded['headers']['type']);
    }

    public function testEncodeDecodeWithTypeToClassMap()
    {
        $serializer = new Serializer(typeToClassMap: ['custom.type' => DummyMessage::class]);

        $envelope = new Envelope(new DummyMessage('Hello'));
        $encoded = $serializer->encode($envelope);

        $this->assertSame('custom.type', $encoded['headers']['type']);

        $decodedEnvelope = $serializer->decode($encoded);

        $this->assertEquals(new DummyMessage('Hello'), $decodedEnvelope->getMessage());
    }

    public function testEncodeFallsBackToFqcnWhenNotInMap()
    {
        $serializer = new Serializer(typeToClassMap: ['other.type' => 'SomeOtherClass']);

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));

        $this->assertSame(DummyMessage::class, $encoded['headers']['type']);
    }

    public function testDecodeFallsBackToHeaderTypeWhenNotInMap()
    {
        $serializer = new Serializer(typeToClassMap: ['other.type' => 'SomeOtherClass']);

        $decodedEnvelope = $serializer->decode([
            'body' => '{"message":"Hello"}',
            'headers' => ['type' => DummyMessage::class],
        ]);

        $this->assertInstanceOf(DummyMessage::class, $decodedEnvelope->getMessage());
    }

    public function testEncodeUsesSerializedTypeNameFromAttribute()
    {
        $serializer = new Serializer();

        $encoded = $serializer->encode(new Envelope(new DummyMessageWithSerializedTypeName('Hello')));

        $this->assertSame('dummy.message', $encoded['headers']['type']);
    }

    public function testEncodeUsesSerializedTypeNameFromInterfaceAttribute()
    {
        $serializer = new Serializer();

        $encoded = $serializer->encode(new Envelope(new DummyMessageWithInterfaceWithSerializedTypeName('Hello')));

        $this->assertSame('dummy.interface.message', $encoded['headers']['type']);
    }

    public function testTypeToClassMapTakesPrecedenceOverAttribute()
    {
        $serializer = new Serializer(typeToClassMap: ['map.type' => DummyMessageWithSerializedTypeName::class]);

        $encoded = $serializer->encode(new Envelope(new DummyMessageWithSerializedTypeName('Hello')));

        $this->assertSame('map.type', $encoded['headers']['type']);
    }

    public function testEncodedWithAttributeIsDecodableWithMap()
    {
        // Encode with attribute detection (no map)
        $encoderSerializer = new Serializer();
        $encoded = $encoderSerializer->encode(new Envelope(new DummyMessageWithSerializedTypeName('Hello')));

        $this->assertSame('dummy.message', $encoded['headers']['type']);

        // Decode with map
        $decoderSerializer = new Serializer(typeToClassMap: ['dummy.message' => DummyMessageWithSerializedTypeName::class]);
        $decodedEnvelope = $decoderSerializer->decode($encoded);

        $this->assertInstanceOf(DummyMessageWithSerializedTypeName::class, $decodedEnvelope->getMessage());
        $this->assertSame('Hello', $decodedEnvelope->getMessage()->getMessage());
    }

    public function testDecodingFailureKeepsDecodedStampsOnTheWrappedEnvelope()
    {
        $serializer = new Serializer();

        // a payload whose stamps decode fine but whose body cannot be deserialized
        $encodedEnvelope = [
            'body' => '{}',
            'headers' => [
                'type' => DummyMessageWithPrivateConstructorProperty::class,
                'X-Message-Stamp-'.RedeliveryStamp::class => '[{"retryCount":2,"redeliveredAt":"2026-08-03T11:47:47+00:00"}]',
                'Content-Type' => 'application/json',
            ],
        ];

        $envelope = $serializer->decode($encodedEnvelope);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());

        $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
        $this->assertNotNull($redeliveryStamp, 'Stamps decoded from headers must be kept on the wrapper envelope, otherwise the retry counter resets on every redelivery.');
        $this->assertSame(2, $redeliveryStamp->getRetryCount());
    }

    public function testEncodingDecodeFailureWrapperReemitsOriginalPayload()
    {
        $serializer = new Serializer();

        $originalEncoded = [
            'body' => '{"some":"payload"}',
            'headers' => ['type' => 'App\NonExistentMessage', 'Content-Type' => 'application/json'],
        ];

        $wrapperEnvelope = $serializer->decode($originalEncoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $wrapperEnvelope->getMessage());

        $reEncoded = $serializer->encode($wrapperEnvelope->with(new RedeliveryStamp(1)));

        $this->assertSame($originalEncoded['body'], $reEncoded['body'], 'Re-sending a decode-failure wrapper must re-emit the original payload.');
        $this->assertSame('App\NonExistentMessage', $reEncoded['headers']['type'], 'The original type header must be preserved so a later decode can succeed after a fix.');
        $this->assertArrayHasKey('X-Message-Stamp-'.RedeliveryStamp::class, $reEncoded['headers'], 'Current stamps must be in the headers so the retry counter survives.');
    }

    public function testEncodingDecodeFailureWrapperWithoutOriginalHeaders()
    {
        $serializer = new Serializer();

        // PhpSerializer::encode() returns a body without any headers
        $envelope = new Envelope(new MessageDecodingFailedException('Could not decode message', 0, null, ['body' => 'a:0:{}']));

        $reEncoded = $serializer->encode($envelope);

        $this->assertSame('a:0:{}', $reEncoded['body']);
        $this->assertSame(MessageDecodingFailedException::class, $reEncoded['headers']['type']);
        $this->assertArrayNotHasKey('Content-Type', $reEncoded['headers'], 'The content type must not describe a body encoded by another serializer.');
    }

    public function testEncodingDecodeFailureWrapperWithoutOriginalBody()
    {
        $serializer = new Serializer();

        $envelope = new Envelope(new MessageDecodingFailedException('Could not decode message', 0, null, ['headers' => ['type' => 'App\NonExistentMessage']]));

        $reEncoded = $serializer->encode($envelope);

        $this->assertStringContainsString('Could not decode message', $reEncoded['body']);
        $this->assertSame(MessageDecodingFailedException::class, $reEncoded['headers']['type'], 'Without an original body, the headers must describe the body that was actually encoded.');
        $this->assertSame('application/json', $reEncoded['headers']['Content-Type']);
        $body = json_decode($reEncoded['body'], true);
        $this->assertArrayNotHasKey('trace', $body, 'The stack trace holds the arguments of every call and must be left out.');
        $this->assertArrayHasKey('traceAsString', $body);
    }

    public function testDecodingFailsWithAStampHeaderThatIsNotAStamp()
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.DummyMessage::class => '[{"message":"injected"}]',
            ],
        ]);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertSame(\sprintf('Could not decode stamp: "%s" is not a "%s".', DummyMessage::class, StampInterface::class), $envelope->getMessage()->getMessage());
    }

    public function testDecodedSkipsNonSendableStamps()
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.ReceivedStamp::class => '[{"transportName":"injected"}]',
                'X-Message-Stamp-'.BusNameStamp::class => '[{"busName":"the_bus"}]',
            ],
        ]);

        $this->assertNull($envelope->last(ReceivedStamp::class));
        $this->assertEquals([new BusNameStamp('the_bus')], $envelope->all(BusNameStamp::class));
    }

    public function testDecodedSerializerStampSkipsCodeAffectingContextOptions()
    {
        $serializer = new Serializer();

        $envelope = $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.SerializerStamp::class => json_encode([['context' => [
                    AbstractNormalizer::CALLBACKS => ['message' => [DummySymfonySerializerCallback::class, 'shout']],
                    AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => [DummySymfonySerializerCallback::class, 'shout'],
                    AbstractObjectNormalizer::MAX_DEPTH_HANDLER => [DummySymfonySerializerCallback::class, 'shout'],
                    XmlEncoder::LOAD_OPTIONS => \LIBXML_NOENT,
                    DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
                ]]]),
            ],
        ]);

        $this->assertSame('hello', $envelope->getMessage()->getMessage());
        $this->assertSame([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'], $envelope->last(SerializerStamp::class)->getContext());
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
class DummyMessageWithPrivateConstructorProperty
{
    public function __construct(private string $secret)
    {
    }
}

class DummySymfonySerializerGroupedMessage
{
    #[Groups(['dummy'])]
    public string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }
}
class DummySymfonySerializerCallback
{
    public static function shout(...$arguments): string
    {
        return strtoupper($arguments[0]);
    }
}
