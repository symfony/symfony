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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Caching\Freshness;
use Symfony\Component\HttpClient\Chunk\ErrorChunk;
use Symfony\Component\HttpClient\Exception\ChunkCacheItemNotFoundException;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
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
 *   3. min-fresh, max-stale:
 *     - These request directives are not parsed; the client ignores them.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9111
 */
class CachingHttpClient implements HttpClientInterface, LoggerAwareInterface, ResetInterface
{
    use AsyncDecoratorTrait {
        stream as asyncStream;
        AsyncDecoratorTrait::withOptions insteadof HttpClientTrait;
    }
    use HttpClientTrait;

    /**
     * The status codes that are cacheable by default.
     */
    private const DEFAULT_CACHEABLE_STATUS_CODES = [200, 203, 204, 300, 301, 308, 404, 405, 410, 414, 501];
    /**
     * The HTTP methods that are always cacheable.
     */
    private const CACHEABLE_METHODS = ['GET', 'HEAD'];
    /**
     * The HTTP methods that are considered safe per RFC 9110.
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];
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
        'keep-alive' => true,
        'proxy-authenticate' => true,
        'proxy-authentication-info' => true,
        'proxy-authorization' => true,
        'te' => true,
        'trailer' => true,
        'transfer-encoding' => true,
        'upgrade' => true,
    ];
    /**
     * Maximum heuristic freshness lifetime in seconds (24 hours).
     */
    private const MAX_HEURISTIC_FRESHNESS_TTL = 86400;
    /**
     * RFC 9111 recommends a heuristic freshness lifetime of 10% of the time since Last-Modified.
     */
    private const HEURISTIC_FRESHNESS_FRACTION = 0.1;

    private array $defaultOptions = self::OPTIONS_DEFAULTS;
    private bool $isInnerRequest = false;
    private ?LoggerInterface $logger = null;

    /**
     * @param bool         $sharedCache Indicates whether this cache is shared or private. When true, responses
     *                                  may be skipped from caching in presence of certain headers
     *                                  (e.g. Authorization) unless explicitly marked as public.
     * @param positive-int $maxTtl      The maximum time-to-live (in seconds) for cached responses.
     *                                  If a server-provided TTL exceeds this value, it will be capped
     *                                  to this maximum.
     */
    public function __construct(
        private HttpClientInterface $client,
        private readonly TagAwareCacheInterface $cache,
        array $defaultOptions = [],
        private readonly bool $sharedCache = true,
        private readonly ?int $maxTtl = 86400,
    ) {
        if (null === $maxTtl) {
            trigger_deprecation('symfony/http-client', '8.1', 'Passing null as "$maxTtl" to "%s()" is deprecated, pass a positive integer instead.', __METHOD__);
        }

        if ($defaultOptions) {
            [, $this->defaultOptions] = self::prepareRequest(null, null, $defaultOptions, $this->defaultOptions);
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->isInnerRequest) {
            return $this->client->request($method, $url, $options);
        }

        [$fullUrl, $options] = self::prepareRequest($method, $url, $options, $this->defaultOptions);

        $fullUrl = implode('', $fullUrl);
        $fullUrlTag = self::hash($fullUrl);
        $requestCacheControl = self::parseRequestCacheControlHeader($options['normalized_headers']['cache-control'] ?? []);

        if ('' !== $options['body'] || ($options['extra']['no_cache'] ?? false) || isset($options['normalized_headers']['range']) || !\in_array($method, self::CACHEABLE_METHODS, true)) {
            if (isset($requestCacheControl['only-if-cached'])) {
                return self::createGatewayTimeoutResponse($method, $url, $options);
            }

            $passthru = function (ChunkInterface $chunk, AsyncContext $context) use ($method, $fullUrlTag): \Generator {
                if (null !== $chunk->getError() || $chunk->isTimeout() || !$chunk->isFirst()) {
                    yield $chunk;

                    return;
                }

                $statusCode = $context->getStatusCode();
                if ($statusCode >= 200 && $statusCode < 400 && !\in_array($method, self::SAFE_METHODS, true)) {
                    $this->cache->invalidateTags([$fullUrlTag]);
                }

                $context->passthru();

                yield $chunk;
            };

            $this->isInnerRequest = true;

            try {
                return new AsyncResponse($this, $method, $url, $options, $passthru);
            } finally {
                $this->isInnerRequest = false;
            }
        }

        if (isset($requestCacheControl['no-store'])) {
            return new AsyncResponse($this->client, $method, $url, $options);
        }

        $requestHash = self::hash($method.$fullUrl.serialize(array_intersect_key($options['normalized_headers'], self::RESPONSE_INFLUENCING_HEADERS)));
        $varyKey = "vary_{$requestHash}";
        $varyFields = $this->cache->get($varyKey, static fn ($item, &$save): array => ($save = false) ?: [], 0);

        $metadataKey = self::getMetadataKey($requestHash, $options['normalized_headers'], $varyFields);
        $cachedData = $this->cache->get($metadataKey, static fn ($item, &$save): array => ($save = false) ?: [], 0);
        $hasClientConditionalValidator = isset($options['normalized_headers']['if-none-match']) || isset($options['normalized_headers']['if-modified-since']);
        $sentCacheValidator = false;
        $allowStaleFallback = true;

        $freshness = null;
        if ($cachedData) {
            $freshness = $this->evaluateCacheFreshness($cachedData);
            $cachedResponseAcceptable = $this->isCachedResponseAcceptable($cachedData, $requestCacheControl, $freshness);
            $allowStaleFallback = $cachedResponseAcceptable || !$this->requestCacheControlRequiresRevalidation($cachedData, $requestCacheControl);

            if ($cachedResponseAcceptable) {
                if ($hasClientConditionalValidator && self::clientValidatorMatchesCachedResponse($options['normalized_headers'], $cachedData)) {
                    return self::createNotModifiedResponse($method, $url, $options, $cachedData['headers']);
                }

                return $this->createResponseFromCache($cachedData, $method, $url, $options, $metadataKey);
            }

            if (isset($requestCacheControl['only-if-cached'])) {
                return self::createGatewayTimeoutResponse($method, $url, $options);
            }

            if (!$hasClientConditionalValidator) {
                if (isset($cachedData['headers']['etag'])) {
                    $options['headers']['If-None-Match'] = implode(', ', $cachedData['headers']['etag']);
                    $sentCacheValidator = true;
                }

                if (isset($cachedData['headers']['last-modified'][0])) {
                    $options['headers']['If-Modified-Since'] = $cachedData['headers']['last-modified'][0];
                    $sentCacheValidator = true;
                }
            }
        }

        if (isset($requestCacheControl['only-if-cached'])) {
            return self::createGatewayTimeoutResponse($method, $url, $options);
        }

        // consistent expiration time for all items
        $expiresAt = \DateTimeImmutable::createFromFormat('U', time() + ($this->maxTtl ?? 86400));

        $passthru = function (ChunkInterface $chunk, AsyncContext $context) use (
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
            $hasClientConditionalValidator,
            $sentCacheValidator,
            $allowStaleFallback,
        ): \Generator {
            static $attemptTag = null;
            static $firstChunkKey = null;
            static $chunkKey = null;

            if (null !== $chunk->getError() || $chunk->isTimeout()) {
                null !== $attemptTag && $this->cache->invalidateTags([$attemptTag]);

                if ($allowStaleFallback && Freshness::StaleButUsable === $freshness) {
                    $this->logger?->info('Serving stale cached response for "{method} {url}" because the upstream call failed: {error}.', [
                        'method' => $method,
                        'url' => $url,
                        'error' => $chunk instanceof ErrorChunk ? $chunk->getError() : 'timeout',
                    ]);
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

                $attemptTag = self::generateChunkKey();

                if (304 === $statusCode && null !== $freshness) {
                    $shouldUpdateCachedMetadata = ($sentCacheValidator || $hasClientConditionalValidator) && self::notModifiedResponseMatchesCachedResponse($headers, $cachedData);

                    if (!$shouldUpdateCachedMetadata) {
                        $context->passthru();

                        yield $chunk;

                        return;
                    }

                    $responseTime = time();
                    $requestTime = (int) ($context->getInfo('start_time') ?? $responseTime);
                    if ($requestTime <= $cachedData['stored_at']) {
                        $requestTime = $responseTime;
                    }
                    $correctedInitialAge = self::getCorrectedInitialAge($headers, $requestTime, $responseTime);
                    $updatedHeaders = self::filterStorableHeaders(array_merge($cachedData['headers'], $headers), self::getExcludedHeaders($headers));
                    $updatedCachedData = array_replace($cachedData, [
                        'stored_at' => $responseTime,
                        'initial_age' => $correctedInitialAge,
                        'headers' => $updatedHeaders,
                    ]);

                    $updatedCacheControl = self::parseCacheControlHeader($updatedCachedData['headers']['cache-control'] ?? []);
                    $updatedCachedData['expires_at'] = self::calculateExpiresAt($this->determineMaxAge($updatedCachedData['headers'], $updatedCacheControl, $correctedInitialAge, $requestTime, $responseTime));

                    $newVaryFields = $this->parseVaryFields($updatedCachedData['headers']['vary'] ?? []);
                    $updatedMetadataIsCacheable = !\in_array('*', $newVaryFields, true)
                        && $varyFields === $newVaryFields
                        && $this->isServerResponseCacheable($cachedData['status_code'], $options['normalized_headers'], $updatedCachedData['headers'], $updatedCacheControl);

                    if (!$updatedMetadataIsCacheable) {
                        $this->cache->delete($metadataKey);

                        $context->passthru();

                        if (!$hasClientConditionalValidator) {
                            $context->replaceResponse($this->createResponseFromCache($updatedCachedData, $method, $url, $options, $metadataKey, $expiresAt));
                        }

                        return;
                    }

                    $updatedCachedData = $this->cache->get($metadataKey, static function (ItemInterface $item) use ($updatedCachedData, $expiresAt, $fullUrlTag, $metadataKey): array {
                        $item->expiresAt($expiresAt)->tag([$fullUrlTag, $metadataKey]);

                        return $updatedCachedData;
                    }, \INF);

                    $context->passthru();

                    if (!$hasClientConditionalValidator) {
                        $context->replaceResponse($this->createResponseFromCache($updatedCachedData, $method, $url, $options, $metadataKey, $expiresAt));
                    }

                    return;
                }

                if ($statusCode >= 500 && $statusCode < 600) {
                    if ($allowStaleFallback && Freshness::StaleButUsable === $freshness) {
                        $this->logger?->info('Serving stale cached response for "{method} {url}" because the upstream returned a server error (HTTP {status}).', [
                            'method' => $method,
                            'url' => $url,
                            'status' => $statusCode,
                        ]);
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

                $cacheControl = self::parseCacheControlHeader($headers['cache-control'] ?? []);

                if (!$this->isServerResponseCacheable($statusCode, $options['normalized_headers'], $headers, $cacheControl)) {
                    $context->passthru();

                    yield $chunk;

                    return;
                }

                // recomputing vary fields in case it changed or for first request
                $newVaryFields = $this->parseVaryFields($headers['vary'] ?? []);

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

                $requestTime = (int) ($context->getInfo('start_time') ?? time());
                $responseTime = time();
                $correctedInitialAge = self::getCorrectedInitialAge($headers, $requestTime, $responseTime);
                $maxAge = $this->determineMaxAge($headers, self::parseCacheControlHeader($headers['cache-control'] ?? []), $correctedInitialAge, $requestTime, $responseTime);
                $this->cache->get($metadataKey, static function (ItemInterface $item) use ($context, $headers, $maxAge, $expiresAt, $fullUrlTag, $metadataKey, $attemptTag, $firstChunkKey, $responseTime, $correctedInitialAge): array {
                    $item->tag([$fullUrlTag, $metadataKey, $attemptTag])->expiresAt($expiresAt);

                    return [
                        'status_code' => $context->getStatusCode(),
                        'headers' => self::filterStorableHeaders($headers),
                        'initial_age' => $correctedInitialAge,
                        'stored_at' => $responseTime,
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
        };

        $this->isInnerRequest = true;

        try {
            return new AsyncResponse($this, $method, $url, $options, $passthru);
        } finally {
            $this->isInnerRequest = false;
        }
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        $mockResponses = [];
        $asyncResponses = [];
        $clientResponses = [];

        foreach ($responses as $response) {
            if ($response instanceof MockResponse) {
                $mockResponses[] = $response;
            } elseif ($response instanceof AsyncResponse) {
                $asyncResponses[] = $response;
            } else {
                $clientResponses[] = $response;
            }
        }

        if (!$mockResponses && !$clientResponses) {
            return $this->asyncStream($asyncResponses, $timeout);
        }

        if (!$asyncResponses && !$clientResponses) {
            return new ResponseStream(MockResponse::stream($mockResponses, $timeout));
        }

        if (!$mockResponses && !$asyncResponses) {
            return $this->client->stream($clientResponses, $timeout);
        }

        return new ResponseStream((function () use ($mockResponses, $asyncResponses, $clientResponses, $timeout) {
            if ($mockResponses) {
                yield from MockResponse::stream($mockResponses, $timeout);
            }
            if ($clientResponses) {
                yield from $this->client->stream($clientResponses, $timeout);
            }
            if ($asyncResponses) {
                yield from $this->asyncStream($asyncResponses, $timeout);
            }
        })());
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
                    $normalizedName = strtolower(trim($name));
                    $normalizedValue = self::unquoteCacheControlValue(trim($value));
                } else {
                    $normalizedName = strtolower(trim($directive));
                    $normalizedValue = true;
                }

                if ('' === $normalizedName) {
                    continue;
                }

                if (\array_key_exists($normalizedName, $parsed)) {
                    // Duplicate directive values are ambiguous; make value-based checks fail closed.
                    $parsed[$normalizedName] = '';

                    continue;
                }

                $parsed[$normalizedName] = $normalizedValue;
            }
        }

        return $parsed;
    }

    private static function unquoteCacheControlValue(string $value): string
    {
        if (2 <= \strlen($value) && '"' === $value[0] && '"' === $value[-1]) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private static function parseDeltaSeconds(string|true|null $value): ?int
    {
        if (!\is_string($value) || '' === $value || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, string[]> $headers
     *
     * @return array<string, string[]>
     */
    private static function filterStorableHeaders(array $headers, ?array $excludedHeaders = null): array
    {
        $excludedHeaders ??= self::getExcludedHeaders($headers);

        return array_diff_key($headers, $excludedHeaders);
    }

    /**
     * @param array<string, string[]> $headers
     *
     * @return array<string, bool>
     */
    private static function getExcludedHeaders(array $headers): array
    {
        $excludedHeaders = self::EXCLUDED_HEADERS;

        foreach ($headers['connection'] ?? [] as $connectionHeader) {
            foreach (explode(',', $connectionHeader) as $headerName) {
                $headerName = strtolower(trim($headerName));

                if ('' !== $headerName) {
                    $excludedHeaders[$headerName] = true;
                }
            }
        }

        return $excludedHeaders;
    }

    /**
     * @param array<string, string[]>                 $headers
     * @param array{headers: array<string, string[]>} $cachedData
     */
    private static function notModifiedResponseMatchesCachedResponse(array $headers, array $cachedData): bool
    {
        if (isset($headers['etag'])) {
            $cachedEtags = $cachedData['headers']['etag'] ?? [];

            foreach ($headers['etag'] as $etag) {
                if (!self::isWeakEtag($etag)) {
                    foreach ($cachedEtags as $cachedEtag) {
                        if (!self::isWeakEtag($cachedEtag) && $etag === $cachedEtag) {
                            return true;
                        }
                    }

                    return false;
                }
            }

            foreach ($headers['etag'] as $etag) {
                foreach ($cachedEtags as $cachedEtag) {
                    if (self::stripWeakPrefix($etag) === self::stripWeakPrefix($cachedEtag)) {
                        return true;
                    }
                }
            }

            return false;
        }

        foreach ($headers['last-modified'] ?? [] as $lastModified) {
            return \in_array($lastModified, $cachedData['headers']['last-modified'] ?? [], true);
        }

        return !isset($cachedData['headers']['etag']) && !isset($cachedData['headers']['last-modified']);
    }

    /**
     * @param array<string, string[]>                 $requestHeaders
     * @param array{headers: array<string, string[]>} $cachedData
     */
    private static function clientValidatorMatchesCachedResponse(array $requestHeaders, array $cachedData): bool
    {
        if (isset($requestHeaders['if-none-match'])) {
            $cachedEtags = $cachedData['headers']['etag'] ?? [];

            foreach ($requestHeaders['if-none-match'] as $ifNoneMatch) {
                $ifNoneMatch = substr($ifNoneMatch, 15);

                foreach (explode(',', $ifNoneMatch) as $etag) {
                    $etag = trim($etag);

                    if ('*' === $etag && [] !== $cachedEtags) {
                        return true;
                    }

                    foreach ($cachedEtags as $cachedEtag) {
                        if (self::stripWeakPrefix($etag) === self::stripWeakPrefix($cachedEtag)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        if (!isset($requestHeaders['if-modified-since'], $cachedData['headers']['last-modified'][0])) {
            return false;
        }

        $lastModified = strtotime($cachedData['headers']['last-modified'][0]);
        if (false === $lastModified) {
            return false;
        }

        foreach ($requestHeaders['if-modified-since'] as $ifModifiedSince) {
            $ifModifiedSince = substr($ifModifiedSince, 19);

            if (false !== $modifiedSince = strtotime($ifModifiedSince)) {
                return $lastModified <= $modifiedSince;
            }
        }

        return false;
    }

    private static function stripWeakPrefix(string $etag): string
    {
        return str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
    }

    private static function isWeakEtag(string $etag): bool
    {
        return str_starts_with($etag, 'W/');
    }

    /**
     * @param string[] $header
     *
     * @return array<string, string|true>
     */
    private static function parseRequestCacheControlHeader(array $header): array
    {
        $cacheControlHeader = [];

        foreach ($header as $line) {
            // Strip the "Cache-Control: " prefix added by normalizeHeaders(); response headers contain values only.
            $cacheControlHeader[] = substr($line, 15);
        }

        return self::parseCacheControlHeader($cacheControlHeader);
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
        $cacheControl = self::parseCacheControlHeader($data['headers']['cache-control'] ?? []);

        if (isset($cacheControl['no-cache'])) {
            return Freshness::Stale;
        }

        $now = time();
        $expires = $data['expires_at'];

        if (null !== $expires && $now < $expires) {
            return Freshness::Fresh;
        }

        if (
            isset($cacheControl['must-revalidate'])
            || (
                $this->sharedCache
                && (
                    isset($cacheControl['proxy-revalidate'])
                    || self::hasValidSharedMaxAge($cacheControl)
                )
            )
        ) {
            return Freshness::MustRevalidate;
        }

        if (
            null !== ($staleIfError = self::parseDeltaSeconds($cacheControl['stale-if-error'] ?? null))
            && ($now - $expires) <= $staleIfError
        ) {
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
    private function determineMaxAge(array $headers, array $cacheControl, int $correctedInitialAge, int $requestTime, int $responseTime): ?int
    {
        $age = $correctedInitialAge;

        if ($this->sharedCache && isset($cacheControl['s-maxage'])) {
            if (null === $sharedMaxAge = self::parseDeltaSeconds($cacheControl['s-maxage'])) {
                return null;
            }

            return max(0, $sharedMaxAge - $age);
        }

        if (isset($cacheControl['max-age'])) {
            if (null === $maxAge = self::parseDeltaSeconds($cacheControl['max-age'])) {
                return null;
            }

            return max(0, $maxAge - $age);
        }

        foreach ($headers['expires'] ?? [] as $expire) {
            if (false !== $expirationTimestamp = strtotime($expire)) {
                $dateTimestamp = self::getDateHeaderTimestamp($headers) ?? $responseTime;
                $timeUntilExpiration = $expirationTimestamp - $dateTimestamp - $age;

                return max($timeUntilExpiration, 0);
            }
        }

        if (null !== $heuristicFreshnessLifetime = $this->determineHeuristicFreshnessLifetime($headers, $cacheControl, $requestTime)) {
            return max(0, $heuristicFreshnessLifetime - $age);
        }

        return null;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, string|true>     $cacheControl
     */
    private function determineHeuristicFreshnessLifetime(array $headers, array $cacheControl, int $requestTime): ?int
    {
        if (!$this->allowsHeuristicFreshness($headers, $cacheControl)) {
            return null;
        }

        foreach ($headers['last-modified'] as $lastModified) {
            if (false === $lastModifiedTimestamp = strtotime($lastModified)) {
                continue;
            }

            $secondsSinceLastModified = $requestTime - $lastModifiedTimestamp;

            if (0 >= $secondsSinceLastModified) {
                continue;
            }

            $heuristicFreshnessLifetime = (int) ($secondsSinceLastModified * self::HEURISTIC_FRESHNESS_FRACTION);

            return min($heuristicFreshnessLifetime, self::MAX_HEURISTIC_FRESHNESS_TTL);
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
     * @param array<string, string|string[]> $headers
     */
    private static function getDateHeaderTimestamp(array $headers): ?int
    {
        foreach ($headers['date'] ?? [] as $date) {
            if (false !== $timestamp = strtotime($date)) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private static function getCorrectedInitialAge(array $headers, int $requestTime, int $responseTime): int
    {
        $ageValue = self::getCurrentAge($headers);
        $dateValue = self::getDateHeaderTimestamp($headers);
        $apparentAge = null === $dateValue ? 0 : max(0, $responseTime - $dateValue);
        $responseDelay = max(0, $responseTime - $requestTime);
        $correctedAgeValue = $ageValue + $responseDelay;

        return max($apparentAge, $correctedAgeValue);
    }

    /**
     * @param array{initial_age: int, stored_at: int} $cachedData
     * @param array<string, string|true>              $requestCacheControl
     */
    private function isCachedResponseAcceptable(array $cachedData, array $requestCacheControl, Freshness $freshness): bool
    {
        if (Freshness::Fresh !== $freshness || isset($requestCacheControl['no-cache'])) {
            return false;
        }

        if (!isset($requestCacheControl['max-age'])) {
            return true;
        }

        if (null === $maxAge = self::parseDeltaSeconds($requestCacheControl['max-age'])) {
            return true;
        }

        if (0 === $maxAge) {
            return false;
        }

        return $this->getCachedResponseAge($cachedData) <= $maxAge;
    }

    /**
     * @param array{initial_age: int, stored_at: int} $cachedData
     * @param array<string, string|true>              $requestCacheControl
     */
    private function requestCacheControlRequiresRevalidation(array $cachedData, array $requestCacheControl): bool
    {
        if (isset($requestCacheControl['no-cache'])) {
            return true;
        }

        if (null === $maxAge = self::parseDeltaSeconds($requestCacheControl['max-age'] ?? null)) {
            return false;
        }

        return $this->getCachedResponseAge($cachedData) > $maxAge;
    }

    /**
     * @param array{initial_age: int, stored_at: int} $cachedData
     */
    private function getCachedResponseAge(array $cachedData): int
    {
        return $cachedData['initial_age'] + (time() - $cachedData['stored_at']);
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
     * @param string[] $varyHeaders
     *
     * @return string[]
     */
    private function parseVaryFields(array $varyHeaders): array
    {
        $varyFields = [];
        foreach ($varyHeaders as $vary) {
            foreach (explode(',', $vary) as $field) {
                $field = strtolower(trim($field));
                if ('cookie' === $field ? $this->sharedCache : !preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D', $field)) {
                    $field = '*';
                }
                $varyFields[] = $field;
            }
        }

        sort($varyFields);

        return $varyFields;
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
        // Only final status codes are cacheable by RFC 9111 Section 3.
        if ($statusCode < 200 || $statusCode > 599 || self::isStatusCodeExcludedFromStorage($statusCode)) {
            return false;
        }

        // no-store => skip caching
        if (isset($cacheControl['no-store'])) {
            return false;
        }

        if ($this->sharedCache) {
            if (
                !isset($cacheControl['public']) && !self::hasValidSharedMaxAge($cacheControl) && !isset($cacheControl['must-revalidate'])
                && isset($requestHeaders['authorization'])
            ) {
                return false;
            }

            if (isset($cacheControl['private'])) {
                return false;
            }

            if (isset($responseHeaders['authentication-info']) || isset($responseHeaders['set-cookie']) || isset($responseHeaders['www-authenticate'])) {
                return false;
            }
        }

        if (\in_array($statusCode, self::DEFAULT_CACHEABLE_STATUS_CODES, true)) {
            return true;
        }

        if ($this->hasExplicitFreshness($responseHeaders, $cacheControl)) {
            return true;
        }

        return $this->allowsHeuristicFreshnessForNonDefaultStatus($responseHeaders, $cacheControl);
    }

    private static function isStatusCodeExcludedFromStorage(int $statusCode): bool
    {
        // 206 is only cacheable for range requests, which this client does not store.
        return 206 === $statusCode || 304 === $statusCode;
    }

    /**
     * Checks if the response has explicit freshness.
     *
     * This function will return true if the response has explicit freshness
     * specified in the headers or in the Cache-Control directives,
     * false otherwise.
     *
     * @param array<string, string|string[]> $headers
     * @param array<string, string|true>     $cacheControl
     */
    private function hasExplicitFreshness(array $headers, array $cacheControl): bool
    {
        if (($this->sharedCache && self::hasValidSharedMaxAge($cacheControl)) || null !== self::parseDeltaSeconds($cacheControl['max-age'] ?? null)) {
            return true;
        }

        foreach ($headers['expires'] ?? [] as $expires) {
            if (false !== strtotime($expires)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string|true> $cacheControl
     */
    private static function hasValidSharedMaxAge(array $cacheControl): bool
    {
        return null !== self::parseDeltaSeconds($cacheControl['s-maxage'] ?? null);
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, string|true>     $cacheControl
     */
    private function allowsHeuristicFreshnessForNonDefaultStatus(array $headers, array $cacheControl): bool
    {
        return isset($cacheControl['public']) && $this->allowsHeuristicFreshness($headers, $cacheControl);
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, string|true>     $cacheControl
     */
    private function allowsHeuristicFreshness(array $headers, array $cacheControl): bool
    {
        return !isset($cacheControl['no-cache'])
            && !isset($cacheControl['no-store'])
            && isset($headers['last-modified']);
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
    private function createResponseFromCache(array $cachedData, string $method, string $url, array $options, string $metadataKey, \DateTimeImmutable|false|null $newExpiresAt = false): MockResponse
    {
        $cache = $this->cache;

        $beta = 0;
        $callback = static function (ItemInterface $item) use ($cache, $metadataKey): never {
            $cache->invalidateTags([$metadataKey]);

            throw new ChunkCacheItemNotFoundException(\sprintf('Missing cache item for chunk with key "%s". This indicates an internal cache inconsistency.', $item->getKey()));
        };

        if (false !== $newExpiresAt) {
            $beta = \INF;
            $callback = static function (ItemInterface $item) use ($callback, $newExpiresAt): array {
                if (!$item->isHit()) {
                    $callback($item);
                }

                $item->expiresAt($newExpiresAt);

                return $item->get();
            };
        }

        $body = static function () use ($cache, $cachedData, $callback, $beta): \Generator {
            while (null !== $cachedData['next_chunk']) {
                $cachedData = $cache->get($cachedData['next_chunk'], $callback, $beta);

                if ('' !== $cachedData['content']) {
                    yield $cachedData['content'];
                }
            }
        };

        return MockResponse::fromRequest($method, $url, $options, new MockResponse($body(), [
            'http_code' => $cachedData['status_code'],
            'response_headers' => [
                'age' => $this->getCachedResponseAge($cachedData),
            ] + $cachedData['headers'],
        ]));
    }

    private static function createGatewayTimeoutResponse(string $method, string $url, array $options): MockResponse
    {
        return MockResponse::fromRequest($method, $url, $options, new MockResponse('', ['http_code' => 504]));
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private static function createNotModifiedResponse(string $method, string $url, array $options, array $headers): MockResponse
    {
        return MockResponse::fromRequest($method, $url, $options, new MockResponse('', [
            'http_code' => 304,
            'response_headers' => array_diff_key($headers, self::EXCLUDED_HEADERS),
        ]));
    }
}
