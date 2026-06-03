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

/**
 * @internal
 */
final class UrlSanitizer
{
    /**
     * Characters with no legitimate place in a URL: explicit-direction BiDi
     * formatting marks plus Unicode whitespace and the zero-width no-break
     * space. ASCII space is tolerated and percent-encoded by parse().
     */
    private const DENIED_CHARS_PATTERN = '/[\t\n\v\f\r\x{0085}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

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

        if (false !== strpbrk($input, '\\') || preg_match('~^(?:https?|ftp|wss?):(/[^/]|///)~i', $input)) {
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

        return UriString::build($url);
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
        if (!$url) {
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

            $parsedUrl = UriString::parse($url);

            if (isset($parsedUrl['host']) && self::decodeUnreservedCharacters($parsedUrl['host']) !== $parsedUrl['host']) {
                return null;
            }

            // Reject denied characters reachable via percent-encoding in any
            // component; otherwise the upfront check is bypassed by encoding.
            foreach (['user', 'pass', 'host', 'path', 'query', 'fragment'] as $part) {
                if (isset($parsedUrl[$part]) && preg_match(self::DENIED_CHARS_PATTERN, rawurldecode($parsedUrl[$part]))) {
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
        return \in_array($scheme, ['blob', 'chrome', 'data', 'file', 'geo', 'mailto', 'maps', 'tel', 'sms', 'view-source'], true);
    }

    private static function isAllowedHost(?string $host, array $allowedHosts): bool
    {
        if (null === $host) {
            return \in_array(null, $allowedHosts, true);
        }

        $parts = array_reverse(explode('.', $host));

        foreach ($allowedHosts as $allowedHost) {
            if (self::matchAllowedHostParts($parts, array_reverse(explode('.', $allowedHost)))) {
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

    /**
     * Implementation borrowed from League\Uri\Encoder::decodeUnreservedCharacters().
     */
    private static function decodeUnreservedCharacters(string $host): string
    {
        return preg_replace_callback(
            ',%(2[1-9A-Fa-f]|[3-7][0-9A-Fa-f]|61|62|64|65|66|7[AB]|5F),',
            static fn (array $matches): string => rawurldecode($matches[0]),
            $host
        );
    }
}
