<?php

namespace Symfony\Component\Serializer\Normalizer;

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
use Symfony\Component\Serializer\Exception\UnsupportedException;

class CustomNormalizer implements NormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format)
    {
        if (!$object instanceof NormalizableInterface) {
            throw new UnsupportedException('Object does not implemented NormalizableInterface.');
        }

        return $object->normalize($this, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, \ReflectionClass $class, $format = null)
    {
        $object = new $class;
        if (!$object instanceof NormalizableInterface) {
            throw new UnsupportedException('Object does not implemented NormalizableInterface.');
        }

        $object->denormalize($this, $data, $format);

        return $object;
    }
}
