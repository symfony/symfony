<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AwsKms;

use AsyncAws\Core\Exception\Http\HttpException;
use AsyncAws\Core\Exception\Http\NetworkException;
use AsyncAws\Kms\Exception\IncorrectKeyException;
use AsyncAws\Kms\Exception\InvalidCiphertextException;
use AsyncAws\Kms\Exception\NotFoundException;
use AsyncAws\Kms\Input\DecryptRequest;
use AsyncAws\Kms\Input\EncryptRequest;
use AsyncAws\Kms\Input\GenerateDataKeyRequest;
use AsyncAws\Kms\KmsClient;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\Exception\UnsupportedOperationException;

/**
 * KMS backend powered by AWS Key Management Service via async-aws.
 *
 * Crypto operations stay server-side: the master key never leaves AWS KMS.
 * The caller passes a pre-configured {@see KmsClient} (region, credentials,
 * endpoint override for LocalStack, ...).
 *
 * AAD is forwarded as AWS's `EncryptionContext`, which only accepts
 * `array<string, string>`. Since this bridge accepts opaque bytes (per the
 * {@see EncrypterInterface} / {@see DecrypterInterface} contract), the AAD is base64-encoded under a single
 * conventional key. The same encoding is applied on decrypt so the AWS-side
 * comparison succeeds when (and only when) the same bytes are provided.
 *
 * Exception mapping follows the {@see EncrypterInterface} / {@see DecrypterInterface} contract:
 *   - {@see NotFoundException} on encrypt → {@see KeyNotFoundException};
 *   - {@see NotFoundException}, {@see InvalidCiphertextException} or
 *     {@see IncorrectKeyException} on decrypt → {@see DecryptionFailedException}
 *     (NotFoundException is masked to avoid a key-enumeration oracle);
 *   - any other AWS error → {@see RuntimeException}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class AwsKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    private const string AAD_CONTEXT_KEY = 'aad';

    public function __construct(
        private readonly KmsClient $client,
    ) {
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('AWS KMS does not expose deterministic encryption per call; the symmetric Encrypt API always picks a random nonce.');
        }

        $input = [
            'KeyId' => $keyId,
            'Plaintext' => $plaintext,
        ];
        if ('' !== $aad) {
            $input['EncryptionContext'] = self::encodeAad($aad);
        }

        try {
            $response = $this->client->encrypt(new EncryptRequest($input));
            $blob = $response->getCiphertextBlob();
        } catch (NotFoundException $e) {
            throw new KeyNotFoundException($keyId, $e);
        } catch (HttpException|NetworkException $e) {
            throw new RuntimeException(\sprintf('AWS KMS encrypt failed: "%s".', $e->getMessage()), 0, $e);
        }

        if (null === $blob) {
            throw new RuntimeException('AWS KMS returned an empty ciphertext blob.');
        }

        return new Ciphertext($blob, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        $input = [
            'KeyId' => $ciphertext->keyId,
            'CiphertextBlob' => $ciphertext->blob,
        ];
        if ('' !== $aad) {
            $input['EncryptionContext'] = self::encodeAad($aad);
        }

        try {
            $response = $this->client->decrypt(new DecryptRequest($input));
            $plaintext = $response->getPlaintext();
        } catch (NotFoundException|InvalidCiphertextException|IncorrectKeyException $e) {
            throw new DecryptionFailedException(previous: $e);
        } catch (HttpException|NetworkException $e) {
            throw new RuntimeException(\sprintf('AWS KMS decrypt failed: "%s".', $e->getMessage()), 0, $e);
        }

        if (null === $plaintext) {
            throw new RuntimeException('AWS KMS returned an empty plaintext.');
        }

        return $plaintext;
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        if ($length < 16) {
            throw new InvalidArgumentException(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));
        }

        $input = [
            'KeyId' => $keyId,
            'NumberOfBytes' => $length,
        ];
        if ('' !== $aad) {
            $input['EncryptionContext'] = self::encodeAad($aad);
        }

        try {
            $response = $this->client->generateDataKey(new GenerateDataKeyRequest($input));
            $plaintext = $response->getPlaintext();
            $blob = $response->getCiphertextBlob();
        } catch (NotFoundException $e) {
            throw new KeyNotFoundException($keyId, $e);
        } catch (HttpException|NetworkException $e) {
            throw new RuntimeException(\sprintf('AWS KMS GenerateDataKey failed: "%s".', $e->getMessage()), 0, $e);
        }

        if (null === $plaintext || null === $blob) {
            throw new RuntimeException('AWS KMS returned a malformed GenerateDataKey response.');
        }

        return new DataKey($plaintext, new Ciphertext($blob, $keyId));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    /**
     * @return array<string, string>
     */
    private static function encodeAad(string $aad): array
    {
        return [self::AAD_CONTEXT_KEY => base64_encode($aad)];
    }
}
