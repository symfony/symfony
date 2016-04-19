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

/**
 * ChainNameConverter allows to process a property name with multiple converters.
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class ChainNameConverter implements NameConverterInterface
{
    private $converters;

    /**
     * @param NameConverterInterface[] $converters A list of name converters.
     */
    public function __construct(array $converters = array())
    {
        $this->converters = $converters;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($propertyName)
    {
        foreach ($this->converters as $converter) {
            $propertyName = $converter->normalize($propertyName);
        }

        return $propertyName;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($propertyName)
    {
        foreach ($this->converters as $converter) {
            $propertyName = $converter->denormalize($propertyName);
        }

        return $propertyName;
    }
}
