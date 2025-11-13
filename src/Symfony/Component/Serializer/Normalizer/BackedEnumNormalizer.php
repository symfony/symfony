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
final class BackedEnumNormalizer implements NormalizerInterface, DenormalizerInterface
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

    public function normalize(mixed $data, ?string $format = null, array $context = []): int|string
    {
        if (!$data instanceof \BackedEnum) {
            throw new InvalidArgumentException('The data must belong to a backed enumeration.');
        }

        return $data->value;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \BackedEnum;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_subclass_of($type, \BackedEnum::class)) {
            throw new InvalidArgumentException('The data must belong to a backed enumeration.');
        }

        $allowInvalidValues = $context[self::ALLOW_INVALID_VALUES] ?? false;

        if (null === $data || (!\is_int($data) && !\is_string($data))) {
            if ($allowInvalidValues && !isset($context['not_normalizable_value_exceptions'])) {
                return null;
            }

            throw NotNormalizableValueException::createForUnexpectedDataType('The data is neither an integer nor a string, you should pass an integer or a string that can be parsed as an enumeration case of type '.$type.'.', $data, ['int', 'string'], $context['deserialization_path'] ?? null, true);
        }

        try {
            return $type::from($data);
        } catch (\ValueError|\TypeError $e) {
            if (isset($context['has_constructor'])) {
                throw new InvalidArgumentException('The data must belong to a backed enumeration of type '.$type, 0, $e);
            }

            if ($allowInvalidValues && !isset($context['not_normalizable_value_exceptions'])) {
                return null;
            }

            throw NotNormalizableValueException::createForUnexpectedDataType('The data must belong to a backed enumeration of type '.$type, $data, ['int', 'string'], $context['deserialization_path'] ?? null, true, 0, $e);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_subclass_of($type, \BackedEnum::class);
    }
}
