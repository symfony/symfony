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
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final class UidNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        return (string) $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof AbstractUid;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        try {
            return Ulid::class === $type ? Ulid::fromString($data) : Uuid::fromString($data);
        } catch (\InvalidArgumentException $exception) {
            throw new NotNormalizableValueException(sprintf('The data is not a valid "%s" string representation.', $type));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return is_a($type, AbstractUid::class, true);
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
