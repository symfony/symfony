<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

use Symfony\Component\Serializer\Debug\Normalizer\Denormalization;
use Symfony\Component\Serializer\Debug\Normalizer\Normalization;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

interface SerializerActionFactoryInterface
{
    /**
     * @param mixed $result
     */
    public function createDenormalization(DenormalizerInterface $denormalizer, string $data, $result, string $type, string $format, array $context = []): Denormalization;

    /**
     * @param mixed $object
     */
    public function createNormalization(NormalizerInterface $normalizer, $object, string $result, string $format, array $context = []): Normalization;

    /**
     * @param mixed $result
     */
    public function createDeserialization(string $data, $result, string $type, string $format, array $context = []): Deserialization;

    /**
     * @param mixed $data
     */
    public function createSerialization($data, string $result, string $format, array $context = []): Serialization;
}
