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

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SerializedMessageStamp;
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
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

    public function testDecodingFailsWithAStampHeaderThatIsNotAStamp()
    {
        $serializer = new Serializer();

        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage(\sprintf('Could not decode stamp: "%s" is not a "%s".', DummyMessage::class, StampInterface::class));

        $serializer->decode([
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.DummyMessage::class => '[{"message":"injected"}]',
            ],
        ]);
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
