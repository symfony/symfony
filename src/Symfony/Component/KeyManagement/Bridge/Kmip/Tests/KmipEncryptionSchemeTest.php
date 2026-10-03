<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AbstractAuthenticatedEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmSivEncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ChaCha20Poly1305EncryptionScheme;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\CiphertextCodec;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipEncryptionSchemeRegistry;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipRequestClientInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

final class KmipEncryptionSchemeTest extends TestCase
{
    private const int TAG_BLOCK_CIPHER_MODE = 0x420011;
    private const int TAG_CRYPTOGRAPHIC_ALGORITHM = 0x420028;
    private const int TAG_CRYPTOGRAPHIC_PARAMETERS = 0x42002B;
    private const int TAG_IV_LENGTH = 0x4200CD;
    private const int TAG_TAG_LENGTH = 0x4200CE;
    private const int ALGORITHM_AES = 3;
    private const int ALGORITHM_CHACHA20_POLY1305 = 0x1E;
    private const int BLOCK_MODE_GCM = 9;
    private const string BLOCK_MODE_GCM_SIV = '0x80000002';
    private const string OTHER_VENDOR_BLOCK_MODE_GCM_SIV = '0x80001234';
    private const int IV_LENGTH_BYTES = 12;
    private const int AES_GCM_IV_LENGTH_BYTES = 16;
    private const int IV_LENGTH_PREFIX_BYTES = 1;
    private const int TAG_LENGTH_BYTES = 16;
    private const int DEFAULT_PORT = 5696;
    private const float TIMEOUT_SECONDS = 10.0;
    private const string NAME_AES_GCM = 'aes-gcm';
    private const string NAMED_FORMAT = "\x01";
    private const string UNKNOWN_FORMAT = "\x02";

    /**
     * @param list<int> $expectedParameterTags
     */
    #[DataProvider('schemes')]
    public function testSchemesSendAuthenticatedEncryptAndDecryptRequests(KmipEncryptionSchemeInterface $scheme, int $algorithm, int|string|null $blockMode, array $expectedParameterTags, int $ivLength)
    {
        $requests = [];
        $client = self::client(static function (int $operation, string $payload) use (&$requests): array {
            $items = Ttlv::items($payload, KmipVersion::Version20);
            $fields = [];
            foreach ($items as $item) {
                $fields[$item['tag']] = $item;
            }
            $requests[] = [$operation, $fields];

            if (ProtocolClient::ENCRYPT === $operation) {
                return [
                    ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                    ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'encrypted'),
                    ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
                    ProtocolClient::IV => $fields[ProtocolClient::IV],
                ];
            }

            return [
                ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'plaintext'),
            ];
        });

        $encrypted = $scheme->encrypt($client, 'key', 'plaintext', 'aad');
        $this->assertSame('key', $encrypted->keyId);
        $this->assertSame($ivLength, \ord($encrypted->blob[0]));
        $this->assertSame(self::IV_LENGTH_PREFIX_BYTES + $ivLength + self::TAG_LENGTH_BYTES + \strlen('encrypted'), \strlen($encrypted->blob));
        $this->assertSame('plaintext', $scheme->decrypt($client, $encrypted, 'aad'));
        $this->assertSame([ProtocolClient::ENCRYPT, ProtocolClient::DECRYPT], array_column($requests, 0));

        foreach ($requests as [, $fields]) {
            $this->assertSame('key', $fields[ProtocolClient::UNIQUE_IDENTIFIER]['value']);
            $this->assertSame('aad', $fields[ProtocolClient::AAD]['value']);
            $this->assertSame($ivLength, \strlen($fields[ProtocolClient::IV]['value']));
            $parameters = Ttlv::children($fields[self::TAG_CRYPTOGRAPHIC_PARAMETERS], self::TAG_CRYPTOGRAPHIC_PARAMETERS, KmipVersion::Version20);
            $this->assertSame($expectedParameterTags, array_column($parameters, 'tag'));
            foreach ($parameters as $item) {
                if (self::TAG_CRYPTOGRAPHIC_ALGORITHM === $item['tag']) {
                    $this->assertSame($algorithm, Ttlv::number($item, self::TAG_CRYPTOGRAPHIC_ALGORITHM, Ttlv::ENUMERATION));
                }
                if (self::TAG_BLOCK_CIPHER_MODE === $item['tag']) {
                    if (\is_string($blockMode)) {
                        $this->assertSame(strtolower(substr($blockMode, 2)), bin2hex($item['value']));
                    } else {
                        $this->assertSame($blockMode, Ttlv::number($item, self::TAG_BLOCK_CIPHER_MODE, Ttlv::ENUMERATION));
                    }
                }
                if (self::TAG_IV_LENGTH === $item['tag']) {
                    $this->assertSame($ivLength * 8, Ttlv::number($item, self::TAG_IV_LENGTH));
                }
            }
        }
        $this->assertSame('plaintext', $requests[0][1][ProtocolClient::DATA]['value']);
        $this->assertSame('encrypted', $requests[1][1][ProtocolClient::DATA]['value']);
        $this->assertSame(str_repeat('t', self::TAG_LENGTH_BYTES), $requests[1][1][ProtocolClient::TAG]['value']);
        $this->assertSame($requests[0][1][ProtocolClient::IV]['value'], $requests[1][1][ProtocolClient::IV]['value']);
    }

    public static function schemes(): iterable
    {
        yield 'AES-256-GCM' => [new AesGcmEncryptionScheme(), self::ALGORITHM_AES, self::BLOCK_MODE_GCM, [self::TAG_BLOCK_CIPHER_MODE, self::TAG_CRYPTOGRAPHIC_ALGORITHM, self::TAG_IV_LENGTH, self::TAG_TAG_LENGTH], self::AES_GCM_IV_LENGTH_BYTES];
        yield 'AES-256-GCM with 12-byte IV' => [new AesGcmEncryptionScheme(self::IV_LENGTH_BYTES), self::ALGORITHM_AES, self::BLOCK_MODE_GCM, [self::TAG_BLOCK_CIPHER_MODE, self::TAG_CRYPTOGRAPHIC_ALGORITHM, self::TAG_IV_LENGTH, self::TAG_TAG_LENGTH], self::IV_LENGTH_BYTES];
        yield 'ChaCha20-Poly1305' => [new ChaCha20Poly1305EncryptionScheme(), self::ALGORITHM_CHACHA20_POLY1305, null, [self::TAG_CRYPTOGRAPHIC_ALGORITHM], self::IV_LENGTH_BYTES];
        yield 'AES-256-GCM-SIV' => [new AesGcmSivEncryptionScheme(self::BLOCK_MODE_GCM_SIV), self::ALGORITHM_AES, self::BLOCK_MODE_GCM_SIV, [self::TAG_BLOCK_CIPHER_MODE, self::TAG_CRYPTOGRAPHIC_ALGORITHM], self::IV_LENGTH_BYTES];
        yield 'AES-256-GCM-SIV with another vendor mode' => [new AesGcmSivEncryptionScheme(self::OTHER_VENDOR_BLOCK_MODE_GCM_SIV), self::ALGORITHM_AES, self::OTHER_VENDOR_BLOCK_MODE_GCM_SIV, [self::TAG_BLOCK_CIPHER_MODE, self::TAG_CRYPTOGRAPHIC_ALGORITHM], self::IV_LENGTH_BYTES];
    }

    #[DataProvider('aesGcmLengthChanges')]
    public function testAesGcmReadsStoredIvLengthAfterConfigurationChanges(int $oldLength, int $newLength)
    {
        $requests = [];
        $client = self::client(static function (int $operation, string $payload) use (&$requests): array {
            $fields = [];
            foreach (Ttlv::items($payload, KmipVersion::Version20) as $item) {
                $fields[$item['tag']] = $item;
            }
            $requests[] = [$operation, $fields];

            $response = [
                ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, ProtocolClient::ENCRYPT === $operation ? 'encrypted' : 'plaintext'),
            ];
            if (ProtocolClient::ENCRYPT === $operation) {
                $response[ProtocolClient::TAG] = self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES));
            }

            return $response;
        });

        $ciphertext = (new AesGcmEncryptionScheme($oldLength))->encrypt($client, 'key', 'plaintext', 'aad');
        $this->assertSame($oldLength, \ord($ciphertext->blob[0]));
        $this->assertSame('plaintext', (new AesGcmEncryptionScheme($newLength))->decrypt($client, $ciphertext, 'aad'));
        $this->assertSame([ProtocolClient::ENCRYPT, ProtocolClient::DECRYPT], array_column($requests, 0));
        $this->assertSame($requests[0][1][ProtocolClient::IV]['value'], $requests[1][1][ProtocolClient::IV]['value']);
        foreach ($requests as [, $fields]) {
            $parameters = Ttlv::children($fields[self::TAG_CRYPTOGRAPHIC_PARAMETERS], self::TAG_CRYPTOGRAPHIC_PARAMETERS, KmipVersion::Version20);
            $ivLength = array_values(array_filter($parameters, static fn (array $item): bool => self::TAG_IV_LENGTH === $item['tag']));
            $this->assertSame($oldLength * 8, Ttlv::number($ivLength[0], self::TAG_IV_LENGTH));
        }
    }

    public static function aesGcmLengthChanges(): iterable
    {
        yield '12 to 16 bytes' => [12, 16];
        yield '16 to 12 bytes' => [16, 12];
    }

    #[DataProvider('invalidStoredIvLengths')]
    public function testInvalidStoredIvLengthIsRejectedBeforeARequest(KmipEncryptionSchemeInterface $scheme, int $declaredLength, int $storedLength)
    {
        $client = self::client(static fn (): array => throw new \LogicException('No request should be sent.'));

        $this->expectException(DecryptionFailedException::class);
        $scheme->decrypt($client, new Ciphertext(\chr($declaredLength).str_repeat('i', $storedLength).str_repeat('t', self::TAG_LENGTH_BYTES), 'key'), 'aad');
    }

    public static function invalidStoredIvLengths(): iterable
    {
        yield 'AES-GCM IV shorter than 12 bytes' => [new AesGcmEncryptionScheme(), 11, 16];
        yield 'AES-GCM truncated IV' => [new AesGcmEncryptionScheme(), 16, 12];
        yield 'ChaCha20-Poly1305 changed IV length' => [new ChaCha20Poly1305EncryptionScheme(), 16, 16];
        yield 'AES-GCM-SIV changed IV length' => [new AesGcmSivEncryptionScheme(self::BLOCK_MODE_GCM_SIV), 16, 16];
    }

    #[DataProvider('protocolVersions')]
    public function testChaCha20EncryptPayloadMatchesIndependentTtlvVector(KmipVersion $version)
    {
        $payload = (new \ReflectionMethod(AbstractAuthenticatedEncryptionScheme::class, 'payload'))->invoke(new ChaCha20Poly1305EncryptionScheme(), 'key', "\0A", str_repeat("\x01", self::IV_LENGTH_BYTES), 'aad');
        $expected = '42009407000000036b65790000000000'
            .'42002b0100000010'
            .'4200280500000004'.\sprintf('%08x', self::ALGORITHM_CHACHA20_POLY1305).'00000000'
            .'4200c208000000020041000000000000'
            .'42003d080000000c'.str_repeat('01', self::IV_LENGTH_BYTES).'00000000'
            .'4200fe08000000036161640000000000';
        $this->assertSame($expected, bin2hex($payload));
        $this->assertStringEndsWith($expected, bin2hex(self::protocol($version)->buildRequest(ProtocolClient::ENCRYPT, $payload)));
    }

    #[DataProvider('protocolVersions')]
    public function testAesGcmSivEncryptPayloadMatchesIndependentTtlvVector(KmipVersion $version)
    {
        $payload = (new \ReflectionMethod(AbstractAuthenticatedEncryptionScheme::class, 'payload'))->invoke(new AesGcmSivEncryptionScheme(self::BLOCK_MODE_GCM_SIV), 'key', "\0A", str_repeat("\x01", self::IV_LENGTH_BYTES), 'aad');
        $expected = '42009407000000036b65790000000000'
            .'42002b0100000020'
            .'42001105000000048000000200000000'
            .'42002805000000040000000300000000'
            .'4200c208000000020041000000000000'
            .'42003d080000000c'.str_repeat('01', self::IV_LENGTH_BYTES).'00000000'
            .'4200fe08000000036161640000000000';
        $this->assertSame($expected, bin2hex($payload));
        $this->assertStringEndsWith($expected, bin2hex(self::protocol($version)->buildRequest(ProtocolClient::ENCRYPT, $payload)));
    }

    public static function protocolVersions(): iterable
    {
        yield '1.4' => [KmipVersion::Version14];
        yield '2.0' => [KmipVersion::Version20];
        yield '2.1' => [KmipVersion::Version21];
    }

    #[DataProvider('schemeInstances')]
    public function testSchemesRejectMalformedEncryptResponses(KmipEncryptionSchemeInterface $scheme, int $ivLength)
    {
        $client = self::client(static fn (): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'too long'),
            ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP returned invalid authenticated ciphertext.');
        $scheme->encrypt($client, 'key', 'x', 'aad');
    }

    #[DataProvider('schemeInstances')]
    public function testSchemesRejectWrongDecryptKeyIds(KmipEncryptionSchemeInterface $scheme, int $ivLength)
    {
        $client = self::client(static fn (): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'different-key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'x'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP returned a different key identifier.');
        $scheme->decrypt($client, new Ciphertext(\chr($ivLength).str_repeat('i', $ivLength).str_repeat('t', self::TAG_LENGTH_BYTES).'x', 'key'), 'aad');
    }

    #[DataProvider('invalidEncryptResponses')]
    public function testSchemesRejectMissingOrAlteredAuthenticationFields(KmipEncryptionSchemeInterface $scheme, string $fault, string $message)
    {
        $client = self::client(static function (int $operation, string $payload) use ($fault): array {
            $fields = [
                ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
                ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'x'),
                ProtocolClient::TAG => self::field(ProtocolClient::TAG, Ttlv::BYTES, str_repeat('t', self::TAG_LENGTH_BYTES)),
            ];
            if ('missing tag' === $fault) {
                unset($fields[ProtocolClient::TAG]);
            } elseif ('short tag' === $fault) {
                $fields[ProtocolClient::TAG]['value'] = 't';
            } elseif ('empty key identifier' === $fault) {
                $fields[ProtocolClient::UNIQUE_IDENTIFIER]['value'] = '';
            } elseif ('invalid UTF-8 key identifier' === $fault) {
                $fields[ProtocolClient::UNIQUE_IDENTIFIER]['value'] = "\xFF";
            } else {
                $items = Ttlv::items($payload, KmipVersion::Version20);
                foreach ($items as $item) {
                    if (ProtocolClient::IV === $item['tag']) {
                        $fields[ProtocolClient::IV] = self::field(ProtocolClient::IV, Ttlv::BYTES, str_repeat('i', self::IV_LENGTH_BYTES));
                    }
                }
            }

            return $fields;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        $scheme->encrypt($client, 'key', 'x', 'aad');
    }

    public static function invalidEncryptResponses(): iterable
    {
        foreach (self::schemeInstances() as $name => [$scheme]) {
            yield $name.' missing tag' => [$scheme, 'missing tag', 'Missing KMIP response payload field.'];
            yield $name.' short tag' => [$scheme, 'short tag', 'KMIP returned invalid authenticated ciphertext.'];
            yield $name.' empty key identifier' => [$scheme, 'empty key identifier', 'KMIP returned an invalid key identifier.'];
            yield $name.' invalid UTF-8 key identifier' => [$scheme, 'invalid UTF-8 key identifier', 'KMIP returned an invalid key identifier.'];
            yield $name.' changed IV' => [$scheme, 'changed IV', 'KMIP returned a different encryption IV.'];
        }
    }

    #[DataProvider('schemeInstances')]
    public function testSchemesRejectChangedPlaintextLength(KmipEncryptionSchemeInterface $scheme, int $ivLength)
    {
        $client = self::client(static fn (): array => [
            ProtocolClient::UNIQUE_IDENTIFIER => self::field(ProtocolClient::UNIQUE_IDENTIFIER, Ttlv::TEXT, 'key'),
            ProtocolClient::DATA => self::field(ProtocolClient::DATA, Ttlv::BYTES, 'longer'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP returned invalid plaintext length.');
        $scheme->decrypt($client, new Ciphertext(\chr($ivLength).str_repeat('i', $ivLength).str_repeat('t', self::TAG_LENGTH_BYTES).'x', 'key'), 'aad');
    }

    #[DataProvider('schemeInstances')]
    public function testCiphertextCodecAuthenticatesNamedHeaderForEveryBuiltInScheme(KmipEncryptionSchemeInterface $scheme, int $ivLength)
    {
        $schemes = self::registry();
        $codec = new CiphertextCodec($schemes);
        $schemeCiphertext = new Ciphertext(\chr($ivLength).str_repeat('i', $ivLength).str_repeat('t', self::TAG_LENGTH_BYTES).'raw-ciphertext', 'key');
        $header = self::NAMED_FORMAT.\chr(\strlen($scheme->name())).$scheme->name();

        $this->assertSame($header.'aad', $codec->authenticatedData($scheme, 'aad'));
        $encoded = $codec->encode($scheme, $schemeCiphertext);
        $this->assertSame($header.$schemeCiphertext->blob, $encoded->blob);
        [$decodedScheme, $decodedSchemeCiphertext, $decodedAad] = $codec->decode($encoded, 'aad');
        $this->assertSame($schemes->get($scheme->name()), $decodedScheme);
        $this->assertEquals($schemeCiphertext, $decodedSchemeCiphertext);
        $this->assertSame($header.'aad', $decodedAad);
    }

    #[DataProvider('invalidNamedCiphertexts')]
    public function testMalformedNamedCiphertextIsRejectedBeforeARequest(string $blob)
    {
        $this->expectException(DecryptionFailedException::class);
        (new CiphertextCodec(self::registry()))->decode(new Ciphertext($blob, 'key'), 'aad');
    }

    public static function invalidNamedCiphertexts(): iterable
    {
        yield 'missing name length' => [self::NAMED_FORMAT];
        yield 'empty name' => [self::NAMED_FORMAT."\x00x"];
        yield 'truncated name' => [self::NAMED_FORMAT."\x05abc"];
        yield 'unknown scheme' => [self::NAMED_FORMAT."\x07unknown"];
        yield 'invalid version' => [self::UNKNOWN_FORMAT.'name'];
    }

    public static function schemeInstances(): iterable
    {
        yield 'AES-256-GCM' => [new AesGcmEncryptionScheme(), self::AES_GCM_IV_LENGTH_BYTES];
        yield 'AES-256-GCM with 12-byte IV' => [new AesGcmEncryptionScheme(self::IV_LENGTH_BYTES), self::IV_LENGTH_BYTES];
        yield 'ChaCha20-Poly1305' => [new ChaCha20Poly1305EncryptionScheme(), self::IV_LENGTH_BYTES];
        yield 'AES-256-GCM-SIV' => [new AesGcmSivEncryptionScheme(self::BLOCK_MODE_GCM_SIV), self::IV_LENGTH_BYTES];
    }

    public function testDuplicateSchemeNamesAreRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate KMIP encryption scheme "aes-gcm".');
        new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme(), new AesGcmEncryptionScheme()]);
    }

    public function testReplacingAnAesGcmSchemeLeavesTheSharedRegistryUnchanged()
    {
        $original = new AesGcmEncryptionScheme();
        $chacha = new ChaCha20Poly1305EncryptionScheme();
        $registry = new KmipEncryptionSchemeRegistry([$original, $chacha]);
        $replacement = new AesGcmEncryptionScheme(12);

        $configured = $registry->withReplacement($replacement);

        $this->assertSame($original, $registry->get(self::NAME_AES_GCM));
        $this->assertSame($replacement, $configured->get(self::NAME_AES_GCM));
        $this->assertSame($chacha, $configured->get('chacha20-poly1305'));
    }

    public function testReplacementRequiresAnExistingSchemeName()
    {
        $registry = new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot replace unregistered KMIP encryption scheme "chacha20-poly1305".');
        $registry->withReplacement(new ChaCha20Poly1305EncryptionScheme());
    }

    #[DataProvider('invalidSchemeNames')]
    public function testInvalidSchemeNameIsRejected(string $name)
    {
        $scheme = new class($name) implements KmipEncryptionSchemeInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function encrypt(KmipRequestClientInterface $client, string $keyId, string $plaintext, string $aad): Ciphertext
            {
                return new Ciphertext($plaintext, $keyId);
            }

            public function decrypt(KmipRequestClientInterface $client, Ciphertext $ciphertext, string $aad): string
            {
                return $ciphertext->blob;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A KMIP encryption scheme name must be a short lowercase ASCII identifier.');
        new KmipEncryptionSchemeRegistry([$scheme]);
    }

    public static function invalidSchemeNames(): iterable
    {
        yield 'uppercase and space' => ['Invalid Scheme'];
        yield 'name longer than one byte length' => [str_repeat('a', 256)];
        yield 'multiple namespace separators' => ['example/custom/extra'];
    }

    public function testDuplicateAesGcmSivSchemeNamesAreRejectedEvenWithDifferentBlockCipherModes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate KMIP encryption scheme "aes-gcm-siv".');

        new KmipEncryptionSchemeRegistry([
            new AesGcmSivEncryptionScheme(self::BLOCK_MODE_GCM_SIV),
            new AesGcmSivEncryptionScheme(self::OTHER_VENDOR_BLOCK_MODE_GCM_SIV),
        ]);
    }

    public function testUnsupportedCipherNameListsRegisteredSchemes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown KMIP encryption scheme "unknown". Supported schemes are aes-gcm, chacha20-poly1305, aes-gcm-siv.');
        self::registry()->get('unknown');
    }

    public function testApplicationSchemeCanUseTheNamedCiphertextFormat()
    {
        $scheme = new class implements KmipEncryptionSchemeInterface {
            public function name(): string
            {
                return 'example/custom';
            }

            public function encrypt(KmipRequestClientInterface $client, string $keyId, string $plaintext, string $aad): Ciphertext
            {
                return new Ciphertext($plaintext, $keyId);
            }

            public function decrypt(KmipRequestClientInterface $client, Ciphertext $ciphertext, string $aad): string
            {
                return $ciphertext->blob;
            }
        };
        $registry = new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme(), $scheme]);
        $codec = new CiphertextCodec($registry);

        $encoded = $codec->encode($scheme, new Ciphertext('custom-blob', 'key'));
        [$decodedScheme, $decoded, $aad] = $codec->decode($encoded, 'context');
        $this->assertSame($scheme, $decodedScheme);
        $this->assertSame('custom-blob', $decoded->blob);
        $this->assertSame('key', $decoded->keyId);
        $this->assertSame($codec->authenticatedData($scheme, 'context'), $aad);
    }

    public function testEmptyRegistryDoesNotImplicitlyRegisterBuiltInSchemes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown KMIP encryption scheme "aes-gcm". No schemes are registered.');

        (new KmipEncryptionSchemeRegistry([]))->get(self::NAME_AES_GCM);
    }

    private static function registry(): KmipEncryptionSchemeRegistry
    {
        return new KmipEncryptionSchemeRegistry([new AesGcmEncryptionScheme(), new ChaCha20Poly1305EncryptionScheme(), new AesGcmSivEncryptionScheme(self::BLOCK_MODE_GCM_SIV)]);
    }

    /**
     * @param \Closure(int, string): array<int, array{tag: int, type: int, value: string}> $handler
     */
    private static function client(\Closure $handler): KmipRequestClientInterface
    {
        return new class($handler) implements KmipRequestClientInterface {
            public function __construct(private \Closure $handler)
            {
            }

            /**
             * @return array<int, array{tag: int, type: int, value: string}>
             */
            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                return ($this->handler)($operation, $payload);
            }
        };
    }

    /**
     * @return array{tag: int, type: int, value: string}
     */
    private static function field(int $tag, int $type, string $value): array
    {
        return ['tag' => $tag, 'type' => $type, 'value' => $value];
    }

    private static function protocol(KmipVersion $version): ProtocolClient
    {
        return new ProtocolClient(new StreamTransport('localhost', self::DEFAULT_PORT, '/missing/cert', '/missing/key', null, 'localhost', null, self::TIMEOUT_SECONDS), $version, null, null);
    }
}
