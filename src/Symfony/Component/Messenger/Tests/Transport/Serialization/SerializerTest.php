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
use Symfony\Component\Messenger\Middleware\Configuration\ValidationConfiguration;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerConfiguration;
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

        $envelope = Envelope::wrap(new DummyMessage('Hello'));

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedWithConfigurationIsDecodable()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = Envelope::wrap(new DummyMessage('Hello'))
            ->with(new SerializerConfiguration(array(ObjectNormalizer::GROUPS => array('foo'))))
            ->with(new ValidationConfiguration(array('foo', 'bar')))
        ;

        $this->assertEquals($envelope, $serializer->decode($serializer->encode($envelope)));
    }

    public function testEncodedIsHavingTheBodyAndTypeHeader()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $encoded = $serializer->encode(Envelope::wrap(new DummyMessage('Hello')));

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertArrayNotHasKey('X-Message-Envelope-Items', $encoded['headers']);
        $this->assertEquals(DummyMessage::class, $encoded['headers']['type']);
    }

    public function testUsesTheCustomFormatAndContext()
    {
        $message = new DummyMessage('Foo');

        $serializer = $this->getMockBuilder(SerializerComponent\SerializerInterface::class)->getMock();
        $serializer->expects($this->once())->method('serialize')->with($message, 'csv', array('foo' => 'bar'))->willReturn('Yay');
        $serializer->expects($this->once())->method('deserialize')->with('Yay', DummyMessage::class, 'csv', array('foo' => 'bar'))->willReturn($message);

        $encoder = new Serializer($serializer, 'csv', array('foo' => 'bar'));

        $encoded = $encoder->encode(Envelope::wrap($message));
        $decoded = $encoder->decode($encoded);

        $this->assertSame('Yay', $encoded['body']);
        $this->assertSame($message, $decoded->getMessage());
    }

    public function testEncodedWithSerializationConfiguration()
    {
        $serializer = new Serializer(
            new SerializerComponent\Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()))
        );

        $envelope = Envelope::wrap(new DummyMessage('Hello'))
            ->with(new SerializerConfiguration(array(ObjectNormalizer::GROUPS => array('foo'))))
            ->with(new ValidationConfiguration(array('foo', 'bar')))
        ;

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('body', $encoded);
        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('type', $encoded['headers']);
        $this->assertEquals(DummyMessage::class, $encoded['headers']['type']);
        $this->assertArrayHasKey('X-Message-Envelope-Items', $encoded['headers']);
        $this->assertSame('a:2:{s:75:"Symfony\Component\Messenger\Transport\Serialization\SerializerConfiguration";C:75:"Symfony\Component\Messenger\Transport\Serialization\SerializerConfiguration":59:{a:1:{s:7:"context";a:1:{s:6:"groups";a:1:{i:0;s:3:"foo";}}}}s:76:"Symfony\Component\Messenger\Middleware\Configuration\ValidationConfiguration";C:76:"Symfony\Component\Messenger\Middleware\Configuration\ValidationConfiguration":82:{a:2:{s:6:"groups";a:2:{i:0;s:3:"foo";i:1;s:3:"bar";}s:17:"is_group_sequence";b:0;}}}', $encoded['headers']['X-Message-Envelope-Items']);
    }
}
