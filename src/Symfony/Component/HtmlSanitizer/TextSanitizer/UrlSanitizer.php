<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\TextSanitizer;

use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriString;
use Uri\Rfc3986\Uri;

/**
 * @internal
 */
final class UrlSanitizer
{
    /**
     * Characters with no legitimate place in a URL: explicit-direction BiDi
     * formatting marks plus Unicode whitespace and the zero-width no-break
     * space. ASCII space is tolerated and percent-encoded by parse().
     *
     * The characters are matched as UTF-8 byte sequences: with the "u" modifier,
     * preg_match() returns false on malformed UTF-8, which percent-decoding can
     * produce, and the check would then be skipped.
     */
    private const DENIED_CHARS_PATTERN = '/[\t\n\x0B\f\r]'
        .'|\xC2[\x85\xA0]'                // U+0085, U+00A0
        .'|\xE1\x9A\x80'                  // U+1680
        .'|\xE2\x80[\x80-\x8A\xA8-\xAF]'  // U+2000-U+200A, U+2028-U+202F
        .'|\xE2\x81[\x9F\xA6-\xA9]'       // U+205F, U+2066-U+2069
        .'|\xE3\x80\x80'                  // U+3000
        .'|\xEF\xBB\xBF/';                // U+FEFF

    /**
     * Sanitizes a given URL string.
     *
     * In addition to ensuring $input is a valid URL, this sanitizer checks that:
     *   * the URL's host is allowed ;
     *   * the URL's scheme is allowed ;
     *   * the URL is allowed to be relative if it is ;
     *
     * It also transforms the URL to HTTPS if requested.
     */
    public static function sanitize(?string $input, ?array $allowedSchemes = null, bool $forceHttps = false, ?array $allowedHosts = null, bool $allowRelative = false): ?string
    {
        if (!$input) {
            return null;
        }

        if (preg_match('~^(?:https?|ftp|wss?):(/[^/]|///)~i', $input)) {
            return null;
        }

        $url = self::parse($input);

        // Malformed URL
        if (!$url || !\is_array($url)) {
            return null;
        }

        // No scheme and relative not allowed
        if (!$allowRelative && !$url['scheme']) {
            return null;
        }

        // Forbidden scheme
        if ($url['scheme'] && null !== $allowedSchemes && !\in_array($url['scheme'], $allowedSchemes, true)) {
            return null;
        }

        // A view-source URL wraps another URL, which has to pass the same checks;
        // browsers reject a view-source URL wrapped in another one
        if ('view-source' === $url['scheme']) {
            $nested = substr($input, \strlen('view-source:'));

            if (0 === strncasecmp($nested, 'view-source:', 12) || null === $nested = self::sanitize($nested, $allowedSchemes, $forceHttps, $allowedHosts, $allowRelative)) {
                return null;
            }

            return 'view-source:'.$nested;
        }

        // If the scheme used is not supposed to have a host, do not check the host
        if (!self::isHostlessScheme($url['scheme'])) {
            // No host and relative not allowed
            if (!$allowRelative && !$url['host']) {
                return null;
            }

            // Forbidden host
            if ($url['host'] && null !== $allowedHosts && !self::isAllowedHost($url['host'], $allowedHosts)) {
                return null;
            }
        }

        // Force HTTPS
        if ($forceHttps && 'http' === $url['scheme']) {
            $url['scheme'] = 'https';
        }

        if (\PHP_VERSION_ID >= 80500) {
            return self::build($url);
        }

        try {
            return UriString::build($url);
        } catch (SyntaxError) {
            return null;
        }
    }

    /**
     * Parses a given URL and returns an array of its components.
     *
     * @return array{
     *     scheme:?string,
     *     user:?string,
     *     pass:?string,
     *     host:?string,
     *     port:?int,
     *     path:string,
     *     query:?string,
     *     fragment:?string
     * }|null
     */
    public static function parse(string $url): ?array
    {
        if (!$url || str_contains($url, '\\')) {
            return null;
        }

        try {
            // Reject explicit-direction BiDi formatting characters and non-space
            // whitespace: they have no legitimate place in a URL and enable
            // visual spoofing of the rendered href when the URL is later
            // embedded in HTML or decoded by a downstream consumer.
            if (preg_match(self::DENIED_CHARS_PATTERN, $url)) {
                return null;
            }

            // Browsers tolerate spaces inside path/query/fragment by transparently
            // percent-encoding them. Mirror that behavior, but never inside the
            // scheme or authority (where spaces are illegal); the whitespace check
            // below rejects any space that didn't fit in the encoded slice.
            if (str_contains($url, ' ')) {
                if (str_starts_with($url, ' ')) {
                    return null;
                }

                if (false !== $i = strpos($url, '://')) {
                    $i += 3 + strcspn($url, '/?#', $i + 3);
                } elseif (str_starts_with($url, '//')) {
                    $i = 2 + strcspn($url, '/?#', 2);
                } elseif (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
                    // Hostless scheme (data:, mailto:, …): leave the URL untouched
                    // and let the whitespace check reject it.
                    $i = \strlen($url);
                } else {
                    $i = 0;
                }

                $url = substr($url, 0, $i).str_replace(' ', '%20', substr($url, $i));
            }

            if (preg_match('/\s/', $url)) {
                return null;
            }

            // RFC 3986 allows neither non-ASCII characters nor some ASCII ones that browsers accept.
            // Percent-encode them, as browsers do for most of them, so that a strict parser accepts the URL.
            // In the authority, only non-ASCII characters are encoded, to support internationalized domain names.
            $url = self::encodeNonAscii($url);
            $i = preg_match('#^(?:[a-z][a-z0-9+.\-]*:)?//[^/?\#]*+#i', $url, $m) ? \strlen($m[0]) : 0;
            $tail = preg_replace_callback('/["<>\[\]^`{|}]|%(?![0-9A-Fa-f]{2})/', static fn ($m) => rawurlencode($m[0]), substr($url, $i));

            if (false !== $j = strpos($tail, '#')) {
                $tail = substr($tail, 0, ++$j).str_replace('#', '%23', substr($tail, $j));
            }

            $url = substr($url, 0, $i).$tail;

            if (\PHP_VERSION_ID < 80500) {
                $parsedUrl = UriString::parse($url);
            } elseif (!$uri = Uri::parse($url)) {
                return null;
            } else {
                $userInfo = $uri->getRawUserInfo();
                [$user, $pass] = null !== $userInfo ? explode(':', $userInfo, 2) + [1 => null] : [null, null];

                $parsedUrl = [
                    'scheme' => $uri->getRawScheme(),
                    'user' => $user,
                    'pass' => $pass,
                    'host' => $uri->getRawHost(),
                    'port' => $uri->getPort(),
                    'path' => $uri->getRawPath(),
                    'query' => $uri->getRawQuery(),
                    'fragment' => $uri->getRawFragment(),
                ];
            }

            if (isset($parsedUrl['scheme'])) {
                $parsedUrl['scheme'] = strtolower($parsedUrl['scheme']);
            }

            // In absence of a scheme and of an authority, RFC 3986 forbids a colon in the first path segment, which would otherwise be read as a scheme.
            // league/uri does not enforce this when parsing.
            if (null === $parsedUrl['scheme'] && null === $parsedUrl['host'] && preg_match('#^[^/:]*+:#', $parsedUrl['path'])) {
                return null;
            }

            // Percent-encoded ASCII characters have no use in a host and could change how the host is matched
            if (isset($parsedUrl['host']) && preg_match('/%[0-7]/', $parsedUrl['host'])) {
                return null;
            }

            // Reject denied characters reachable via percent-encoding in any
            // component; otherwise the upfront check is bypassed by encoding.
            // Percent-encoded line breaks and tabs are legitimate in the query
            // and fragment of hostless schemes: RFC 6068 requires %0D%0A for
            // line breaks in the body of a mailto URL.
            $isHostless = self::isHostlessScheme($parsedUrl['scheme']);
            foreach (['user', 'pass', 'host', 'path', 'query', 'fragment'] as $part) {
                if (!isset($parsedUrl[$part])) {
                    continue;
                }

                $decoded = rawurldecode($parsedUrl[$part]);

                if ($isHostless && ('query' === $part || 'fragment' === $part)) {
                    $decoded = str_replace(["\r", "\n", "\t"], '', $decoded);
                }

                if (preg_match(self::DENIED_CHARS_PATTERN, $decoded)) {
                    return null;
                }
            }

            return $parsedUrl;
        } catch (SyntaxError) {
            return null;
        }
    }

    private static function isHostlessScheme(?string $scheme): bool
    {
        return \in_array($scheme, ['blob', 'chrome', 'data', 'file', 'geo', 'mailto', 'maps', 'tel', 'sms'], true);
    }

    private static function isAllowedHost(?string $host, array $allowedHosts): bool
    {
        if (null === $host) {
            return \in_array(null, $allowedHosts, true);
        }

        $parts = array_reverse(explode('.', strtolower($host)));

        foreach ($allowedHosts as $allowedHost) {
            if (self::matchAllowedHostParts($parts, array_reverse(explode('.', strtolower(self::encodeNonAscii($allowedHost)))))) {
                return true;
            }
        }

        return false;
    }

    private static function matchAllowedHostParts(array $uriParts, array $trustedParts): bool
    {
        // Check each chunk of the domain is valid
        foreach ($trustedParts as $key => $trustedPart) {
            if (!\array_key_exists($key, $uriParts) || $uriParts[$key] !== $trustedPart) {
                return false;
            }
        }

        return true;
    }

    private static function encodeNonAscii(string $value): string
    {
        return preg_replace_callback('/[\x80-\xFF]/', static fn ($m) => rawurlencode($m[0]), $value);
    }

    /**
     * Recomposes a URL from its components, as described in RFC 3986, section 5.3.
     */
    private static function build(array $url): string
    {
        $uri = null !== $url['scheme'] ? $url['scheme'].':' : '';

        if (null !== $url['host']) {
            $uri .= '//';

            if (null !== $url['user']) {
                $uri .= $url['user'].(null !== $url['pass'] ? ':'.$url['pass'] : '').'@';
            }

            $uri .= $url['host'].(null !== $url['port'] ? ':'.$url['port'] : '');
        }

        $uri .= $url['path'];

        if (null !== $url['query']) {
            $uri .= '?'.$url['query'];
        }

        if (null !== $url['fragment']) {
            $uri .= '#'.$url['fragment'];
        }

        return $uri;
    }
}
