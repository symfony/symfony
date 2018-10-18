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
use Symfony\Component\Messenger\Stamp\SerializerStamp;
use Symfony\Component\Messenger\Stamp\ValidationStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Serializer as SerializerComponent;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class SerializerTest extends TestCase
{
    public function testEncodedIsDecodable()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = new Envelope(new DummyMessage('Hello'));

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedWithStampsIsDecodable()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with(new SerializerStamp(array(ObjectNormalizer::GROUPS => array('foo'))))
            ->with(new ValidationStamp(array('foo', 'bar')))
        ;

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedIsHavingTheBodyAndTypeHeader()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $encoded = $serializer->encode(new Envelope(new DummyMessage('Hello')));

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertEquals(DummyMessage::class, $encoded['headers']['type']);
    }

    public function testUsesTheCustomFormatAndContext()
    {
        $message = new DummyMessage('Foo');

        $serializer = $this->getMockBuilder(SerializerComponent\SerializerInterface::class)->getMock();
        $serializer->expects($this->once())->method('serialize')->with($message, 'csv', array('foo' => 'bar'))->willReturn('Yay');
        $serializer->expects($this->once())->method('deserialize')->with('Yay', DummyMessage::class, 'csv', array('foo' => 'bar'))->willReturn($message);

        $encoder = new Serializer($serializer, 'csv', array('foo' => 'bar'));

        $encoded = $encoder->encode(new Envelope($message));
        $decoded = $encoder->decode($encoded);

        $this->assertSame('Yay', $encoded['body']);
        $this->assertSame($message, $decoded->getMessage());
    }

    public function testEncodedWithSymfonySerializerForStamps()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder())),
            'json',
            array()
        );

        $envelope = (new Envelope(new DummyMessage('Hello')))
            ->with($serializerStamp = new SerializerStamp(array(ObjectNormalizer::GROUPS => array('foo'))))
            ->with($validationStamp = new ValidationStamp(array('foo', 'bar')))
        ;

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.SerializerStamp::class, $encoded['headers']);
        $this->assertArrayHasKey('X-Message-Stamp-'.ValidationStamp::class, $encoded['headers']);

        $decoded = $serializer->decode($encoded);

        $this->assertEquals($serializerStamp, $decoded->get(SerializerStamp::class));
        $this->assertEquals($validationStamp, $decoded->get(ValidationStamp::class));
    }
}
