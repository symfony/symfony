<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Oidc;

use Jose\Component\Core\JWKSet;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcJwks;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The keys an OIDC provider publishes at the "jwks_uri" of its discovery document, of which
 * only the ones it designates for signature are read so far.
 *
 * The cache entry is keyed by that "jwks_uri" rather than by the service reading it, so every
 * reader of one provider shares it.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7517 JSON Web Key (RFC 7517)
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class OidcProviderKeys
{
    /**
     * How long a key set refetched for an unknown "kid" is kept before another refetch is allowed.
     */
    private const ROTATION_COOLDOWN = 60;

    private readonly ClockInterface $clock;

    /**
     * @param int  $defaultCacheTtl             The lifetime used when the provider advertises none, or 0 not to cache
     * @param bool $enforceKeyUsageVerification See {@see OidcJwks::fromResponse()}
     * @param bool $requireSecureJwksUri        Whether the "jwks_uri" must use HTTPS, loopback excepted. Turn it off
     *                                          only for a document itself allowed to be served over plain HTTP, and
     *                                          then list "jwks_uri" among the $checkedEndpoints of {@see OidcDiscovery}.
     *                                          An absolute URL is required either way
     */
    public function __construct(
        private readonly OidcDiscovery $discovery,
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        ?ClockInterface $clock = null,
        private readonly int $defaultCacheTtl = 3600,
        private readonly bool $enforceKeyUsageVerification = true,
        private readonly bool $requireSecureJwksUri = true,
    ) {
        if ($defaultCacheTtl < 0) {
            throw new \InvalidArgumentException(\sprintf('The "$defaultCacheTtl" argument of "%s()" cannot be negative.', __METHOD__));
        }

        if (null === $clock && !class_exists(Clock::class)) {
            throw new \LogicException(\sprintf('The "symfony/clock" component is required to build "%s" without a clock. Try running "composer require symfony/clock", or pass any PSR-20 clock to the constructor.', self::class));
        }

        $this->clock = $clock ?? new Clock();
    }

    /**
     * @param string|null $kid The "kid" of the token to verify, which drives the refetch of {@see getSignatureKeys()}
     *
     * @throws AuthenticationException If the discovery document or the key set cannot be fetched
     */
    public function getSignatureKeySet(?string $kid = null): JWKSet
    {
        if (!class_exists(JWKSet::class)) {
            throw new \LogicException(\sprintf('You cannot build the key set of an OIDC provider since the "web-token/jwt-library" package is not installed. Try running "composer require web-token/jwt-library", or read the keys with "%s::getSignatureKeys()".', self::class));
        }

        return JWKSet::createFromKeyData(['keys' => $this->getSignatureKeys($kid)]);
    }

    /**
     * They are refetched when the token announces a "kid" none of the cached ones holds, which a
     * provider that rotated its keys signs with. That refetch is throttled.
     *
     * @return list<array<string, mixed>>
     *
     * @throws AuthenticationException If the discovery document or the key set cannot be fetched
     */
    public function getSignatureKeys(?string $kid = null): array
    {
        $jwksUri = $this->getJwksUri();

        $cacheKey = $this->getCacheKey($jwksUri);
        $compute = fn (ItemInterface $item): array => [
            'keys' => OidcJwks::fetchKeys($this->httpClient, $jwksUri, $item, $this->defaultCacheTtl, $this->enforceKeyUsageVerification),
            // the key set is stored with the time it was fetched, so that the refetch below
            // can be throttled without a second cache entry
            'fetched_at' => $this->clock->now()->getTimestamp(),
        ];

        /** @var array{keys: list<array<string, mixed>>, fetched_at: int} $jwks */
        $jwks = $this->cache->get($cacheKey, $compute);

        if (null !== $kid
            && !self::hasKey($jwks['keys'], $kid)
            && ($jwks['fetched_at'] ?? 0) <= $this->clock->now()->getTimestamp() - self::ROTATION_COOLDOWN
        ) {
            $jwks = $this->cache->get($cacheKey, $compute, \INF);
        }

        return $jwks['keys'];
    }

    /**
     * @throws AuthenticationException If the endpoint is not announced, or does not use HTTPS
     */
    private function getJwksUri(): string
    {
        if ($this->requireSecureJwksUri) {
            return $this->discovery->getSecureEndpoint('jwks_uri');
        }

        $jwksUri = $this->discovery->getConfiguration()['jwks_uri'] ?? null;

        if (!\is_string($jwksUri) || '' === $jwksUri) {
            throw new AuthenticationException('The "jwks_uri" is missing from the OIDC discovery document.');
        }

        // a relative reference names one endpoint per HTTP client that resolves it, so it
        // identifies no key set: two providers announcing the same one would share an entry,
        // and the keys of whichever filled it first would verify the tokens of the other
        if (!parse_url($jwksUri, \PHP_URL_SCHEME) || !parse_url($jwksUri, \PHP_URL_HOST)) {
            throw new AuthenticationException(\sprintf('The "jwks_uri" announced by the OIDC provider names no host ("%s"), where OpenID Connect Discovery 1.0 requires a URL.', $jwksUri));
        }

        return $jwksUri;
    }

    /**
     * Unlike {@see OidcDiscovery::getIssuer()} this needs no expected issuer, a reader holding
     * several providers having to tell them apart before it knows which one to expect. Nothing is
     * trusted for it: an announced issuer only selects the keys a signature is then checked against.
     *
     * @throws AuthenticationException If the discovery document cannot be fetched
     */
    public function getAnnouncedIssuer(): ?string
    {
        $issuer = $this->discovery->getConfiguration()['issuer'] ?? null;

        return \is_string($issuer) && '' !== $issuer ? $issuer : null;
    }

    /**
     * Calling it on several instances before reading any of them lets their requests travel
     * concurrently.
     */
    public function prefetch(): void
    {
        $this->discovery->prefetch();
    }

    /**
     * The filter and the lifetime are part of the key, so readers share the entry only when they
     * would have filled it with the same thing, and are kept out of the hash, where a "jwks_uri"
     * ending with either would collide with another reading of the same endpoint.
     */
    private function getCacheKey(string $jwksUri): string
    {
        return 'oidc_jwks.'.($this->enforceKeyUsageVerification ? '' : 'lax.').$this->defaultCacheTtl.'.'.hash('xxh128', $jwksUri);
    }

    /**
     * @param list<array<string, mixed>> $keys
     */
    private static function hasKey(array $keys, string $kid): bool
    {
        foreach ($keys as $key) {
            if (isset($key['kid']) && hash_equals($kid, (string) $key['kid'])) {
                return true;
            }
        }

        return false;
    }
}
