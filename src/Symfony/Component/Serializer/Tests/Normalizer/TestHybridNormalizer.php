<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TestHybridNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
    }

    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return true;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
    }

    public function supportsNormalization($data, string $format = null)
    {
        return true;
    }
}
