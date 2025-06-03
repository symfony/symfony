<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpFoundation\Response;

/**
 * ResponseCacheStrategy knows how to compute the Response cache HTTP header
 * based on the different response cache headers.
 *
 * This implementation changes the main response TTL to the smallest TTL received
 * or force validation if one of the surrogates has validation cache strategy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ResponseCacheStrategy implements ResponseCacheStrategyInterface
{
    /**
     * Cache-Control headers that are sent to the final response if they appear in ANY of the responses.
     */
    private const OVERRIDE_DIRECTIVES = ['private', 'no-cache', 'no-store', 'no-transform', 'must-revalidate', 'proxy-revalidate'];

    /**
     * Cache-Control headers that are sent to the final response if they appear in ALL of the responses.
     */
    private const INHERIT_DIRECTIVES = ['public', 'immutable'];

    private int $embeddedResponses = 0;
    private bool $isNotCacheableResponseEmbedded = false;
    private int $age = 0;
    private \DateTimeInterface|null|false $lastModified = null;
    private array $flagDirectives = [
        'no-cache' => null,
        'no-store' => null,
        'no-transform' => null,
        'must-revalidate' => null,
        'proxy-revalidate' => null,
        'public' => null,
        'private' => null,
        'immutable' => null,
    ];
    private array $ageDirectives = [
        'max-age' => null,
        's-maxage' => null,
        'expires' => null,
    ];

    /**
     * @return void
     */
    public function add(Response $response)
    {
        ++$this->embeddedResponses;

        foreach (self::OVERRIDE_DIRECTIVES as $directive) {
            if ($response->headers->hasCacheControlDirective($directive)) {
                $this->flagDirectives[$directive] = true;
            }
        }

        foreach (self::INHERIT_DIRECTIVES as $directive) {
            if (false !== $this->flagDirectives[$directive]) {
                $this->flagDirectives[$directive] = $response->headers->hasCacheControlDirective($directive);
            }
        }

        $age = $response->getAge();
        $this->age = max($this->age, $age);

        if ($this->willMakeFinalResponseUncacheable($response)) {
            $this->isNotCacheableResponseEmbedded = true;

            return;
        }

        $isHeuristicallyCacheable = $response->headers->hasCacheControlDirective('public');
        $maxAge = $response->headers->hasCacheControlDirective('max-age') ? (int) $response->headers->getCacheControlDirective('max-age') : null;
        $this->storeRelativeAgeDirective('max-age', $maxAge, $age, $isHeuristicallyCacheable);
        $sharedMaxAge = $response->headers->hasCacheControlDirective('s-maxage') ? (int) $response->headers->getCacheControlDirective('s-maxage') : $maxAge;
        $this->storeRelativeAgeDirective('s-maxage', $sharedMaxAge, $age, $isHeuristicallyCacheable);

        $expires = $response->getExpires();
        $expires = null !== $expires ? (int) $expires->format('U') - (int) $response->getDate()->format('U') : null;
        $this->storeRelativeAgeDirective('expires', $expires >= 0 ? $expires : null, 0, $isHeuristicallyCacheable);

        if (false !== $this->lastModified) {
            $lastModified = $response->getLastModified();
            $this->lastModified = $lastModified ? max($this->lastModified, $lastModified) : false;
        }
    }

    /**
     * @return void
     */
    public function update(Response $response)
    {
        // if we have no embedded Response, do nothing
        if (0 === $this->embeddedResponses) {
            return;
        }

        // Remove Etag since it cannot be merged from embedded responses.
        $response->setEtag(null);

        $this->add($response);

        $response->headers->set('Age', $this->age);

        if ($this->isNotCacheableResponseEmbedded) {
            $response->setLastModified(null);

            if ($this->flagDirectives['no-store']) {
                $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            } else {
                $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            }

            return;
        }

        $response->setLastModified($this->lastModified ?: null);

        $flags = array_filter($this->flagDirectives);

        if (isset($flags['must-revalidate'])) {
            $flags['no-cache'] = true;
        }

        $response->headers->set('Cache-Control', implode(', ', array_keys($flags)));

        $maxAge = null;

        if (is_numeric($this->ageDirectives['max-age'])) {
            $maxAge = $this->ageDirectives['max-age'] + $this->age;
            $response->headers->addCacheControlDirective('max-age', $maxAge);
        }

        if (is_numeric($this->ageDirectives['s-maxage'])) {
            $sMaxage = $this->ageDirectives['s-maxage'] + $this->age;

            if ($maxAge !== $sMaxage) {
                $response->headers->addCacheControlDirective('s-maxage', $sMaxage);
            }
        }

        if (is_numeric($this->ageDirectives['expires'])) {
            $date = clone $response->getDate();
            $date = $date->modify('+'.($this->ageDirectives['expires'] + $this->age).' seconds');
            $response->setExpires($date);
        }
    }

    /**
     * RFC2616, Section 13.4.
     *
     * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html#sec13.4
     */
    private function willMakeFinalResponseUncacheable(Response $response): bool
    {
        // RFC2616: A response received with a status code of 200, 203, 300, 301 or 410
        // MAY be stored by a cache […] unless a cache-control directive prohibits caching.
        if ($response->headers->hasCacheControlDirective('no-cache')
            || $response->headers->hasCacheControlDirective('no-store')
        ) {
            return true;
        }

        // Etag headers cannot be merged, they render the response uncacheable
        // by default (except if the response also has max-age etc.).
        if (null === $response->getEtag() && \in_array($response->getStatusCode(), [200, 203, 300, 301, 410])) {
            return false;
        }

        // RFC2616: A response received with any other status code (e.g. status codes 302 and 307)
        // MUST NOT be returned in a reply to a subsequent request unless there are
        // cache-control directives or another header(s) that explicitly allow it.
        $cacheControl = ['max-age', 's-maxage', 'must-revalidate', 'proxy-revalidate', 'public', 'private'];
        foreach ($cacheControl as $key) {
            if ($response->headers->hasCacheControlDirective($key)) {
                return false;
            }
        }

        if ($response->headers->has('Expires')) {
            return false;
        }

        return true;
    }

    /**
     * Store lowest max-age/s-maxage/expires for the final response.
     *
     * The response might have been stored in cache a while ago. To keep things comparable,
     * we have to subtract the age so that the value is normalized for an age of 0.
     *
     * If the value is lower than the currently stored value, we update the value, to keep a rolling
     * minimal value of each instruction.
     *
     * If the value is NULL and the isHeuristicallyCacheable parameter is false, the directive will
     * not be set on the final response. In this case, not all responses had the directive set and no
     * value can be found that satisfies the requirements of all responses. The directive will be dropped
     * from the final response.
     *
     * If the isHeuristicallyCacheable parameter is true, however, the current response has been marked
     * as cacheable in a public (shared) cache, but did not provide an explicit lifetime that would serve
     * as an upper bound. In this case, we can proceed and possibly keep the directive on the final response.
     */
    private function storeRelativeAgeDirective(string $directive, ?int $value, int $age, bool $isHeuristicallyCacheable): void
    {
        if (null === $value) {
            if ($isHeuristicallyCacheable) {
                /*
                 * See https://datatracker.ietf.org/doc/html/rfc7234#section-4.2.2
                 * This particular response does not require maximum lifetime; heuristics might be applied.
                 * Other responses, however, might have more stringent requirements on maximum lifetime.
                 * So, return early here so that the final response can have the more limiting value set.
                 */
                return;
            }
            $this->ageDirectives[$directive] = false;
        }

        if (false !== $this->ageDirectives[$directive]) {
            $value -= $age;
            $this->ageDirectives[$directive] = null !== $this->ageDirectives[$directive] ? min($this->ageDirectives[$directive], $value) : $value;
        }
    }
}
