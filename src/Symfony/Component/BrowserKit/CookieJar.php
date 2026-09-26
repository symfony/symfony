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
    private const int MAX_SET_COOKIE_FIELD_LENGTH = 8190;
    private const int MAX_SET_COOKIE_FIELDS = 50;
    private const int MAX_REQUEST_COOKIES = 150;
    private const int MAX_COOKIE_HEADER_LENGTH = 8190;

    /** @var array<string, array<string, array<string, Cookie>>> */
    protected array $cookieJar = [];

    /** @var list<Cookie> */
    private array $browserCompatibleCookies = [];
    private bool $browserCompatible = false;

    public static function createBrowserCompatible(): static
    {
        // A subclass that changes the constructor signature breaks this factory anyway,
        // and late static binding is what lets it keep its own type here.
        // @phpstan-ignore new.static
        $cookieJar = new static();
        $cookieJar->browserCompatible = true;

        return $cookieJar;
    }

    public function set(Cookie $cookie): void
    {
        if ($this->browserCompatible) {
            $this->setBrowserCompatibleCookie($cookie);

            return;
        }

        $this->cookieJar[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
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

        if ($this->browserCompatible) {
            $match = null;
            foreach ($this->browserCompatibleCookies as $cookie) {
                if ($cookie->getName() !== $name || !self::pathMatches($cookie->getPath(), $path)) {
                    continue;
                }
                if (null !== $domain && !self::domainMatches($cookie, $domain)) {
                    continue;
                }
                if (null === $match || self::isMoreSpecific($cookie, $match)) {
                    $match = $cookie;
                }
            }

            return $match;
        }

        $match = null;

        foreach ($this->cookieJar as $cookieDomain => $pathCookies) {
            if ($cookieDomain && $domain) {
                $cookieDomain = '.'.ltrim($cookieDomain, '.');
                if (!str_ends_with('.'.$domain, $cookieDomain)) {
                    continue;
                }
            }

            foreach ($pathCookies as $cookiePath => $namedCookies) {
                if (!str_starts_with($path, $cookiePath) || !isset($namedCookies[$name])) {
                    continue;
                }
                if (null === $match || self::isMoreSpecific($namedCookies[$name], $match)) {
                    $match = $namedCookies[$name];
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

        if ($this->browserCompatible) {
            $this->browserCompatibleCookies = array_values(array_filter(
                $this->browserCompatibleCookies,
                static fn (Cookie $cookie): bool => $cookie->getName() !== $name
                    || $cookie->getPath() !== $path
                    || (null !== $domain && self::canonicalDomain($cookie->getDomain()) !== self::canonicalDomain($domain)),
            ));

            return;
        }

        if (!$domain) {
            // an empty domain means any domain
            // this should never happen but it allows for a better BC
            $domains = array_keys($this->cookieJar);
        } else {
            $domains = [$domain];
        }

        foreach ($domains as $domain) {
            unset($this->cookieJar[$domain][$path][$name]);

            if (empty($this->cookieJar[$domain][$path])) {
                unset($this->cookieJar[$domain][$path]);

                if (empty($this->cookieJar[$domain])) {
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
        $this->browserCompatibleCookies = [];
    }

    /**
     * Removes all session cookies from the jar.
     */
    public function clearSessionCookies(): void
    {
        if ($this->browserCompatible) {
            $this->browserCompatibleCookies = array_values(array_filter(
                $this->browserCompatibleCookies,
                static fn (Cookie $cookie): bool => null !== $cookie->getExpiresTime() && 0 != $cookie->getExpiresTime(),
            ));

            return;
        }

        foreach ($this->cookieJar as $domain => $pathCookies) {
            foreach ($pathCookies as $path => $namedCookies) {
                foreach ($namedCookies as $name => $cookie) {
                    if (null === $cookie->getExpiresTime() || 0 == $cookie->getExpiresTime()) {
                        unset($this->cookieJar[$domain][$path][$name]);
                    }
                }
            }
        }
    }

    /**
     * Updates the cookie jar from a response Set-Cookie headers.
     *
     * @param string[] $setCookies Set-Cookie headers from an HTTP response
     */
    public function updateFromSetCookie(array $setCookies, ?string $uri = null): void
    {
        if ($this->browserCompatible) {
            $cookies = $setCookies;
        } else {
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
        }

        $accepted = 0;
        foreach ($cookies as $cookie) {
            if ($this->browserCompatible) {
                if (self::MAX_SET_COOKIE_FIELD_LENGTH < \strlen($cookie)) {
                    continue;
                }

                $header = $cookie;
                try {
                    $cookie = Cookie::fromStringBrowserCompatible($header, $uri);
                } catch (InvalidArgumentException) {
                    continue;
                }

                if (!$this->acceptBrowserCompatibleCookie($cookie, $uri, $header)) {
                    continue;
                }

                if ($this->setBrowserCompatibleCookie($cookie) && self::MAX_SET_COOKIE_FIELDS === ++$accepted) {
                    break;
                }

                continue;
            }

            try {
                $this->set(Cookie::fromString($cookie, $uri));
            } catch (InvalidArgumentException) {
                // invalid cookies are just ignored
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

        if ($this->browserCompatible) {
            return $this->browserCompatibleCookies;
        }

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
        $this->flushExpiredCookies();

        if ($this->browserCompatible) {
            $cookies = [];
            foreach ($this->getBrowserCompatibleCookiesForUri($uri) as $cookie) {
                $name = $cookie->getName();
                if (!isset($cookies[$name]) || self::isMoreSpecific($cookie, $cookies[$name])) {
                    $cookies[$name] = $cookie;
                }
            }

            return array_map(static fn (Cookie $cookie) => $returnsRawValue ? $cookie->getRawValue() : $cookie->getValue(), $cookies);
        }

        $parts = array_replace(['path' => '/'], parse_url($uri));
        $cookies = [];
        foreach ($this->cookieJar as $domain => $pathCookies) {
            if ($domain) {
                $domain = '.'.ltrim($domain, '.');
                if (!str_ends_with('.'.$parts['host'], $domain)) {
                    continue;
                }
            }

            foreach ($pathCookies as $path => $namedCookies) {
                if (!str_starts_with($parts['path'], $path)) {
                    continue;
                }

                foreach ($namedCookies as $cookie) {
                    if ($cookie->isSecure() && 'https' !== $parts['scheme']) {
                        continue;
                    }

                    $name = $cookie->getName();

                    if (!isset($cookies[$name]) || self::isMoreSpecific($cookie, $cookies[$name])) {
                        $cookies[$name] = $cookie;
                    }
                }
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
     * Returns the Cookie header value for the given URI.
     */
    public function getCookieHeader(string $uri): string
    {
        if (!$this->browserCompatible) {
            $cookies = [];
            foreach ($this->allRawValues($uri) as $name => $value) {
                $cookies[] = $name.'='.$value;
            }

            return implode('; ', $cookies);
        }

        $values = [];
        $headerLength = \strlen('Cookie: ');
        foreach ($this->getBrowserCompatibleCookiesForUri($uri) as $cookie) {
            $value = $cookie->getName().'='.$cookie->getRawValue();
            $separatorLength = [] === $values ? 0 : 2;
            if (self::MAX_COOKIE_HEADER_LENGTH < $headerLength + $separatorLength + \strlen($value)) {
                break;
            }

            $values[] = $value;
            $headerLength += $separatorLength + \strlen($value);
            if (self::MAX_REQUEST_COOKIES === \count($values)) {
                break;
            }
        }

        return implode('; ', $values);
    }

    /**
     * Removes all expired cookies.
     */
    public function flushExpiredCookies(): void
    {
        if ($this->browserCompatible) {
            $this->browserCompatibleCookies = array_values(array_filter($this->browserCompatibleCookies, static fn (Cookie $cookie): bool => !self::isBrowserCompatibleCookieExpired($cookie)));

            return;
        }

        foreach ($this->cookieJar as $domain => $pathCookies) {
            foreach ($pathCookies as $path => $namedCookies) {
                foreach ($namedCookies as $name => $cookie) {
                    if ($cookie->isExpired()) {
                        unset($this->cookieJar[$domain][$path][$name]);
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

    private function setBrowserCompatibleCookie(Cookie $cookie): bool
    {
        if (!self::isBrowserCompatibleCookieValid($cookie)) {
            return false;
        }

        foreach ($this->browserCompatibleCookies as $key => $stored) {
            if (!self::hasSameIdentity($cookie, $stored)) {
                continue;
            }

            if (self::isBrowserCompatibleCookieExpired($cookie)) {
                unset($this->browserCompatibleCookies[$key]);
                $this->browserCompatibleCookies = array_values($this->browserCompatibleCookies);

                return false;
            }

            $expires = null === $cookie->getExpiresTime() ? 0 : (int) $cookie->getExpiresTime();
            $storedExpires = null === $stored->getExpiresTime() ? 0 : (int) $stored->getExpiresTime();
            if ($expires <= $storedExpires && $cookie->getRawValue() === $stored->getRawValue()) {
                return false;
            }

            unset($this->browserCompatibleCookies[$key]);
            $this->browserCompatibleCookies = array_values($this->browserCompatibleCookies);

            break;
        }

        if (self::isBrowserCompatibleCookieExpired($cookie)) {
            return false;
        }

        $this->browserCompatibleCookies[] = $cookie;

        return true;
    }

    private function acceptBrowserCompatibleCookie(Cookie $cookie, ?string $uri, string $header): bool
    {
        if (null === $uri) {
            return false;
        }

        $parts = parse_url($uri);

        if (!self::isBrowserCompatibleCookieValid($cookie)) {
            return false;
        }

        $name = $cookie->getName();
        $domain = $cookie->getDomain();
        if (str_starts_with($domain, '.') || !self::domainMatches($cookie, $parts['host'])) {
            return false;
        }

        $secureRequest = 'https' === strtolower($parts['scheme'] ?? '');
        if (!$secureRequest && ($cookie->isSecure() || $this->overlaysSecureCookie($cookie))) {
            return false;
        }

        $lowerName = strtolower($name);
        if (str_starts_with($lowerName, '__secure-') && !$cookie->isSecure()) {
            return false;
        }

        if (str_starts_with($lowerName, '__host-') && (
            !$cookie->isSecure()
            || !$cookie->isHostOnly()
            || '/' !== $cookie->getPath()
            || !self::hasPathAttribute($header)
        )) {
            return false;
        }

        return true;
    }

    /**
     * @return list<Cookie>
     */
    private function getBrowserCompatibleCookiesForUri(string $uri): array
    {
        $parts = array_replace(['path' => '/'], parse_url($uri));
        $cookies = [];
        foreach ($this->browserCompatibleCookies as $cookie) {
            if (self::isBrowserCompatibleCookieExpired($cookie) || !self::pathMatches($cookie->getPath(), $parts['path'])) {
                continue;
            }
            if ('' !== $cookie->getDomain() && (!isset($parts['host']) || !self::domainMatches($cookie, $parts['host']))) {
                continue;
            }
            if ($cookie->isSecure() && 'https' !== strtolower($parts['scheme'] ?? '')) {
                continue;
            }

            $cookies[] = $cookie;
        }

        return $cookies;
    }

    private function overlaysSecureCookie(Cookie $cookie): bool
    {
        foreach ($this->browserCompatibleCookies as $stored) {
            if ($stored->getName() !== $cookie->getName() || !$stored->isSecure() || self::isBrowserCompatibleCookieExpired($stored)) {
                continue;
            }

            $cookieDomain = self::canonicalDomain($cookie->getDomain());
            $storedDomain = self::canonicalDomain($stored->getDomain());
            if ($cookieDomain !== $storedDomain
                && !self::domainSuffixMatches($cookieDomain, $storedDomain)
                && !self::domainSuffixMatches($storedDomain, $cookieDomain)
            ) {
                continue;
            }

            if (self::pathMatches($stored->getPath(), $cookie->getPath())) {
                return true;
            }
        }

        return false;
    }

    private static function hasSameIdentity(Cookie $cookie, Cookie $other): bool
    {
        return $cookie->getName() === $other->getName()
            && $cookie->getPath() === $other->getPath()
            && self::canonicalDomain($cookie->getDomain()) === self::canonicalDomain($other->getDomain())
            && $cookie->isHostOnly() === $other->isHostOnly();
    }

    private static function isBrowserCompatibleCookieValid(Cookie $cookie): bool
    {
        $name = $cookie->getName();
        if ('' === $name || preg_match('/[\x00-\x20\x22\x28-\x29\x2c\x2f\x3a-\x40\x5c\x7b\x7d\x7f]/', $name)) {
            return false;
        }

        $domain = $cookie->getDomain();

        return '' !== $domain && '' !== ltrim(trim($domain), '.');
    }

    private static function isBrowserCompatibleCookieExpired(Cookie $cookie): bool
    {
        return null !== $cookie->getExpiresTime() && time() > (int) $cookie->getExpiresTime();
    }

    private static function domainMatches(Cookie $cookie, string $domain): bool
    {
        $cookieDomain = self::canonicalDomain($cookie->getDomain());
        $domain = self::canonicalDomain($domain);

        if ($cookie->isHostOnly()) {
            return $domain === $cookieDomain;
        }

        return $domain === $cookieDomain || self::domainSuffixMatches($domain, $cookieDomain);
    }

    private static function domainSuffixMatches(string $domain, string $cookieDomain): bool
    {
        if (str_contains($cookieDomain, '%')) {
            return false;
        }

        if (!self::isDomainSuffixEligible($domain) || !self::isDomainSuffixEligible($cookieDomain)) {
            return false;
        }

        return str_ends_with($domain, '.'.$cookieDomain);
    }

    private static function canonicalDomain(string $domain): string
    {
        $domain = strtolower($domain);
        if (str_starts_with($domain, '.')) {
            $domain = substr($domain, 1);
        }
        if (str_starts_with($domain, '[') && str_ends_with($domain, ']') && false !== ($packed = @inet_pton(substr($domain, 1, -1))) && false !== ($canonical = inet_ntop($packed))) {
            return '['.$canonical.']';
        }
        if (str_contains($domain, ':') && false !== ($packed = @inet_pton($domain)) && false !== ($canonical = inet_ntop($packed))) {
            return $canonical;
        }

        return $domain;
    }

    private static function isDomainSuffixEligible(string $domain): bool
    {
        if ('' === $domain
            || false !== strpbrk($domain, '[]:')
            || preg_match('/[\x00-\x20\x7f\/\?#@\\\\]/', $domain)
            || !self::hasValidHostPercentEncoding($domain)
            || false !== filter_var($domain, \FILTER_VALIDATE_IP)
        ) {
            return false;
        }

        $labels = explode('.', rtrim($domain, '.'));
        $last = end($labels);

        return '' !== $last && !ctype_digit($last) && !(str_starts_with($last, '0x') && ctype_xdigit(substr($last, 2)));
    }

    private static function hasValidHostPercentEncoding(string $host): bool
    {
        $offset = 0;
        while (false !== $offset = strpos($host, '%', $offset)) {
            $encoded = substr($host, $offset + 1, 2);
            if (2 !== \strlen($encoded) || !ctype_xdigit($encoded)) {
                return false;
            }

            if (preg_match('/[\x00-\x20\x7f\/\?#@\\\\\[\]:%]/', \chr(hexdec($encoded)))) {
                return false;
            }

            $offset += 3;
        }

        return true;
    }

    private static function pathMatches(string $cookiePath, string $requestPath): bool
    {
        if ('/' === $cookiePath || $cookiePath === $requestPath) {
            return true;
        }

        if (!str_starts_with($requestPath, $cookiePath)) {
            return false;
        }

        return str_ends_with($cookiePath, '/') || '/' === ($requestPath[\strlen($cookiePath)] ?? null);
    }

    private static function hasPathAttribute(string $header): bool
    {
        $parts = explode(';', $header);
        array_shift($parts);
        foreach ($parts as $part) {
            if (2 === \count($attribute = explode('=', $part, 2)) && 'path' === strtolower(trim($attribute[0], " \t"))) {
                return true;
            }
        }

        return false;
    }
}
