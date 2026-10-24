<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\HashiCorpVault;

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
 * KMS backend powered by HashiCorp Vault's Transit secret engine.
 *
 * Crypto operations stay server-side: the master key never leaves Vault. The
 * caller passes a pre-configured {@see HttpClientInterface} (typically scoped
 * to the Vault base URL via `framework.http_client.scoped_clients`) plus a
 * {@see TokenProviderInterface}; this class adds the auth headers on every request.
 *
 * Vault answers an expired or revoked token with a 403 whose errors contain "invalid token".
 * The token is then invalidated and the request is retried once with a new one.
 * Other 403s, like a policy that lacks a capability, are not retried since a new token would not fix them.
 *
 * AAD is prefixed with {@see self::CONTEXT_PREFIX} and passed as Vault's `context` parameter (HKDF input).
 * Nonempty AAD is refused for keys not created with `derived=true`, since Vault silently ignores the context on those.
 *
 * Every request has a nonempty context, as required by derived keys, and different AAD bytes always produce different contexts.
 * A derived key is therefore usable when the caller passes no AAD.
 * With `convergent_encryption=true`, equal plaintexts with the same AAD produce equal ciphertexts.
 * See the bridge README for migration guidance and the security differences from AEAD AAD.
 *
 * On the decrypt path, only HTTP 400 is masked as {@see DecryptionFailedException}
 * (key-enumeration oracle hardening); auth (401/403), rate limits (429) and
 * server errors (5xx) bubble up as {@see RuntimeException} so operators see
 * the real issue. 404 is also masked on the decrypt path for the same reason.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class TransitKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    /**
     * Vault `datakey` only accepts 128, 256 or 512 bits.
     *
     * @see https://developer.hashicorp.com/vault/api-docs/secret/transit#bits-1
     */
    private const array SUPPORTED_DATA_KEY_BITS = [128, 256, 512];

    private const string CONTEXT_PREFIX = "symfony/key-management/hashicorp-vault/transit/v1\0";

    /**
     * @var array<string, bool> Whether each key was created with `derived=true`, cached per key name
     */
    private array $keyIsDerived = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly TokenProviderInterface $tokens,
        private readonly string $mountPoint = 'transit',
        private readonly ?string $namespace = null,
    ) {
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        if ($deterministic) {
            throw new UnsupportedOperationException('Vault Transit cannot offer per-call deterministic encryption: convergent mode must be set at key creation (`derived=true, convergent_encryption=true`).');
        }

        $body = ['plaintext' => base64_encode($plaintext), 'context' => base64_encode($this->contextFor($keyId, $aad, false))];

        $data = $this->request('POST', \sprintf('%s/encrypt/%s', self::encodePath($this->mountPoint), rawurlencode($keyId)), $body, $keyId);

        if (!isset($data['ciphertext']) || !\is_string($data['ciphertext'])) {
            throw new RuntimeException('Vault returned a malformed response (missing "ciphertext").');
        }

        return new Ciphertext($data['ciphertext'], $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        $body = ['ciphertext' => $ciphertext->blob, 'context' => base64_encode($this->contextFor($ciphertext->keyId, $aad, true))];

        $data = $this->request('POST', \sprintf('%s/decrypt/%s', self::encodePath($this->mountPoint), rawurlencode($ciphertext->keyId)), $body, $ciphertext->keyId, true);

        if (!isset($data['plaintext']) || !\is_string($data['plaintext'])) {
            throw new RuntimeException('Vault returned a malformed response (missing "plaintext").');
        }

        $plaintext = base64_decode($data['plaintext'], true);
        if (false === $plaintext) {
            throw new RuntimeException('Vault returned a non-base64 plaintext.');
        }

        return $plaintext;
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        $bits = $length * 8;
        if (!\in_array($bits, self::SUPPORTED_DATA_KEY_BITS, true)) {
            throw new InvalidArgumentException(\sprintf('Vault Transit can only generate data keys of 16, 32 or 64 bytes, %d given.', $length));
        }

        $body = ['bits' => $bits, 'context' => base64_encode($this->contextFor($keyId, $aad, false))];

        $data = $this->request('POST', \sprintf('%s/datakey/plaintext/%s', self::encodePath($this->mountPoint), rawurlencode($keyId)), $body, $keyId);

        if (!isset($data['plaintext'], $data['ciphertext']) || !\is_string($data['plaintext']) || !\is_string($data['ciphertext'])) {
            throw new RuntimeException('Vault returned a malformed datakey response.');
        }

        $plaintext = base64_decode($data['plaintext'], true);
        if (false === $plaintext) {
            throw new RuntimeException('Vault returned a non-base64 plaintext.');
        }

        return new DataKey($plaintext, new Ciphertext($data['ciphertext'], $keyId));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }

    private function contextFor(string $keyId, string $aad, bool $treatClientErrorAsDecryptionFailure): string
    {
        if ('' !== $aad) {
            $this->assertAadIsEnforceable($keyId, $treatClientErrorAsDecryptionFailure);
        }

        return self::CONTEXT_PREFIX.$aad;
    }

    /**
     * Checks that a key honours the `context` parameter before AAD is relied on.
     *
     * Vault only honours it on keys created with `derived=true`; on any other key it silently
     * ignores it, so an AAD mismatch would decrypt fine. The key configuration is read once per
     * key name and cached for the process.
     */
    private function assertAadIsEnforceable(string $keyId, bool $treatClientErrorAsDecryptionFailure): void
    {
        $this->keyIsDerived[$keyId] ??= true === ($this->request('GET', \sprintf('%s/keys/%s', self::encodePath($this->mountPoint), rawurlencode($keyId)), [], $keyId, $treatClientErrorAsDecryptionFailure)['derived'] ?? false);

        if (!$this->keyIsDerived[$keyId]) {
            throw new UnsupportedOperationException(\sprintf('Vault Transit silently ignores the context parameter on non-derived keys, so AAD cannot be enforced for key "%s"; recreate the key with "derived=true" or pass an empty AAD.', $keyId));
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed> The `data` field of the Vault response
     */
    private function request(string $method, string $path, array $body, string $keyId, bool $treatClientErrorAsDecryptionFailure = false): array
    {
        $token = $this->getToken();
        $response = $this->send($method, $path, $body, $token);

        if (self::isInvalidTokenResponse($response)) {
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

                if (self::isInvalidTokenResponse($response)) {
                    $this->tokens->invalidateToken($newToken);
                }
            }
        }

        $status = $response->getStatusCode();

        if (404 === $status) {
            if ($treatClientErrorAsDecryptionFailure) {
                throw new DecryptionFailedException();
            }

            throw new KeyNotFoundException($keyId);
        }

        if ($status >= 300) {
            if ($treatClientErrorAsDecryptionFailure && 400 === $status) {
                throw new DecryptionFailedException();
            }

            $message = implode('; ', self::errors($response));

            throw new RuntimeException(\sprintf('Vault request failed (HTTP %d): "%s".', $status, $message ?: 'unknown error'));
        }

        try {
            $payload = $response->toArray();
        } catch (DecodingExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Vault returned a non-JSON response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Failed to read the Vault response (HTTP %d) for "%s".', $status, $response->getInfo('url')), 0, $e);
        }

        if (!\is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Vault returned a malformed response (missing "data" object).');
        }

        return $payload['data'];
    }

    private function getToken(): string
    {
        try {
            return $this->tokens->getToken();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Vault.', 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function send(string $method, string $path, array $body, #[\SensitiveParameter] string $token): ResponseInterface
    {
        $headers = ['X-Vault-Token' => $token];
        if (null !== $this->namespace) {
            $headers['X-Vault-Namespace'] = $this->namespace;
        }

        $options = ['headers' => $headers, 'max_redirects' => 0];
        if ($body) {
            $options['json'] = $body;
        }

        try {
            $response = $this->client->request($method, $path, $options);
            $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException('Failed to reach Vault.', 0, $e);
        }

        return $response;
    }

    private static function isInvalidTokenResponse(ResponseInterface $response): bool
    {
        if (403 !== $response->getStatusCode()) {
            return false;
        }

        try {
            $errors = self::errors($response);
        } catch (RuntimeException) {
            return false;
        }

        foreach ($errors as $error) {
            if (str_contains($error, 'invalid token')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function errors(ResponseInterface $response): array
    {
        try {
            $errors = $response->toArray(false)['errors'] ?? [];
        } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('Vault request failed (HTTP %d) for "%s".', $response->getStatusCode(), $response->getInfo('url')), 0, $e);
        }

        return \is_array($errors) ? array_values(array_filter($errors, \is_string(...))) : [];
    }

    /**
     * Encodes each path segment individually.
     *
     * Nested mount points (`transit/v2`, `kms/transit`, ...) keep their `/`
     * separators while any special characters inside a segment are properly
     * percent-encoded.
     */
    private static function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
