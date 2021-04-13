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

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class BackedEnumNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     *
     * @param \BackedEnum $object
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        return $object->value;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        if (\version_compare(\PHP_VERSION, '8.1', '<')) {
            return false;
        }

        return $data instanceof \BackedEnum;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $denormalized = $type::tryFrom($data);

        if (is_null($denormalized)) {
            throw new NotNormalizableValueException(sprintf('The data is not a valid "%s" value.', $type));
        }

        return $denormalized;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        if (\version_compare(\PHP_VERSION, '8.1', '<')) {
            return false;
        }

        return is_a($type, \BackedEnum::class, true);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
