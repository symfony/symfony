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
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Handler\RawMessage;
use Symfony\Component\Messenger\Stamp\ContentTypeStamp;
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
        $serializer = Serializer::create();

        $envelope = new Envelope(new DummyMessage('Hello'));

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedWithStampsIsDecodable()
    {
        $serializer = Serializer::create();

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ValidationStamp(['foo', 'bar']))
        ;

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
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

        $serializer = $this->getMockBuilder(SerializerComponent\SerializerInterface::class)->getMock();
        $serializer->expects($this->once())->method('serialize')->with($message, 'csv', ['foo' => 'bar'])->willReturn('Yay');
        $serializer->expects($this->once())->method('deserialize')->with('Yay', DummyMessage::class, 'csv', ['foo' => 'bar'])->willReturn($message);

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

        $symfonySerializer
            ->expects($this->at(0))
            ->method('serialize')->with(
                $message,
                'json',
                [
                    ObjectNormalizer::GROUPS => ['foo'],
                ]
            )
        ;

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.SerializerStamp::class, $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.ValidationStamp::class, $encoded['headers']);
    }

    public function testDecodeWithoutSymfonySerializerRaiseError()
    {
        $serializer = new Serializer(null, 'json');

        $this->expectException(LogicException::class);
        $serializer->decode([
            'body' => 'foo',
        ]);
    }

    public function testEncodeWithoutSymfonySerializerRaiseError()
    {
        $serializer = new Serializer(null, 'json');

        $this->expectException(LogicException::class);
        $serializer->encode(new Envelope('foo'));
    }

    public static function getPhpFormatAliases()
    {
        yield ['php'];
        yield [Serializer::CONTENT_TYPE_PHP_SERIALIZED];
    }

    /**
     * @dataProvider getPhpFormatAliases
     */
    public function testEncodeWithoutSymfonySerializerWorksWithPhpSerializer(string $phpFormat)
    {
        $serializer = new Serializer(null, $phpFormat);

        $encodedEnvelope = $serializer->encode(new Envelope('foo'));
        $this->assertArrayHasKey('Content-Type', $encodedEnvelope['headers']);
        $this->assertSame(Serializer::CONTENT_TYPE_PHP_SERIALIZED, $encodedEnvelope['headers']['Content-Type']);
        $this->assertStringEndsWith('message\\";s:3:\\"foo\\";}', $encodedEnvelope['body']);
    }

    /**
     * @dataProvider getPhpFormatAliases
     */
    public function testDecodeWithoutSymfonySerializerWorksWithPhpSerializer(string $phpFormat)
    {
        $serializer = new Serializer(null, $phpFormat);

        $envelope = $serializer->decode([
            'body' => 's:3:"foo";',
        ]);

        $this->assertSame('foo', $envelope->getMessage());
    }

    public function testDecodeWithoutSymfonySerializerReturnsRawMessage()
    {
        $serializer = new Serializer(null, 'json', [], true);

        $envelope = $serializer->decode([
            'body' => 'foo',
            'headers' => [
                'type' => 'sample.message',
                'Content-Type' => 'application/x-foo',
            ],
        ]);

        $message = $envelope->getMessage();

        $this->assertInstanceOf(RawMessage::class, $message);
        $this->assertSame($message->getData(), 'foo');
        $this->assertSame($message->getContentType(), 'application/x-foo');
        $this->assertSame($message->getType(), 'sample.message');
    } 

    public function testEncodeWithContentTypeStamp()
    {
        $serializer = new Serializer(
            $symfonySerializer = $this->createMock(SerializerComponentInterface::class),
            'json'
        );

        $envelope = (new Envelope($message = new DummyMessage('test')))
            ->with(new SerializerStamp([ObjectNormalizer::GROUPS => ['foo']]))
            ->with(new ContentTypeStamp('text/xml'))
            ->with(new ValidationStamp(['foo', 'bar']));

        $symfonySerializer
            ->expects($this->at(0))
            ->method('serialize')->with(
                $message,
                'xml',
                [
                    ObjectNormalizer::GROUPS => ['foo'],
                ]
            )
        ;

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('Content-Type', $encoded['headers']);
        $this->assertSame('text/xml', $encoded['headers']['Content-Type']);
    }

    public function testDecodeWithContentType()
    {
        $serializer = new Serializer(
            $symfonySerializer = $this->createMock(SerializerComponentInterface::class),
            'json'
        );

        $symfonySerializer
            ->expects($this->at(0))
            ->method('deserialize')->with(
                '<xml/>',
                DummyMessage::class,
                'xml',
                []
            )
            ->willReturn(new DummyMessage('test'))
        ;

        $serializer->decode([
            'body' => '<xml/>',
            'headers' => [
                'type' => DummyMessage::class,
                'Content-Type' => 'application/text+xml',
            ],
        ]);
    }

    public function testDecodeWithSymfonySerializerStamp()
    {
        $serializer = new Serializer(
            $symfonySerializer = $this->createMock(SerializerComponentInterface::class)
        );

        $symfonySerializer
            ->expects($this->at(0))
            ->method('deserialize')
            ->with('[{"context":{"groups":["foo"]}}]', SerializerStamp::class.'[]', 'json', [])
            ->willReturn([new SerializerStamp(['groups' => ['foo']])])
        ;

        $symfonySerializer
            ->expects($this->at(1))
            ->method('deserialize')->with(
                '{}',
                DummyMessage::class,
                'json',
                [
                    ObjectNormalizer::GROUPS => ['foo'],
                ]
            )
            ->willReturn(new DummyMessage('test'))
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

    public function getMissingKeyTests()
    {
        yield 'no_body' => [
            ['headers' => ['type' => 'bar']],
            'Encoded envelope should have at least a "body".',
        ];

        yield 'no_headers_type' => [
            ['body' => '{}', 'headers' => ['foo' => 'bar']],
            'Encoded envelope does not have a "type" header.',
        ];
    }

    public function testDecodingFailsWithBadClass()
    {
        $this->expectException(MessageDecodingFailedException::class);

        $serializer = Serializer::create();

        $serializer->decode([
            'body' => '{}',
            'headers' => ['type' => 'NonExistentClass'],
        ]);
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
}
class DummySymfonySerializerNonSendableStamp implements NonSendableStampInterface
{
}
