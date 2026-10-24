<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass;

use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Http\PrivacyPass\Exception\IssuerDirectoryException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The keys an issuer publishes, which an origin verifies its tokens with.
 *
 * Iterating it fetches the directory of the issuer, and caches it: the keys of a token type are returned with the one to challenge with first, and a key the issuer has not started using yet last, since a token signed with it only shows up once the issuer rotates to it.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9578#section-4
 *
 * @implements \IteratorAggregate<int, TokenKeyInterface>
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class IssuerDirectory implements \IteratorAggregate, ResetInterface
{
    private const WELL_KNOWN_URI = '/.well-known/private-token-issuer-directory';

    // directories hold a handful of keys. This limits untrusted data retained by the cache.
    private const MAX_DIRECTORY_SIZE = 1024 * 1024;

    private readonly string $url;

    /**
     * @var list<TokenKeyInterface>|null
     */
    private ?array $tokenKeys = null;

    /**
     * @param string              $issuerName The host, with an optional port, of the issuer
     * @param int|null            $cacheTtl   The longest a directory is kept, in seconds, which the cache directives of the issuer may shorten, or null to leave the lifetime to the cache pool
     * @param ClockInterface|null $clock      Any PSR-20 implementation, or null to read the system time
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        string $issuerName,
        private readonly TokenTypeInterface $tokenType,
        private readonly ?int $cacheTtl = 3600,
        private readonly ?ClockInterface $clock = null,
    ) {
        if (!TokenChallenge::isServerName($issuerName)) {
            throw new \InvalidArgumentException(\sprintf('The issuer name "%s" must be an ASCII host with an optional port, and no user information.', $issuerName));
        }

        // the directory is read over HTTPS whatever the origin itself is served over: the keys it
        // carries are what every token is verified against
        $this->url = 'https://'.$issuerName.self::WELL_KNOWN_URI;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->getTokenKeys());
    }

    public function reset(): void
    {
        $this->tokenKeys = null;
    }

    /**
     * @return list<TokenKeyInterface>
     *
     * @throws IssuerDirectoryException If the directory cannot be fetched, or announces no usable key
     */
    private function getTokenKeys(): array
    {
        // the keys are memoized for the lifetime of the instance, so that verifying several tokens
        // in one request neither re-reads the cache nor rebuilds the keys
        if (null !== $this->tokenKeys) {
            return $this->tokenKeys;
        }

        $payload = $this->cache->get('privacy-pass.issuer-directory.'.hash('xxh128', $this->url), function (ItemInterface $item, bool &$save): string {
            $response = $this->request();
            $payload = $this->read($response);

            // parsed here so that a directory holding no usable key is rejected before it reaches
            // the cache, where it would otherwise fail on every read until the entry expires
            $this->parse($payload);

            $directives = self::getCacheDirectives($response);

            if (isset($directives['no-store']) || isset($directives['no-cache'])) {
                $save = false;
            } elseif (null !== $this->cacheTtl) {
                $item->expiresAfter(min($this->cacheTtl, $directives['max-age'] ?? $this->cacheTtl));
            }

            return $payload;
        });

        return $this->tokenKeys = $this->parse($payload);
    }

    private function request(): ResponseInterface
    {
        try {
            $response = $this->httpClient->request('GET', $this->url, ['max_redirects' => 0]);
            $response->getHeaders(); // triggers the exception on a 3xx, 4xx or 5xx status

            return $response;
        } catch (HttpClientExceptionInterface $e) {
            throw new IssuerDirectoryException(\sprintf('The issuer directory could not be fetched from "%s": "%s".', $this->url, $e->getMessage()), previous: $e);
        }
    }

    private function read(ResponseInterface $response): string
    {
        try {
            $payload = '';
            foreach ($this->httpClient->stream($response) as $chunk) {
                $payload .= $chunk->getContent();

                if (self::MAX_DIRECTORY_SIZE < \strlen($payload)) {
                    $response->cancel();

                    throw new IssuerDirectoryException(\sprintf('The issuer directory at "%s" exceeds the maximum size of 1 MiB.', $this->url));
                }
            }

            return $payload;
        } catch (HttpClientExceptionInterface $e) {
            throw new IssuerDirectoryException(\sprintf('The issuer directory could not be fetched from "%s": "%s".', $this->url, $e->getMessage()), previous: $e);
        }
    }

    /**
     * @return list<TokenKeyInterface>
     */
    private function parse(string $payload): array
    {
        try {
            $directory = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new IssuerDirectoryException(\sprintf('The issuer directory at "%s" is not valid JSON: "%s".', $this->url, $e->getMessage()), previous: $e);
        }

        if (!\is_array($directory['token-keys'] ?? null)) {
            throw new IssuerDirectoryException(\sprintf('The issuer directory at "%s" announces no "token-keys".', $this->url));
        }

        $now = $this->clock?->now()->getTimestamp() ?? time();
        $rejected = null;
        $active = [];
        $staged = [];

        foreach ($directory['token-keys'] as $entry) {
            if (!\is_array($entry) || $this->tokenType->getValue() !== ($entry['token-type'] ?? null) || !\is_string($entry['token-key'] ?? null)) {
                continue;
            }

            try {
                $tokenKey = $this->tokenType->createTokenKey($entry['token-key']);
            } catch (\InvalidArgumentException $e) {
                // a key the issuer rotates to may be one this origin cannot read, which is no
                // reason to stop verifying the tokens signed with the others
                $rejected ??= $e;

                continue;
            }

            if (\is_int($entry['not-before'] ?? null) && $entry['not-before'] > $now) {
                $staged[] = $tokenKey;
            } else {
                $active[] = $tokenKey;
            }
        }

        if (!$active && !$staged) {
            throw new IssuerDirectoryException(\sprintf('The issuer directory at "%s" announces no key a token of type "0x%04X" can be verified with%s', $this->url, $this->tokenType->getValue(), null !== $rejected ? ': '.$rejected->getMessage() : '.'), previous: $rejected);
        }

        return [...$active, ...$staged];
    }

    /**
     * @return array<string, int|true>
     */
    private static function getCacheDirectives(ResponseInterface $response): array
    {
        $directives = [];

        foreach ($response->getHeaders(false)['cache-control'] ?? [] as $header) {
            foreach (explode(',', $header) as $directive) {
                [$name, $value] = array_pad(explode('=', trim($directive), 2), 2, null);
                $directives[strtolower($name)] = null !== $value ? (int) trim($value, '"') : true;
            }
        }

        return $directives;
    }
}
