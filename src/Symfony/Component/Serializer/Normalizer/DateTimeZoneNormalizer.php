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
 * Normalizes a {@see \DateTimeZone} object to a timezone string.
 *
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
final class DateTimeZoneNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [
            \DateTimeZone::class => true,
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof \DateTimeZone) {
            throw new InvalidArgumentException('The object must be an instance of "\DateTimeZone".');
        }

        return $data->getName();
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \DateTimeZone;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \DateTimeZone
    {
        if (!\is_string($data) && !\is_int($data) || '' === $data) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The data is either not a string or an empty string, you should pass a string that can be parsed as a DateTimeZone.', $data, ['string'], $context['deserialization_path'] ?? null, true);
        }

        try {
            return new \DateTimeZone($data);
        } catch (\Exception|\ValueError $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, ['string'], $context['deserialization_path'] ?? null, true, $e->getCode(), $e);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \DateTimeZone::class === $type;
    }
}
