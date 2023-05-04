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
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes a {@see \BackedEnum} enumeration to a string or an integer.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class BackedEnumNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    /**
     * If true, will denormalize any invalid value into null.
     */
    public const ALLOW_INVALID_VALUES = 'allow_invalid_values';

    public function getSupportedTypes(?string $format): array
    {
        return [
           \BackedEnum::class => true,
        ];
    }

    public function normalize(mixed $object, string $format = null, array $context = []): int|string
    {
        if (!$object instanceof \BackedEnum) {
            throw new InvalidArgumentException('The data must belong to a backed enumeration.');
        }

        return $object->value;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof \BackedEnum;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        if (!is_subclass_of($type, \BackedEnum::class)) {
            throw new InvalidArgumentException('The data must belong to a backed enumeration.');
        }

        if ($context[self::ALLOW_INVALID_VALUES] ?? false) {
            if (null === $data || (!\is_int($data) && !\is_string($data))) {
                return null;
            }

            try {
                return $type::tryFrom($data);
            } catch (\TypeError) {
                return null;
            }
        }

        $backingType = (new \ReflectionEnum($type))->getBackingType()->getName();
        $givenType = \get_debug_type($data);

        if ($givenType !== $backingType) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data expected to be "%s", "%s" given. You should pass a value that can be parsed as an enumeration case of type %s.', $backingType, $givenType, $type), $data, [$backingType], $context['deserialization_path'] ?? null, true);
        }

        try {
            return $type::from($data);
        } catch (\ValueError $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data must belong to a backed enumeration of type '.$type, $data, [$backingType], $context['deserialization_path'] ?? null, true);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return is_subclass_of($type, \BackedEnum::class);
    }

    /**
     * @deprecated since Symfony 6.3, use "getSupportedTypes()" instead
     */
    public function hasCacheableSupportsMethod(): bool
    {
        trigger_deprecation('symfony/serializer', '6.3', 'The "%s()" method is deprecated, use "getSupportedTypes()" instead.', __METHOD__);

        return true;
    }
}
