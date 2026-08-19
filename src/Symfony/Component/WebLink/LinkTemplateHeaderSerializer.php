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
 * Serializes a list of Link instances to an HTTP Link-Template header.
 *
 * Only templated links are serialized: the others belong to the Link header.
 * Repeated target attributes are serialized as repeated structured field
 * parameters, of which only the last one is significant.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9652.html
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
class LinkTemplateHeaderSerializer
{
    /**
     * Structured field parameter key, as defined by RFC 9651.
     */
    private const KEY_PATTERN = '/^[a-z*][a-z0-9_.*-]*$/';

    /**
     * Builds the value of the "Link-Template" HTTP header.
     *
     * @param LinkInterface[]|\Traversable $links
     *
     * @throws InvalidArgumentException When a target attribute cannot be serialized as a structured field parameter key
     *                                  or when a non-ASCII value is not encoded in UTF-8
     */
    public function serialize(iterable $links): ?string
    {
        $elements = [];

        foreach ($links as $link) {
            if (!$link->isTemplated()) {
                continue;
            }

            $parts = [self::serializeString($link->getHref())];

            if ($rels = $link->getRels()) {
                $parts[] = 'rel='.self::serializeString(implode(' ', $rels));
            }

            foreach ($link->getAttributes() as $key => $value) {
                $key = strtolower($key);

                if (!preg_match(self::KEY_PATTERN, $key)) {
                    throw new InvalidArgumentException(\sprintf('The "%s" target attribute cannot be serialized as a structured field parameter key.', $key));
                }

                foreach (\is_array($value) ? $value : [$value] as $item) {
                    if (false === $item) {
                        continue;
                    }

                    $parts[] = true === $item ? $key : $key.'='.self::serializeString((string) $item);
                }
            }

            $elements[] = implode('; ', $parts);
        }

        return $elements ? implode(', ', $elements) : null;
    }

    /**
     * Serializes a string as a structured field String, or as a Display String when it holds non-ASCII characters.
     */
    private static function serializeString(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/D', $value)) {
            return '"'.addcslashes($value, '"\\').'"';
        }

        if (!preg_match('//u', $value)) {
            throw new InvalidArgumentException('Non-ASCII strings must be encoded in UTF-8 to be serialized as structured field display strings.');
        }

        return '%"'.preg_replace_callback('/[%"\x00-\x1F\x7F-\xFF]/', static fn (array $m) => \sprintf('%%%02x', \ord($m[0])), $value).'"';
    }
}
