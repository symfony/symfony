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

/**
 * {@inheritdoc}
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class WebLinkManager implements WebLinkManagerInterface
{
    private $resources = array();

    /**
     * {@inheritdoc}
     */
    public function add($uri, $rel, array $attributes = array())
    {
        $this->resources[$uri][$rel] = $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->resources = array();
    }

    /**
     * {@inheritdoc}
     */
    public function buildHeaderValue()
    {
        $elements = array();
        foreach ($this->resources as $uri => $attributes) {
            foreach ($attributes as $rel => $otherAttributes) {
                $attributesParts = array('', sprintf('rel=%s', $rel));
                foreach ($otherAttributes as $key => $value) {
                    if (!is_bool($value)) {
                        $attributesParts[] = sprintf('%s=%s', $key, $value);

                        continue;
                    }

                    if (true === $value) {
                        $attributesParts[] = $key;
                    }
                }

                $elements[] = sprintf('<%s>%s', $uri, implode('; ', $attributesParts));
            }
        }

        return $elements ? implode(',', $elements) : null;
    }
}
