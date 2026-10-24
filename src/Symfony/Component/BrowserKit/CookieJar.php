<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit;

use Symfony\Component\BrowserKit\Exception\InvalidArgumentException;

/**
 * CookieJar.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CookieJar
{
    protected array $cookieJar = [];
    private array $creationOrder = [];
    private int $nextCreationOrder = 0;

    public function set(Cookie $cookie): void
    {
        $name = ($cookie->isHostOnly() ? 'h:' : 'd:').$cookie->getName();
        $this->creationOrder[$cookie->getDomain()][$cookie->getPath()][$name] ??= $this->nextCreationOrder++;
        $this->cookieJar[$cookie->getDomain()][$cookie->getPath()][$name] = $cookie;
    }

    /**
     * Gets a cookie by name.
     *
     * When several cookies match, the one with the longest path is returned,
     * then the one with the most specific domain.
     *
     * You should never use an empty domain, but if you do so,
     * this method matches cookies of any domain (this behavior ensures
     * a BC behavior with previous versions of Symfony).
     */
    public function get(string $name, string $path = '/', ?string $domain = null): ?Cookie
    {
        $this->flushExpiredCookies();

        $match = null;

        foreach ($this->cookieJar as $cookieDomain => $pathCookies) {
            if ($cookieDomain && $domain) {
                $cookieDomain = '.'.ltrim($cookieDomain, '.');
                if (!str_ends_with('.'.$domain, $cookieDomain)) {
                    continue;
                }
            }

            foreach ($pathCookies as $cookiePath => $namedCookies) {
                if (!self::pathMatches($cookiePath, $path)) {
                    continue;
                }
                foreach ($namedCookies as $cookie) {
                    if ($name !== $cookie->getName() || ($domain && $cookie->isHostOnly() && ltrim($domain, '.') !== $cookie->getDomain())) {
                        continue;
                    }
                    if (null === $match || self::isMoreSpecific($cookie, $match)) {
                        $match = $cookie;
                    }
                }
            }
        }

        return $match;
    }

    /**
     * Removes a cookie by name.
     *
     * You should never use an empty domain, but if you do so,
     * all cookies for the given name/path expire (this behavior
     * ensures a BC behavior with previous versions of Symfony).
     */
    public function expire(string $name, ?string $path = '/', ?string $domain = null): void
    {
        $path ??= '/';

        if (!$domain) {
            // an empty domain means any domain
            // this should never happen but it allows for a better BC
            $domains = array_keys($this->cookieJar);
        } else {
            $domains = [$domain];
        }

        foreach ($domains as $domain) {
            foreach (['d:'.$name, 'h:'.$name] as $key) {
                if (isset($this->cookieJar[$domain][$path][$key])) {
                    unset($this->creationOrder[$domain][$path][$key], $this->cookieJar[$domain][$path][$key]);
                }
            }

            if (!($this->cookieJar[$domain][$path] ?? [])) {
                unset($this->cookieJar[$domain][$path]);

                if (!($this->cookieJar[$domain] ?? [])) {
                    unset($this->cookieJar[$domain]);
                }
            }
        }
    }

    /**
     * Removes all the cookies from the jar.
     */
    public function clear(): void
    {
        $this->cookieJar = [];
        $this->creationOrder = [];
        $this->nextCreationOrder = 0;
    }

    /**
     * Updates the cookie jar from a response Set-Cookie headers.
     *
     * @param string[] $setCookies Set-Cookie headers from an HTTP response
     */
    public function updateFromSetCookie(array $setCookies, ?string $uri = null): void
    {
        $cookies = [];

        foreach ($setCookies as $cookie) {
            foreach (explode(',', $cookie) as $i => $part) {
                if (0 === $i || preg_match('/^(?P<token>\s*[0-9A-Za-z!#\$%\&\'\*\+\-\.^_`\|~]+)=/', $part)) {
                    $cookies[] = ltrim($part);
                } else {
                    $cookies[\count($cookies) - 1] .= ','.$part;
                }
            }
        }

        $host = null !== $uri ? parse_url($uri, \PHP_URL_HOST) : null;

        foreach ($cookies as $cookie) {
            try {
                $cookie = Cookie::fromString($cookie, $uri);
            } catch (InvalidArgumentException) {
                // invalid cookies are just ignored
                continue;
            }

            if (null === $host || self::domainMatches($host, $cookie->getDomain())) {
                $this->set($cookie);
            }
        }
    }

    /**
     * Updates the cookie jar from a Response object.
     */
    public function updateFromResponse(Response $response, ?string $uri = null): void
    {
        $this->updateFromSetCookie($response->getHeader('Set-Cookie', false), $uri);
    }

    /**
     * Returns not yet expired cookies.
     *
     * @return Cookie[]
     */
    public function all(): array
    {
        $this->flushExpiredCookies();

        $flattenedCookies = [];
        foreach ($this->cookieJar as $path) {
            foreach ($path as $cookies) {
                foreach ($cookies as $cookie) {
                    $flattenedCookies[] = $cookie;
                }
            }
        }

        return $flattenedCookies;
    }

    /**
     * Returns not yet expired cookie values for the given URI.
     */
    public function allValues(string $uri, bool $returnsRawValue = false): array
    {
        $cookies = [];
        foreach ($this->getCookiesForUri($uri) as $cookie) {
            $name = $cookie->getName();
            if (!isset($cookies[$name]) || self::isMoreSpecific($cookie, $cookies[$name])) {
                $cookies[$name] = $cookie;
            }
        }

        return array_map(static fn (Cookie $cookie) => $returnsRawValue ? $cookie->getRawValue() : $cookie->getValue(), $cookies);
    }

    /**
     * Returns not yet expired raw cookie values for the given URI.
     */
    public function allRawValues(string $uri): array
    {
        return $this->allValues($uri, true);
    }

    /**
     * Returns cookies for the URI, ordered by path length and creation time.
     *
     * @return list<Cookie>
     */
    public function allForUri(string $uri): array
    {
        $cookies = $this->getCookiesForUri($uri);
        usort($cookies, fn (Cookie $left, Cookie $right) => \strlen($right->getPath()) <=> \strlen($left->getPath()) ?: $this->getCreationOrder($left) <=> $this->getCreationOrder($right));

        return $cookies;
    }

    /**
     * Removes all expired cookies.
     */
    public function flushExpiredCookies(): void
    {
        foreach ($this->cookieJar as $domain => $pathCookies) {
            foreach ($pathCookies as $path => $namedCookies) {
                foreach ($namedCookies as $name => $cookie) {
                    if ($cookie->isExpired()) {
                        unset($this->creationOrder[$domain][$path][$name], $this->cookieJar[$domain][$path][$name]);
                    }
                }
            }
        }
    }

    private static function isMoreSpecific(Cookie $cookie, Cookie $other): bool
    {
        $pathLength = \strlen($cookie->getPath());
        $otherPathLength = \strlen($other->getPath());

        if ($pathLength !== $otherPathLength) {
            return $pathLength > $otherPathLength;
        }

        return \strlen(ltrim($cookie->getDomain(), '.')) > \strlen(ltrim($other->getDomain(), '.'));
    }

    private static function domainMatches(string $host, string $cookieDomain): bool
    {
        $host = strtolower($host);
        $cookieDomain = strtolower(ltrim($cookieDomain, '.'));

        return $host === $cookieDomain || (str_ends_with($host, '.'.$cookieDomain) && false === filter_var($host, \FILTER_VALIDATE_IP));
    }

    private static function pathMatches(string $cookiePath, string $requestPath): bool
    {
        if ($cookiePath === $requestPath) {
            return true;
        }

        return str_starts_with($requestPath, $cookiePath) && (str_ends_with($cookiePath, '/') || '/' === $requestPath[\strlen($cookiePath)]);
    }

    private function getCookiesForUri(string $uri): array
    {
        $this->flushExpiredCookies();

        $parts = array_replace(['path' => '/'], parse_url($uri));
        $cookies = [];
        foreach ($this->cookieJar as $domain => $pathCookies) {
            if ($domain && !str_ends_with('.'.$parts['host'], '.'.ltrim($domain, '.'))) {
                continue;
            }

            foreach ($pathCookies as $path => $namedCookies) {
                if (!self::pathMatches($path, $parts['path'])) {
                    continue;
                }

                foreach ($namedCookies as $cookie) {
                    if ($cookie->isSecure() && 'https' !== $parts['scheme']) {
                        continue;
                    }

                    if ($cookie->isHostOnly() && $parts['host'] !== $cookie->getDomain()) {
                        continue;
                    }

                    $cookies[] = $cookie;
                }
            }
        }

        return $cookies;
    }

    private function getCreationOrder(Cookie $cookie): int
    {
        return $this->creationOrder[$cookie->getDomain()][$cookie->getPath()][($cookie->isHostOnly() ? 'h:' : 'd:').$cookie->getName()];
    }
}
