<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AzureKeyVault;

use Symfony\Component\KeyManagement\Base64UrlSafe;
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
 * KMS backend powered by Azure Key Vault (and Managed HSM) over its REST API.
 *
 * Crypto operations stay server-side: the master key never leaves the vault.
 * The caller passes a {@see HttpClientInterface} scoped to the vault base URI
 * (e.g. `https://my-vault.vault.azure.net/`) and a {@see TokenProviderInterface}
 * that provides Azure AD bearer tokens for the `https://vault.azure.net`
 * audience.
 *
 * Algorithm matrix:
 *   - RSA keys: `RSA-OAEP-256` (default), `RSA-OAEP`, `RSA1_5`. Suited to
 *     small payloads (config secrets, DEK wrapping). RSA does not support
 *     AAD, so a non-empty `$aad` triggers {@see UnsupportedOperationException}.
 *   - Symmetric AES keys (Managed HSM only): `A256GCM` / `A192GCM` / `A128GCM`
 *     are AEAD and accept AAD natively; CBC variants do not.
 *
 * `generateDataKey()` is implemented locally (Azure has no `GenerateDataKey`
 * primitive): a fresh DEK is drawn with `random_bytes()` and wrapped via
 * `wrapKey`, mirroring the GCP / Managed HSM pattern.
 *
 * Key identifier format: `$keyId` is the key name. To pin to a specific
 * version, append it with a `/` separator (`mykey/abc123def`); without a
 * version, the latest enabled version is used.
 *
 * On the decrypt path, HTTP 400 and 404 are masked as
 * {@see DecryptionFailedException} so that an unknown key id or a tampered
 * ciphertext cannot be distinguished by an attacker. Auth (401/403),
 * throttling (429) and server errors (5xx) bubble up as
 * {@see RuntimeException} so operators see the real cause.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class AzureKeyVault implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    private const array AEAD_ALGORITHMS = ['A128GCM', 'A192GCM', 'A256GCM'];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly TokenProviderInterface $tokens,
        private readonly string $encryptAlgorithm = 'RSA-OAEP-256',
        private readonly string $wrapAlgorithm = 'RSA-OAEP-256',
        private readonly string $apiVersion = '7.4',
    ) {
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('Azure Key Vault does not expose deterministic encryption per call.');
        }
        if ('' !== $aad && !\in_array($this->encryptAlgorithm, self::AEAD_ALGORITHMS, true)) {
            throw new UnsupportedOperationException(\sprintf('Algorithm "%s" cannot enforce AAD; configure an AEAD algorithm (A128GCM, A192GCM, A256GCM) to use AAD with Azure Key Vault.', $this->encryptAlgorithm));
        }

        $body = [
            'alg' => $this->encryptAlgorithm,
            'value' => Base64UrlSafe::encode($plaintext),
        ];
        if ('' !== $aad) {
            $body['aad'] = Base64UrlSafe::encode($aad);
        }

        $data = $this->request('POST', $this->keyPath($keyId, 'encrypt'), $body, $keyId);

        if (!isset($data['value']) || !\is_string($data['value'])) {
            throw new RuntimeException('Azure Key Vault returned a malformed response (missing "value").');
        }

        $blob = $data['value'];
        if (\in_array($this->encryptAlgorithm, self::AEAD_ALGORITHMS, true)) {
            if (!isset($data['iv'], $data['tag']) || !\is_string($data['iv']) || !\is_string($data['tag'])) {
                throw new RuntimeException('Azure Key Vault returned a malformed AEAD response (missing "iv"/"tag").');
            }
            $blob = $this->encryptAlgorithm.'.'.$data['iv'].'.'.$data['tag'].'.'.$data['value'];
        }

        return new Ciphertext($blob, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        $body = $this->buildDecryptBody($ciphertext->blob, $aad, $this->encryptAlgorithm);

        $data = $this->request('POST', $this->keyPath($ciphertext->keyId, 'decrypt'), $body, $ciphertext->keyId, true);

        if (!isset($data['value']) || !\is_string($data['value'])) {
            throw new RuntimeException('Azure Key Vault returned a malformed response (missing "value").');
        }

        $plaintext = Base64UrlSafe::decode($data['value']);
        if (false === $plaintext) {
            throw new RuntimeException('Azure Key Vault returned a non-base64url plaintext.');
        }

        return $plaintext;
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        if ($length < 16) {
            throw new InvalidArgumentException(\sprintf('Data key length must be at least 16 bytes, %d given.', $length));
        }
        if ('' !== $aad && !\in_array($this->wrapAlgorithm, self::AEAD_ALGORITHMS, true)) {
            throw new UnsupportedOperationException(\sprintf('Wrap algorithm "%s" cannot enforce AAD; configure an AEAD wrap algorithm to use AAD with Azure Key Vault.', $this->wrapAlgorithm));
        }

        $plaintext = random_bytes($length);

        $body = [
            'alg' => $this->wrapAlgorithm,
            'value' => Base64UrlSafe::encode($plaintext),
        ];
        if ('' !== $aad) {
            $body['aad'] = Base64UrlSafe::encode($aad);
        }

        $data = $this->request('POST', $this->keyPath($keyId, 'wrapkey'), $body, $keyId);

        if (!isset($data['value']) || !\is_string($data['value'])) {
            throw new RuntimeException('Azure Key Vault returned a malformed response (missing "value").');
        }

        $blob = $data['value'];
        if (\in_array($this->wrapAlgorithm, self::AEAD_ALGORITHMS, true)) {
            if (!isset($data['iv'], $data['tag']) || !\is_string($data['iv']) || !\is_string($data['tag'])) {
                throw new RuntimeException('Azure Key Vault returned a malformed AEAD wrap response (missing "iv"/"tag").');
            }
            $blob = $this->wrapAlgorithm.'.'.$data['iv'].'.'.$data['tag'].'.'.$data['value'];
        }

        return new DataKey($plaintext, new Ciphertext($blob, $keyId));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        $body = $this->buildDecryptBody($wrapped->blob, $aad, $this->wrapAlgorithm);

        $data = $this->request('POST', $this->keyPath($wrapped->keyId, 'unwrapkey'), $body, $wrapped->keyId, true);

        if (!isset($data['value']) || !\is_string($data['value'])) {
            throw new RuntimeException('Azure Key Vault returned a malformed response (missing "value").');
        }

        $plaintext = Base64UrlSafe::decode($data['value']);
        if (false === $plaintext) {
            throw new RuntimeException('Azure Key Vault returned a non-base64url plaintext.');
        }

        return new DataKey($plaintext, $wrapped);
    }

    private function keyPath(string $keyId, string $operation): string
    {
        $segments = explode('/', $keyId);
        if (\count($segments) > 2 || \in_array('', $segments, true)) {
            throw new InvalidArgumentException(\sprintf('Azure Key Vault key id "%s" must be either "<name>" or "<name>/<version>".', $keyId));
        }
        $name = rawurlencode($segments[0]);
        $version = isset($segments[1]) ? '/'.rawurlencode($segments[1]) : '';

        return \sprintf('keys/%s%s/%s?api-version=%s', $name, $version, $operation, rawurlencode($this->apiVersion));
    }

    /**
     * Builds the decrypt/unwrap body, auto-detecting the algorithm from the
     * AEAD blob prefix when present so that ciphertexts written under a
     * previous configuration can still be decoded after a config rotation.
     *
     * @return array<string, mixed>
     */
    private function buildDecryptBody(string $blob, string $aad, string $fallbackAlgorithm): array
    {
        $aeadPrefix = self::detectAeadAlgorithm($blob);

        if (null !== $aeadPrefix) {
            [$algorithm, $iv, $tag, $value] = $aeadPrefix;
            $body = ['alg' => $algorithm, 'iv' => $iv, 'tag' => $tag, 'value' => $value];
            if ('' !== $aad) {
                $body['aad'] = Base64UrlSafe::encode($aad);
            }

            return $body;
        }

        if (\in_array($fallbackAlgorithm, self::AEAD_ALGORITHMS, true)) {
            throw new DecryptionFailedException();
        }

        if ('' !== $aad) {
            throw new UnsupportedOperationException(\sprintf('Algorithm "%s" cannot enforce AAD; configure an AEAD algorithm (A128GCM, A192GCM, A256GCM) to use AAD with Azure Key Vault.', $fallbackAlgorithm));
        }

        return ['alg' => $fallbackAlgorithm, 'value' => $blob];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     */
    private static function detectAeadAlgorithm(string $blob): ?array
    {
        if (!str_contains($blob, '.')) {
            return null;
        }
        $parts = explode('.', $blob, 4);
        if (4 !== \count($parts) || !\in_array($parts[0], self::AEAD_ALGORITHMS, true)) {
            return null;
        }

        return $parts;
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
            throw new RuntimeException('Failed to reach Azure Key Vault.', 0, $e);
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
                throw new RuntimeException(\sprintf('Azure Key Vault request failed (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
            }
            $message = (string) ($payload['error']['message'] ?? $payload['error']['code'] ?? 'unknown error');

            throw new RuntimeException(\sprintf('Azure Key Vault request failed (HTTP %d): "%s".', $status, $message));
        }

        try {
            return $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Azure Key Vault returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }
    }
}
