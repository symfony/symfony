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
     * @return null|array{
     *     scheme:?string,
     *     user:?string,
     *     pass:?string,
     *     host:?string,
     *     port:?int,
     *     path:string,
     *     query:?string,
     *     fragment:?string
     * }
     */
    public static function parse(string $url): ?array
    {
        if (!$url) {
            return null;
        }

        try {
            $parsedUrl = UriString::parse($url);

            if (preg_match('/\s/', $url)) {
                return null;
            }

            if (isset($parsedUrl['host']) && self::decodeUnreservedCharacters($parsedUrl['host']) !== $parsedUrl['host']) {
                return null;
            }

            return $parsedUrl;
        } catch (SyntaxError) {
            return null;
        }
    }

    private static function isHostlessScheme(?string $scheme): bool
    {
        return \in_array($scheme, ['blob', 'chrome', 'data', 'file', 'geo', 'mailto', 'maps', 'tel', 'view-source'], true);
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
