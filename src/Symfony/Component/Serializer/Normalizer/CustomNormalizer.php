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

use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @final since Symfony 6.3
 */
class CustomNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface, CacheableSupportsMethodInterface
{
    use ObjectToPopulateTrait;
    use SerializerAwareTrait;

    public function getSupportedTypes(?string $format): array
    {
        return [
            NormalizableInterface::class => __CLASS__ === static::class || $this->hasCacheableSupportsMethod(),
        ];
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $object->normalize($this->serializer, $format, $context);
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        $object = $this->extractObjectToPopulate($type, $context) ?? new $type();
        $object->denormalize($this->serializer, $data, $format, $context);

        return $object;
    }

    /**
     * Checks if the given class implements the NormalizableInterface.
     *
     * @param mixed       $data   Data to normalize
     * @param string|null $format The format being (de-)serialized from or into
     * @param array       $context
     */
    public function supportsNormalization(mixed $data, string $format = null /* , array $context = [] */): bool
    {
        return $data instanceof NormalizableInterface;
    }

    /**
     * Checks if the given class implements the DenormalizableInterface.
     *
     * @param mixed       $data   Data to denormalize from
     * @param string      $type   The class to which the data should be denormalized
     * @param string|null $format The format being deserialized from
     * @param array       $context
     */
    public function supportsDenormalization(mixed $data, string $type, string $format = null /* , array $context = [] */): bool
    {
        return is_subclass_of($type, DenormalizableInterface::class);
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, use "getSupportedTypes()" instead.', __METHOD__);

        return __CLASS__ === static::class;
    }
}
