<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Scaleway;

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
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * KMS backend powered by Scaleway Key Manager.
 *
 * Crypto operations stay server-side: the key never leaves Scaleway.
 * The caller passes an {@see HttpClientInterface} scoped to the regional Key Manager base URL (`https://api.scaleway.com/key-manager/v1alpha1/regions/<region>/`) plus the secret key of an IAM API key; this class adds the auth header on every request.
 *
 * AAD maps to Scaleway's `associated_data`, authenticated by AES-256-GCM.
 *
 * Data keys are generated locally with `random_bytes()` and wrapped through the `encrypt` endpoint.
 * Scaleway's `generate-data-key` endpoint is not used: it only makes 32-byte keys, cannot bind associated data and refuses asymmetric keys.
 *
 * On the decrypt path, HTTP 400 and 404 are masked as {@see DecryptionFailedException} (key-enumeration oracle hardening).
 * Auth (401/403), rate limits (429) and server errors (5xx) bubble up as {@see RuntimeException}.
 *
 * @author Marcus Stöhr <marcus@fischteich.net>
 *
 * @experimental
 */
final class ScalewayKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        #[\SensitiveParameter] private readonly string $secretKey,
    ) {
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('Scaleway Key Manager does not expose deterministic encryption.');
        }

        $data = $this->request($keyId, 'encrypt', ['plaintext' => base64_encode($plaintext)] + self::associatedData($aad));

        if (!isset($data['ciphertext']) || !\is_string($data['ciphertext'])) {
            throw new RuntimeException('Scaleway Key Manager returned a malformed response (missing "ciphertext").');
        }

        return new Ciphertext($data['ciphertext'], $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        $data = $this->request($ciphertext->keyId, 'decrypt', ['ciphertext' => $ciphertext->blob] + self::associatedData($aad), true);

        return self::decodePlaintext($data);
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        if ($length < 16) {
            throw new InvalidArgumentException(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));
        }

        $plaintext = random_bytes($length);

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $keyId, string $operation, array $body, bool $treatClientErrorAsDecryptionFailure = false): array
    {
        try {
            $response = $this->client->request('POST', \sprintf('keys/%s/%s', rawurlencode($keyId), $operation), [
                'headers' => ['X-Auth-Token' => $this->secretKey],
                'json' => $body,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Scaleway Key Manager.', 0, $e);
        }

        if ($treatClientErrorAsDecryptionFailure && (400 === $status || 404 === $status)) {
            throw new DecryptionFailedException();
        }

        if (404 === $status) {
            throw new KeyNotFoundException($keyId);
        }

        if ($status >= 300) {
            try {
                $error = $response->toArray(false);
            } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
                throw new RuntimeException(\sprintf('Scaleway Key Manager request failed (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
            }
            // the message is generic for a precondition failure, the help message tells which one
            $message = implode(': ', array_filter([$error['message'] ?? null, $error['help_message'] ?? null], static fn ($part): bool => \is_string($part) && '' !== $part));

            throw new RuntimeException(\sprintf('Scaleway Key Manager request failed (HTTP %d): "%s".', $status, $message ?: 'unknown error'));
        }

        try {
            return $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Scaleway Key Manager returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Failed to read the Scaleway Key Manager response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }
    }

    /**
     * @return array{associated_data?: string}
     */
    private static function associatedData(string $aad): array
    {
        return '' === $aad ? [] : ['associated_data' => base64_encode($aad)];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodePlaintext(array $data): string
    {
        if (!isset($data['plaintext']) || !\is_string($data['plaintext'])) {
            throw new RuntimeException('Scaleway Key Manager returned a malformed response (missing "plaintext").');
        }

        $plaintext = base64_decode($data['plaintext'], true);
        if (false === $plaintext) {
            throw new RuntimeException('Scaleway Key Manager returned a non-base64 plaintext.');
        }

        return $plaintext;
    }
}
