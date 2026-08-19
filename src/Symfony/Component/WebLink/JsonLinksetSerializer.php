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

use Psr\Link\LinkInterface;
use Symfony\Component\WebLink\Exception\InvalidArgumentException;

/**
 * Serializes a list of Link instances to an "application/linkset+json" document.
 *
 * Links are grouped by link context ("anchor" attribute), then by relation type.
 * Templated links and links without a relation type are skipped, as the format
 * conveys URI references grouped by relation type.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9264.html
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class JsonLinksetSerializer
{
    /**
     * Target attributes that RFC 8288 defines as non-repeatable strings.
     */
    private const SINGLE_VALUED_ATTRIBUTES = ['media', 'title', 'type'];

    /**
     * Builds an "application/linkset+json" document.
     *
     * @param LinkInterface[]|\Traversable $links
     * @param int                          $flags Bitmask of json_encode() options
     *
     * @throws InvalidArgumentException When a link has the "anchor" relation type or when the links cannot be encoded to JSON
     */
    public function serialize(iterable $links, int $flags = 0): string
    {
        $contexts = [];

        foreach ($links as $link) {
            if ($link->isTemplated()) {
                continue;
            }

            $attributes = $link->getAttributes();
            $anchor = $attributes['anchor'] ?? null;
            unset($attributes['anchor']);

            if (\is_array($anchor)) {
                $anchor = [] === $anchor ? null : $anchor[array_key_first($anchor)];
            }
            $anchor = null === $anchor || false === $anchor ? null : self::stringify($anchor);

            $target = ['href' => $link->getHref()] + self::serializeAttributes($attributes);

            $context = null === $anchor ? '' : "\0".$anchor;

            foreach ($link->getRels() as $rel) {
                if ('anchor' === $rel) {
                    throw new InvalidArgumentException('A link with the "anchor" relation type cannot be represented in an "application/linkset+json" document.');
                }

                $contexts[$context]['anchor'] = $anchor;
                $contexts[$context]['rels'][$rel][] = $target;
            }
        }

        $linkset = [];
        foreach ($contexts as $context) {
            $linkset[] = (null === $context['anchor'] ? [] : ['anchor' => $context['anchor']]) + $context['rels'];
        }

        try {
            return json_encode(['linkset' => $linkset], \JSON_THROW_ON_ERROR | $flags);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException(\sprintf('The link set cannot be serialized to JSON: "%s".', $e->getMessage()), previous: $e);
        }
    }

    /**
     * @param array<string, scalar|\Stringable|list<scalar|\Stringable>> $attributes
     */
    private static function serializeAttributes(array $attributes): array
    {
        $target = [];

        foreach ($attributes as $key => $value) {
            if (false === $value) {
                continue;
            }

            $values = \is_array($value) ? array_values($value) : [$value];

            if (str_ends_with($key, '*')) {
                $target[$key] = array_map(self::decodeExtendedValue(...), $values);

                continue;
            }

            if (\in_array($key, self::SINGLE_VALUED_ATTRIBUTES, true)) {
                $target[$key] = self::stringify($values[0] ?? '');

                continue;
            }

            $target[$key] = array_map(self::stringify(...), $values);
        }

        return $target;
    }

    /**
     * Splits an RFC 8187 encoded value into its unescaped content and its language tag.
     */
    private static function decodeExtendedValue(mixed $value): array
    {
        $value = self::stringify($value);
        $parts = explode("'", $value, 3);

        if (3 !== \count($parts)) {
            return ['value' => $value];
        }

        [, $language, $encoded] = $parts;
        $decoded = rawurldecode($encoded);

        return '' === $language ? ['value' => $decoded] : ['value' => $decoded, 'language' => $language];
    }

    private static function stringify(mixed $value): string
    {
        return true === $value ? '' : (string) $value;
    }
}
