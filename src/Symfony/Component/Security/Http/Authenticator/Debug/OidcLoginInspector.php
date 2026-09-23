<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Debug;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Describes the OIDC login of a firewall for the profiler.
 *
 * The description covers the configuration, the discovery document and the signing keys
 * held in cache, the calls made to the provider during the request, and the tokens the
 * logged-in user holds. Nothing here contacts the provider nor fills the cache: what is
 * not cached is reported as such. No token is reported raw either, see
 * {@see OidcTokenDescriber}.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcLoginInspector
{
    private const KEY_FIELDS = ['kid', 'kty', 'alg', 'use', 'crv'];
    private const TOKEN_ATTRIBUTES = ['oidc_id_token', 'oidc_access_token', 'oidc_access_token_type', 'oidc_access_token_expires_at', 'oidc_refresh_token', 'oidc_acr', 'oidc_amr'];

    private readonly ClockInterface $clock;

    /**
     * @param array<string, mixed> $config The options of the "oidc_login" firewall, secrets excluded
     */
    public function __construct(
        private readonly string $firewallName,
        private readonly OidcDiscovery $discovery,
        private readonly ?OidcSignatureVerifier $signatureVerifier,
        private readonly TraceableOidcClient $client,
        private readonly array $config = [],
        ?ClockInterface $clock = null,
    ) {
        if (null === $clock && !class_exists(Clock::class)) {
            throw new \LogicException(\sprintf('The "symfony/clock" component is required to build "%s" without a clock. Try running "composer require symfony/clock", or pass any PSR-20 clock to the constructor.', self::class));
        }

        $this->clock = $clock ?? new Clock();
    }

    public function getFirewallName(): string
    {
        return $this->firewallName;
    }

    /**
     * @param TokenInterface|null $token The security token of the request, if any
     *
     * @return array<string, mixed> Scalars and arrays only, ready to be stored in a profile
     */
    public function inspect(?TokenInterface $token): array
    {
        $now = $this->clock->now()->getTimestamp();

        $config = $this->config;
        try {
            $config['client_authentication'] = $this->client->getClientAuthenticationMethod();
        } catch (\Throwable) {
            $config['client_authentication'] = 'unknown';
        }

        $discovery = $this->guard(fn (): array => $this->inspectDiscovery($now));
        $tokens = $this->guard(fn (): ?array => $this->inspectToken($token, $now));
        $jwks = null !== $this->signatureVerifier
            ? $this->guard(fn (): array => $this->inspectJwks($discovery['document'] ?? null, $tokens['id_token']['header']['kid'] ?? null, $now))
            : null;

        try {
            $warnings = $this->findWarnings($config, $discovery, $tokens);
        } catch (\Throwable $e) {
            $warnings = [\sprintf('The warnings could not be computed: %s', $e->getMessage())];
        }

        return [
            'firewall' => $this->firewallName,
            'config' => $config,
            'discovery' => $discovery,
            'jwks' => $jwks,
            'calls' => $this->client->getCalls(),
            'token' => $tokens,
            'warnings' => $warnings,
        ];
    }

    /**
     * A debugging read never breaks the request: a failing block reports its error instead.
     *
     * @param \Closure(): ?array $inspect
     *
     * @return array<string, mixed>|null
     */
    private function guard(\Closure $inspect): ?array
    {
        try {
            return $inspect();
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-suppress UndefinedThisPropertyFetch, UndefinedMethod The closure below is bound to the discovery
     */
    private function inspectDiscovery(int $now): array
    {
        // the cache key and the decoding are private to the discovery, and stay so: the
        // state is read from inside, as the traceable firewall reads the firewall context
        $state = \Closure::bind(fn (): array => [
            'issuer' => $this->issuer,
            'url' => $this->openIdConfigurationUrl,
            'cache' => $this->cache,
            'cache_key' => $this->getCacheKey(),
            'document' => $this->configuration,
            'decode' => $this->decode(...),
        ], $this->discovery, OidcDiscovery::class)();

        $document = $state['document'];
        // memoized: loaded during this request; unknown: the pool cannot be read without a callback
        $status = null !== $document ? 'memoized' : 'unknown';
        $expiresAt = null;

        if ($state['cache'] instanceof CacheItemPoolInterface) {
            [$cached, $metadata] = $this->peek($state['cache'], $state['cache_key']);

            if (\is_array($cached) && \is_string($cached['payload'] ?? null)) {
                $status = 'cached';
                $expiresAt = isset($metadata[ItemInterface::METADATA_EXPIRY]) ? (int) $metadata[ItemInterface::METADATA_EXPIRY] : null;
                $document ??= $state['decode']($cached['payload']);
            } elseif (null === $document) {
                $status = 'not_cached';
            }
        }

        return [
            'status' => $status,
            'url' => $state['url'],
            'issuer' => $state['issuer'],
            'cache_key' => $state['cache_key'],
            'expires_at' => $expiresAt,
            'expires_in' => null !== $expiresAt ? $expiresAt - $now : null,
            'document' => $document,
        ];
    }

    /**
     * @param array<string, mixed>|null $document   The discovery document, when known
     * @param string|null               $currentKid The "kid" of the ID token the logged-in user holds
     *
     * @return array<string, mixed>
     *
     * @psalm-suppress UndefinedThisPropertyFetch, UndefinedMethod The closure below is bound to the verifier
     */
    private function inspectJwks(?array $document, ?string $currentKid, int $now): array
    {
        $jwksUri = \is_string($document['jwks_uri'] ?? null) && '' !== $document['jwks_uri'] ? $document['jwks_uri'] : null;

        $state = \Closure::bind(fn (): array => [
            'cache' => $this->jwksCache,
            'cache_key' => null !== $jwksUri ? $this->getJwksCacheKey($jwksUri) : null,
            'algorithms' => $this->algorithms,
            'enforce_key_usage_verification' => $this->enforceKeyUsageVerification,
        ], $this->signatureVerifier, OidcSignatureVerifier::class)();

        $jwks = [
            'status' => 'unknown',
            'uri' => $jwksUri,
            'cache_key' => $state['cache_key'],
            'algorithms' => $state['algorithms'],
            'enforce_key_usage_verification' => $state['enforce_key_usage_verification'],
            'fetched_at' => null,
            'expires_at' => null,
            'expires_in' => null,
            'keys' => [],
        ];

        // without the discovery document, the JWKS URI is unknown, and so is the cache key
        if (null === $jwksUri || !$state['cache'] instanceof CacheItemPoolInterface) {
            return $jwks;
        }

        [$cached, $metadata] = $this->peek($state['cache'], $state['cache_key']);

        if (!\is_array($cached) || !\is_array($cached['keys'] ?? null)) {
            $jwks['status'] = 'not_cached';

            return $jwks;
        }

        $jwks['status'] = 'cached';
        $jwks['fetched_at'] = is_numeric($cached['fetched_at'] ?? null) ? (int) $cached['fetched_at'] : null;
        $jwks['expires_at'] = isset($metadata[ItemInterface::METADATA_EXPIRY]) ? (int) $metadata[ItemInterface::METADATA_EXPIRY] : null;
        $jwks['expires_in'] = null !== $jwks['expires_at'] ? $jwks['expires_at'] - $now : null;

        foreach ($cached['keys'] as $key) {
            if (!\is_array($key)) {
                continue;
            }

            // the public key material is public, but noise: the identifiers are what tells keys apart
            $description = self::pick($key, self::KEY_FIELDS);
            $description['current'] = null !== $currentKid && \is_string($key['kid'] ?? null) && hash_equals($key['kid'], $currentKid);
            $jwks['keys'][] = $description;
        }

        return $jwks;
    }

    /**
     * @return array<string, mixed>|null Null when the token was not issued by the OIDC login
     */
    private function inspectToken(?TokenInterface $token, int $now): ?array
    {
        if (null === $token) {
            return null;
        }

        $attributes = [];
        foreach (self::TOKEN_ATTRIBUTES as $name) {
            $attributes[$name] = $token->hasAttribute($name) ? $token->getAttribute($name) : null;
        }

        if (null === $attributes['oidc_id_token'] && null === $attributes['oidc_access_token']) {
            return null;
        }

        $describe = static fn (mixed $value): ?array => \is_string($value) && '' !== $value ? OidcTokenDescriber::describe($value) : null;
        $expiresAt = is_numeric($attributes['oidc_access_token_expires_at']) ? (int) $attributes['oidc_access_token_expires_at'] : null;
        $refreshEnabled = (bool) ($this->config['refresh_access_token']['enabled'] ?? false);
        $leeway = (int) ($this->config['refresh_access_token']['leeway'] ?? 0);

        return [
            'id_token' => $describe($attributes['oidc_id_token']),
            'access_token' => $describe($attributes['oidc_access_token']),
            // RFC 6749, Section 5.1: how the access token is presented, "Bearer" or "DPoP"
            'access_token_type' => \is_string($attributes['oidc_access_token_type']) ? $attributes['oidc_access_token_type'] : null,
            'access_token_expires_at' => $expiresAt,
            'access_token_expires_in' => null !== $expiresAt ? $expiresAt - $now : null,
            'refresh_token' => $describe($attributes['oidc_refresh_token']),
            'acr' => \is_string($attributes['oidc_acr']) ? $attributes['oidc_acr'] : null,
            'amr' => \is_array($attributes['oidc_amr']) ? array_values(array_filter($attributes['oidc_amr'], \is_string(...))) : [],
            'refresh' => [
                'enabled' => $refreshEnabled,
                'leeway' => $leeway,
                // what OidcTokenRefresher::refreshIfNeeded() decides on the next request
                'due' => $refreshEnabled && null !== $attributes['oidc_refresh_token'] && null !== $expiresAt && $now + $leeway >= $expiresAt,
            ],
        ];
    }

    /**
     * Compares the configuration with what the provider announces, and points at what
     * weakens the login or is bound to fail.
     *
     * @param array<string, mixed>      $config
     * @param array<string, mixed>|null $discovery
     * @param array<string, mixed>|null $tokens
     *
     * @return list<string>
     */
    private function findWarnings(array $config, ?array $discovery, ?array $tokens): array
    {
        $warnings = [];
        $signatureVerified = (bool) ($config['id_token_signature']['required'] ?? true);
        $pkceEnabled = (bool) ($config['pkce']['enabled'] ?? true);
        $pkceMethod = (string) ($config['pkce']['method'] ?? 'S256');

        if (!$signatureVerified) {
            $warnings[] = 'The ID token signature is not verified ("id_token_signature.required" is false): only the TLS verification of the token request ties the ID token to the provider.';
        }

        if (!$pkceEnabled) {
            $warnings[] = 'PKCE is disabled: nothing but the client secret binds the authorization code to this client.';
        } elseif ('plain' === $pkceMethod) {
            $warnings[] = 'PKCE uses the "plain" method, which sends the code verifier as it is in the authorization request; "S256" is what RFC 7636 recommends.';
        }

        if ('not_cached' === ($discovery['status'] ?? null)) {
            $warnings[] = 'The discovery document is not cached: the next authentication fetches it from the provider first.';
        }

        $document = $discovery['document'] ?? null;
        if (\is_array($document)) {
            $announced = static fn (string $field): ?array => \is_array($document[$field] ?? null) && array_is_list($document[$field]) ? $document[$field] : null;
            $list = static fn (array $values): string => implode('", "', array_map(strval(...), $values));

            if ($pkceEnabled && null !== $methods = $announced('code_challenge_methods_supported')) {
                if (!\in_array($pkceMethod, $methods, true)) {
                    $warnings[] = \sprintf('The provider does not announce the "%s" PKCE method: its "code_challenge_methods_supported" lists "%s".', $pkceMethod, $list($methods));
                }
            }

            $algorithms = (array) ($config['id_token_signature']['algorithms'] ?? []);
            if ($signatureVerified && $algorithms && null !== $announcedAlgorithms = $announced('id_token_signing_alg_values_supported')) {
                if (!array_intersect($algorithms, $announcedAlgorithms)) {
                    $warnings[] = \sprintf('None of the configured ID token signature algorithms ("%s") is announced by the provider, whose "id_token_signing_alg_values_supported" lists "%s".', $list($algorithms), $list($announcedAlgorithms));
                }
            }

            // OpenID Connect Discovery 1.0, Section 3: "client_secret_basic" is the default
            $announcedMethods = $announced('token_endpoint_auth_methods_supported') ?? ['client_secret_basic'];
            $method = $config['client_authentication'] ?? null;
            if (\is_string($method) && !\in_array($method, $announcedMethods, true)) {
                $warnings[] = \sprintf('The provider does not announce the "%s" client authentication method: its "token_endpoint_auth_methods_supported" lists "%s".', $method, $list($announcedMethods));
            }

            if ('userinfo' === ($config['user_data_source'] ?? 'userinfo') && !\is_string($document['userinfo_endpoint'] ?? null)) {
                $warnings[] = 'The user claims are read from the UserInfo endpoint, which the provider does not announce: set "user_data_source" to "id_token".';
            }

            if (($config['enable_end_session'] ?? false) && !\is_string($document['end_session_endpoint'] ?? null)) {
                $warnings[] = 'RP-Initiated Logout is enabled, but the provider announces no "end_session_endpoint": logouts stay local.';
            }

            if (true !== ($document['authorization_response_iss_parameter_supported'] ?? null)) {
                $warnings[] = 'The provider does not announce the "iss" authorization response parameter of RFC 9207: a callback cannot be tied to the provider that issued it.';
            }
        }

        if (null !== $tokens && null !== ($tokens['access_token_expires_in'] ?? null) && 0 >= $tokens['access_token_expires_in']) {
            if (null === ($tokens['refresh_token'] ?? null)) {
                $warnings[] = 'The access token expired, and the security token holds no refresh token to renew it: the provider only issues one when asked for, e.g. with the "offline_access" scope.';
            } elseif (!($tokens['refresh']['enabled'] ?? false)) {
                $warnings[] = 'The access token expired: enable "refresh_access_token" to renew it with the refresh token the security token holds.';
            }
        }

        return $warnings;
    }

    /**
     * Reads a cache entry as it is, without computing nor saving anything.
     *
     * @return array{0: mixed, 1: array<string, mixed>} The cached value, or null on a miss, and the item metadata
     */
    private function peek(CacheItemPoolInterface $pool, string $key): array
    {
        $item = $pool->getItem($key);

        return [$item->isHit() ? $item->get() : null, $item instanceof ItemInterface ? $item->getMetadata() : []];
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     */
    private static function pick(array $values, array $keys): array
    {
        $picked = [];
        foreach ($keys as $key) {
            $picked[$key] = $values[$key] ?? null;
        }

        return $picked;
    }
}
