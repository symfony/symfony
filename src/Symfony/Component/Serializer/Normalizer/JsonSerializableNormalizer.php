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

/**
 * A normalizer that uses an objects own JsonSerializable implementation.
 *
 * @author Fred Cox <mcfedr@gmail.com>
 */
final class JsonSerializableNormalizer extends AbstractNormalizer
{
    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object, $format, $context);
        }

        if (!$object instanceof \JsonSerializable) {
            throw new InvalidArgumentException(\sprintf('The object must implement "%s".', \JsonSerializable::class));
        }

        if (!$this->serializer instanceof NormalizerInterface) {
            throw new LogicException('Cannot normalize object because injected serializer is not a normalizer.');
        }

        return $this->serializer->normalize($object->jsonSerialize(), $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            \JsonSerializable::class => true,
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \JsonSerializable;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return false;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        throw new LogicException(\sprintf('Cannot denormalize with "%s".', \JsonSerializable::class));
    }
}
