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
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\OperationFailed;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\RequestClientInterface;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKms;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKmsFactory;
use Symfony\Component\KeyManagement\Bridge\Kmip\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

final class KmipKmsTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private const int TIMEOUT_SECONDS = 10;
    private const int MIN_DATA_KEY_LENGTH = 16;
    private const int DEFAULT_DATA_KEY_LENGTH = 32;
    private const int VERSION_MINOR_ITEM_OFFSET = 40;
    private const int INTEGER_ITEM_LENGTH = 16;
    private const int REASON_ITEM_NOT_FOUND = 0x01;
    private const int REASON_CRYPTOGRAPHIC_FAILURE = 0x0A;
    private const int REASON_GENERAL_FAILURE = 0x100;
    private const string AES_GCM_CIPHERTEXT_HEADER = "\x01\x00\x00\x00\x03\x00\x00\x00\x09";

    public function testInjectedClientDoesNotRequireConnectionSettings()
    {
        $client = new class implements RequestClientInterface {
            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                throw new RuntimeException('Test client was called.');
            }
        };

        $kms = new KmipKms($client);

        $this->assertInstanceOf(KmipKms::class, $kms);
        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('key', 'plaintext', '', true);
    }

    public function testOversizedStoredCiphertextIsRejectedAsInvalidCiphertext()
    {
        $client = new class implements RequestClientInterface {
            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                throw new RuntimeException('An oversized ciphertext must not reach the server.');
            }
        };
        $kms = new KmipKms($client);

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext(self::AES_GCM_CIPHERTEXT_HEADER."\x10".str_repeat('i', 16).str_repeat('t', 16).str_repeat('x', Ttlv::MAX_FRAME), 'key'));
    }

    public function testFactoryRequiresCertificateAndKeyBeforeConnection()
    {
        $this->expectException(InvalidArgumentException::class);

        self::factory()->create(Dsn::fromString('kmip://localhost?cert=/tmp/client.crt&version=2.0'));
    }

    public function testExceptionTraceRedactsPlaintextAadAndCredentials()
    {
        $plaintext = 'plaintext-marker-unique';
        $aad = 'aad-marker-unique';
        $password = 'password-marker-unique';
        $kms = self::factory()->create(Dsn::fromString('kmip://user:'.$password.'@localhost?cert=/missing/cert&key=/missing/key&version=2.0'));

        $trace = self::traceOf(static fn () => $kms->encrypt('key', $plaintext, $aad));

        self::assertRedacted($plaintext, $trace);
        foreach ([$plaintext, $aad, $password] as $secret) {
            foreach ($trace as $frame) {
                foreach ($frame['args'] ?? [] as $argument) {
                    $this->assertFalse(self::argumentContains($argument, $secret), \sprintf('%s() exposes %s in its trace arguments.', $frame['function'], $secret));
                }
            }
        }
    }

    public function testInvalidCiphertextIsRejectedBeforeOpeningAConnection()
    {
        $kms = self::kms();

        $this->expectException(DecryptionFailedException::class);
        $kms->decrypt(new Ciphertext(self::AES_GCM_CIPHERTEXT_HEADER.'short', 'key'));
    }

    #[DataProvider('decryptFailures')]
    public function testDecryptDistinguishesCiphertextFailuresFromServerFailures(int $reason, string $exceptionClass, bool $hasOperation = true)
    {
        $client = new class($reason, $hasOperation) implements RequestClientInterface {
            public function __construct(private readonly int $reason, private readonly bool $hasOperation)
            {
            }

            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                throw new OperationFailed($this->reason, $this->hasOperation);
            }
        };
        $kms = new KmipKms($client);
        $ciphertext = new Ciphertext(self::AES_GCM_CIPHERTEXT_HEADER."\x10".str_repeat('i', 16).str_repeat('t', 16).'encrypted', 'key');

        try {
            $kms->decrypt($ciphertext);
            $this->fail('The KMIP failure must be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame($exceptionClass, $e::class);
        }
    }

    public static function decryptFailures(): iterable
    {
        yield 'invalid authentication tag' => [self::REASON_CRYPTOGRAPHIC_FAILURE, DecryptionFailedException::class];
        yield 'missing key' => [self::REASON_ITEM_NOT_FOUND, DecryptionFailedException::class];
        yield 'server failure' => [self::REASON_GENERAL_FAILURE, OperationFailed::class];
        yield 'outer failure with no operation' => [self::REASON_ITEM_NOT_FOUND, OperationFailed::class, false];
    }

    #[DataProvider('encryptFailures')]
    public function testEncryptMapsOnlyOperationLevelMissingKeys(int $reason, bool $hasOperation, string $exceptionClass)
    {
        $client = new class($reason, $hasOperation) implements RequestClientInterface {
            public function __construct(private readonly int $reason, private readonly bool $hasOperation)
            {
            }

            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                throw new OperationFailed($this->reason, $this->hasOperation);
            }
        };
        $kms = new KmipKms($client);

        try {
            $kms->encrypt('key', 'plaintext');
            $this->fail('The KMIP failure must be reported.');
        } catch (RuntimeException $e) {
            $this->assertSame($exceptionClass, $e::class);
        }
    }

    public static function encryptFailures(): iterable
    {
        yield 'missing key' => [self::REASON_ITEM_NOT_FOUND, true, KeyNotFoundException::class];
        yield 'server failure' => [self::REASON_GENERAL_FAILURE, true, OperationFailed::class];
        yield 'outer missing-key failure' => [self::REASON_ITEM_NOT_FOUND, false, OperationFailed::class];
    }

    public function testInjectedClientReceivesAuthenticatedHeaderAndIvLengthSurvivesConfigurationChange()
    {
        $client = new class implements RequestClientInterface {
            public array $requests = [];

            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                $fields = [];
                foreach (Ttlv::items($payload, ProtocolVersion::Version20) as $item) {
                    $fields[$item['tag']] = $item;
                }
                $this->requests[] = [$operation, $fields];

                return [
                    ProtocolClient::UNIQUE_IDENTIFIER => ['tag' => ProtocolClient::UNIQUE_IDENTIFIER, 'type' => Ttlv::TEXT, 'value' => 'key'],
                    ProtocolClient::DATA => ['tag' => ProtocolClient::DATA, 'type' => Ttlv::BYTES, 'value' => ProtocolClient::ENCRYPT === $operation ? 'encrypted' : 'plaintext'],
                ] + (ProtocolClient::ENCRYPT === $operation ? [ProtocolClient::TAG => ['tag' => ProtocolClient::TAG, 'type' => Ttlv::BYTES, 'value' => str_repeat('t', 16)]] : []);
            }
        };
        $short = new KmipKms($client, 12);
        $long = new KmipKms($client, 16);

        $ciphertext = $short->encrypt('key', 'plaintext', 'aad');
        $this->assertStringStartsWith(self::AES_GCM_CIPHERTEXT_HEADER."\x0c", $ciphertext->blob);
        $this->assertSame('plaintext', $long->decrypt($ciphertext, 'aad'));
        $this->assertSame([ProtocolClient::ENCRYPT, ProtocolClient::DECRYPT], array_column($client->requests, 0));
        $this->assertSame(self::AES_GCM_CIPHERTEXT_HEADER."\x0c".'aad', $client->requests[0][1][ProtocolClient::AAD]['value']);
        $this->assertSame(self::AES_GCM_CIPHERTEXT_HEADER."\x0c".'aad', $client->requests[1][1][ProtocolClient::AAD]['value']);
    }

    public function testDataKeyGenerationAndUnwrapUseTheSameAuthenticatedDataWithoutAServer()
    {
        $client = new class implements RequestClientInterface {
            private const int AUTHENTICATION_TAG_LENGTH = 16;

            public string $plaintext = '';

            public array $associatedData = [];

            public function request(int $operation, #[\SensitiveParameter] string $payload): array
            {
                $fields = [];
                foreach (Ttlv::items($payload, ProtocolVersion::Version20) as $item) {
                    $fields[$item['tag']] = $item;
                }
                $this->associatedData[] = $fields[ProtocolClient::AAD]['value'];

                if (ProtocolClient::ENCRYPT === $operation) {
                    $this->plaintext = $fields[ProtocolClient::DATA]['value'];

                    return [
                        ProtocolClient::UNIQUE_IDENTIFIER => ['tag' => ProtocolClient::UNIQUE_IDENTIFIER, 'type' => Ttlv::TEXT, 'value' => 'key'],
                        ProtocolClient::DATA => ['tag' => ProtocolClient::DATA, 'type' => Ttlv::BYTES, 'value' => str_repeat('e', \strlen($this->plaintext))],
                        ProtocolClient::TAG => ['tag' => ProtocolClient::TAG, 'type' => Ttlv::BYTES, 'value' => str_repeat('t', self::AUTHENTICATION_TAG_LENGTH)],
                    ];
                }

                return [
                    ProtocolClient::UNIQUE_IDENTIFIER => ['tag' => ProtocolClient::UNIQUE_IDENTIFIER, 'type' => Ttlv::TEXT, 'value' => 'key'],
                    ProtocolClient::DATA => ['tag' => ProtocolClient::DATA, 'type' => Ttlv::BYTES, 'value' => $this->plaintext],
                ];
            }
        };
        $kms = new KmipKms($client);

        $generated = $kms->generateDataKey('key', self::DEFAULT_DATA_KEY_LENGTH, 'context');
        $this->assertSame(self::AES_GCM_CIPHERTEXT_HEADER, substr($generated->wrapped->blob, 0, \strlen(self::AES_GCM_CIPHERTEXT_HEADER)));
        $this->assertSame(self::DEFAULT_DATA_KEY_LENGTH, $generated->use(static fn (#[\SensitiveParameter] string $plaintext): int => \strlen($plaintext)));
        $this->assertSame($client->plaintext, $kms->unwrapDataKey($generated->wrapped, 'context')->use(static fn (#[\SensitiveParameter] string $plaintext): string => $plaintext));
        $this->assertSame([self::AES_GCM_CIPHERTEXT_HEADER."\x0c".'context', self::AES_GCM_CIPHERTEXT_HEADER."\x0c".'context'], $client->associatedData);
    }

    public function testFactorySetsTheIvLength()
    {
        foreach (['' => 12, '&iv_length=12' => 12, '&iv_length=16' => 16] as $option => $ivLength) {
            $kms = self::factory()->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=2.0'.$option));
            $cipher = (new \ReflectionProperty($kms, 'cipher'))->getValue($kms);
            $this->assertSame($ivLength, (new \ReflectionProperty($cipher, 'ivLength'))->getValue($cipher));
        }
    }

    #[DataProvider('invalidIvLengthDsns')]
    public function testFactoryRejectsInvalidAesGcmIvLengthOptions(string $dsn)
    {
        $this->expectException(InvalidArgumentException::class);
        self::factory()->create(Dsn::fromString($dsn));
    }

    public static function invalidIvLengthDsns(): iterable
    {
        yield 'too short' => ['kmip://localhost?cert=a&key=b&version=2.0&iv_length=11'];
        yield 'too long' => ['kmip://localhost?cert=a&key=b&version=2.0&iv_length=256'];
        yield 'not an integer' => ['kmip://localhost?cert=a&key=b&version=2.0&iv_length=12.5'];
    }

    public function testFactoryRejectsNonStringIvLength()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "iv_length" option of the "kmip://" DSN must be a string.');

        self::factory()->create(new Dsn('kmip', 'localhost', null, null, null, '', ['cert' => 'a', 'key' => 'b', 'version' => '2.0', 'iv_length' => 12]));
    }

    #[DataProvider('invalidCiphertexts')]
    public function testMalformedCiphertextIsRejectedBeforeConnection(string $blob, string $keyId)
    {
        $this->expectException(DecryptionFailedException::class);
        self::kms()->decrypt(new Ciphertext($blob, $keyId));
    }

    public static function invalidCiphertexts(): iterable
    {
        yield 'empty' => ['', 'key'];
        yield 'short' => [self::AES_GCM_CIPHERTEXT_HEADER."\x0c".str_repeat("\0", 27), 'key'];
        yield 'unknown format' => ["\x02".substr(self::AES_GCM_CIPHERTEXT_HEADER, 1)."\x0c".str_repeat("\0", 28), 'key'];
        yield 'invalid key id' => [self::AES_GCM_CIPHERTEXT_HEADER."\x0c".str_repeat("\0", 28), "\xFF"];
    }

    public function testInvalidKeyIdIsRejectedBeforeOpeningAConnection()
    {
        $kms = self::kms();

        $this->expectException(InvalidArgumentException::class);
        $kms->encrypt("\xFF", 'plaintext');
    }

    #[DataProvider('invalidKeyIds')]
    public function testInvalidKeyIdVariantsAreRejectedBeforeConnection(string $keyId)
    {
        $this->expectException(InvalidArgumentException::class);
        self::kms()->encrypt($keyId, 'plaintext');
    }

    public static function invalidKeyIds(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid UTF-8' => ["\xFF"];
        yield 'too long for frame' => [str_repeat('a', Ttlv::MAX_FRAME)];
    }

    public function testDataKeyBelowMinimumIsRejectedBeforeConnection()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP data key length must be at least 16 bytes and fit in one frame.');
        self::kms()->generateDataKey('key', self::MIN_DATA_KEY_LENGTH - 1);
    }

    public function testDisplayedKeyIdEscapesUtf8ControlCharacters()
    {
        $this->assertSame('missing\\xC29B[31m', (new \ReflectionMethod(KmipKms::class, 'safeKeyId'))->invoke(null, "missing\u{009B}[31m"));
        $this->assertSame('missing\\xE280AEevil', (new \ReflectionMethod(KmipKms::class, 'safeKeyId'))->invoke(null, "missing\u{202E}evil"));
    }

    public function testDeterministicModeIsRejectedBeforeOpeningAConnection()
    {
        $kms = self::kms();

        $this->expectException(UnsupportedOperationException::class);
        $kms->encrypt('key', 'plaintext', '', true);
    }

    public function testSecretHoldersAreNotDumpableOrSerializable()
    {
        $kms = self::factory()->create(Dsn::fromString('kmip://user:header-secret@localhost?cert=/missing/cert&key=/missing/key&version=2.0&passphrase=private-secret'));
        foreach ([print_r($kms, true), var_export($kms, true)] as $dump) {
            $this->assertStringNotContainsString('private-secret', $dump);
            $this->assertStringNotContainsString('header-secret', $dump);
        }

        $this->expectException(LogicException::class);
        serialize($kms);
    }

    public function testFactoryAcceptsAnUnopenedClientAndParsedCredentials()
    {
        $client = self::factory()->create(Dsn::fromString('kmip://u:p@localhost?cert=/missing/cert&key=/missing/key&version=1.4'));
        $this->assertInstanceOf(KmipKms::class, $client);
        $this->assertInstanceOf(KmipKms::class, self::factory()->create(Dsn::fromString('kmip://:@localhost?cert=/missing/cert&key=/missing/key&version=2.0')));
    }

    public function testInvalidVersionMessageListsEverySupportedVersion()
    {
        try {
            self::factory()->create(Dsn::fromString('kmip://localhost?cert=a&key=b&version=unsupported'));
            $this->fail('An unsupported KMIP version must fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Supported KMIP versions are 1.4 and 2.0.', $e->getMessage());
        }
    }

    public function testFactorySupportsOnlyKmip()
    {
        $factory = self::factory();
        $this->assertTrue($factory->supports(Dsn::fromString('kmip://localhost?cert=a&key=b')));
        $foreign = Dsn::fromString('sodium://localhost');
        $this->assertFalse($factory->supports($foreign));

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create($foreign);
    }

    public function testFactoryUsesExplicitVersionAndEmptyUserinfoOmitsAuthentication()
    {
        $factory = self::factory();
        foreach (['kmip://localhost?cert=a&key=b&version=2.0', 'kmip://:@localhost?cert=a&key=b&version=2.0'] as $dsn) {
            $kms = $factory->create(Dsn::fromString($dsn));
            $protocol = (new \ReflectionProperty(KmipKms::class, 'requestClient'))->getValue($kms);
            $this->assertInstanceOf(ProtocolClient::class, $protocol);
            $frame = $protocol->buildRequest(ProtocolClient::ENCRYPT, '');
            $this->assertSame('42006b02000000040000000000000000', bin2hex(substr($frame, self::VERSION_MINOR_ITEM_OFFSET, self::INTEGER_ITEM_LENGTH)));
            $this->assertStringNotContainsString('42000c01', bin2hex($frame));
        }
    }

    public function testFactoryRequiresVersion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "kmip://" DSN requires a "version" option.');

        self::factory()->create(Dsn::fromString('kmip://localhost?cert=a&key=b'));
    }

    public function testFactoryAcceptsBracketedIpv6Host()
    {
        $this->assertInstanceOf(KmipKms::class, self::factory()->create(Dsn::fromString('kmip://[::1]:5696?cert=/missing/cert&key=/missing/key&version=2.0')));
    }

    #[DataProvider('invalidBracketedIpv6Hosts')]
    public function testFactoryRejectsInvalidBracketedIpv6Host(string $host)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP host is invalid.');
        self::factory()->create(new Dsn('kmip', $host, null, null, null, '', ['cert' => '/missing/cert', 'key' => '/missing/key']));
    }

    public static function invalidBracketedIpv6Hosts(): iterable
    {
        yield 'invalid address' => ['[invalid]'];
        yield 'missing closing bracket' => ['[::1'];
    }

    public function testFactoryRejectsNonStringOptionalValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "timeout" option of the "kmip://" DSN must be a string.');
        self::factory()->create(new Dsn('kmip', 'localhost', null, null, null, '', ['cert' => '/missing/cert', 'key' => '/missing/key', 'version' => '2.0', 'timeout' => self::TIMEOUT_SECONDS]));
    }

    public function testFactoryRejectsNonStringVersion()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "version" option of the "kmip://" DSN must be a string.');

        self::factory()->create(new Dsn('kmip', 'localhost', null, null, null, '', ['cert' => '/missing/cert', 'key' => '/missing/key', 'version' => 2]));
    }

    #[DataProvider('invalidPorts')]
    public function testFactoryRejectsAnInvalidPort(int $port)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP port must be between 1 and 65535.');

        self::factory()->create(new Dsn('kmip', 'localhost', null, null, $port, '', ['cert' => '/missing/cert', 'key' => '/missing/key', 'version' => '2.0']));
    }

    public static function invalidPorts(): iterable
    {
        yield 'zero' => [0];
        yield 'above 65535' => [65536];
    }

    #[DataProvider('invalidHosts')]
    public function testFactoryRejectsAnInvalidHost(string $host)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The KMIP host is invalid.');

        self::factory()->create(new Dsn('kmip', $host, null, null, null, '', ['cert' => '/missing/cert', 'key' => '/missing/key', 'version' => '2.0']));
    }

    public static function invalidHosts(): iterable
    {
        yield 'slash' => ['bad/host'];
        yield 'whitespace' => ['bad host'];
    }

    public function testFactoryRejectsInvalidCredentialEncoding()
    {
        $this->expectException(InvalidArgumentException::class);

        self::factory()->create(new Dsn('kmip', 'localhost', 'user', "\xFF", null, '', ['cert' => '/missing/cert', 'key' => '/missing/key', 'version' => '2.0']));
    }

    public function testFactoryRejectsHalfParsedCredentials()
    {
        $this->expectException(InvalidArgumentException::class);
        self::factory()->create(Dsn::fromString('kmip://u:@localhost?cert=/missing/cert&key=/missing/key&version=2.0'));
    }

    #[DataProvider('invalidDsns')]
    public function testFactoryRejectsInvalidDsnBeforeOpeningAConnection(string $dsn)
    {
        $this->expectException(InvalidArgumentException::class);
        self::factory()->create(Dsn::fromString($dsn));
    }

    public static function invalidDsns(): iterable
    {
        yield 'no host' => ['kmip://?cert=a&key=b&version=2.0'];
        yield 'path' => ['kmip://localhost/path?cert=a&key=b&version=2.0'];
        yield 'no cert' => ['kmip://localhost?key=b&version=2.0'];
        yield 'no key' => ['kmip://localhost?cert=a&version=2.0'];
        yield 'empty cert' => ['kmip://localhost?cert=&key=b&version=2.0'];
        yield 'unknown option' => ['kmip://localhost?cert=a&key=b&version=2.0&verify=false'];
        yield 'forbidden peer override' => ['kmip://localhost?cert=a&key=b&version=2.0&verify_peer=0'];
        yield 'nonscalar option' => ['kmip://localhost?cert=a&key=b&version=2.0&ca[]=x'];
        yield 'nonscalar cert' => ['kmip://localhost?cert[]=x&key=b&version=2.0'];
        yield 'invalid version' => ['kmip://localhost?cert=a&key=b&version=3.0'];
        yield 'version 1.0' => ['kmip://localhost?cert=a&key=b&version=1.0'];
        yield 'version 1.1' => ['kmip://localhost?cert=a&key=b&version=1.1'];
        yield 'version 1.2' => ['kmip://localhost?cert=a&key=b&version=1.2'];
        yield 'version 1.3' => ['kmip://localhost?cert=a&key=b&version=1.3'];
        yield 'future version' => ['kmip://localhost?cert=a&key=b&version=2.2'];
        yield 'incomplete version' => ['kmip://localhost?cert=a&key=b&version=2'];
        yield 'empty version' => ['kmip://localhost?cert=a&key=b&version='];
        yield 'empty peer name' => ['kmip://localhost?cert=a&key=b&version=2.0&peer_name='];
        yield 'empty certificate authority' => ['kmip://localhost?cert=a&key=b&version=2.0&ca='];
        yield 'cipher' => ['kmip://localhost?cert=a&key=b&version=2.0&cipher=aes-gcm'];
        yield 'version 2.1' => ['kmip://localhost?cert=a&key=b&version=2.1'];
        yield 'zero timeout' => ['kmip://localhost?cert=a&key=b&version=2.0&timeout=0'];
        yield 'invalid timeout' => ['kmip://localhost?cert=a&key=b&version=2.0&timeout=nan'];
        yield 'password without user' => ['kmip://:pass@localhost?cert=a&key=b&version=2.0'];
        yield 'user without password' => ['kmip://user@localhost?cert=a&key=b&version=2.0'];
    }

    public function testCertificateReadabilityIsCheckedAtConnectionTime()
    {
        $kms = self::factory()->create(Dsn::fromString('kmip://localhost?cert=/missing/cert&key=/missing/key&version=2.0'));
        try {
            $kms->encrypt('key', 'plaintext');
            $this->fail('Missing certificate must fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cert', $e->getMessage());
            $this->assertStringNotContainsString('/missing', $e->getMessage());
        }
    }

    private static function argumentContains(mixed $argument, string $secret): bool
    {
        if (\is_string($argument)) {
            return str_contains($argument, $secret);
        }
        if (\is_array($argument)) {
            foreach ($argument as $value) {
                if (self::argumentContains($value, $secret)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function factory(): KmipKmsFactory
    {
        return new KmipKmsFactory();
    }

    private static function kms(): KmipKms
    {
        return self::factory()->create(Dsn::fromString('kmip://localhost?cert=/missing/cert&key=/missing/key&version=2.0'));
    }
}
