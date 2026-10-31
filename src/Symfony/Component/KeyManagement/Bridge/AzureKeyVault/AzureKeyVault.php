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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * KMS backend powered by Azure Key Vault (and Managed HSM) over its REST API.
 *
 * Crypto operations stay server-side: the master key never leaves the vault.
 * The caller passes a {@see HttpClientInterface} scoped to the vault or Managed HSM
 * base URI (e.g. `https://my-vault.vault.azure.net/`) and a {@see TokenProviderInterface}
 * for its audience.
 *
 * Algorithm matrix:
 *   - RSA keys: `RSA-OAEP-256` (default). Suited to
 *     small payloads (config secrets, DEK wrapping). RSA does not support
 *     AAD, so a non-empty `$aad` triggers {@see UnsupportedOperationException}.
 *   - Symmetric AES keys (Managed HSM or Key Vault Premium in preview):
 *     `A256GCM` / `A192GCM` / `A128GCM` are AEAD and accept AAD natively.
 *     `A128KW` / `A192KW` / `A256KW` are accepted only for data-key wrapping and have no AAD support.
 *
 * `generateDataKey()` is implemented locally (Azure has no `GenerateDataKey`
 * primitive): a fresh DEK is drawn with `random_bytes()` and wrapped via
 * `wrapKey`, mirroring the GCP / Managed HSM pattern. `wrapDataKey()` goes
 * through the same `wrapKey`, never through `encrypt`.
 *
 * Key identifier format: `$keyId` is the key name. To select a specific
 * version for a write, append it with a `/` separator (`mykey/abc123def`).
 * A versionless write uses the latest version. The recorded key id always
 * carries a version, so a rotation leaves earlier data readable.
 *
 * On the decrypt path, HTTP 400 and 404 are masked as
 * {@see DecryptionFailedException} so that an unknown key id or a tampered
 * ciphertext cannot be distinguished by an attacker.
 * A 401 invalidates the token and is retried once with a new token.
 * Auth failures that remain, throttling (429) and server errors (5xx) bubble up as {@see RuntimeException} so operators see the real cause.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class AzureKeyVault implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    private const array AEAD_ALGORITHMS = ['A128GCM', 'A192GCM', 'A256GCM'];
    private const array ENCRYPT_ALGORITHMS = ['RSA-OAEP-256', ...self::AEAD_ALGORITHMS];
    private const array WRAP_ALGORITHMS = [...self::ENCRYPT_ALGORITHMS, 'A128KW', 'A192KW', 'A256KW'];

    // See https://learn.microsoft.com/azure/key-vault/general/about-keys-secrets-certificates#object-identifiers
    private const string KEY_ID_PATTERN = '~\A([0-9A-Za-z-]{1,127})(?:/(?1))?\z~';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly TokenProviderInterface $tokens,
        private readonly string $encryptAlgorithm = 'RSA-OAEP-256',
        private readonly string $wrapAlgorithm = 'RSA-OAEP-256',
        private readonly string $apiVersion = '7.4',
    ) {
        self::validateAlgorithm($encryptAlgorithm, self::ENCRYPT_ALGORITHMS, 'encryption');
        self::validateAlgorithm($wrapAlgorithm, self::WRAP_ALGORITHMS, 'wrapping');
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = ''): Ciphertext
    {
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

        $blob = $this->encryptAlgorithm.'.'.$data['value'];
        if (\in_array($this->encryptAlgorithm, self::AEAD_ALGORITHMS, true)) {
            if (!isset($data['iv'], $data['tag']) || !\is_string($data['iv']) || '' === $data['iv'] || !\is_string($data['tag']) || '' === $data['tag']) {
                throw new RuntimeException('Azure Key Vault returned a malformed AEAD response (missing "iv"/"tag").');
            }
            $blob = $this->encryptAlgorithm.'.'.$data['iv'].'.'.$data['tag'].'.'.$data['value'];
        } elseif ('' === $data['value']) {
            throw new RuntimeException('Azure Key Vault returned an empty ciphertext.');
        }

        return new Ciphertext($blob, $this->resolvedKeyId($data, $keyId));
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        if (!self::isKeyId($ciphertext->keyId)) {
            throw new DecryptionFailedException();
        }

        $body = $this->buildDecryptBody($ciphertext->blob, $aad, self::ENCRYPT_ALGORITHMS);

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

        $plaintext = random_bytes($length);

        return new DataKey($plaintext, $this->wrapDataKey($keyId, $plaintext, $aad));
    }

    public function wrapDataKey(string $keyId, #[\SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
    {
        if ('' !== $aad && !\in_array($this->wrapAlgorithm, self::AEAD_ALGORITHMS, true)) {
            throw new UnsupportedOperationException(\sprintf('Wrap algorithm "%s" cannot enforce AAD; configure an AEAD wrap algorithm to use AAD with Azure Key Vault.', $this->wrapAlgorithm));
        }

        $body = [
            'alg' => $this->wrapAlgorithm,
            'value' => Base64UrlSafe::encode($dataKey),
        ];
        if ('' !== $aad) {
            $body['aad'] = Base64UrlSafe::encode($aad);
        }

        $data = $this->request('POST', $this->keyPath($keyId, 'wrapkey'), $body, $keyId);

        if (!isset($data['value']) || !\is_string($data['value'])) {
            throw new RuntimeException('Azure Key Vault returned a malformed response (missing "value").');
        }

        $blob = $this->wrapAlgorithm.'.'.$data['value'];
        if (\in_array($this->wrapAlgorithm, self::AEAD_ALGORITHMS, true)) {
            if (!isset($data['iv'], $data['tag']) || !\is_string($data['iv']) || '' === $data['iv'] || !\is_string($data['tag']) || '' === $data['tag']) {
                throw new RuntimeException('Azure Key Vault returned a malformed AEAD wrap response (missing "iv"/"tag").');
            }
            $blob = $this->wrapAlgorithm.'.'.$data['iv'].'.'.$data['tag'].'.'.$data['value'];
        } elseif ('' === $data['value']) {
            throw new RuntimeException('Azure Key Vault returned an empty wrapped data key.');
        }

        return new Ciphertext($blob, $this->resolvedKeyId($data, $keyId));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        if (!self::isKeyId($wrapped->keyId)) {
            throw new DecryptionFailedException();
        }

        $body = $this->buildDecryptBody($wrapped->blob, $aad, self::WRAP_ALGORITHMS);

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

    /**
     * @param list<string> $supported
     */
    private static function validateAlgorithm(string $algorithm, array $supported, string $operation): void
    {
        if (!\in_array($algorithm, $supported, true)) {
            throw new InvalidArgumentException(\sprintf('The "%s" algorithm must be one of "%s", "%s" given.', $operation, implode('", "', $supported), $algorithm));
        }
    }

    private function keyPath(string $keyId, string $operation): string
    {
        if (!self::isKeyId($keyId)) {
            throw new InvalidArgumentException(\sprintf('Azure Key Vault key id "%s" must be either "<name>" or "<name>/<version>".', $keyId));
        }
        $segments = explode('/', $keyId);
        $name = rawurlencode($segments[0]);
        $version = isset($segments[1]) ? '/'.rawurlencode($segments[1]) : '';

        return \sprintf('keys/%s%s/%s?api-version=%s', $name, $version, $operation, rawurlencode($this->apiVersion));
    }

    private static function isKeyId(string $keyId): bool
    {
        return 1 === preg_match(self::KEY_ID_PATTERN, $keyId);
    }

    /**
     * Returns the key id to record in a ciphertext, pinned to the key version Azure used.
     *
     * Azure reports that version in `kid` when the request did not name one. Recording it keeps later reads
     * addressed to the version that produced the data, which a rotation would otherwise break.
     * A request that already named a version needs nothing from `kid`, so an unusable one is not fatal there.
     */
    private function resolvedKeyId(array $data, string $requestedKeyId): string
    {
        [$requestedName, $requestedVersion] = array_pad(explode('/', $requestedKeyId, 2), 2, null);

        $path = \is_string($data['kid'] ?? null) ? parse_url($data['kid'], \PHP_URL_PATH) : null;
        $keyId = \is_string($path) && 0 === strncasecmp($path, '/keys/', 6) ? substr($path, 6) : '';

        if (!str_contains($keyId, '/') || !self::isKeyId($keyId)) {
            if (null !== $requestedVersion) {
                return $requestedKeyId;
            }

            throw new RuntimeException('Azure Key Vault did not report the version of the key it used.');
        }

        [$name, $version] = explode('/', $keyId, 2);
        if (0 !== strcasecmp($name, $requestedName) || (null !== $requestedVersion && 0 !== strcasecmp($version, $requestedVersion))) {
            throw new RuntimeException('Azure Key Vault returned an unexpected key identifier.');
        }

        return null !== $requestedVersion ? $requestedKeyId : $requestedName.'/'.$version;
    }

    /**
     * Builds the decrypt/unwrap body from the algorithm recorded in the blob.
     *
     * @param list<string> $supported
     *
     * @return array<string, mixed>
     */
    private function buildDecryptBody(string $blob, string $aad, array $supported): array
    {
        $parts = explode('.', $blob, 5);
        $algorithm = array_shift($parts);

        if (!\in_array($algorithm, $supported, true)) {
            throw new DecryptionFailedException();
        }

        if (\in_array($algorithm, self::AEAD_ALGORITHMS, true)) {
            if (3 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
                throw new DecryptionFailedException();
            }

            [$iv, $tag, $value] = $parts;
            $body = ['alg' => $algorithm, 'iv' => $iv, 'tag' => $tag, 'value' => $value];
            if ('' !== $aad) {
                $body['aad'] = Base64UrlSafe::encode($aad);
            }

            return $body;
        }

        if (1 !== \count($parts) || '' === $parts[0]) {
            throw new DecryptionFailedException();
        }

        if ('' !== $aad) {
            throw new UnsupportedOperationException(\sprintf('Algorithm "%s" cannot enforce AAD; configure an AEAD algorithm (A128GCM, A192GCM, A256GCM) to use AAD with Azure Key Vault.', $algorithm));
        }

        return ['alg' => $algorithm, 'value' => $parts[0]];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body, string $keyId, bool $treatClientErrorAsDecryptionFailure = false): array
    {
        $token = $this->getToken();
        $response = $this->send($method, $path, $body, $token);

        if (401 === $response->getStatusCode()) {
            $this->tokens->invalidateToken($token);

            try {
                $newToken = $this->getToken();
            } catch (\Throwable $e) {
                $response->cancel();

                throw $e;
            }

            if ($newToken === $token) {
                // retrying with the same token cannot succeed, and it must not stay cached for the next call
                $this->tokens->invalidateToken($newToken);
            } else {
                $response->cancel();
                $response = $this->send($method, $path, $body, $newToken);

                if (401 === $response->getStatusCode()) {
                    $this->tokens->invalidateToken($newToken);
                }
            }
        }

        $status = $response->getStatusCode();

        if ($treatClientErrorAsDecryptionFailure && (400 === $status || 404 === $status)) {
            throw new DecryptionFailedException();
        }

        if (404 === $status) {
            throw new KeyNotFoundException($keyId);
        }

        if ($status >= 300) {
            try {
                $payload = $response->toArray(false);
            } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
                throw new RuntimeException(\sprintf('Azure Key Vault request failed (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
            }
            $message = $payload['error']['message'] ?? $payload['error']['code'] ?? null;
            $message = \is_string($message) ? $message : 'unknown error';

            throw new RuntimeException(\sprintf('Azure Key Vault request failed (HTTP %d): "%s".', $status, $message));
        }

        try {
            return $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Azure Key Vault returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Failed to read the Azure Key Vault response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }
    }

    private function getToken(): string
    {
        try {
            return $this->tokens->getToken();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Azure Key Vault.', 0, $e);
        }
    }

    private function send(string $method, string $path, array $body, #[\SensitiveParameter] string $token): ResponseInterface
    {
        try {
            $response = $this->client->request($method, $path, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'max_redirects' => 0,
            ]);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Azure Key Vault.', 0, $e);
        }

        return $response;
    }
}
