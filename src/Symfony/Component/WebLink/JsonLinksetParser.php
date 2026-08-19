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
use Symfony\Component\WebLink\Exception\InvalidArgumentException;

/**
 * Parses an "application/linkset+json" document into a list of Link instances.
 *
 * The link context of each link context object is carried over to its links as
 * an "anchor" attribute.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9264.html
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class JsonLinksetParser
{
    /**
     * @throws InvalidArgumentException When the document does not follow the "application/linkset+json" format
     */
    public function parse(string $document): EvolvableLinkProviderInterface
    {
        try {
            $data = json_decode($document, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('The link set is not a valid JSON document.', previous: $e);
        }

        if (!\is_array($data) || !\is_array($data['linkset'] ?? null)) {
            throw new InvalidArgumentException('The link set must be a JSON object holding a "linkset" member.');
        }

        $links = new GenericLinkProvider();

        foreach ($data['linkset'] as $context) {
            if (!\is_array($context)) {
                throw new InvalidArgumentException('Each member of the "linkset" array must be a link context object.');
            }

            $anchor = $context['anchor'] ?? null;
            unset($context['anchor']);

            if (null !== $anchor && !\is_string($anchor)) {
                throw new InvalidArgumentException('The "anchor" member of a link context object must be a string.');
            }

            foreach ($context as $rel => $targets) {
                if (!\is_array($targets)) {
                    throw new InvalidArgumentException(\sprintf('The "%s" member of a link context object must be an array of link target objects.', $rel));
                }

                foreach ($targets as $target) {
                    $links = $links->withLink(self::parseTarget((string) $rel, $anchor, $target));
                }
            }
        }

        return $links;
    }

    private static function parseTarget(string $rel, ?string $anchor, mixed $target): Link
    {
        if (!\is_array($target) || !\is_string($target['href'] ?? null)) {
            throw new InvalidArgumentException(\sprintf('Each link target object of the "%s" relation type must hold an "href" member.', $rel));
        }

        $link = new Link($rel, $target['href']);
        unset($target['href']);

        if (null !== $anchor) {
            $link = $link->withAttribute('anchor', $anchor);
        }

        foreach ($target as $key => $value) {
            $link = $link->withAttribute((string) $key, self::parseAttribute((string) $key, $value));
        }

        return $link;
    }

    private static function parseAttribute(string $key, mixed $value): string|array
    {
        if (!\is_array($value)) {
            return self::stringify($key, $value);
        }

        if (str_ends_with($key, '*')) {
            $values = array_map(static fn ($item) => self::encodeExtendedValue($key, $item), array_is_list($value) ? $value : [$value]);
        } else {
            $values = array_map(static fn ($item) => self::stringify($key, $item), array_values($value));
        }

        return 1 === \count($values) ? $values[0] : $values;
    }

    /**
     * Encodes the content and the language tag of an internationalized attribute back to RFC 8187.
     */
    private static function encodeExtendedValue(string $key, mixed $item): string
    {
        if (!\is_array($item) || !\is_string($item['value'] ?? null)) {
            throw new InvalidArgumentException(\sprintf('The "%s" target attribute must hold objects with a "value" member.', $key));
        }

        $language = $item['language'] ?? '';

        if (!\is_string($language)) {
            throw new InvalidArgumentException(\sprintf('The "language" member of the "%s" target attribute must be a string.', $key));
        }

        return \sprintf("UTF-8'%s'%s", $language, rawurlencode($item['value']));
    }

    private static function stringify(string $key, mixed $value): string
    {
        if (!\is_scalar($value)) {
            throw new InvalidArgumentException(\sprintf('The "%s" target attribute must hold string values.', $key));
        }

        return \is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }
}
