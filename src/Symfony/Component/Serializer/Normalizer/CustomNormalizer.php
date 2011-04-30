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
class CustomNormalizer implements NormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null)
    {
        if (!$object instanceof NormalizableInterface) {
            throw new \RuntimeException('Object does not implemented NormalizableInterface.');
        }

        return $object->normalize($this, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null)
    {
        if (!class_exists($type)) {
            throw new \RuntimeException(sprintf('The class "%s" does not exist.', $type));
        }

        $object = new $type;
        if (!$object instanceof NormalizableInterface) {
            throw new \RuntimeException('Object does not implemented NormalizableInterface.');
        }

        $object->denormalize($this, $data, $format);

        return $object;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof NormalizableInterface;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        $class = new \ReflectionClass($type);

        return $class->isSubclassOf('Symfony\Component\Serializer\Normalizer\NormalizableInterface');
    }
}
