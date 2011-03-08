<?php

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\SerializerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class CustomNormalizer extends AbstractNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format, $properties = null)
    {
        return $object->normalize($this, $format, $properties);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null)
    {
        $object = new $class;
        $object->denormalize($this, $data, $format);
        return $object;
    }

    /**
     * Checks if the given class implements the NormalizableInterface.
     *
     * @param  ReflectionClass $class  A ReflectionClass instance of the class
     *                                 to serialize into or from.
     * @param  string $format The format being (de-)serialized from or into.
     * @return Boolean
     */
    public function supports(\ReflectionClass $class, $format = null)
    {
        return $class->implementsInterface('Symfony\Component\Serializer\Normalizer\NormalizableInterface');
    }
}
