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

use Symfony\Component\Serializer\Normalizer\DenormalizableInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class NormalizableTraversableDummy extends TraversableDummy implements NormalizableInterface, DenormalizableInterface
{
    public function normalize(NormalizerInterface $normalizer, string $format = null, array $context = [])
    {
        return [
            'foo' => 'normalizedFoo',
            'bar' => 'normalizedBar',
        ];
    }

    public function denormalize(DenormalizerInterface $denormalizer, $data, string $format = null, array $context = [])
    {
        return [
            'foo' => 'denormalizedFoo',
            'bar' => 'denormalizedBar',
        ];
    }
}
