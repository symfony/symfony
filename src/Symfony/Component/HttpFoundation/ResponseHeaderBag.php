<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * ResponseHeaderBag is a container for Response HTTP headers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ResponseHeaderBag extends HeaderBag
{
    public const COOKIES_FLAT = 'flat';
    public const COOKIES_ARRAY = 'array';

    public const DISPOSITION_ATTACHMENT = 'attachment';
    public const DISPOSITION_INLINE = 'inline';

    protected array $cookies = [];
    protected array $headerNames = [];

    public function __construct(array $headers = [])
    {
        parent::__construct($headers);

        // ensure that an empty default cache control object is initialized
        $this->getCacheControl();

        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (!isset($this->headers['date'])) {
            $this->initDate();
        }
    }

    /**
     * Returns the headers, with original capitalizations.
     */
    public function allPreserveCase(): array
    {
        $headers = [];
        foreach ($this->all() as $name => $value) {
            $headers[$this->headerNames[$name] ?? $name] = $value;
        }
        foreach ($this->cacheControls as $target => $cacheControl) {
            if (self::DEFAULT_CACHE_CONTROL_TARGET === $target) {
                unset($headers['cache-control']);
                $headers['Cache-Control'] = [$this->computeCacheControl()->getCacheControlHeader()];
            } else {
                unset($headers[$target.'-cache-control']);
                $headers[$target.'-Cache-Control'] = [$cacheControl->getCacheControlHeader()];
            }
        }

        return $headers;
    }

    public function allPreserveCaseWithoutCookies(): array
    {
        $headers = $this->allPreserveCase();
        if (isset($this->headerNames['set-cookie'])) {
            unset($headers[$this->headerNames['set-cookie']]);
        }

        return $headers;
    }

    public function replace(array $headers = []): void
    {
        $this->headerNames = [];

        parent::replace($headers);

        // ensure that an empty default cache control object is initialized
        $this->getCacheControl();

        if (!isset($this->headers['date'])) {
            $this->initDate();
        }
    }

    public function all(?string $key = null): array
    {
        $headers = parent::all();

        if (null !== $key) {
            $key = strtr($key, self::UPPER, self::LOWER);

            return match ($key) {
                'set-cookie' => array_map('strval', $this->getCookies()),
                'cache-control' => [$this->computeCacheControl()->getCacheControlHeader()],
                default => $headers[$key] ?? [],
            };
        }

        foreach ($this->getCookies() as $cookie) {
            $headers['set-cookie'][] = (string) $cookie;
        }
        if (\array_key_exists(self::DEFAULT_CACHE_CONTROL_TARGET, $this->cacheControls)) {
            $headers['cache-control'] = [$this->computeCacheControl()->getCacheControlHeader()];
        }

        return $headers;
    }

    public function set(string $key, string|array|null $values, bool $replace = true): void
    {
        $uniqueKey = strtr($key, self::UPPER, self::LOWER);

        if ('set-cookie' === $uniqueKey) {
            if ($replace) {
                $this->cookies = [];
            }
            foreach ((array) $values as $cookie) {
                $this->setCookie(Cookie::fromString($cookie));
            }
            $this->headerNames[$uniqueKey] = $key;

            return;
        }

        $this->headerNames[$uniqueKey] = $key;

        parent::set($key, $values, $replace);
    }

    public function remove(string $key): void
    {
        $uniqueKey = strtr($key, self::UPPER, self::LOWER);
        unset($this->headerNames[$uniqueKey]);

        if ('set-cookie' === $uniqueKey) {
            $this->cookies = [];

            return;
        }

        parent::remove($key);

        if ('date' === $uniqueKey) {
            $this->initDate();
        }
    }

    /**
     * @deprecated use computeCacheControl()->hasCacheControlDirective instead)
     */
    public function hasCacheControlDirective(string $key): bool
    {
        return $this->computeCacheControl()->hasCacheControlDirective($key);
    }

    /**
     * @deprecated use computeCacheControl()->getCacheControlDirective instead)
     */
    public function getCacheControlDirective(string $key): bool|string|null
    {
        return $this->computeCacheControl()->getCacheControlDirective($key);
    }

    public function setCookie(Cookie $cookie): void
    {
        $this->cookies[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
        $this->headerNames['set-cookie'] = 'Set-Cookie';
    }

    /**
     * Removes a cookie from the array, but does not unset it in the browser.
     */
    public function removeCookie(string $name, ?string $path = '/', ?string $domain = null): void
    {
        $path ??= '/';

        unset($this->cookies[$domain][$path][$name]);

        if (empty($this->cookies[$domain][$path])) {
            unset($this->cookies[$domain][$path]);

            if (empty($this->cookies[$domain])) {
                unset($this->cookies[$domain]);
            }
        }

        if (!$this->cookies) {
            unset($this->headerNames['set-cookie']);
        }
    }

    /**
     * Returns an array with all cookies.
     *
     * @return Cookie[]
     *
     * @throws \InvalidArgumentException When the $format is invalid
     */
    public function getCookies(string $format = self::COOKIES_FLAT): array
    {
        if (!\in_array($format, [self::COOKIES_FLAT, self::COOKIES_ARRAY])) {
            throw new \InvalidArgumentException(\sprintf('Format "%s" invalid (%s).', $format, implode(', ', [self::COOKIES_FLAT, self::COOKIES_ARRAY])));
        }

        if (self::COOKIES_ARRAY === $format) {
            return $this->cookies;
        }

        $flattenedCookies = [];
        foreach ($this->cookies as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattenedCookies[] = $cookie;
                }
            }
        }

        return $flattenedCookies;
    }

    /**
     * Clears a cookie in the browser.
     *
     * @param bool $partitioned
     */
    public function clearCookie(string $name, ?string $path = '/', ?string $domain = null, bool $secure = false, bool $httpOnly = true, ?string $sameSite = null /* , bool $partitioned = false */): void
    {
        $partitioned = 6 < \func_num_args() ? func_get_arg(6) : false;

        $this->setCookie(new Cookie($name, null, 1, $path, $domain, $secure, $httpOnly, false, $sameSite, $partitioned));
    }

    /**
     * @see HeaderUtils::makeDisposition()
     */
    public function makeDisposition(string $disposition, string $filename, string $filenameFallback = ''): string
    {
        return HeaderUtils::makeDisposition($disposition, $filename, $filenameFallback);
    }

    /**
     * Returns the calculated value of the cache-control header.
     *
     * This considers several other headers and calculates or modifies the
     * cache-control header to a sensible, conservative value.
     */
    protected function computeCacheControl(): CacheControl
    {
        if (!\array_key_exists(self::DEFAULT_CACHE_CONTROL_TARGET, $this->cacheControls)) {
            // cache control explicitly removed
            return new CacheControl();
        }
        $cacheControl = $this->getCacheControl();
        if ($cacheControl->empty()) {
            if (\array_key_exists('last-modified', $this->headers) || \array_key_exists('expires', $this->headers)) {
                return new CacheControl(['private' => true, 'must-revalidate' => true]); // allows for heuristic expiration (RFC 7234 Section 4.2.2) in the case of "Last-Modified"
            }

            // conservative by default
            return new CacheControl(['no-cache' => true, 'private' => true]);
        }

        if ($cacheControl->hasCacheControlDirective('public') || $cacheControl->hasCacheControlDirective('private')) {
            return $cacheControl; // do we need to clone? i think not
        }

        // public if s-maxage is defined, private otherwise
        if (!$cacheControl->hasCacheControlDirective('s-maxage')) {
            $cacheControl = clone $cacheControl;
            $cacheControl->addCacheControlDirective('private');
        }

        return $cacheControl;
    }

    private function initDate(): void
    {
        $this->set('Date', gmdate('D, d M Y H:i:s').' GMT');
    }
}
