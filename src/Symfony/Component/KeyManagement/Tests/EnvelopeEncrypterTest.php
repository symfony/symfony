<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;
use Symfony\Component\KeyManagement\SelfContainedFormat;
use Symfony\Component\KeyManagement\Test\InMemoryKms;
use Symfony\Component\KeyManagement\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Tests\Fixtures\WrongLengthDataKeyKms;

#[RequiresPhpExtension('openssl')]
class EnvelopeEncrypterTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private InMemoryKms $kms;
    private EnvelopeEncrypter $encrypter;

    protected function setUp(): void
    {
        $this->kms = new InMemoryKms();
        $this->encrypter = new EnvelopeEncrypter($this->kms);
    }

    public static function providePayloads(): iterable
    {
        yield 'empty' => [''];
        yield 'small text' => ['hello'];
        yield 'unicode' => ['héllo café 日本語'];
        yield 'binary' => ["\x00\xFF\x01\x02\x03\xFE"];
        yield 'long' => [str_repeat('lorem ipsum ', 4096)];
    }

    #[DataProvider('providePayloads')]
    public function testRoundTrip(string $payload)
    {
        $envelope = $this->encrypter->encrypt('app-key', $payload);

        $this->assertInstanceOf(Envelope::class, $envelope);
        $this->assertSame($payload, $this->encrypter->decrypt($envelope));
    }

    public function testRoundTripIsNotDeterministic()
    {
        $a = $this->encrypter->encrypt('app-key', 'hello');
        $b = $this->encrypter->encrypt('app-key', 'hello');

        $this->assertNotSame((string) $a, (string) $b, 'Each call must use a fresh DEK and IV.');
    }

    public function testEnvelopeRoundTripsThroughItsByteFraming()
    {
        $original = $this->encrypter->encrypt('app-key', 'hello');

        $reparsed = Envelope::fromBytes((string) $original);

        $this->assertSame($original->keyId, $reparsed->keyId);
        $this->assertSame($original->wrappedDek, $reparsed->wrappedDek);
        $this->assertSame($original->format->id(), $reparsed->format->id());
        $this->assertSame($original->iv, $reparsed->iv);
        $this->assertSame($original->tag, $reparsed->tag);
        $this->assertSame($original->ciphertext, $reparsed->ciphertext);
        $this->assertSame('hello', $this->encrypter->decrypt($reparsed));
    }

    public function testTheEnvelopeIsWrittenInTheSelfContainedFormat()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');

        $this->assertSame(SelfContainedFormat::ID, $envelope->format->id());
        $this->assertNotNull($envelope->wrappedDek, 'the wrapped key travels with the payload, which is what makes it self-sufficient.');
        $this->assertNull($envelope->reference);
    }

    public function testTheEnvelopeRecordsTheKeyIdReportedByTheBackend()
    {
        $resolving = new class implements DataKeyGeneratorInterface {
            public const string ARN = 'arn:aws:kms:eu-west-1:111122223333:key/1234abcd';

            private InMemoryKms $inner;

            public function __construct()
            {
                $this->inner = new InMemoryKms();
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                $dataKey = $this->inner->generateDataKey($keyId, $length, $aad);

                return new DataKey($dataKey->use(static fn (#[\SensitiveParameter] string $plaintext): string => $plaintext), new Ciphertext($dataKey->wrapped->blob, self::ARN));
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                return $this->inner->unwrapDataKey(new Ciphertext($wrapped->blob, 'alias/app-key'), $aad);
            }
        };

        $envelope = (new EnvelopeEncrypter($resolving))->encrypt('alias/app-key', 'hello');

        $this->assertSame($resolving::ARN, $envelope->keyId);
        $this->assertSame('hello', (new EnvelopeEncrypter($resolving))->decrypt($envelope));
    }

    public function testAadRoundTrip()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello', 'tenant=acme');

        $this->assertSame('hello', $this->encrypter->decrypt($envelope, 'tenant=acme'));
    }

    public function testWrongAadFails()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello', 'tenant=acme');

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($envelope, 'tenant=globex');
    }

    public function testTheAadDoesNotReachTheKms()
    {
        $spying = new class($this->kms) implements DataKeyGeneratorInterface {
            /**
             * @var list<string>
             */
            public array $seen = [];

            public function __construct(
                private readonly DataKeyGeneratorInterface $inner,
            ) {
            }

            public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
            {
                $this->seen[] = $aad;

                return $this->inner->generateDataKey($keyId, $length, $aad);
            }

            public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
            {
                $this->seen[] = $aad;

                return $this->inner->unwrapDataKey($wrapped, $aad);
            }
        };
        $encrypter = new EnvelopeEncrypter($spying);

        $this->assertSame('hello', $encrypter->decrypt($encrypter->encrypt('app-key', 'hello', 'tenant=acme'), 'tenant=acme'));
        $this->assertSame(['', ''], $spying->seen, 'the AAD binds the payload locally, so the wrapping of the data key carries none.');
    }

    #[RequiresPhpExtension('sodium')]
    public function testAnAadIsBoundOnABackendThatRefusesAuthenticatedData()
    {
        $encrypter = new EnvelopeEncrypter(new SealedBoxKms(new InMemoryKeyLoader(['app-key' => sodium_crypto_box_keypair()])));
        $envelope = $encrypter->encrypt('app-key', 'hello', 'tenant=acme');

        $this->assertSame('hello', $encrypter->decrypt($envelope, 'tenant=acme'));

        $this->expectException(DecryptionFailedException::class);
        $encrypter->decrypt($envelope, 'tenant=globex');
    }

    public function testEnvelopeIsPortableAcrossEncrypterInstances()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');

        $other = new EnvelopeEncrypter($this->kms);

        $this->assertSame('hello', $other->decrypt($envelope));
    }

    public function testChangingTheKeyIdToAnAliasIsRejected()
    {
        $key = random_bytes(32);
        $kms = new OpenSslKms(new InMemoryKeyLoader(['app' => $key, 'alias' => $key]));
        $encrypter = new EnvelopeEncrypter($kms);
        $envelope = $encrypter->encrypt('app', 'hello', 'context');
        $aliased = Envelope::selfContained('alias', $envelope->wrappedDek, $envelope->iv, $envelope->tag, $envelope->ciphertext);

        $this->assertSame('hello', $encrypter->decrypt($envelope, 'context'));
        $this->expectException(DecryptionFailedException::class);
        $encrypter->decrypt($aliased, 'context');
    }

    public function testChangingTheWrappingOfTheSameDataKeyIsRejected()
    {
        $kms = new OpenSslKms(new InMemoryKeyLoader(['app' => random_bytes(32)]));
        $encrypter = new EnvelopeEncrypter($kms);
        $envelope = $encrypter->encrypt('app', 'hello', 'context');
        $wrapped = $kms->unwrapDataKey(new Ciphertext($envelope->wrappedDek, $envelope->keyId))
            ->use(static fn (string $key): Ciphertext => $kms->encrypt('app', $key));
        $rewrapped = Envelope::selfContained($wrapped->keyId, $wrapped->blob, $envelope->iv, $envelope->tag, $envelope->ciphertext);

        $this->assertNotSame($envelope->wrappedDek, $wrapped->blob);
        $this->assertSame('hello', $encrypter->decrypt($envelope, 'context'));
        $this->expectException(DecryptionFailedException::class);
        $encrypter->decrypt($rewrapped, 'context');
    }

    public function testTheKeyIdAndWrappingCannotBeShiftedIntoEachOther()
    {
        $kms = $this->createStub(DataKeyGeneratorInterface::class);
        $kms->method('generateDataKey')->willReturn(new DataKey(str_repeat('K', 32), new Ciphertext('bc', 'a')));
        $kms->method('unwrapDataKey')->willReturnCallback(static fn (Ciphertext $wrapped): DataKey => new DataKey(str_repeat('K', 32), $wrapped));
        $encrypter = new EnvelopeEncrypter($kms);
        $envelope = $encrypter->encrypt('a', 'hello', 'context');
        $shifted = Envelope::selfContained('ab', 'c', $envelope->iv, $envelope->tag, $envelope->ciphertext);

        $this->assertSame('hello', $encrypter->decrypt($envelope, 'context'));
        $this->expectException(DecryptionFailedException::class);
        $encrypter->decrypt($shifted, 'context');
    }

    public function testTamperedCiphertextFails()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello world');
        $tampered = Envelope::selfContained(
            $envelope->keyId,
            $envelope->wrappedDek,
            $envelope->iv,
            $envelope->tag,
            substr_replace($envelope->ciphertext, $envelope->ciphertext[-1] ^ "\x01", -1, 1),
        );

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($tampered);
    }

    public function testTheDataKeyDoesNotReachStackTraces()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello world');
        $dataKey = $this->kms->decrypt(new Ciphertext($envelope->wrappedDek, $envelope->keyId));
        $tampered = Envelope::selfContained(
            $envelope->keyId,
            $envelope->wrappedDek,
            $envelope->iv,
            $envelope->tag,
            substr_replace($envelope->ciphertext, $envelope->ciphertext[-1] ^ "\x01", -1, 1),
        );

        $trace = self::traceOf(fn () => $this->encrypter->decrypt($tampered));

        self::assertRedacted($dataKey, $trace);
    }

    public function testTamperedTagFails()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');
        $tampered = Envelope::selfContained(
            $envelope->keyId,
            $envelope->wrappedDek,
            $envelope->iv,
            substr_replace($envelope->tag, $envelope->tag[0] ^ "\x01", 0, 1),
            $envelope->ciphertext,
        );

        $this->expectException(DecryptionFailedException::class);
        $this->encrypter->decrypt($tampered);
    }

    public function testEnvelopeKmsIsCalledForGenerateAndUnwrap()
    {
        $countBefore = $this->kms->calls;
        $envelope = $this->encrypter->encrypt('app-key', 'hello');
        $afterEncrypt = $this->kms->calls - $countBefore;

        $this->encrypter->decrypt($envelope);
        $afterDecrypt = $this->kms->calls - $countBefore - $afterEncrypt;

        $this->assertSame(2, $afterEncrypt);
        $this->assertSame(1, $afterDecrypt);
    }

    public function testADataKeyOfTheWrongLengthIsRefusedAtEncryptTime()
    {
        $encrypter = new EnvelopeEncrypter(new WrongLengthDataKeyKms(16));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The data key must be 32 bytes long for "aes-256-gcm", 16 given.');
        $encrypter->encrypt('app-key', 'hello');
    }

    public function testADataKeyOfTheWrongLengthIsRefusedAtDecryptTime()
    {
        $envelope = $this->encrypter->encrypt('app-key', 'hello');
        $decrypter = new EnvelopeEncrypter(new WrongLengthDataKeyKms(16, $this->kms));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The data key must be 32 bytes long for "aes-256-gcm", 16 given.');
        $decrypter->decrypt($envelope);
    }
}
