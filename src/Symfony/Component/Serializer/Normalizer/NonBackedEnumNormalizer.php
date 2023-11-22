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
 * Normalizes a {@see \UnitEnum} that is not a {@see \BackedEnum} to a string identifier.
 *
 * @author Misha Kulakovsky <misha@kulakovs.ky>
 */
final class NonBackedEnumNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [
            \UnitEnum::class => true,
        ];
    }

    public function normalize(mixed $object, string $format = null, array $context = []): int|string
    {
        if (!$object instanceof \UnitEnum || $object instanceof \BackedEnum) {
            throw new InvalidArgumentException('The data must belong to a non-backed enumeration.');
        }

        return $object->name;
    }

    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof \UnitEnum
            && !$data instanceof \BackedEnum;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        if (!is_subclass_of($type, \UnitEnum::class) || is_subclass_of($type, \BackedEnum::class)) {
            throw new InvalidArgumentException('The data must belong to a non-backed enumeration.');
        }

        if (!\is_string($data) && !($context[BackedEnumNormalizer::ALLOW_INVALID_VALUES] ?? false)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf("The data is not a string, you should pass a string that can be parsed as an enumeration case of type %s.", $type), $data, [Type::BUILTIN_TYPE_STRING], $context['deserialization_path'] ?? null, true);
        }

        try {
            $constantName = "$type::$data";
            if (!\defined($constantName)) {
                throw new \ValueError(sprintf("%s is not a valid case for enum %s.", $data, $type));
            }

            $value = \constant($constantName);
            if (!\is_object($value) || $value::class !== $type) {
                throw new \ValueError(sprintf("%s is not a valid enum case.", $constantName));
            }

            return $value;
        } catch (\ValueError $e) {
            if ($context[BackedEnumNormalizer::ALLOW_INVALID_VALUES] ?? false) {
                return null;
            }
            if (isset($context['has_constructor'])) {
                throw new InvalidArgumentException(sprintf("The data must belong to a non-backed enumeration of type %s.", $type));
            }
            throw NotNormalizableValueException::createForUnexpectedDataType(sprintf("The data must belong to a non-backed enumeration of type %s.", $type), $data, [$type], $context['deserialization_path'] ?? null, true, 0, $e);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return is_subclass_of($type, \UnitEnum::class)
            && !is_subclass_of($type, \BackedEnum::class);
    }
}
