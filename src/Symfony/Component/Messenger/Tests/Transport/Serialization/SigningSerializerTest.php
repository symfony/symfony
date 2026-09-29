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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageEnum;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageWithSerializedTypeName;
use Symfony\Component\Messenger\Transport\Serialization\MessageTypeAwareSerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;

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

    public function testDecodeRejectsNonStringSignature()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $envelope = new Envelope(new DummyMessage('hello'));
        $encoded = $serializer->encode($envelope);
        $encoded['headers']['Body-Sign'] = [$encoded['headers']['Body-Sign']];

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
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

    public function testDecodeDoesNotRejectUnknownTypeWhenNoMessageTypeRequiresSignature()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public function getMessageType(array $encodedEnvelope): ?string
            {
                return null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'irrelevant'];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', []);

        $decoded = $serializer->decode(['body' => 'irrelevant']);

        $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
    }

    public function testDecodePassesHeadersThroughWhenNoMessageTypeRequiresSignature()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public array $receivedHeaders = [];

            public function getMessageType(array $encodedEnvelope): ?string
            {
                return null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                $this->receivedHeaders = $encodedEnvelope['headers'] ?? [];

                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'irrelevant'];
            }
        };

        $serializer = new SigningSerializer($inner, 'secret-key', []);

        $decoded = $serializer->decode(['body' => 'irrelevant', 'headers' => ['Body-Sign' => 'not-a-valid-signature', 'Sign-Algo' => 'sha256']]);

        $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
        $this->assertSame(['Body-Sign' => 'not-a-valid-signature', 'Sign-Algo' => 'sha256'], $inner->receivedHeaders);
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

        $decoded = $serializer->decode($serializer->encode(new Envelope(new DummyMessage('hello'))));
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

    public function testDecodeRejectsATamperedTypeHeader()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));
        $encoded['headers']['type'] = ChildDummyMessage::class;

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeRejectsAGraftedStampHeader()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));
        $encoded['headers']['X-Message-Stamp-'.BusNameStamp::class] = '[{"busName":"other_bus"}]';

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeIgnoresHeadersThatDoNotDescribeTheMessage()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));
        $encoded['headers']['Content-Type'] = 'application/x-something-else';
        $encoded['headers']['x-death'] = 'added by the broker';

        $decoded = $serializer->decode($encoded);

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
    }

    public function testDecodeAcceptsSignedHeadersInAnyOrder()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello'), [new BusNameStamp('the_bus')]));
        $encoded['headers'] = array_reverse($encoded['headers'], true);

        $decoded = $serializer->decode($encoded);

        $this->assertSame('the_bus', $decoded->last(BusNameStamp::class)->getBusName());
    }

    public function testEncodeMarksTheSignatureAsCoveringTheHeaders()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);

        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));

        $this->assertStringStartsWith('v2:', $encoded['headers']['Body-Sign']);
    }

    public function testDecodeAcceptsABodyOnlySignature()
    {
        $inner = new Serializer();
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $decoded = $serializer->decode($this->signBodyOnly($inner->encode(new Envelope(new DummyMessage('hello')))));

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
    }

    public function testDecodeAcceptsTamperedHeadersOnABodyOnlySignature()
    {
        $inner = new Serializer();
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);
        $encoded = $this->signBodyOnly($inner->encode(new Envelope(new DummyMessage('hello'))));
        $encoded['headers']['type'] = ChildDummyMessage::class;

        // messages signed before the headers were covered keep working, headers included
        $decoded = $serializer->decode($encoded);

        $this->assertInstanceOf(ChildDummyMessage::class, $decoded->getMessage());
    }

    public function testDecodeRejectsASignatureStrippedOfItsMarker()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));
        $encoded['headers']['Body-Sign'] = substr($encoded['headers']['Body-Sign'], \strlen('v2:'));
        $encoded['headers']['type'] = ChildDummyMessage::class;

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeRejectsAHeaderCoveringSignatureReplayedAsBodyOnly()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));

        // the signed payload is built from public data: replay it as the body of a message bearing a body-only signature
        $forged = [
            'body' => serialize([$encoded['body'], ['type' => $encoded['headers']['type']]]),
            'headers' => [
                'type' => ChildDummyMessage::class,
                'Body-Sign' => substr($encoded['headers']['Body-Sign'], \strlen('v2:')),
            ],
        ];

        $envelope = $serializer->decode($forged);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    #[DataProvider('provideEnumCasesCarriedBySignedMessages')]
    public function testDecodeRejectsMissingSignatureWithoutAutoloadingTheEnumsTheBodyCarries(string $search, string $replace)
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $body = str_replace($search, $replace, serialize(new Envelope(new DummyMessage('hello'))));
        $requested = [];
        $autoloader = static function (string $class) use (&$requested) {
            $requested[] = $class;
        };
        spl_autoload_register($autoloader, true, true);

        try {
            $envelope = $serializer->decode(['body' => addslashes($body), 'headers' => []]);
        } finally {
            spl_autoload_unregister($autoloader);
        }

        $this->assertNotContains('Unknown\Missing\EnumName', $requested);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame(\sprintf('Message "%s" requires a signature but none was found.', DummyMessage::class), $envelope->getMessage()->getPrevious()->getMessage());
    }

    public static function provideEnumCasesCarriedBySignedMessages(): iterable
    {
        yield 'in the stamps' => ['a:0:{}', 'a:1:{i:0;E:33:"Unknown\Missing\EnumName:CaseName";}'];
        yield 'in a property of the message' => ['s:5:"hello"', 'E:33:"Unknown\Missing\EnumName:CaseName"'];
    }

    #[DataProvider('provideUnsignedMessagesOfClassesThatDoNotExist')]
    public function testDecodeRejectsUnsignedMessageOfAClassThatDoesNotExist(string $message)
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $body = str_replace('O:8:"stdClass":0:{}', $message, serialize(new Envelope(new \stdClass())));

        $envelope = $serializer->decode(['body' => addslashes($body), 'headers' => []]);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
    }

    public static function provideUnsignedMessagesOfClassesThatDoNotExist(): iterable
    {
        yield 'object' => ['O:25:"Unknown\Missing\ClassName":0:{}'];
        yield 'enum case' => ['E:33:"Unknown\Missing\EnumName:CaseName";'];
    }

    public function testDecodeRejectsUnsignedEnumCaseOfASignedType()
    {
        $serializer = $this->createSerializer([DummyMessageEnum::class]);
        $case = DummyMessageEnum::class.':A';
        $body = str_replace('O:8:"stdClass":0:{}', 'E:'.\strlen($case).':"'.$case.'";', serialize(new Envelope(new \stdClass())));

        $envelope = $serializer->decode(['body' => addslashes($body), 'headers' => []]);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame(\sprintf('Message "%s" requires a signature but none was found.', DummyMessageEnum::class), $envelope->getMessage()->getPrevious()->getMessage());
    }

    public function testDecodeFailureOfASignedMessageCarriesTheSignedEnvelope()
    {
        $encoded = $this->createJsonSerializer([DummyMessage::class], ['dummy' => DummyMessage::class])->encode(new Envelope(new DummyMessage('hello'), [new BusNameStamp('the_bus')]));

        $envelope = $this->createJsonSerializer([DummyMessage::class])->decode($encoded);
        $failure = $envelope->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame($encoded, $failure->encodedEnvelope);
        $this->assertStringStartsWith('Could not decode message: ', $failure->getMessage());
        $this->assertInstanceOf(SerializerExceptionInterface::class, $failure->getPrevious());
        $this->assertSame('the_bus', $envelope->last(BusNameStamp::class)?->getBusName());

        $decoded = $this->createJsonSerializer([DummyMessage::class], ['dummy' => DummyMessage::class])->decode($failure->encodedEnvelope);

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
    }

    public function testDecodeFailureThrownByTheInnerSerializerCarriesTheSignedEnvelope()
    {
        $inner = new class implements SerializerInterface {
            public function decode(array $encodedEnvelope): Envelope
            {
                throw new MessageDecodingFailedException('Cannot decode.');
            }

            public function encode(Envelope $envelope): array
            {
                return ['body' => 'the-body'];
            }
        };
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);
        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));

        $failure = $serializer->decode($encoded)->getMessage();

        $this->assertSame('Cannot decode.', $failure->getMessage());
        $this->assertSame($encoded, $failure->encodedEnvelope);
    }

    public function testEncodeSignsTheDecodeFailureOfASignedMessage()
    {
        $fixedSerializer = $this->createJsonSerializer([DummyMessage::class], ['dummy' => DummyMessage::class]);
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $failed = $serializer->decode($fixedSerializer->encode(new Envelope(new DummyMessage('hello'), [new BusNameStamp('the_bus')])));

        $encoded = $serializer->encode($failed->with(new SentToFailureTransportStamp('async'), new DelayStamp(0), new RedeliveryStamp(0)));

        $failure = $serializer->decode($encoded)->getMessage();
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame($encoded, $failure->encodedEnvelope);

        $decoded = $fixedSerializer->decode($encoded);

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
        $this->assertSame('the_bus', $decoded->last(BusNameStamp::class)?->getBusName());
        $this->assertSame('async', $decoded->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertSame(0, $decoded->last(RedeliveryStamp::class)?->getRetryCount());
    }

    #[DataProvider('provideUnverifiedMessages')]
    public function testEncodeDoesNotSignTheDecodeFailureOfAnUnverifiedMessage(array $encodedEnvelope)
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class, \Throwable::class]);
        $failed = $serializer->decode($encodedEnvelope);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failed->getMessage());

        $encoded = $serializer->encode($failed->with(new DelayStamp(1000), new RedeliveryStamp(1)));

        $this->assertArrayNotHasKey('Body-Sign', $encoded['headers']);
        $this->assertArrayNotHasKey('Sign-Algo', $encoded['headers']);

        $envelope = $serializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public static function provideUnverifiedMessages(): iterable
    {
        $forged = ['body' => '{"message":"forged"}', 'headers' => ['type' => DummyMessage::class]];

        yield 'without signature' => [$forged];
        yield 'with an invalid signature' => [['headers' => $forged['headers'] + ['Body-Sign' => 'v2:'.str_repeat('0', 64), 'Sign-Algo' => 'sha256']] + $forged];
        yield 'signed with another key' => [(new SigningSerializer(new Serializer(), 'another-key', [DummyMessage::class]))->encode(new Envelope(new DummyMessage('forged')))];
    }

    public function testEncodeDoesNotSignADecodeFailureWhenNoMessageTypeRequiresSignature()
    {
        $signed = $this->createJsonSerializer([DummyMessage::class])->encode(new Envelope(new DummyMessage('hello')));
        $failure = new Envelope(new MessageDecodingFailedException('Cannot decode.', 0, null, $signed), [new BusNameStamp('other_bus')]);

        $encoded = $this->createJsonSerializer([])->encode($failure);

        $envelope = $this->createJsonSerializer([DummyMessage::class])->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testDecodeRejectsAnUnsignedDecodeFailureThatCarriesASignedEnvelope()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);
        $wrapper = [
            'body' => json_encode(['message' => 'Cannot decode.', 'code' => 0, 'previous' => null, 'encodedEnvelope' => $serializer->encode(new Envelope(new DummyMessage('hello')))]),
            'headers' => ['type' => MessageDecodingFailedException::class, 'X-Message-Stamp-'.BusNameStamp::class => '[{"busName":"other_bus"}]'],
        ];

        $envelope = $serializer->decode($wrapper);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame($wrapper, $envelope->getMessage()->encodedEnvelope);
        $this->assertNull($envelope->last(BusNameStamp::class));
    }

    public function testDecodeFailureOfASignedMessageCanBeReplayedWithPhpSerializer()
    {
        $serializer = $this->createSerializer([DummyMessage::class]);
        $encoded = $this->sign(['body' => addslashes(str_replace('s:5:"hello";', 'O:25:"Unknown\Missing\ClassName":0:{}', serialize(new Envelope(new DummyMessage('hello')))))]);

        $failed = $serializer->decode($encoded);

        $this->assertSame($encoded, $failed->getMessage()->encodedEnvelope);

        $envelope = $serializer->decode($serializer->encode($failed->with(new DelayStamp(1000), new RedeliveryStamp(1))));
        $failure = $envelope->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $failure);
        $this->assertSame($encoded, $failure->encodedEnvelope);
        $this->assertSame(1, $envelope->last(RedeliveryStamp::class)?->getRetryCount());

        $replayed = $serializer->decode($failure->encodedEnvelope)->getMessage();

        $this->assertInstanceOf(MessageDecodingFailedException::class, $replayed);
        $this->assertSame('Could not decode Envelope: Message class "Unknown\Missing\ClassName" not found during decoding.', $replayed->getMessage());
    }

    private function createSerializer(array $signedTypes): SerializerInterface
    {
        return new SigningSerializer(new PhpSerializer(), 'secret-key', $signedTypes);
    }

    private function createJsonSerializer(array $signedTypes, array $typeToClassMap = []): SerializerInterface
    {
        return new SigningSerializer(new Serializer(null, 'json', [], $typeToClassMap), 'secret-key', $signedTypes);
    }

    private function sign(array $encoded): array
    {
        $inner = new class($encoded) implements SerializerInterface {
            public function __construct(
                private array $encoded,
            ) {
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                throw new \BadMethodCallException();
            }

            public function encode(Envelope $envelope): array
            {
                return $this->encoded;
            }
        };

        return (new SigningSerializer($inner, 'secret-key', [DummyMessage::class]))->encode(new Envelope(new DummyMessage('hello')));
    }

    private function signBodyOnly(array $encoded): array
    {
        $encoded['headers']['Body-Sign'] = hash_hmac('sha256', $encoded['body'] ?? '', 'secret-key');
        $encoded['headers']['Sign-Algo'] = 'sha256';

        return $encoded;
    }
}
