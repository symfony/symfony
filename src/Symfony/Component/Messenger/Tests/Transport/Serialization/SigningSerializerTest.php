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
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\InvalidMessageSignatureException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\RedispatchStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
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

        $decoded = $serializer->decode(self::signBodyOnly($inner->encode(new Envelope(new DummyMessage('hello')))));

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
    }

    public function testDecodeAcceptsTamperedHeadersOnABodyOnlySignature()
    {
        $inner = new Serializer();
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);
        $encoded = self::signBodyOnly($inner->encode(new Envelope(new DummyMessage('hello'))));
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
        $serializer = $this->createSerializer([DummyMessage::class]);
        $wrapper = (new PhpSerializer())->encode(new Envelope(new MessageDecodingFailedException('Cannot decode.', 0, null, $serializer->encode(new Envelope(new DummyMessage('hello')))), [new BusNameStamp('other_bus')]));

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

    public function testSignAllSignsEveryMessage()
    {
        $serializer = $this->createSignAllSerializer(new Serializer());

        $encoded = $serializer->encode(new Envelope(new DummyMessage('hello')));

        $this->assertStringStartsWith('v2:', $encoded['headers']['Body-Sign']);
        $this->assertSame('sha256', $encoded['headers']['Sign-Algo']);

        $decoded = $serializer->decode($encoded);

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
        $this->assertTrue($decoded->last(TrustStamp::class)?->isTrusted());
    }

    public function testWildcardAmongMessageTypesRequiresASignatureForEveryMessage()
    {
        $serializer = new SigningSerializer(new PhpSerializer(), 'secret-key', [DummyMessage::class, '*']);

        $this->assertStringStartsWith('v2:', $serializer->encode(new Envelope(new \stdClass()))['headers']['Body-Sign']);

        $envelope = $serializer->decode((new PhpSerializer())->encode(new Envelope(new \stdClass())));

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertSame('The message requires a signature but none was found.', $envelope->getMessage()->getMessage());
    }

    #[DataProvider('provideMessagesRefusedByATransportThatSignsEverything')]
    public function testSignAllRefusesAMessageWithoutReadingIt(array $encodedEnvelope, string $expectedError)
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public array $calls = [];

            public function getMessageType(array $encodedEnvelope): ?string
            {
                $this->calls[] = __FUNCTION__;

                return $encodedEnvelope['headers']['type'] ?? null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                $this->calls[] = __FUNCTION__;

                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                throw new \BadMethodCallException();
            }
        };
        $serializer = $this->createSignAllSerializer($inner);
        $requested = [];
        $autoloader = static function (string $class) use (&$requested) {
            $requested[] = $class;
        };
        spl_autoload_register($autoloader, true, true);

        try {
            $envelope = $serializer->decode($encodedEnvelope);
        } finally {
            spl_autoload_unregister($autoloader);
        }

        $this->assertSame([], preg_grep('/^Unknown\\\\/', $requested));
        $this->assertSame([], $inner->calls);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame($expectedError, $envelope->getMessage()->getMessage());
        $this->assertSame($encodedEnvelope, $envelope->getMessage()->encodedEnvelope);
        $this->assertSame([], $envelope->all());
    }

    public static function provideMessagesRefusedByATransportThatSignsEverything(): iterable
    {
        $message = [
            'body' => '{"message":"forged"}',
            'headers' => [
                'type' => 'Unknown\Missing\MessageClass',
                'X-Message-Stamp-Unknown\Missing\StampClass' => '[{}]',
                'X-Message-Stamp-'.BusNameStamp::class => '[{"busName":"the_bus"}]',
            ],
        ];
        $signed = self::signWith($message, 'secret-key');
        $signedWithAnotherKey = self::signWith($message, 'another-key');

        yield 'without signature' => [$message, 'The message requires a signature but none was found.'];
        yield 'with a signature that is not a string' => [['headers' => ['Body-Sign' => [$signed['headers']['Body-Sign']]] + $message['headers']] + $message, 'The message requires a signature but none was found.'];
        yield 'signed with another key' => [$signedWithAnotherKey, 'Invalid message signature.'];
        yield 'with a tampered type' => [['headers' => ['type' => 'Unknown\Missing\OtherClass'] + $signed['headers']] + $signed, 'Invalid message signature.'];
        yield 'with a body-only signature' => [['headers' => ['Body-Sign' => hash_hmac('sha256', $message['body'], 'secret-key'), 'Sign-Algo' => 'sha256'] + $message['headers']] + $message, 'The signature of the message does not cover its headers.'];
        yield 'with another algorithm' => [['headers' => ['Sign-Algo' => 'md5'] + $signedWithAnotherKey['headers']] + $signedWithAnotherKey, 'Expected "sha256" signature algorithm, "md5" given.'];
        yield 'signed as unverified' => [self::signWith($message, 'secret-key', false), 'The message is signed as unverified: only a failure transport accepts it.'];
    }

    public function testSignAllRefusesAnUnsignedDecodeFailureThatCarriesASignedEnvelope()
    {
        $serializer = $this->createSignAllSerializer(new PhpSerializer());
        $wrapper = (new PhpSerializer())->encode(new Envelope(new MessageDecodingFailedException('Cannot decode.', 0, null, $serializer->encode(new Envelope(new DummyMessage('hello')))), [new BusNameStamp('other_bus')]));

        $envelope = $serializer->decode($wrapper);

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame($wrapper, $envelope->getMessage()->encodedEnvelope);
        $this->assertSame([], $envelope->all());
    }

    #[DataProvider('provideEnvelopesSignedAsUnverifiedUnlessTrusted')]
    public function testSignAllSignsEveryEnvelopeAndMarksTheOnesItDidNotVerify(\Closure $createEnvelope, bool $expectVerified)
    {
        $serializer = $this->createSignAllSerializer(new Serializer());

        $encoded = $serializer->encode($createEnvelope($serializer));

        $this->assertStringStartsWith('v2:', $encoded['headers']['Body-Sign']);
        $this->assertSame('sha256', $encoded['headers']['Sign-Algo']);
        $this->assertSame($expectVerified ? null : 'unverified', $encoded['headers']['Sign-Trust'] ?? null);
    }

    public static function provideEnvelopesSignedAsUnverifiedUnlessTrusted(): iterable
    {
        yield 'dispatched in process' => [static fn () => new Envelope(new DummyMessage('hello')), true];
        yield 'verified on receipt' => [static fn (SerializerInterface $serializer) => $serializer->decode($serializer->encode(new Envelope(new DummyMessage('hello'))))->with(new ReceivedStamp('async')), true];
        yield 'verified on receipt, then redispatched' => [static fn (SerializerInterface $serializer) => $serializer->decode($serializer->encode(new Envelope(new DummyMessage('hello')))), true];
        yield 'received without verification' => [static fn () => (new Serializer())->decode((new Serializer())->encode(new Envelope(new DummyMessage('hello'))))->with(new ReceivedStamp('async')), false];
        yield 'redispatched without verification' => [static fn () => new Envelope(new DummyMessage('hello'), [TrustStamp::untrusted()]), false];
        yield 'received with a forged trust stamp' => [static fn (SerializerInterface $serializer) => new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async'), unserialize(serialize($serializer->decode($serializer->encode(new Envelope(new DummyMessage('hello'))))->last(TrustStamp::class)))]), false];
        yield 'verified by a transport that signs per message type' => [static fn () => (new SigningSerializer(new Serializer(), 'secret-key', [DummyMessage::class]))->decode((new SigningSerializer(new Serializer(), 'secret-key', [DummyMessage::class]))->encode(new Envelope(new DummyMessage('hello'))))->with(new ReceivedStamp('async')), true];
        yield 'received with a body-only signature' => [static fn () => (new SigningSerializer(new Serializer(), 'secret-key', [DummyMessage::class]))->decode(self::signBodyOnly((new Serializer())->encode(new Envelope(new DummyMessage('hello')))))->with(new ReceivedStamp('async')), false];
        yield 'received signed as unverified by a failure transport' => [static fn () => self::createSignAllFailureSerializer()->decode(self::signWith((new Serializer())->encode(new Envelope(new DummyMessage('hello'))), 'secret-key', false))->with(new ReceivedStamp('failed')), false];
        yield 'created in process and received from a transport that trusts it' => [static fn () => new Envelope(new DummyMessage('hello'), [new ReceivedStamp('scheduler_default'), TrustStamp::trusted()]), true];
        yield 'received from a transport that redispatches it without trusting it' => [static fn () => new Envelope(new DummyMessage('hello'), [new ReceivedStamp('scheduler_default'), new RedispatchStamp()]), false];
        yield 'redispatched after a failed verification' => [static fn () => new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async'), TrustStamp::untrusted(), new RedispatchStamp()]), false];
    }

    #[DataProvider('provideDecodeFailuresSignedAsUnverifiedUnlessTheirEnvelopeWasVerified')]
    public function testSignAllSignsEveryDecodeFailureAndMarksTheOnesWhoseEnvelopeItDidNotVerify(array $carriedEnvelope, bool $expectVerified)
    {
        $serializer = $this->createSignAllSerializer(new Serializer());

        $encoded = $serializer->encode(new Envelope(new MessageDecodingFailedException('Cannot decode.', 0, null, $carriedEnvelope)));

        $this->assertStringStartsWith('v2:', $encoded['headers']['Body-Sign']);
        $this->assertSame($expectVerified ? null : 'unverified', $encoded['headers']['Sign-Trust'] ?? null);
    }

    public static function provideDecodeFailuresSignedAsUnverifiedUnlessTheirEnvelopeWasVerified(): iterable
    {
        $encoded = (new Serializer())->encode(new Envelope(new DummyMessage('hello')));

        yield 'signed' => [self::signWith($encoded, 'secret-key'), true];
        yield 'unsigned' => [$encoded, false];
        yield 'signed with another key' => [self::signWith($encoded, 'another-key'), false];
        yield 'with a body-only signature' => [self::signBodyOnly($encoded), false];
        yield 'signed as unverified' => [self::signWith($encoded, 'secret-key', false), false];
    }

    #[DataProvider('provideMessagesRefusedBySignature')]
    public function testDecodeFailureOfAMessageRefusedForItsSignatureIsNeverSigned(SerializerInterface $serializer, array $encodedEnvelope)
    {
        $refused = $serializer->decode($encodedEnvelope);
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $refused->getMessage()->getPrevious());

        $failureSerializer = self::createSignAllFailureSerializer();
        $encoded = $failureSerializer->encode($refused->with(new SentToFailureTransportStamp('async'), new DelayStamp(0), new RedeliveryStamp(0)));

        $this->assertArrayNotHasKey('Body-Sign', $encoded['headers'] ?? []);
        $this->assertArrayNotHasKey('Sign-Algo', $encoded['headers'] ?? []);
        $this->assertArrayNotHasKey('Sign-Trust', $encoded['headers'] ?? []);

        $envelope = $failureSerializer->decode($encoded);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public static function provideMessagesRefusedBySignature(): iterable
    {
        foreach (['JSON' => new Serializer(), 'PHP' => new PhpSerializer()] as $format => $inner) {
            $encoded = $inner->encode(new Envelope(new DummyMessage('hello')));

            yield $format.', unsigned, by a transport that signs every message' => [new SigningSerializer($inner, 'secret-key', ['*']), $encoded];
            yield $format.', signed as unverified, by a transport that signs every message' => [new SigningSerializer($inner, 'secret-key', ['*']), self::signWith($encoded, 'secret-key', false)];
            yield $format.', unsigned, by a transport that signs the message type' => [new SigningSerializer($inner, 'secret-key', [DummyMessage::class]), $encoded];
            yield $format.', signed as unverified, by a transport that signs the message type' => [new SigningSerializer($inner, 'secret-key', [DummyMessage::class]), self::signWith($encoded, 'secret-key', false)];
        }
    }

    public function testSignAllFailureTransportAcceptsAMessageSignedAsUnverified()
    {
        $encoded = $this->createSignAllSerializer(new Serializer())->encode(new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async')]));

        $envelope = self::createSignAllFailureSerializer()->decode($encoded);

        $this->assertSame('hello', $envelope->getMessage()->getMessage());
        $this->assertNotNull($trust = $envelope->last(TrustStamp::class));
        $this->assertFalse($trust->isTrusted());
    }

    public function testSignAllFailureTransportAcceptsAVerifiedMessageAsVerified()
    {
        $encoded = $this->createSignAllSerializer(new Serializer())->encode(new Envelope(new DummyMessage('hello')));

        $this->assertTrue(self::createSignAllFailureSerializer()->decode($encoded)->last(TrustStamp::class)?->isTrusted());
    }

    public function testSignAllFailureTransportRefusesAMessageSignedAsUnverifiedWhoseTypeRequiresASignature()
    {
        $inner = new class implements SerializerInterface, MessageTypeAwareSerializerInterface {
            public array $calls = [];

            public function getMessageType(array $encodedEnvelope): ?string
            {
                $this->calls[] = __FUNCTION__;

                return $encodedEnvelope['headers']['type'] ?? null;
            }

            public function decode(array $encodedEnvelope): Envelope
            {
                $this->calls[] = __FUNCTION__;

                return new Envelope(new DummyMessage('hello'));
            }

            public function encode(Envelope $envelope): array
            {
                throw new \BadMethodCallException();
            }
        };
        $encoded = self::signWith((new Serializer())->encode(new Envelope(new DummyMessage('hello'))), 'secret-key', false);

        $envelope = (new SigningSerializer($inner, 'secret-key', ['*', DummyMessage::class], 'sha256', true))->decode($encoded);

        $this->assertSame(['getMessageType'], $inner->calls);
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame(\sprintf('Message "%s" requires a verified signature, but it is signed as unverified.', DummyMessage::class), $envelope->getMessage()->getMessage());
    }

    #[DataProvider('provideSigningSerializersThatAcceptMessagesSignedAsUnverified')]
    public function testAddingOrRemovingTheUnverifiedMarkInvalidatesTheSignature(array $signedMessageTypes)
    {
        $serializer = new SigningSerializer(new Serializer(), 'secret-key', $signedMessageTypes, 'sha256', true);
        $verified = $serializer->encode(new Envelope(new DummyMessage('hello')));
        $unverified = $serializer->encode(new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async')]));

        $this->assertArrayNotHasKey('Sign-Trust', $verified['headers']);
        $this->assertSame('unverified', $unverified['headers']['Sign-Trust']);
        $this->assertTrue($serializer->decode($verified)->last(TrustStamp::class)?->isTrusted());

        $marked = $verified;
        $marked['headers']['Sign-Trust'] = 'unverified';
        $unmarked = $unverified;
        unset($unmarked['headers']['Sign-Trust']);
        $remarked = $unverified;
        $remarked['headers']['Sign-Trust'] = 'verified';

        foreach ([$marked, $unmarked, $remarked] as $tampered) {
            $envelope = $serializer->decode($tampered);

            $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
            $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        }
    }

    public static function provideSigningSerializersThatAcceptMessagesSignedAsUnverified(): iterable
    {
        yield 'signing every message' => [['*']];
        yield 'signing the message type' => [[DummyMessage::class]];
    }

    public function testMessageSignedBeforeTheUnverifiedMarkExistedCountsAsVerified()
    {
        $encoded = [
            'body' => '{"message":"hello"}',
            'headers' => [
                'type' => DummyMessage::class,
                'X-Message-Stamp-'.BusNameStamp::class => '[{"busName":"the_bus"}]',
                'Content-Type' => 'application/json',
                'Body-Sign' => 'v2:2848ec5ccbd264b23a447faa609df7def2eb2f1ff3fad580f26630025da2c3a0',
                'Sign-Algo' => 'sha256',
            ],
        ];

        foreach ([$this->createSignAllSerializer(new Serializer()), self::createSignAllFailureSerializer(), $this->createJsonSerializer([DummyMessage::class])] as $serializer) {
            $envelope = $serializer->decode($encoded);

            $this->assertSame('hello', $envelope->getMessage()->getMessage());
            $this->assertSame('the_bus', $envelope->last(BusNameStamp::class)?->getBusName());
            $this->assertTrue($envelope->last(TrustStamp::class)?->isTrusted());
        }
    }

    #[DataProvider('provideEnvelopesOfASignedTypeSignedAsUnverifiedUnlessTrusted')]
    public function testEncodeMarksAMessageOfASignedTypeAsUnverifiedUnlessTrusted(Envelope $envelope, bool $expectVerified)
    {
        $encoded = $this->createJsonSerializer([DummyMessage::class])->encode($envelope);

        $this->assertStringStartsWith('v2:', $encoded['headers']['Body-Sign']);
        $this->assertSame($expectVerified ? null : 'unverified', $encoded['headers']['Sign-Trust'] ?? null);
    }

    public static function provideEnvelopesOfASignedTypeSignedAsUnverifiedUnlessTrusted(): iterable
    {
        yield 'dispatched in process' => [new Envelope(new DummyMessage('hello')), true];
        yield 'verified on receipt' => [new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async'), TrustStamp::trusted()]), true];
        yield 'received without verification' => [new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async')]), false];
        yield 'redispatched without verification' => [new Envelope(new DummyMessage('hello'), [TrustStamp::untrusted()]), false];
        yield 'created in process and received from a transport that trusts it' => [new Envelope(new DummyMessage('hello'), [new ReceivedStamp('scheduler_default'), TrustStamp::trusted()]), true];
        yield 'received from a transport that redispatches it without trusting it' => [new Envelope(new DummyMessage('hello'), [new ReceivedStamp('scheduler_default'), new RedispatchStamp()]), false];
    }

    public function testEncodeDoesNotSignAMessageOfAnUnsignedTypeEvenWhenUnverified()
    {
        $encoded = $this->createJsonSerializer([DummyMessage::class])->encode(new Envelope(new DummyMessageWithSerializedTypeName('hello'), [new ReceivedStamp('async'), TrustStamp::untrusted()]));

        $this->assertArrayNotHasKey('Body-Sign', $encoded['headers']);
        $this->assertArrayNotHasKey('Sign-Trust', $encoded['headers']);
    }

    public function testDecodeRefusesAMessageSignedAsUnverifiedWhoseTypeRequiresASignature()
    {
        $serializer = $this->createJsonSerializer([DummyMessage::class]);

        $envelope = $serializer->decode($serializer->encode(new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async')])));

        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
        $this->assertSame(\sprintf('Message "%s" requires a verified signature, but it is signed as unverified.', DummyMessage::class), $envelope->getMessage()->getMessage());
    }

    public function testDecodeAcceptsAMessageSignedAsUnverifiedWhoseTypeDoesNotRequireASignature()
    {
        $encoded = $this->createSignAllSerializer(new Serializer())->encode(new Envelope(new DummyMessage('hello'), [new ReceivedStamp('async')]));

        $envelope = $this->createJsonSerializer([ChildDummyMessage::class])->decode($encoded);

        $this->assertSame('hello', $envelope->getMessage()->getMessage());
        $this->assertNotNull($trust = $envelope->last(TrustStamp::class));
        $this->assertFalse($trust->isTrusted());
    }

    public function testDecodeMarksOnlyASignatureThatCoversTheHeadersAsVerified()
    {
        $inner = new Serializer();
        $serializer = new SigningSerializer($inner, 'secret-key', [DummyMessage::class]);

        $this->assertTrue($serializer->decode($serializer->encode(new Envelope(new DummyMessage('hello'))))->last(TrustStamp::class)?->isTrusted());
        $this->assertNull($serializer->decode(self::signBodyOnly($inner->encode(new Envelope(new DummyMessage('hello')))))->last(TrustStamp::class));
    }

    public function testEncodeSignsWithTheFirstOfSeveralKeys()
    {
        $envelope = new Envelope(new DummyMessage('hello'));

        $encoded = (new SigningSerializer(new Serializer(), ['new-key', 'old-key'], [DummyMessage::class]))->encode($envelope);

        $this->assertSame((new SigningSerializer(new Serializer(), 'new-key', [DummyMessage::class]))->encode($envelope), $encoded);
    }

    public function testDecodeAcceptsASignatureFromAnyOfSeveralKeys()
    {
        $serializer = new SigningSerializer(new Serializer(), ['new-key', 'old-key'], [DummyMessage::class]);

        $decoded = $serializer->decode((new SigningSerializer(new Serializer(), 'old-key', [DummyMessage::class]))->encode(new Envelope(new DummyMessage('hello'))));
        $this->assertSame('hello', $decoded->getMessage()->getMessage());

        $envelope = $serializer->decode((new SigningSerializer(new Serializer(), 'other-key', [DummyMessage::class]))->encode(new Envelope(new DummyMessage('hello'))));
        $this->assertInstanceOf(MessageDecodingFailedException::class, $envelope->getMessage());
        $this->assertInstanceOf(InvalidMessageSignatureException::class, $envelope->getMessage()->getPrevious());
    }

    public function testSignAllAcceptsASignatureFromAnyOfSeveralKeys()
    {
        $encoded = self::signWith((new Serializer())->encode(new Envelope(new DummyMessage('hello'))), 'old-key');

        $decoded = (new SigningSerializer(new Serializer(), ['new-key', 'old-key'], ['*']))->decode($encoded);

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
        $this->assertTrue($decoded->last(TrustStamp::class)?->isTrusted());
    }

    public function testDecodeAcceptsABodyOnlySignatureFromAnyOfSeveralKeys()
    {
        $inner = new Serializer();
        $serializer = new SigningSerializer($inner, ['new-key', 'secret-key'], [DummyMessage::class]);

        $decoded = $serializer->decode(self::signBodyOnly($inner->encode(new Envelope(new DummyMessage('hello')))));

        $this->assertSame('hello', $decoded->getMessage()->getMessage());
    }

    public function testEncodeSignsWithTheFirstKeyTheDecodeFailureOfAMessageSignedWithAnotherKey()
    {
        $typeToClassMap = ['dummy' => DummyMessage::class];
        $serializer = new SigningSerializer(new Serializer(), ['new-key', 'old-key'], [DummyMessage::class]);
        $failed = $serializer->decode((new SigningSerializer(new Serializer(null, 'json', [], $typeToClassMap), 'old-key', [DummyMessage::class]))->encode(new Envelope(new DummyMessage('hello'))));
        $this->assertInstanceOf(MessageDecodingFailedException::class, $failed->getMessage());

        $encoded = $serializer->encode($failed->with(new DelayStamp(0), new RedeliveryStamp(0)));

        $decoded = (new SigningSerializer(new Serializer(null, 'json', [], $typeToClassMap), 'new-key', [DummyMessage::class]))->decode($encoded);
        $this->assertSame('hello', $decoded->getMessage()->getMessage());
    }

    public function testConstructorRejectsAnEmptyListOfKeys()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one signing key is required.');

        new SigningSerializer(new PhpSerializer(), [], [DummyMessage::class]);
    }

    private function createSerializer(array $signedTypes): SerializerInterface
    {
        return new SigningSerializer(new PhpSerializer(), 'secret-key', $signedTypes);
    }

    private function createSignAllSerializer(SerializerInterface $inner): SerializerInterface
    {
        return new SigningSerializer($inner, 'secret-key', ['*']);
    }

    private static function createSignAllFailureSerializer(): SerializerInterface
    {
        return new SigningSerializer(new Serializer(), 'secret-key', ['*'], 'sha256', true);
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

    private static function signWith(array $encoded, string $signingKey, bool $verified = true): array
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

        return (new SigningSerializer($inner, $signingKey, ['*']))->encode(new Envelope(new DummyMessage('hello'), $verified ? [] : [new ReceivedStamp('async')]));
    }

    private static function signBodyOnly(array $encoded): array
    {
        $encoded['headers']['Body-Sign'] = hash_hmac('sha256', $encoded['body'] ?? '', 'secret-key');
        $encoded['headers']['Sign-Algo'] = 'sha256';

        return $encoded;
    }
}
