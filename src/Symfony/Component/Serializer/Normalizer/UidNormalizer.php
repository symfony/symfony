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
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

final class UidNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof AbstractUid) {
            throw new InvalidArgumentException('The object must be an instance of "\Symfony\Component\Uid\AbstractUid".');
        }

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
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        if (!class_exists(AbstractUid::class)) {
            throw new LogicException('You cannot use the "Symfony\Component\Serializer\Normalizer\UidNormalizer" as the Symfony Uid Component is not installed. Try running "composer require symfony/uid".');
        }

        try {
            $uid = Ulid::class === $type ? Ulid::fromString($data) : Uuid::fromString($data);
        } catch (\InvalidArgumentException $exception) {
            throw new NotNormalizableValueException('The data is not a valid '.$type.' string representation.');
        }

        return $uid;
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
