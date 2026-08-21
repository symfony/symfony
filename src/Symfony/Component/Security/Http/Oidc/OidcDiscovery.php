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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Fetches and caches the OpenID Connect discovery configuration.
 *
 * It lives outside the authenticator namespace on purpose: resolving the endpoints
 * of a provider is a protocol concern shared by every OIDC authentication mechanism,
 * not something specific to the Authorization Code Flow.
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcDiscovery implements ResetInterface
{
    // Discovery documents are small. This limits untrusted data retained by the cache.
    private const MAX_CONFIGURATION_SIZE = 1024 * 1024;

    private readonly ?string $issuer;
    private readonly string $openIdConfigurationUrl;
    private ?ResponseInterface $response = null;
    private ?array $configuration = null;

    /**
     * @param string       $openIdConfigurationUrl Absolute, or relative to $issuer when one is given,
     *                                             or to the HTTP client "base_uri"
     * @param string|null  $issuer                 The expected issuer, or null to skip the check of OIDC Discovery 1.0, Section 4.3
     * @param int|null     $cacheTtl               The cache entry lifetime, or null to leave it to the cache pool
     * @param string|null  $cacheKey               The cache key, or null to derive it from the configuration URL
     * @param list<string> $checkedEndpoints       Endpoints that must be announced and must not downgrade to plain
     *                                             HTTP the transport that carried the discovery document, checked
     *                                             before the document is cached
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        string $openIdConfigurationUrl = '.well-known/openid-configuration',
        ?string $issuer = null,
        private readonly ?int $cacheTtl = 3600,
        private readonly ?string $cacheKey = null,
        private readonly array $checkedEndpoints = [],
    ) {
        // The trailing slash is trimmed here, and the configuration URL built from the
        // issuer, because an issuer coming from an environment variable is an unresolved
        // placeholder while the container compiles: trimming it there leaves the resolved
        // value untouched, and the discovery URL ends up carrying a double slash.
        $this->issuer = null !== $issuer ? rtrim($issuer, '/') : null;
        // OIDC Discovery 1.0, Section 4.1: the well-known path is appended to the issuer
        $this->openIdConfigurationUrl = null !== $this->issuer && !preg_match('#^https?://#i', $openIdConfigurationUrl)
            ? $this->issuer.'/'.ltrim($openIdConfigurationUrl, '/')
            : $openIdConfigurationUrl;
    }

    /**
     * @return array<string, mixed> The raw discovery document
     *
     * @throws AuthenticationException If the discovery document cannot be fetched or announces another issuer
     */
    public function getConfiguration(): array
    {
        // Checked outside the cache callback so it also applies to warm entries, and
        // here rather than only in the configuration of the firewall because a
        // "provider_uri" coming from an environment variable is unknown until runtime.
        if (null !== $this->issuer && !self::isSecureUrl($this->issuer)) {
            throw new AuthenticationException(\sprintf('The OIDC issuer "%s" must use HTTPS: the authorization code, the PKCE verifier and the tokens it is exchanged for are only confidential over TLS.', $this->issuer));
        }

        // the decoded document is memoized for the lifetime of the instance (one per
        // request on classic runtimes, reset between requests in worker mode), so
        // repeated endpoint reads within one authentication do not re-read the cache
        // nor re-decode, and a zero cache TTL costs a single HTTP request per
        // authentication instead of one per read
        if (null !== $this->configuration) {
            return $this->configuration;
        }

        $value = $this->cache->get($this->getCacheKey(), function (ItemInterface $item, bool &$save): array {
            if (null !== $this->cacheTtl) {
                $item->expiresAfter($this->cacheTtl);
            }

            // the request may have been sent by prefetch() already, and is consumed once
            $response = $this->response ?? $this->request();
            $this->response = null;

            try {
                // decoding here rejects a malformed document before it reaches the cache,
                // where it would otherwise fail on every read until the entry expires
                $payload = $this->getConfigurationPayload($response);
                $configuration = $this->decode($payload);
            } catch (HttpClientExceptionInterface $e) {
                throw $this->cannotFetch($e);
            }

            // OpenID Connect Discovery 1.0, Section 4.3: the "issuer" returned MUST be
            // identical to the issuer used to build the discovery URL. Validating it
            // prevents a tampered discovery document from redefining the expected issuer.
            // A trailing slash is ignored on both sides: providers do announce issuers
            // ending with one (Authentik does), and it carries no security meaning since
            // the origin and the path are otherwise identical.
            if (null !== $this->issuer
                && rtrim((string) ($configuration['issuer'] ?? ''), '/') !== $this->issuer
            ) {
                throw new AuthenticationException(\sprintf('The OIDC provider announced the issuer "%s", which does not match the configured issuer "%s".', $configuration['issuer'] ?? '', $this->issuer));
            }

            // checked before the document is stored, so that a document sending its
            // clients to plain HTTP is never cached, and a cached configuration does
            // not tell which URL served it, so store only when that URL is known
            $url = $response->getInfo('url');
            $url = \is_string($url) ? $url : '';
            foreach ($this->checkedEndpoints as $endpoint) {
                self::checkEndpointScheme($configuration[$endpoint] ?? null, $endpoint, $url);
            }
            $save = '' !== $url;

            // the raw payload is cached next to the URL that served it, rather than the
            // decoded document, so that the whole document survives the round trip unchanged
            return ['url' => $url, 'payload' => $payload];
        });

        // a request sent by prefetch() is dropped when another process warmed the entry
        // in between, instead of being consumed the next time the document is read
        $this->response = null;

        return $this->configuration = $this->decode($value['payload'] ?? '');
    }

    /**
     * Sends the discovery request, without waiting for its response, when the document is
     * not cached yet.
     *
     * Calling it on several instances before reading any of them lets their requests travel
     * concurrently, since the response is only consumed by {@see getConfiguration()}.
     */
    public function prefetch(): void
    {
        if (null !== $this->response || null !== $this->configuration) {
            return;
        }

        $this->cache->get($this->getCacheKey(), function (ItemInterface $item, bool &$save): string {
            // the document is not cached: its request is sent and left for
            // getConfiguration() to consume, so nothing is computed nor stored here
            $save = false;
            $this->response = $this->request();

            return '';
        });
    }

    public function reset(): void
    {
        $this->configuration = null;
        $this->response = null;
    }

    /**
     * Returns an endpoint announced by the discovery document, checking that it provides
     * the transport security the ID token relies on.
     *
     * The document is fetched from the provider, so a tampered or misconfigured one must
     * not be able to downgrade a request to plain HTTP: enforcing HTTPS on the configured
     * issuer would be pointless if the endpoints it announces were used as they are.
     *
     * @throws AuthenticationException If the endpoint is not announced, or does not use HTTPS
     */
    public function getSecureEndpoint(string $endpoint): string
    {
        $url = $this->getConfiguration()[$endpoint] ?? null;

        if (!\is_string($url) || '' === $url) {
            throw new AuthenticationException(\sprintf('The OIDC provider does not announce any "%s".', $endpoint));
        }

        // The token endpoint carries the authorization code and the PKCE verifier one way,
        // and returns the ID and access tokens the other, so its transport must be secure even
        // for the loopback and test-domain issuers allowed during local development. A public
        // client sends no secret there, and still needs this.
        if (!self::isSecureUrl($url) || ('token_endpoint' === $endpoint && 'https' !== strtolower((string) parse_url($url, \PHP_URL_SCHEME)))) {
            throw new AuthenticationException(\sprintf('The "%s" announced by the OIDC provider must use HTTPS (got "%s"): the authorization code, the PKCE verifier and the tokens it is exchanged for are only confidential over TLS.', $endpoint, $url));
        }

        return $url;
    }

    /**
     * Tells whether the given URL provides the transport security the OIDC flow relies on.
     *
     * The authorization code, the PKCE verifier and the tokens it is exchanged for are only
     * confidential over TLS, so HTTPS is mandatory, loopback hosts and the names reserved for
     * testing excepted, for local development.
     */
    public static function isSecureUrl(string $url): bool
    {
        // parse_url() returns the scheme as it is written, and a scheme is case-insensitive
        if ('https' === strtolower((string) parse_url($url, \PHP_URL_SCHEME))) {
            return true;
        }

        // parse_url() keeps the square brackets around IPv6 hosts
        $host = strtolower(trim((string) parse_url($url, \PHP_URL_HOST), '[]'));

        if (\in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        // special-use domain names reserved for testing: RFC 2606 §2, RFC 6761 §6.2 and §6.3
        return str_ends_with($host, '.localhost') || str_ends_with($host, '.test');
    }

    private function request(): ResponseInterface
    {
        try {
            // redirects are not followed: the endpoints announced by the document are
            // checked against the URL it was requested from, which a redirect would change
            $response = $this->httpClient->request('GET', $this->openIdConfigurationUrl, ['max_redirects' => 0]);
            $response->getHeaders(); // triggers the exception on a 3xx, 4xx or 5xx status

            return $response;
        } catch (HttpClientExceptionInterface $e) {
            throw $this->cannotFetch($e);
        }
    }

    /**
     * The discovery specification requires the endpoints it advertises to use the "https"
     * scheme: they are rejected when they downgrade the transport that carried the document.
     */
    private static function checkEndpointScheme(mixed $endpoint, string $key, string $discoveryUrl): void
    {
        if (!\is_string($endpoint) || '' === $endpoint) {
            throw new AuthenticationException(\sprintf('The "%s" is missing from the OIDC discovery document.', $key));
        }

        $scheme = parse_url($endpoint, \PHP_URL_SCHEME);

        if ($scheme && 'https' !== strtolower($scheme) && str_starts_with($discoveryUrl, 'https://')) {
            throw new AuthenticationException(\sprintf('The "%s" found in the OIDC discovery document must use the "https" scheme, "%s" given.', $key, $scheme));
        }
    }

    private function getConfigurationPayload(ResponseInterface $response): string
    {
        $payload = '';
        foreach ($this->httpClient->stream($response) as $chunk) {
            $payload .= $chunk->getContent();
            if (self::MAX_CONFIGURATION_SIZE < \strlen($payload)) {
                $response->cancel();

                throw new AuthenticationException('The OIDC discovery document exceeds the maximum size of 1 MiB.');
            }
        }

        return $payload;
    }

    private function cannotFetch(\Throwable $e): AuthenticationException
    {
        return new AuthenticationException(\sprintf('The OIDC discovery document could not be fetched from "%s": "%s"', $this->openIdConfigurationUrl, $e->getMessage()), previous: $e);
    }

    /**
     * Decodes a JSON payload and ensures it is a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws AuthenticationException
     */
    private function decode(string $payload): array
    {
        try {
            $configuration = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw $this->cannotFetch(new \JsonException(\sprintf('Invalid JSON: "%s"', $e->getMessage()), $e->getCode(), $e));
        }

        // a valid JSON payload may still hold a scalar, which none of the readers expect
        if (!\is_array($configuration)) {
            throw $this->cannotFetch(new \JsonException('The document is not a JSON object.'));
        }

        return $configuration;
    }

    private function getCacheKey(): string
    {
        return $this->cacheKey ?? 'oidc_discovery.'.hash('xxh128', $this->openIdConfigurationUrl);
    }
}
