<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

/**
 * Expand an URI according a current URI.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UriExpander
{
    /**
     * Expand an URI according to a current Uri.
     *
     * For example if $uri=/foo/bar and $currentUri=https://symfony.com it will
     * return https://symfony.com/foo/bar
     *
     * If the $uri is not absolute you must pass an absolute $currentUri
     */
    public static function expand(string $uri, ?string $currentUri): string
    {
        $uri = trim($uri);

        // absolute URL?
        if (null !== parse_url($uri, PHP_URL_SCHEME)) {
            return $uri;
        }

        if (null === $currentUri) {
            throw new \InvalidArgumentException('The URI is relative, so you must define its base URI passing an absolute URL.');
        }

        // empty URI
        if (!$uri) {
            return $currentUri;
        }

        // an anchor
        if ('#' === $uri[0]) {
            return self::cleanupAnchor($currentUri).$uri;
        }

        $baseUri = self::cleanupUri($currentUri);

        if ('?' === $uri[0]) {
            return $baseUri.$uri;
        }

        // absolute URL with relative schema
        if (0 === strpos($uri, '//')) {
            return preg_replace('#^([^/]*)//.*$#', '$1', $baseUri).$uri;
        }

        $baseUri = preg_replace('#^(.*?//[^/]*)(?:\/.*)?$#', '$1', $baseUri);

        // absolute path
        if ('/' === $uri[0]) {
            return $baseUri.$uri;
        }

        // relative path
        $path = parse_url(substr($currentUri, \strlen($baseUri)), PHP_URL_PATH);
        $path = self::canonicalizePath(substr($path, 0, strrpos($path, '/')).'/'.$uri);

        return $baseUri.('' === $path || '/' !== $path[0] ? '/' : '').$path;
    }

    /**
     * Returns the canonicalized URI path (see RFC 3986, section 5.2.4).
     */
    private static function canonicalizePath(string $path): string
    {
        if ('' === $path || '/' === $path) {
            return $path;
        }

        if ('.' === substr($path, -1)) {
            $path .= '/';
        }

        $output = [];

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                array_pop($output);
            } elseif ('.' !== $segment) {
                $output[] = $segment;
            }
        }

        return implode('/', $output);
    }

    /**
     * Removes the query string and the anchor from the given uri.
     */
    private static function cleanupUri(string $uri): string
    {
        return self::cleanupQuery(self::cleanupAnchor($uri));
    }

    /**
     * Removes the query string from the uri.
     */
    private static function cleanupQuery(string $uri): string
    {
        if (false !== $pos = strpos($uri, '?')) {
            return substr($uri, 0, $pos);
        }

        return $uri;
    }

    /**
     * Removes the anchor from the uri.
     */
    private static function cleanupAnchor(string $uri): string
    {
        if (false !== $pos = strpos($uri, '#')) {
            return substr($uri, 0, $pos);
        }

        return $uri;
    }
}
