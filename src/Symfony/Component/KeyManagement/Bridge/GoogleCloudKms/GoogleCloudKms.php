<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\GoogleCloudKms;

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
 * KMS backend powered by Google Cloud KMS over its REST API.
 *
 * Crypto operations stay server-side: the master key never leaves Google
 * Cloud. The caller passes a {@see HttpClientInterface} (typically scoped to
 * `https://cloudkms.googleapis.com/v1/`) and a {@see TokenProviderInterface}
 * that yields OAuth2 access tokens for the
 * `https://www.googleapis.com/auth/cloudkms` scope.
 *
 * Cloud KMS does not expose a `GenerateDataKey` primitive. The bridge mirrors
 * the Azure Key Vault pattern: a fresh DEK is drawn locally with
 * `random_bytes()` and wrapped via the regular `:encrypt` endpoint.
 *
 * Key identifier format: `$keyId` is the Cloud KMS resource name
 * (`projects/<p>/locations/<l>/keyRings/<r>/cryptoKeys/<k>`). To pin to a
 * specific key version, append the version as a sub-path
 * (`.../cryptoKeyVersions/<v>`); without a version, Cloud KMS uses the
 * primary version of the key.
 *
 * AAD maps to Cloud KMS's `additionalAuthenticatedData`, integrity-protected
 * but not encrypted.
 *
 * On the decrypt path, HTTP 400 and 404 are masked as
 * {@see DecryptionFailedException} so an unknown key id cannot be
 * distinguished from a tampered ciphertext.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class GoogleCloudKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly TokenProviderInterface $tokens,
    ) {
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('Google Cloud KMS does not expose deterministic encryption per call.');
        }

        $body = ['plaintext' => base64_encode($plaintext)];
        if ('' !== $aad) {
            $body['additionalAuthenticatedData'] = base64_encode($aad);
        }

        $data = $this->request('POST', $keyId.':encrypt', $body, $keyId);

        if (!isset($data['ciphertext']) || !\is_string($data['ciphertext'])) {
            throw new RuntimeException('Google Cloud KMS returned a malformed response (missing "ciphertext").');
        }

        return new Ciphertext($data['ciphertext'], $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        $body = ['ciphertext' => $ciphertext->blob];
        if ('' !== $aad) {
            $body['additionalAuthenticatedData'] = base64_encode($aad);
        }

        $data = $this->request('POST', self::stripVersion($ciphertext->keyId).':decrypt', $body, $ciphertext->keyId, true);

        if (!isset($data['plaintext']) || !\is_string($data['plaintext'])) {
            throw new RuntimeException('Google Cloud KMS returned a malformed response (missing "plaintext").');
        }

        $plaintext = base64_decode($data['plaintext'], true);
        if (false === $plaintext) {
            throw new RuntimeException('Google Cloud KMS returned a non-base64 plaintext.');
        }

        return $plaintext;
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        if ($length < 16) {
            throw new InvalidArgumentException(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));
        }

        $plaintext = random_bytes($length);
        $wrapped = $this->encrypt($keyId, $plaintext, $aad);

        return new DataKey($plaintext, $wrapped);
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
    private function request(string $method, string $path, array $body, string $keyId, bool $treatClientErrorAsDecryptionFailure = false): array
    {
        try {
            $response = $this->client->request($method, $path, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->tokens->getToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Google Cloud KMS.', 0, $e);
        }

        if ($treatClientErrorAsDecryptionFailure && (400 === $status || 404 === $status)) {
            throw new DecryptionFailedException();
        }

        if (404 === $status) {
            throw new KeyNotFoundException($keyId);
        }

        if ($status >= 300) {
            try {
                $payload = $response->toArray(false);
            } catch (DecodingExceptionInterface $e) {
                throw new RuntimeException(\sprintf('Google Cloud KMS request failed (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
            }
            $message = (string) ($payload['error']['message'] ?? $payload['error']['status'] ?? 'unknown error');

            throw new RuntimeException(\sprintf('Google Cloud KMS request failed (HTTP %d): "%s".', $status, $message));
        }

        try {
            return $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Google Cloud KMS returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }
    }

    private static function stripVersion(string $keyId): string
    {
        $position = strpos($keyId, '/cryptoKeyVersions/');

        return false === $position ? $keyId : substr($keyId, 0, $position);
    }
}
