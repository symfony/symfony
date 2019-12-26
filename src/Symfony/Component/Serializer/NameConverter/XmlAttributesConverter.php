<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\NameConverter;

final class XmlAttributesConverter implements NameConverterInterface
{
    const ATTRIBUTE_PREFIX = 'attr';
    const NODE_VALUE_ATTRIBUTE_NAME = 'value';

    private $attributePrefix;
    private $nodeValueAttributeName;

    public function __construct(string $attributePrefix = self::ATTRIBUTE_PREFIX, string $nodeValueAttributeName = self::NODE_VALUE_ATTRIBUTE_NAME)
    {
        $this->attributePrefix = $attributePrefix;
        $this->nodeValueAttributeName = $nodeValueAttributeName;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($propertyName): string
    {
        if (0 === strncmp($this->nodeValueAttributeName, $propertyName, \strlen($this->nodeValueAttributeName))) {
            return '#';
        }

        if (0 === strncmp($this->attributePrefix, $propertyName, \strlen($this->attributePrefix))) {
            return '@'.substr($propertyName, \strlen($this->attributePrefix));
        }

        return $propertyName;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($propertyName): string
    {
        $propertyName = (string) $propertyName;

        if (0 === strpos($propertyName, '#')) {
            return $this->nodeValueAttributeName;
        }

        if (0 === strpos($propertyName, '@')) {
            return $this->attributePrefix.substr($propertyName, 1);
        }

        return $propertyName;
    }
}
