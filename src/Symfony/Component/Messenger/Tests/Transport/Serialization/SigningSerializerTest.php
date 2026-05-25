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
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithSerializedTypeName;
use Symfony\Component\Messenger\Transport\Serialization\MessageTypeAwareSerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
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

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeRejectsInvalidSignature()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $envelope = new Envelope(new DummyMessage('hello'));
        $encoded = $serializer->encode($envelope);
        $encoded['headers']['Body-Sign'] = 'tampered';

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeDoesNotInvokeInnerSerializerWhenSignatureIsInvalid()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public bool $decoded = false;

            public function getMessageType(array $encodedEnvelope): ?string
            {
                return DummyMessage::class;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                $this->decoded = true;

                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'irrelevant'];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $envelope = $serializer->decode(['body' => 'irrelevant', 'headers' => ['Body-Sign' => 'tampered', 'Sign-Algo' => 'sha256']]);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());

        $this->assertFalse($inner->decoded, 'The inner serializer must not be invoked when a signed message has an invalid signature.');
    }

    public function testDecodeRejectsMissingSignatureBeforeInvokingTypeAwareInnerSerializer()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public bool $decoded = false;

            public function getMessageType(array $encodedEnvelope): ?string
            {
                return $encodedEnvelope['headers']['type'] ?? null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                $this->decoded = true;

                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'irrelevant', 'headers' => ['type' => $envelope->getMessage()::class]];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $envelope = $serializer->decode(['body' => 'irrelevant', 'headers' => ['type' => DummyMessage::class]]);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());

        $this->assertFalse($inner->decoded, 'A signed message arriving without a signature must be rejected before the inner serializer is invoked.');
    }

    public function testDecodeDoesNotRejectUnsignedTypeReportedByTypeAwareInnerSerializer()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public function getMessageType(array $encodedEnvelope): ?string
            {
                return $encodedEnvelope['headers']['type'] ?? null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'irrelevant', 'headers' => ['type' => $envelope->getMessage()::class]];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', []);

        // a stray (and here invalid) signature on a type that is not configured for signing is ignored
        $decoded = $serializer->decode(['body' => 'irrelevant', 'headers' => ['type' => DummyMessage::class, 'Body-Sign' => 'not-a-valid-signature', 'Sign-Algo' => 'sha256']]);
        $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
    }

    public function testDecodeRejectsMessageWithoutSignatureWhenTypeAwareInnerSerializerCannotDetermineType()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public bool $decoded = false;

            public function getMessageType(array $encodedEnvelope): ?string
            {
                return null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                $this->decoded = true;

                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'irrelevant'];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $envelope = $serializer->decode(['body' => 'irrelevant']);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());

        $this->assertFalse($inner->decoded, 'A message whose type cannot be determined and that carries no signature must not be decoded.');
    }

    public function testDecodeAcceptsValidSignatureRegardlessOfReportedType()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public function getMessageType(array $encodedEnvelope): ?string
            {
                return null; // not consulted: a valid signature is enough
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'the-body'];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $decoded = $serializer->decode([
            'body' => 'the-body',
            'headers' => ['Body-Sign' => hash_hmac('sha256', 'the-body', 'secret-key'), 'Sign-Algo' => 'sha256'],
        ]);
        $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
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

        // Encode with signature by using the SigningSerializer against a child instance
        $encoded = $serializer->encode(new Envelope(new ChildDummyMessage('child')));

        // Tamper by removing signature to ensure verification occurs for child type
        unset($encoded['headers']['Body-Sign']);

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeRejectsMissingSignatureForMessageWithSerializedTypeName()
    {
        $inner = new Serializer(typeToClassMap: ['dummy.message' => DummyMessageWithSerializedTypeName::class]);
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessageWithSerializedTypeName::class]);

        $encoded = $serializer->encode(new Envelope(new DummyMessageWithSerializedTypeName('hello')));

        $this->assertSame('dummy.message', $encoded['headers']['type']);
        $this->assertArrayHasKey('Body-Sign', $encoded['headers']);

        unset($encoded['headers']['Body-Sign'], $encoded['headers']['Sign-Algo']);

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeRejectsMissingSignatureWhenInnerSerializerUsesTypeToClassMap()
    {
        $inner = new Serializer(typeToClassMap: ['dummy.mapped' => DummyMessage::class]);
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));

        $this->assertSame('dummy.mapped', $encoded['headers']['type']);
        $this->assertArrayHasKey('Body-Sign', $encoded['headers']);

        unset($encoded['headers']['Body-Sign'], $encoded['headers']['Sign-Algo']);

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    private function createSerializer(array $signedTypes): SerializerInterface
    {
        return new SigningSerializer(new PhpSerializer(), 'secret-key', $signedTypes);
    }
}
