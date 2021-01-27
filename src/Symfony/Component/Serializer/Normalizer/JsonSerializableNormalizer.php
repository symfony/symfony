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
use Symfony\Component\Serializer\Result\NormalizationResult;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A normalizer that uses an objects own JsonSerializable implementation.
 *
 * @author Fred Cox <mcfedr@gmail.com>
 */
class JsonSerializableNormalizer extends AbstractNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object, string $format = null, array $context = [])
    {
        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object);
        }

        if (!$object instanceof \JsonSerializable) {
            throw new InvalidArgumentException(sprintf('The object must implement "%s".', \JsonSerializable::class));
        }

        if (!$this->serializer instanceof NormalizerInterface) {
            throw new LogicException('Cannot normalize object because injected serializer is not a normalizer.');
        }

        $result = $this->serializer->normalize($object->jsonSerialize(), $format, $context);

        if ($context[SerializerInterface::RETURN_RESULT] ?? false) {
            return NormalizationResult::success($result);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null)
    {
        return $data instanceof \JsonSerializable;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        throw new LogicException(sprintf('Cannot denormalize with "%s".', \JsonSerializable::class));
    }

    /**
     * {@inheritdoc}
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }
}
