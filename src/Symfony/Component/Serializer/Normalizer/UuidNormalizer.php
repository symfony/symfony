<?php

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Uid\Uuid;

class UuidNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof Uuid) {
            throw new InvalidArgumentException('The object must be an instance of "\Symfony\Component\Uid\Uuid".');
        }

        return (string)$object;
    }

    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof Uuid;
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        try {
            $uuid = Uuid::fromString($data);
        } catch (\InvalidArgumentException $exception) {
            throw new NotNormalizableValueException('The data is not a valid UUID string representation.');
        }

        return $uuid;
    }

    public function supportsDenormalization($data, string $type, string $format = null)
    {
        $class = new \ReflectionClass($type);

        return $class->isSubclassOf(Uuid::class);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
