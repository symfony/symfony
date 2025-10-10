<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Symfony\Component\HttpClient\Caching\Freshness;
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Exception\ChunkCacheItemNotFoundException;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\HttpClientKernel;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds caching on top of an HTTP client (per RFC 9111).
 *
 * Known omissions / partially supported features per RFC 9111:
 *   1. Range requests:
 *     - All range requests ("partial content") are passed through and never cached.
 *   2. stale-while-revalidate:
 *     - There's no actual "background revalidation" for stale responses, they will
 *       always be revalidated.
 *   3. min-fresh, max-stale, only-if-cached:
 *     - Request directives are not parsed; the client ignores them.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9111
 */
class CachingHttpClient implements HttpClientInterface, ResetInterface
{
    use AsyncDecoratorTrait {
        stream as asyncStream;
        AsyncDecoratorTrait::withOptions insteadof HttpClientTrait;
    }
    use HttpClientTrait;

    /**
     * The status codes that are always cacheable.
     */
    private const CACHEABLE_STATUS_CODES = [200, 203, 204, 300, 301, 404, 410];
    /**
     * The status codes that are cacheable if the response carries explicit cache directives.
     */
    private const CONDITIONALLY_CACHEABLE_STATUS_CODES = [302, 303, 307, 308];
    /**
     * The HTTP methods that are always cacheable.
     */
    private const CACHEABLE_METHODS = ['GET', 'HEAD'];
    /**
     * The HTTP methods that will trigger a cache invalidation.
     */
    private const UNSAFE_METHODS = ['POST', 'PUT', 'DELETE', 'PATCH'];
    /**
     * Headers that influence the response and may affect caching behavior.
     */
    private const RESPONSE_INFLUENCING_HEADERS = [
        'accept' => true,
        'accept-charset' => true,
        'accept-encoding' => true,
        'accept-language' => true,
        'authorization' => true,
        'cookie' => true,
        'expect' => true,
        'host' => true,
        'user-agent' => true,
    ];
    /**
     * Headers that MUST NOT be stored as per RFC 9111 Section 3.1.
     */
    private const EXCLUDED_HEADERS = [
        'connection' => true,
        'proxy-authenticate' => true,
        'proxy-authentication-info' => true,
        'proxy-authorization' => true,
    ];
    /**
     * Maximum heuristic freshness lifetime in seconds (24 hours).
     */
    private const MAX_HEURISTIC_FRESHNESS_TTL = 86400;

    private TagAwareCacheInterface|HttpCache $cache;
    private array $defaultOptions = self::OPTIONS_DEFAULTS;

    /**
     * @param bool     $sharedCache Indicates whether this cache is shared or private. When true, responses
     *                              may be skipped from caching in presence of certain headers
     *                              (e.g. Authorization) unless explicitly marked as public.
     * @param int|null $maxTtl      The maximum time-to-live (in seconds) for cached responses.
     *                              If a server-provided TTL exceeds this value, it will be capped
     *                              to this maximum.
     */
    public function __construct(
        private HttpClientInterface $client,
        TagAwareCacheInterface|StoreInterface $cache,
        array $defaultOptions = [],
        private readonly bool $sharedCache = true,
        private readonly ?int $maxTtl = null,
    ) {
        if ($cache instanceof StoreInterface) {
            trigger_deprecation('symfony/http-client', '7.4', 'Passing a "%s" as constructor\'s 2nd argument of "%s" is deprecated, "%s" expected.', StoreInterface::class, __CLASS__, TagAwareCacheInterface::class);

            if (!class_exists(HttpClientKernel::class)) {
                throw new \LogicException(\sprintf('Using "%s" requires the HttpKernel component, try running "composer require symfony/http-kernel".', __CLASS__));
            }

            $kernel = new HttpClientKernel($client);
            $this->cache = new HttpCache($kernel, $cache, null, $defaultOptions);

            unset($defaultOptions['debug']);
            unset($defaultOptions['default_ttl']);
            unset($defaultOptions['private_headers']);
            unset($defaultOptions['skip_response_headers']);
            unset($defaultOptions['allow_reload']);
            unset($defaultOptions['allow_revalidate']);
            unset($defaultOptions['stale_while_revalidate']);
            unset($defaultOptions['stale_if_error']);
            unset($defaultOptions['trace_level']);
            unset($defaultOptions['trace_header']);
        } else {
            $this->cache = $cache;
        }

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->cache instanceof HttpCache) {
            return $this->legacyRequest($method, $url, $options);
        }

        [$fullUrl, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);

        $fullUrl = implode('', $fullUrl);
        $fullUrlTag = self::hash($fullUrl);

        if ('' !== $options['body'] || ($options['extra']['no_cache'] ?? false) || isset($options['normalized_headers']['range']) || !\in_array($method, self::CACHEABLE_METHODS, true)) {
            return new AsyncResponse($this->client, $method, $url, $options, function (ChunkInterface $chunk, AsyncContext $context) use ($method, $fullUrlTag): \Generator {
                if (null !== $chunk->getError() || $chunk->isTimeout() || !$chunk->isFirst()) {
                    yield $chunk;

                    return;
                }

                $statusCode = $context->getStatusCode();
                if ($statusCode >= 100 && $statusCode < 400 && \in_array($method, self::UNSAFE_METHODS, true)) {
                    $this->cache->invalidateTags([$fullUrlTag]);
                }

                $context->passthru();

                yield $chunk;
            });
        }

        $requestHash = self::hash($method.$fullUrl.serialize(array_intersect_key($options['normalized_headers'], self::RESPONSE_INFLUENCING_HEADERS)));
        $varyKey = "vary_{$requestHash}";
        $varyFields = $this->cache->get($varyKey, static fn ($item, &$save): array => ($save = false) ?: [], 0);

        $metadataKey = self::getMetadataKey($requestHash, $options['normalized_headers'], $varyFields);
        $cachedData = $this->cache->get($metadataKey, static fn ($item, &$save): array => ($save = false) ?: [], 0);

        $freshness = null;
        if ($cachedData) {
            $freshness = $this->evaluateCacheFreshness($cachedData);

            if (Freshness::Fresh === $freshness) {
                return $this->createResponseFromCache($cachedData, $method, $url, $options, $metadataKey);
            }

            if (isset($cachedData['headers']['etag'])) {
                $options['headers']['If-None-Match'] = implode(', ', $cachedData['headers']['etag']);
            }

            if (isset($cachedData['headers']['last-modified'][0])) {
                $options['headers']['If-Modified-Since'] = $cachedData['headers']['last-modified'][0];
            }
        }

        // consistent expiration time for all items
        $expiresAt = null === $this->maxTtl ? null : \DateTimeImmutable::createFromFormat('U', time() + $this->maxTtl);

        return new AsyncResponse(
            $this->client,
            $method,
            $url,
            $options,
            function (ChunkInterface $chunk, AsyncContext $context) use (
                $expiresAt,
                $fullUrlTag,
                $requestHash,
                $varyKey,
                $varyFields,
                &$metadataKey,
                $cachedData,
                $freshness,
                $url,
                $method,
                $options,
            ): \Generator {
                static $attemptTag = null;
                static $firstChunkKey = null;
                static $chunkKey = null;

                if (null !== $chunk->getError() || $chunk->isTimeout()) {
                    null !== $attemptTag && $this->cache->invalidateTags([$attemptTag]);

                    if (Freshness::StaleButUsable === $freshness) {
                        // avoid throwing exception in ErrorChunk#__destruct()
                        $chunk instanceof ErrorChunk && $chunk->didThrow(true);
                        $context->passthru();
                        $context->replaceResponse($this->createResponseFromCache($cachedData, $method, $url, $options, $metadataKey));

                        return;
                    }

                    if (Freshness::MustRevalidate === $freshness) {
                        // avoid throwing exception in ErrorChunk#__destruct()
                        $chunk instanceof ErrorChunk && $chunk->didThrow(true);
                        $context->passthru();
                        $context->replaceResponse(self::createGatewayTimeoutResponse($method, $url, $options));

                        return;
                    }

                    yield $chunk;

                    return;
                }

                $headers = $context->getHeaders();

                if ($chunk->isFirst()) {
                    $statusCode = $context->getStatusCode();
                    $cacheControl = self::parseCacheControlHeader($headers['cache-control'] ?? []);

                    $attemptTag = self::generateChunkKey();

                    if (304 === $statusCode && null !== $freshness) {
                        $maxAge = $this->determineMaxAge($headers, $cacheControl);

                        $this->cache->get($metadataKey, static function (ItemInterface $item) use ($headers, $maxAge, $cachedData, $expiresAt, $fullUrlTag, $metadataKey): array {
                            $item->expiresAt($expiresAt)->tag([$fullUrlTag, $metadataKey]);

                            $cachedData['expires_at'] = self::calculateExpiresAt($maxAge);
                            $cachedData['stored_at'] = time();
                            $cachedData['initial_age'] = (int) ($headers['age'][0] ?? 0);
                            $cachedData['headers'] = array_merge($cachedData['headers'], array_diff_key($headers, self::EXCLUDED_HEADERS));

                            return $cachedData;
                        }, \INF);

                        $context->passthru();
                        $context->replaceResponse($this->createResponseFromCache($cachedData, $method, $url, $options, $metadataKey));

                        return;
                    }

                    if ($statusCode >= 500 && $statusCode < 600) {
                        if (Freshness::StaleButUsable === $freshness) {
                            $context->passthru();
                            $context->replaceResponse($this->createResponseFromCache($cachedData, $method, $url, $options, $metadataKey));

                            return;
                        }

                        if (Freshness::MustRevalidate === $freshness) {
                            $context->passthru();
                            $context->replaceResponse(self::createGatewayTimeoutResponse($method, $url, $options));

                            return;
                        }
                    }

                    if (!$this->isServerResponseCacheable($statusCode, $options['normalized_headers'], $headers, $cacheControl)) {
                        $context->passthru();

                        yield $chunk;

                        return;
                    }

                    // recomputing vary fields in case it changed or for first request
                    $newVaryFields = [];
                    foreach ($headers['vary'] ?? [] as $vary) {
                        foreach (explode(',', $vary) as $field) {
                            $field = strtolower(trim($field));
                            if ('cookie' === $field ? $this->sharedCache : !preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $field)) {
                                $field = '*';
                            }
                            $newVaryFields[] = $field;
                        }
                    }

                    if (\in_array('*', $newVaryFields, true)) {
                        $context->passthru();

                        yield $chunk;

                        return;
                    }

                    sort($newVaryFields);

                    if ($varyFields !== $newVaryFields) {
                        $this->cache->invalidateTags([$fullUrlTag]);

                        $metadataKey = self::getMetadataKey($requestHash, $options['normalized_headers'], $newVaryFields);
                    }

                    $this->cache->get($varyKey, static function (ItemInterface $item) use ($newVaryFields, $expiresAt, $fullUrlTag): array {
                        $item->tag([$fullUrlTag])->expiresAt($expiresAt);

                        return $newVaryFields;
                    }, \INF);

                    $firstChunkKey = $chunkKey = self::generateChunkKey();

                    yield $chunk;

                    return;
                }

                if (null === $chunkKey) {
                    // informational chunks
                    yield $chunk;

                    return;
                }

                if ($chunk->isLast()) {
                    $this->cache->get($chunkKey, static function (ItemInterface $item) use ($expiresAt, $fullUrlTag, $metadataKey, $chunk, $attemptTag): array {
                        $item->tag([$fullUrlTag, $metadataKey, $attemptTag])->expiresAt($expiresAt);

                        return [
                            'content' => $chunk->getContent(),
                            'next_chunk' => null,
                        ];
                    }, \INF);

                    $maxAge = $this->determineMaxAge($headers, self::parseCacheControlHeader($headers['cache-control'] ?? []));
                    $this->cache->get($metadataKey, static function (ItemInterface $item) use ($context, $headers, $maxAge, $expiresAt, $fullUrlTag, $metadataKey, $attemptTag, $firstChunkKey): array {
                        $item->tag([$fullUrlTag, $metadataKey, $attemptTag])->expiresAt($expiresAt);

                        return [
                            'status_code' => $context->getStatusCode(),
                            'headers' => array_diff_key($headers, self::EXCLUDED_HEADERS),
                            'initial_age' => (int) ($headers['age'][0] ?? 0),
                            'stored_at' => time(),
                            'expires_at' => self::calculateExpiresAt($maxAge),
                            'next_chunk' => $firstChunkKey,
                        ];
                    }, \INF);

                    yield $chunk;

                    return;
                }

                $nextChunkKey = self::generateChunkKey();
                $this->cache->get($chunkKey, static function (ItemInterface $item) use ($expiresAt, $fullUrlTag, $metadataKey, $attemptTag, $chunk, $nextChunkKey): array {
                    $item->tag([$fullUrlTag, $metadataKey, $attemptTag])->expiresAt($expiresAt);

                    return [
                        'content' => $chunk->getContent(),
                        'next_chunk' => $nextChunkKey,
                    ];
                }, \INF);
                $chunkKey = $nextChunkKey;

                yield $chunk;
            }
        );
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        $mockResponses = [];
        $asyncResponses = [];

        foreach ($responses as $response) {
            if ($response instanceof MockResponse) {
                $mockResponses[] = $response;
            } else {
                $asyncResponses[] = $response;
            }
        }

        if (!$mockResponses) {
            return $this->asyncStream($asyncResponses, $timeout);
        }

        if (!$asyncResponses) {
            return new ResponseStream(MockResponse::stream($mockResponses, $timeout));
        }

        return new ResponseStream((function () use ($mockResponses, $asyncResponses, $timeout) {
            yield from MockResponse::stream($mockResponses, $timeout);
            yield from $this->asyncStream($asyncResponses, $timeout);
        })());
    }

    private function legacyRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        [$url, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions, true);
        $url = implode('', $url);

        if (!empty($options['body']) || !empty($options['extra']['no_cache']) || !\in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $request = Request::create($url, $method);
        $request->attributes->set('http_client_options', $options);

        foreach ($options['normalized_headers'] as $name => $values) {
            if ('cookie' !== $name) {
                foreach ($values as $value) {
                    $request->headers->set($name, substr($value, 2 + \strlen($name)), false);
                }

                continue;
            }

            foreach ($values as $cookies) {
                foreach (explode('; ', substr($cookies, \strlen('Cookie: '))) as $cookie) {
                    if ('' !== $cookie) {
                        $cookie = explode('=', $cookie, 2);
                        $request->cookies->set($cookie[0], $cookie[1] ?? '');
                    }
                }
            }
        }

        $response = $this->cache->handle($request);
        $response = new MockResponse($response->getContent(), [
            'http_code' => $response->getStatusCode(),
            'response_headers' => $response->headers->allPreserveCase(),
        ]);

        return MockResponse::fromRequest($method, $url, $options, $response);
    }

    private static function hash(string $toHash): string
    {
        return str_replace('/', '_', base64_encode(hash('sha256', $toHash, true)));
    }

    private static function generateChunkKey(): string
    {
        return str_replace('/', '_', base64_encode(random_bytes(6)));
    }

    /**
     * Generates a unique metadata key based on the request hash and varying headers.
     *
     * @param string                         $requestHash       A hash representing the request details
     * @param array<string, string|string[]> $normalizedHeaders Normalized headers of the request
     * @param string[]                       $varyFields        Headers to consider for building the variant key
     *
     * @return string The metadata key composed of the request hash and variant key
     */
    private static function getMetadataKey(string $requestHash, array $normalizedHeaders, array $varyFields): string
    {
        $variantKey = self::hash(self::buildVariantKey($normalizedHeaders, $varyFields));

        return "metadata_{$requestHash}_{$variantKey}";
    }

    /**
     * Build a variant key for caching, given an array of normalized headers and the vary fields.
     *
     * The key is an ampersand-separated string of "header=value" pairs, with
     * the special case of "header=" for headers that are not present.
     *
     * @param array<string, string|string[]> $normalizedHeaders
     * @param string[]                       $varyFields
     */
    private static function buildVariantKey(array $normalizedHeaders, array $varyFields): string
    {
        $parts = [];
        foreach ($varyFields as $field) {
            $lower = strtolower($field);
            if (!isset($normalizedHeaders[$lower])) {
                $parts[$lower] = $lower.'=';
            } else {
                $parts[$lower] = $lower.'='.implode(',', array_map(rawurlencode(...), (array) $normalizedHeaders[$lower]));
            }
        }
        ksort($parts);

        return implode('&', $parts);
    }

    /**
     * Parse the Cache-Control header and return an array of directive names as keys
     * and their values as values, or true if the directive has no value.
     *
     * @param array<string, string|string[]> $header The Cache-Control header as an array of strings
     *
     * @return array<string, string|true> The parsed Cache-Control directives
     */
    private static function parseCacheControlHeader(array $header): array
    {
        $parsed = [];
        foreach ($header as $line) {
            foreach (explode(',', $line) as $directive) {
                if (str_contains($directive, '=')) {
                    [$name, $value] = explode('=', $directive, 2);
                    $parsed[trim($name)] = trim($value);
                } else {
                    $parsed[trim($directive)] = true;
                }
            }
        }

        return $parsed;
    }

    /**
     * Evaluates the freshness of a cached response based on its headers and expiration time.
     *
     * This method determines the state of the cached response by analyzing the Cache-Control
     * directives and the expiration timestamp.
     *
     * @param array{headers: array<string, string[]>, expires_at: int|null} $data The cached response data, including headers and expiration time
     */
    private function evaluateCacheFreshness(array $data): Freshness
    {
        $parseCacheControlHeader = self::parseCacheControlHeader($data['headers']['cache-control'] ?? []);

        if (isset($parseCacheControlHeader['no-cache'])) {
            return Freshness::Stale;
        }

        $now = time();
        $expires = $data['expires_at'];

        if (null === $expires || $now <= $expires) {
            return Freshness::Fresh;
        }

        if (
            isset($parseCacheControlHeader['must-revalidate'])
            || ($this->sharedCache && isset($parseCacheControlHeader['proxy-revalidate']))
        ) {
            return Freshness::MustRevalidate;
        }

        if (isset($parseCacheControlHeader['stale-if-error']) && ($now - $expires) <= (int) $parseCacheControlHeader['stale-if-error']) {
            return Freshness::StaleButUsable;
        }

        return Freshness::Stale;
    }

    /**
     * Determine the maximum age of the response.
     *
     * This method first checks for the presence of the s-maxage directive, and if
     * present, returns its value minus the current age. If s-maxage is not present,
     * it checks for the presence of the max-age directive, and if present, returns
     * its value minus the current age. If neither directive is present, it checks
     * the Expires header for a valid timestamp, and if present, returns the
     * difference between the timestamp and the current time minus the current age.
     *
     * If none of the above directives or headers are present, the method returns null.
     *
     * @param array<string, string|string[]> $headers      An array of HTTP headers
     * @param array<string, string|true>     $cacheControl An array of parsed Cache-Control directives
     *
     * @return int|null The maximum age of the response, or null if it cannot be determined
     */
    private function determineMaxAge(array $headers, array $cacheControl): ?int
    {
        $age = self::getCurrentAge($headers);

        if ($this->sharedCache && isset($cacheControl['s-maxage'])) {
            $sharedMaxAge = (int) $cacheControl['s-maxage'];

            return max(0, $sharedMaxAge - $age);
        }

        if (isset($cacheControl['max-age'])) {
            $maxAge = (int) $cacheControl['max-age'];

            return max(0, $maxAge - $age);
        }

        foreach ($headers['expires'] ?? [] as $expire) {
            if (false !== $expirationTimestamp = strtotime($expire)) {
                $timeUntilExpiration = $expirationTimestamp - time() - $age;

                return max($timeUntilExpiration, 0);
            }
        }

        // Heuristic freshness fallback when no explicit directives are present
        if (
            !isset($cacheControl['no-cache'])
            && !isset($cacheControl['no-store'])
            && isset($headers['last-modified'])
        ) {
            foreach ($headers['last-modified'] as $lastModified) {
                if (false === $lastModifiedTimestamp = strtotime($lastModified)) {
                    continue;
                }
                if (0 < $secondsSinceLastModified = time() - $lastModifiedTimestamp) {
                    // Heuristic: 10% of time since last modified, capped at max heuristic freshness
                    $heuristicFreshnessSeconds = (int) ($secondsSinceLastModified * 0.1);
                    $cappedHeuristicFreshness = min($heuristicFreshnessSeconds, self::MAX_HEURISTIC_FRESHNESS_TTL);

                    return max(0, $cappedHeuristicFreshness - $age);
                }
            }
        }

        return null;
    }

    /**
     * Retrieves the current age of the response from the headers.
     *
     * @param array<string, string|string[]> $headers An array of HTTP headers
     *
     * @return int The age of the response in seconds, defaults to 0 if not present
     */
    private static function getCurrentAge(array $headers): int
    {
        return (int) ($headers['age'][0] ?? 0);
    }

    /**
     * Calculates the expiration time of the response given the maximum age.
     *
     * @param int|null $maxAge The maximum age of the response in seconds, or null if it cannot be determined
     *
     * @return int|null The expiration time of the response as a Unix timestamp, or null if the maximum age is null
     */
    private static function calculateExpiresAt(?int $maxAge): ?int
    {
        if (null === $maxAge) {
            return null;
        }

        return time() + $maxAge;
    }

    /**
     * Checks if the server response is cacheable according to the HTTP 1.1
     * specification (RFC 9111).
     *
     * This function will return true if the server response can be cached,
     * false otherwise.
     *
     * @param array<string, string|string[]> $requestHeaders
     * @param array<string, string|string[]> $responseHeaders
     * @param array<string, string|true>     $cacheControl
     */
    private function isServerResponseCacheable(int $statusCode, array $requestHeaders, array $responseHeaders, array $cacheControl): bool
    {
        // no-store => skip caching
        if (isset($cacheControl['no-store'])) {
            return false;
        }

        if (
            $this->sharedCache
            && !isset($cacheControl['public']) && !isset($cacheControl['s-maxage']) && !isset($cacheControl['must-revalidate'])
            && isset($requestHeaders['authorization'])
        ) {
            return false;
        }

        if ($this->sharedCache && isset($cacheControl['private'])) {
            return false;
        }

        // Conditionals require an explicit expiration
        if (\in_array($statusCode, self::CONDITIONALLY_CACHEABLE_STATUS_CODES, true)) {
            return $this->hasExplicitExpiration($responseHeaders, $cacheControl);
        }

        return \in_array($statusCode, self::CACHEABLE_STATUS_CODES, true);
    }

    /**
     * Checks if the response has an explicit expiration.
     *
     * This function will return true if the response has an explicit expiration
     * time specified in the headers or in the Cache-Control directives,
     * false otherwise.
     *
     * @param array<string, string|string[]> $headers
     * @param array<string, string|true>     $cacheControl
     */
    private function hasExplicitExpiration(array $headers, array $cacheControl): bool
    {
        return isset($headers['expires'])
            || ($this->sharedCache && isset($cacheControl['s-maxage']))
            || isset($cacheControl['max-age']);
    }

    /**
     * Creates a MockResponse object from cached data.
     *
     * This function constructs a MockResponse from the cached data, including
     * the original request method, URL, and options, as well as the cached
     * response headers and content. The constructed MockResponse is then
     * returned.
     *
     * @param array{next_chunk: string, status_code: int, initial_age: int, headers: array<string, string|string[]>, stored_at: int} $cachedData
     */
    private function createResponseFromCache(array $cachedData, string $method, string $url, array $options, string $metadataKey): MockResponse
    {
        $cache = $this->cache;
        $callback = static function (ItemInterface $item) use ($cache, $metadataKey): never {
            $cache->invalidateTags([$metadataKey]);

            throw new ChunkCacheItemNotFoundException(\sprintf('Missing cache item for chunk with key "%s". This indicates an internal cache inconsistency.', $item->getKey()));
        };
        $body = static function () use ($cache, $cachedData, $callback): \Generator {
            while (null !== $cachedData['next_chunk']) {
                $cachedData = $cache->get($cachedData['next_chunk'], $callback, 0);

                if ('' !== $cachedData['content']) {
                    yield $cachedData['content'];
                }
            }
        };

        return MockResponse::fromRequest($method, $url, $options, new MockResponse($body(), [
            'http_code' => $cachedData['status_code'],
            'response_headers' => [
                'age' => $cachedData['initial_age'] + (time() - $cachedData['stored_at']),
            ] + $cachedData['headers'],
        ]));
    }

    private static function createGatewayTimeoutResponse(string $method, string $url, array $options): MockResponse
    {
        return MockResponse::fromRequest($method, $url, $options, new MockResponse('', ['http_code' => 504]));
    }
}
