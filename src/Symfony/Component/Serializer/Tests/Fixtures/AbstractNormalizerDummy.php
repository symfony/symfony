<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Provides a dummy Normalizer which extends the AbstractNormalizer.
 *
 * @author Konstantin S. M. Möllers <ksm.moellers@gmail.com>
 */
class AbstractNormalizerDummy extends AbstractNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function getAllowedAttributes($classOrObject, array $context,  bool $attributesAsString = false)
    {
        return parent::getAllowedAttributes($classOrObject, $context, $attributesAsString);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return true;
    }
}
