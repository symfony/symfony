<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Util;

/**
 * Resolves a URL reference against a base URL, like RFC 3986 (section 5.2).
 *
 * @internal
 */
final class UrlResolver
{
    /**
     * A reference to a fragment of the page alone (#id) stays one, so that it keeps going to an element of the view.
     */
    public static function resolve(string $reference, string $base): string
    {
        $reference = trim($reference);
        if ('' === $reference || str_starts_with($reference, '#') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $reference)) {
            return $reference;
        }

        $parts = parse_url($base);
        if (false === $parts || !isset($parts['scheme'])) {
            return $reference;
        }
        $scheme = $parts['scheme'].':';
        $authority = isset($parts['host']) ? '//'.(isset($parts['user']) ? $parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@' : '').$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '';
        $path = $parts['path'] ?? '';

        if (str_starts_with($reference, '//')) {
            return $scheme.$reference;
        }
        if (str_starts_with($reference, '?')) {
            return $scheme.$authority.$path.$reference;
        }

        // The query and the fragment are not paths: their dots and slashes stay
        $end = strcspn($reference, '?#');
        $suffix = substr($reference, $end);
        $reference = substr($reference, 0, $end);

        if (!str_starts_with($reference, '/')) {
            // Merged with the base path, past its last segment; with an authority and no path, from the root
            $directory = '' !== $authority && '' === $path ? '/' : substr($path, 0, (int) strrpos($path, '/') + 1);
            $reference = $directory.$reference;
        }

        return $scheme.$authority.self::removeDotSegments($reference).$suffix;
    }

    private static function removeDotSegments(string $path): string
    {
        $output = [];
        $segments = explode('/', $path);
        $last = \count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ('.' === $segment || '..' === $segment) {
                if ('..' === $segment && \count($output) > 1) {
                    array_pop($output);
                }
                // A path ending with a dot segment ends with a slash
                if ($i === $last) {
                    $output[] = '';
                }
                continue;
            }
            $output[] = $segment;
        }

        return implode('/', $output);
    }
}
