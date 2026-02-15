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
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;

class SigningSerializerTest extends TestCase
{
    public function testEncodeAddsSignatureHeadersWhenTypeIsSigned()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $envelope = new Envelope(new DummyMessage('hello'));

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('Body-Sign', $encoded['headers']);
        $this->assertArrayHasKey('Sign-Algo', $encoded['headers']);
        $this->assertSame('sha256', $encoded['headers']['Sign-Algo']);
        $this->assertNotEmpty($encoded['headers']['Body-Sign']);
    }

    public function testEncodeDoesNotAddSignatureForUnsignedType()
    {
        $serializer = $this->createSerializer([]);
        $envelope = new Envelope(new DummyMessage('hello'));

        $encoded = $serializer->encode($envelope);

        $this->assertArrayNotHasKey('headers', $encoded);
    }

    public function testDecodeAcceptsValidSignature()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $envelope = new Envelope(new DummyMessage('hello'));
        $encoded = $serializer->encode($envelope);

        $decoded = $serializer->decode($encoded);
        $this->assertInstanceOf(Envelope::class, $decoded);
        $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
    }

    public function testDecodeRejectsMissingSignature()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $inner = new PhpSerializer();
        $envelope = new Envelope(new DummyMessage('hello'));
        $encoded = $inner->encode($envelope);

        $this->expectException(InvalidMessageSignatureException::class);
        $serializer->decode($encoded);
    }

    public function testDecodeRejectsInvalidSignature()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $envelope = new Envelope(new DummyMessage('hello'));
        $encoded = $serializer->encode($envelope);
        $encoded['headers']['Body-Sign'] = 'tampered';

        $this->expectException(InvalidMessageSignatureException::class);
        $serializer->decode($encoded);
    }

    public function testEncodeSignsWhenSignedTypeIsInterfaceImplementedByMessage()
    {
        $serializer = $this->createSerializer([DummyMessageInterface::class]);
        $envelope = new Envelope(new DummyMessage('hello'));

        $encoded = $serializer->encode($envelope);

        $this->assertArrayHasKey('headers', $encoded);
        $this->assertArrayHasKey('Body-Sign', $encoded['headers']);
        $this->assertArrayHasKey('Sign-Algo', $encoded['headers']);
    }

    public function testDecodeVerifiesWhenSignedTypeIsParentClassOfMessage()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $inner = new PhpSerializer();

        // Encode with signature by using the SigningSerializer against a child instance
        $encoded = $serializer->encode(new Envelope(new ChildDummyMessage('child')));

        // Tamper by removing signature to ensure verification occurs for child type
        unset($encoded['headers']['Body-Sign']);

        $this->expectException(InvalidMessageSignatureException::class);
        $serializer->decode($encoded);
    }

    private function createSerializer(array $signedTypes): SerializerInterface
    {
        return new SigningSerializer(new PhpSerializer(), 'secret-key', $signedTypes);
    }
}
