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

use BcMath\Number;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * Normalizes {@see Number} and {@see \GMP} to a string.
 */
final class NumberNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [
            Number::class => true,
            \GMP::class => true,
        ];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof Number && !$data instanceof \GMP) {
            throw new InvalidArgumentException(\sprintf('The data must be an instance of "%s" or "%s".', Number::class, \GMP::class));
        }

        return (string) $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Number || $data instanceof \GMP;
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Number|\GMP
    {
        if (!\is_string($data) && !\is_int($data)) {
            throw $this->createNotNormalizableValueException($type, $data, $context);
        }

        try {
            return match ($type) {
                Number::class => new Number($data),
                \GMP::class => new \GMP($data),
                default => throw new InvalidArgumentException(\sprintf('Only "%s" and "%s" types are supported.', Number::class, \GMP::class)),
            };
        } catch (\ValueError $e) {
            throw $this->createNotNormalizableValueException($type, $data, $context, $e);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return \in_array($type, [Number::class, \GMP::class], true) && null !== $data;
    }

    private function createNotNormalizableValueException(string $type, mixed $data, array $context, ?\Throwable $previous = null): NotNormalizableValueException
    {
        $message = match ($type) {
            Number::class => 'The data must be a "string" representing a decimal number, or an "int".',
            \GMP::class => 'The data must be a "string" representing an integer, or an "int".',
        };

        return NotNormalizableValueException::createForUnexpectedDataType($message, $data, ['string', 'int'], $context['deserialization_path'] ?? null, true, 0, $previous);
    }
}
