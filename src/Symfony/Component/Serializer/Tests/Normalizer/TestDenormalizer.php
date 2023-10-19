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

/**
 * Provides a test Normalizer which only implements the DenormalizerInterface.
 *
 * @author Lin Clark <lin@lin-clark.com>
 */
class TestDenormalizer implements DenormalizerInterface
{
    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
    {
        return true;
    }
}
