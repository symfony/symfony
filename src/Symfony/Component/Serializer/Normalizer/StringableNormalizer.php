<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;

/**
 * A normalizer that uses an objects own Stringable implementation.
 *
 * @author Craig Morris <craig.michael.morris@gmail.com>
 */
class StringableNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof \Stringable && !method_exists($object, '__toString')) {
            throw new InvalidArgumentException(sprintf('The object must implement "%s or __toString()".', \Stringable::class));
        }

        return $object->__toString();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof \Stringable || method_exists($data, '__toString');
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        throw new LogicException(sprintf('Cannot denormalize with "%s".', \Stringable::class));
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
