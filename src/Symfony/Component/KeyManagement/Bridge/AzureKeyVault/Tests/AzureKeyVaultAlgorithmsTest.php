<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVault;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVaultFactory;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\TokenProviderInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

class AzureKeyVaultAlgorithmsTest extends TestCase
{
    private const string VAULT = 'https://my-vault.vault.azure.net/';

    #[DataProvider('provideUnsupportedAlgorithms')]
    public function testConstructorRejectsUnsupportedAlgorithms(string $algorithm, bool $wrapping)
    {
        $this->expectException(InvalidArgumentException::class);

        new AzureKeyVault(new MockHttpClient(), $this->tokens(), $wrapping ? 'RSA-OAEP-256' : $algorithm, $wrapping ? $algorithm : 'RSA-OAEP-256');
    }

    #[DataProvider('provideUnsupportedAlgorithms')]
    public function testFactoryRejectsUnsupportedAlgorithms(string $algorithm, bool $wrapping)
    {
        $this->expectException(InvalidArgumentException::class);

        (new AzureKeyVaultFactory())->create(Dsn::fromString('azure-keyvault://id:secret@my-vault.vault.azure.net?tenant=t&'.($wrapping ? 'wrap_algorithm' : 'algorithm').'='.$algorithm));
    }

    public static function provideUnsupportedAlgorithms(): iterable
    {
        foreach (['RSA-OAEP', 'RSA1_5', 'A128CBC', 'A192CBC', 'A256CBC', 'A128CBCPAD', 'A192CBCPAD', 'A256CBCPAD', 'unknown'] as $algorithm) {
            yield 'encrypt '.$algorithm => [$algorithm, false];
            yield 'wrap '.$algorithm => [$algorithm, true];
        }

        foreach (['A128KW', 'A192KW', 'A256KW'] as $algorithm) {
            yield 'encrypt '.$algorithm => [$algorithm, false];
        }
    }

    #[DataProvider('provideNonAeadWrites')]
    public function testNonAeadWritesRecordTheirAlgorithmAndSurviveConfigurationChanges(string $operation, string $algorithm)
    {
        $plaintext = 'a data key or payload of 32 bytes';
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, &$plaintext): MockResponse {
            $requests[] = [$url, json_decode($options['body'], true)];

            return new MockResponse(json_encode(1 === \count($requests)
                ? ['kid' => self::VAULT.'keys/app/v1', 'value' => Base64UrlSafe::encode('opaque ciphertext')]
                : ['value' => Base64UrlSafe::encode($plaintext)]));
        }, self::VAULT);

        $wrapping = 'encrypt' !== $operation;
        $writer = new AzureKeyVault($client, $this->tokens(), $wrapping ? 'RSA-OAEP-256' : $algorithm, $wrapping ? $algorithm : 'RSA-OAEP-256');
        $ciphertext = match ($operation) {
            'encrypt' => $writer->encrypt('app', $plaintext),
            'wrapDataKey' => $writer->wrapDataKey('app', $plaintext),
            'generateDataKey' => $writer->generateDataKey('app'),
        };
        if ('generateDataKey' === $operation) {
            $plaintext = $ciphertext->use(static fn (string $key): string => $key);
            $ciphertext = $ciphertext->wrapped;
        }

        $this->assertSame($algorithm.'.'.Base64UrlSafe::encode('opaque ciphertext'), $ciphertext->blob);
        $reader = new AzureKeyVault($client, $this->tokens(), 'A256GCM', 'A256GCM');
        $decrypted = $wrapping
            ? $reader->unwrapDataKey($ciphertext)->use(static fn (string $key): string => $key)
            : $reader->decrypt($ciphertext);

        $this->assertSame($plaintext, $decrypted);
        $this->assertSame(self::VAULT.'keys/app/'.($wrapping ? 'wrapkey' : 'encrypt').'?api-version=7.4', $requests[0][0]);
        $this->assertSame(['alg' => $algorithm, 'value' => Base64UrlSafe::encode($plaintext)], $requests[0][1]);
        $this->assertSame(self::VAULT.'keys/app/v1/'.($wrapping ? 'unwrapkey' : 'decrypt').'?api-version=7.4', $requests[1][0]);
        $this->assertSame(['alg' => $algorithm, 'value' => Base64UrlSafe::encode('opaque ciphertext')], $requests[1][1]);
    }

    public static function provideNonAeadWrites(): iterable
    {
        yield ['encrypt', 'RSA-OAEP-256'];

        foreach (['wrapDataKey', 'generateDataKey'] as $operation) {
            foreach (['RSA-OAEP-256', 'A128KW', 'A192KW', 'A256KW'] as $algorithm) {
                yield [$operation, $algorithm];
            }
        }
    }

    #[DataProvider('provideAeadWrites')]
    public function testAeadRoundTripsKeepTheirParametersAfterAConfigurationChange(string $operation, string $algorithm, string $plaintext)
    {
        $iv = Base64UrlSafe::encode(str_repeat('I', 12));
        $tag = Base64UrlSafe::encode(str_repeat('T', 16));
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests, $iv, $tag): MockResponse {
            $body = json_decode($options['body'], true);
            $requests[] = $body;

            return new MockResponse(json_encode(1 === \count($requests)
                ? ['kid' => self::VAULT.'keys/app/v1', 'value' => $body['value'], 'iv' => $iv, 'tag' => $tag]
                : ['value' => $body['value']]));
        }, self::VAULT);
        $writer = new AzureKeyVault($client, $this->tokens(), $algorithm, $algorithm);
        $ciphertext = match ($operation) {
            'encrypt' => $writer->encrypt('app', $plaintext, 'tenant=acme'),
            'wrapDataKey' => $writer->wrapDataKey('app', $plaintext, 'tenant=acme'),
            'generateDataKey' => $writer->generateDataKey('app', 32, 'tenant=acme'),
        };
        if ('generateDataKey' === $operation) {
            $plaintext = $ciphertext->use(static fn (string $key): string => $key);
            $ciphertext = $ciphertext->wrapped;
        }

        $this->assertSame($algorithm.'.'.$iv.'.'.$tag.'.'.Base64UrlSafe::encode($plaintext), $ciphertext->blob);
        $reader = new AzureKeyVault($client, $this->tokens());
        $decrypted = 'encrypt' === $operation
            ? $reader->decrypt($ciphertext, 'tenant=acme')
            : $reader->unwrapDataKey($ciphertext, 'tenant=acme')->use(static fn (string $key): string => $key);

        $this->assertSame($plaintext, $decrypted);
        $this->assertSame(['alg' => $algorithm, 'value' => Base64UrlSafe::encode($plaintext), 'aad' => Base64UrlSafe::encode('tenant=acme')], $requests[0]);
        $this->assertSame(['alg' => $algorithm, 'iv' => $iv, 'tag' => $tag, 'value' => Base64UrlSafe::encode($plaintext), 'aad' => Base64UrlSafe::encode('tenant=acme')], $requests[1]);
    }

    public static function provideAeadWrites(): iterable
    {
        foreach (['A128GCM', 'A192GCM', 'A256GCM'] as $algorithm) {
            foreach (['encrypt', 'wrapDataKey', 'generateDataKey'] as $operation) {
                yield [$operation, $algorithm, str_repeat('K', 32)];
            }

            yield ['encrypt', $algorithm, ''];
        }
    }

    #[DataProvider('provideNonAeadWrites')]
    public function testNonAeadWritesRejectAnEmptyResponse(string $operation, string $algorithm)
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['kid' => self::VAULT.'keys/app/v1', 'value' => ''])), self::VAULT);
        $wrapping = 'encrypt' !== $operation;
        $kms = new AzureKeyVault($client, $this->tokens(), $wrapping ? 'RSA-OAEP-256' : $algorithm, $wrapping ? $algorithm : 'RSA-OAEP-256');

        $this->expectException(RuntimeException::class);

        match ($operation) {
            'encrypt' => $kms->encrypt('app', 'hello'),
            'wrapDataKey' => $kms->wrapDataKey('app', str_repeat('K', 32)),
            'generateDataKey' => $kms->generateDataKey('app'),
        };
    }

    #[DataProvider('provideEmptyAeadParameters')]
    public function testAeadWritesRejectAnEmptyIvOrTag(string $operation, string $field)
    {
        $response = ['kid' => self::VAULT.'keys/app/v1', 'value' => 'VALUE', 'iv' => 'IV', 'tag' => 'TAG'];
        $response[$field] = '';
        $kms = new AzureKeyVault(new MockHttpClient(new MockResponse(json_encode($response)), self::VAULT), $this->tokens(), 'A256GCM', 'A256GCM');

        $this->expectException(RuntimeException::class);

        match ($operation) {
            'encrypt' => $kms->encrypt('app', 'hello'),
            'wrapDataKey' => $kms->wrapDataKey('app', str_repeat('K', 32)),
            'generateDataKey' => $kms->generateDataKey('app'),
        };
    }

    public static function provideEmptyAeadParameters(): iterable
    {
        foreach (['encrypt', 'wrapDataKey', 'generateDataKey'] as $operation) {
            yield [$operation, 'iv'];
            yield [$operation, 'tag'];
        }
    }

    #[DataProvider('provideMalformedBlobs')]
    public function testMalformedBlobsAreRejectedBeforeContactingAzure(string $blob, bool $wrapping)
    {
        $client = new MockHttpClient(static function (): void {
            self::fail('Malformed blobs must not reach Azure.');
        }, self::VAULT);
        $kms = new AzureKeyVault($client, $this->tokens());

        $this->expectException(DecryptionFailedException::class);

        $wrapping ? $kms->unwrapDataKey(new Ciphertext($blob, 'app/v1')) : $kms->decrypt(new Ciphertext($blob, 'app/v1'));
    }

    public static function provideMalformedBlobs(): iterable
    {
        foreach ([
            '',
            'unprefixed',
            'unknown.VALUE',
            'RSA-OAEP.VALUE',
            'RSA1_5.VALUE',
            'A256CBC.IV.VALUE',
            'RSA-OAEP-256',
            'RSA-OAEP-256.',
            'RSA-OAEP-256.VALUE.EXTRA',
            'A256GCM.IV.TAG',
            'A256GCM..TAG.VALUE',
            'A256GCM.IV..VALUE',
            'A256GCM.IV.TAG.VALUE.EXTRA',
        ] as $blob) {
            yield 'decrypt '.$blob => [$blob, false];
            yield 'unwrap '.$blob => [$blob, true];
        }

        foreach (['A128KW', 'A192KW', 'A256KW'] as $algorithm) {
            yield 'decrypt '.$algorithm => [$algorithm.'.VALUE', false];
            yield 'unwrap empty '.$algorithm => [$algorithm.'.', true];
            yield 'unwrap extra '.$algorithm => [$algorithm.'.VALUE.EXTRA', true];
        }
    }

    #[DataProvider('provideNonAeadReads')]
    public function testNonAeadReadsRejectAad(string $algorithm, bool $wrapping)
    {
        $client = new MockHttpClient(static function (): void {
            self::fail('An unsupported AAD must not reach Azure.');
        }, self::VAULT);
        $kms = new AzureKeyVault($client, $this->tokens(), 'A256GCM', 'A256GCM');
        $ciphertext = new Ciphertext($algorithm.'.VALUE', 'app/v1');

        $this->expectException(UnsupportedOperationException::class);

        $wrapping ? $kms->unwrapDataKey($ciphertext, 'context') : $kms->decrypt($ciphertext, 'context');
    }

    public static function provideNonAeadReads(): iterable
    {
        yield ['RSA-OAEP-256', false];

        foreach (['RSA-OAEP-256', 'A128KW', 'A192KW', 'A256KW'] as $algorithm) {
            yield [$algorithm, true];
        }
    }

    private function tokens(): TokenProviderInterface
    {
        return new class implements TokenProviderInterface {
            public function getToken(): string
            {
                return 'TOKEN';
            }

            public function invalidateToken(string $token): void
            {
            }
        };
    }
}
