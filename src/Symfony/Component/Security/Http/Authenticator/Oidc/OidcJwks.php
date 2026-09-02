<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fetches OpenID Connect signing keys (JWKS) and derives their cache lifetime
 * from the provider's HTTP cache headers.
 *
 * The "oidc" access token handler and the "oidc_login" authenticator both parse
 * and filter JWKS responses through this class, so the two OIDC entry points
 * accept the same key material.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 *
 * @internal
 */
final class OidcJwks
{
    /**
     * Cap the cache lifetime to 30 days to avoid keeping JWKS indefinitely.
     */
    public const MAX_TTL = 30 * 24 * 60 * 60;

    /**
     * JWKS documents are small. This limits untrusted data retained by the cache,
     * as done for the discovery document.
     */
    private const MAX_JWKS_SIZE = 1024 * 1024;

    /**
     * Extracts the signing keys from a JWKS endpoint response, together with the TTL
     * (in seconds) advertised by the provider via "Cache-Control: max-age" or
     * "Expires", or null when none is advertised.
     *
     * @param bool $enforceKeyUsageVerification When true (default, strict), only JWKs whose `use` is "sig" or whose
     *                                          `key_ops` contains "sign"/"verify" are kept. When false (lax), JWKs
     *                                          missing both `use` and `key_ops` are kept too; JWKs explicitly scoped
     *                                          to encryption are dropped either way.
     *
     * @return array{0: list<array<string, mixed>>, 1: int|null}
     *
     * @throws AuthenticationException If the JWKS cannot be fetched or decoded
     */
    public static function fromResponse(ResponseInterface $response, bool $enforceKeyUsageVerification = true): array
    {
        try {
            $headers = $response->getHeaders();
            $payload = $response->getContent();
        } catch (HttpClientExceptionInterface $e) {
            throw new AuthenticationException(\sprintf('The OIDC provider JWKS could not be fetched: "%s"', $e->getMessage()), previous: $e);
        }

        if (self::MAX_JWKS_SIZE < \strlen($payload)) {
            throw new AuthenticationException('The OIDC provider JWKS exceeds the maximum size of 1 MiB.');
        }

        try {
            $keys = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AuthenticationException('The OIDC provider JWKS could not be fetched: invalid JSON: '.$e->getMessage(), previous: $e);
        }

        // a "keys" member that is not a list of JWK objects yields no usable key
        $keys = \is_array($keys['keys'] ?? null) ? array_values(array_filter($keys['keys'], is_array(...))) : [];

        $ttl = null;
        if (preg_match('/max-age=(\d+)/', $headers['cache-control'][0] ?? '', $m)) {
            $ttl = (int) $m[1];
        } elseif (0 < $expires = strtotime($headers['expires'][0] ?? '@0') - time()) {
            $ttl = $expires;
        }

        return [self::filterSignatureKeys($keys, $enforceKeyUsageVerification), $ttl];
    }

    /**
     * Fetches the provider signing keys from the given JWKS URI and adjusts the
     * cache item lifetime from the response headers (capped at 30 days).
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchKeys(HttpClientInterface $httpClient, string $jwksUri, ItemInterface $item, int $defaultTtl = 3600, bool $enforceKeyUsageVerification = true): array
    {
        [$keys, $ttl] = self::fromResponse($httpClient->request('GET', $jwksUri), $enforceKeyUsageVerification);

        $item->expiresAfter(null === $ttl ? $defaultTtl : min($ttl, self::MAX_TTL));

        return $keys;
    }

    /**
     * @param list<array<string, mixed>> $keys
     *
     * @return list<array<string, mixed>>
     */
    private static function filterSignatureKeys(array $keys, bool $enforceKeyUsageVerification): array
    {
        return array_values(array_filter($keys, static function (array $jwk) use ($enforceKeyUsageVerification): bool {
            if ($enforceKeyUsageVerification) {
                if (isset($jwk['use']) && 'sig' === $jwk['use']) {
                    return true;
                }
                if (isset($jwk['key_ops']) && \is_array($jwk['key_ops'])) {
                    return (bool) array_intersect($jwk['key_ops'], ['sign', 'verify']);
                }

                return false;
            }

            if (isset($jwk['use']) && 'enc' === $jwk['use']) {
                return false;
            }
            if (isset($jwk['key_ops']) && \is_array($jwk['key_ops'])) {
                $hasEnc = (bool) array_intersect($jwk['key_ops'], ['encrypt', 'decrypt', 'wrapKey', 'unwrapKey', 'deriveKey', 'deriveBits']);
                $hasSig = (bool) array_intersect($jwk['key_ops'], ['sign', 'verify']);
                if ($hasEnc && !$hasSig) {
                    return false;
                }
            }

            return true;
        }));
    }
}
