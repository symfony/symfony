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

use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes a {@see \DateTimeZone} object to a timezone string.
 *
 * @author Jérôme Desjardins <jewome62@gmail.com>
 *
 * @final since Symfony 6.3
 */
class DateTimeZoneNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [
            \DateTimeZone::class => __CLASS__ === static::class || $this->hasCacheableSupportsMethod(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (!$object instanceof \DateTimeZone) {
            throw new InvalidArgumentException('The object must be an instance of "\DateTimeZone".');
        }

        return $object->getName();
    }

    /**
     * @param array $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null /* , array $context = [] */): bool
    {
        return $data instanceof \DateTimeZone;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \DateTimeZone
    {
        if ('' === $data || null === $data) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data is either an empty string or null, you should pass a string that can be parsed as a DateTimeZone.', $data, [Type::BUILTIN_TYPE_STRING], $context['deserialization_path'] ?? null, true);
        }

        try {
            return new \DateTimeZone($data);
        } catch (\Exception $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, [Type::BUILTIN_TYPE_STRING], $context['deserialization_path'] ?? null, true, $e->getCode(), $e);
        }
    }

    /**
     * @param array $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null /* , array $context = [] */): bool
    {
        return \DateTimeZone::class === $type;
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, implement "%s::getSupportedTypes()" instead.', __METHOD__, get_debug_type($this));

        return __CLASS__ === static::class;
    }
}
