<?php

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class UidNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof AbstractUid) {
            throw new InvalidArgumentException('The object must be an instance of "\Symfony\Component\Uid\Uuid".');
        }

        return (string)$object;
    }

    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof AbstractUid;
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        try {
            $uid = Ulid::class === $type ? Ulid::fromString($data) : Uuid::fromString($data);
        } catch (\InvalidArgumentException $exception) {
            throw new NotNormalizableValueException('The data is not a valid UUID or ULID string representation.');
        }

        return $uid;
    }

    public function supportsDenormalization($data, string $type, string $format = null)
    {
        $class = new \ReflectionClass($type);

        return $class->isSubclassOf(AbstractUid::class);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
