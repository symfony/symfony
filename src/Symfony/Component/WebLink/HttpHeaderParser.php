<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\WebLink;

use Psr\Link\EvolvableLinkProviderInterface;

/**
 * Parse a list of HTTP Link headers into a list of Link instances.
 *
 * @see https://tools.ietf.org/html/rfc5988
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class HttpHeaderParser
{
    // Regex to match each link entry: <...>; param1=...; param2=...
    private const LINK_PATTERN = '/<([^>]*)>\s*((?:\s*;\s*[a-zA-Z0-9\-_]+(?:\s*=\s*(?:"(?:[^"\\\\]|\\\\.)*"|[^";,\s]+))?)*)/';

    // Regex to match parameters: ; key[=value]
    private const PARAM_PATTERN = '/;\s*([a-zA-Z0-9\-_]+)(?:\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|([^";,\s]+)))?/';

    /**
     * @param string|string[] $headers Value of the "Link" HTTP header
     */
    public function parse(string|array $headers): EvolvableLinkProviderInterface
    {
        if (\is_array($headers)) {
            $headers = implode(', ', $headers);
        }
        $links = new GenericLinkProvider();

        if (!preg_match_all(self::LINK_PATTERN, $headers, $matches, \PREG_SET_ORDER)) {
            return $links;
        }

        foreach ($matches as $match) {
            $href = $match[1];
            $attributesString = $match[2];

            $attributes = [];
            if (preg_match_all(self::PARAM_PATTERN, $attributesString, $attributeMatches, \PREG_SET_ORDER)) {
                $rels = null;
                foreach ($attributeMatches as $pm) {
                    $key = $pm[1];
                    $value = match (true) {
                        // Quoted value, unescape quotes
                        ($pm[2] ?? '') !== '' => stripcslashes($pm[2]),
                        ($pm[3] ?? '') !== '' => $pm[3],
                        // No value
                        default => true,
                    };

                    if ('rel' === $key) {
                        // Only the first occurrence of the "rel" attribute is read
                        $rels ??= true === $value ? [] : preg_split('/\s+/', $value, 0, \PREG_SPLIT_NO_EMPTY);
                    } elseif (\is_array($attributes[$key] ?? null)) {
                        $attributes[$key][] = $value;
                    } elseif (isset($attributes[$key])) {
                        $attributes[$key] = [$attributes[$key], $value];
                    } else {
                        $attributes[$key] = $value;
                    }
                }
            }

            $link = new Link(null, $href);
            foreach ($rels ?? [] as $rel) {
                $link = $link->withRel($rel);
            }
            foreach ($attributes as $k => $v) {
                $link = $link->withAttribute($k, $v);
            }
            $links = $links->withLink($link);
        }

        return $links;
    }
}
