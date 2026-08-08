<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Serializer;

use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException as SerializerInvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serializes an {@see Envelope} to (and from) a base64-encoded string so it
 * can travel through any structured format (JSON, XML, YAML, ...).
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EnvelopeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @return array<string, bool|null>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [Envelope::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof Envelope) {
            throw new SerializerInvalidArgumentException(\sprintf('The "%s" normalizer expects an instance of "%s", "%s" given.', self::class, Envelope::class, get_debug_type($data)));
        }

        return base64_encode((string) $data);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Envelope;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Envelope
    {
        if (!\is_string($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The Envelope normalizer expects a base64-encoded string.', $data, ['string'], $context['deserialization_path'] ?? null);
        }

        $bytes = base64_decode($data, true);
        if (false === $bytes) {
            throw NotNormalizableValueException::createForUnexpectedDataType('The Envelope normalizer expects a base64-encoded string.', $data, ['string'], $context['deserialization_path'] ?? null);
        }

        try {
            return Envelope::fromBytes($bytes);
        } catch (InvalidArgumentException $e) {
            throw NotNormalizableValueException::createForUnexpectedDataType($e->getMessage(), $data, ['string'], $context['deserialization_path'] ?? null);
        }
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return Envelope::class === $type;
    }
}
