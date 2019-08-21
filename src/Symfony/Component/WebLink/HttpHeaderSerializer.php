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

/**
 * Serializes a list of Link instances to a HTTP Link header.
 *
 * @see https://tools.ietf.org/html/rfc5988
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class HttpHeaderSerializer
{
    /**
     * Builds the value of the "Link" HTTP header.
     *
     * @param LinkInterface[]|\Traversable $links
     */
    public function serialize(iterable $links): ?string
    {
        $elements = [];
        foreach ($links as $link) {
            if ($link->isTemplated()) {
                continue;
            }

            $attributesParts = ['', sprintf('rel="%s"', implode(' ', $link->getRels()))];
            foreach ($link->getAttributes() as $key => $value) {
                if (\is_array($value)) {
                    foreach ($value as $v) {
                        $attributesParts[] = sprintf('%s="%s"', $key, $v);
                    }

                    continue;
                }

                if (!\is_bool($value)) {
                    $attributesParts[] = sprintf('%s="%s"', $key, $value);

                    continue;
                }

                if (true === $value) {
                    $attributesParts[] = $key;
                }
            }

            $elements[] = sprintf('<%s>%s', $link->getHref(), implode('; ', $attributesParts));
        }

        return $elements ? implode(',', $elements) : null;
    }
}
