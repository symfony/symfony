<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\Encoder\ChainDecoder;
use Symfony\Component\Serializer\Encoder\ChainEncoder;
use Symfony\Component\Serializer\Encoder\ContextAwareDecoderInterface;
use Symfony\Component\Serializer\Encoder\ContextAwareEncoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Exception\UnsupportedFormatException;
use Symfony\Component\Serializer\Normalizer\ChainDenormalizer;
use Symfony\Component\Serializer\Normalizer\ChainNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Serializer serializes and deserializes data.
 *
 * objects are turned into arrays by normalizers.
 * arrays are turned into various output formats by encoders.
 *
 *     $serializer->serialize($obj, 'xml')
 *     $serializer->decode($data, 'xml')
 *     $serializer->denormalize($data, 'Class', 'xml')
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Serializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface, ContextAwareEncoderInterface, ContextAwareDecoderInterface
{
    /**
     * Flag to control whether an empty array should be transformed to an
     * object (in JSON: {}) or to a list (in JSON: []).
     */
    public const EMPTY_ARRAY_AS_OBJECT = 'empty_array_as_object';

    private NormalizerInterface $normalizer;

    private DenormalizerInterface $denormalizer;

    protected ChainEncoder $encoder;

    protected ChainDecoder $decoder;

    /**
     * @param array<NormalizerInterface|DenormalizerInterface> $normalizers
     * @param array<EncoderInterface|DecoderInterface>         $encoders
     */
    public function __construct(
        array $normalizers = [],
        array $encoders = [],
        ?NormalizerInterface $normalizer = null,
        ?DenormalizerInterface $denormalizer = null,
    ) {
        if ([] !== $normalizers && (null !== $normalizer || null !== $denormalizer)) {
            throw new InvalidArgumentException('You cannot use an array of $normalizers with a $normalizer/$denormalizer. Please use the $normalizer/$denormalizer arguments only instead.');
        }

        if ([] !== $normalizers) {
            trigger_deprecation('symfony/serializer', '7.2', 'Passing normalizers as first argument to "%s" is deprecated, use a chain normalizer/denormalizer instead.', __METHOD__);
        }

        $localNormalizers = [];
        $localDenormalizers = [];

        foreach ($normalizers as $item) {
            if ($item instanceof SerializerAwareInterface) {
                if (!$item instanceof NormalizerAwareInterface) {
                    trigger_deprecation('symfony/serializer', '7.2', 'Interface %s is deprecated, use %s instead.', SerializerAwareInterface::class, NormalizerAwareInterface::class);
                }
                $item->setSerializer($this);
            }

            if ($item instanceof DenormalizerInterface) {
                $localNormalizers[] = $item;
            }

            if ($item instanceof NormalizerInterface) {
                $localDenormalizers[] = $item;
            }

            if (!($item instanceof NormalizerInterface || $item instanceof DenormalizerInterface)) {
                throw new InvalidArgumentException(sprintf('The class "%s" neither implements "%s" nor "%s".', get_debug_type($item), NormalizerInterface::class, DenormalizerInterface::class));
            }
        }

        $this->normalizer = $normalizer ?? new ChainNormalizer($localNormalizers);
        $this->denormalizer = $denormalizer ?? new ChainDenormalizer($localDenormalizers);

        $decoders = [];
        $realEncoders = [];
        foreach ($encoders as $encoder) {
            if ($encoder instanceof SerializerAwareInterface) {
                if (!$encoder instanceof NormalizerAwareInterface) {
                    trigger_deprecation('symfony/serializer', '7.2', 'Interface %s is deprecated, use %s and/or %s instead.', SerializerAwareInterface::class, NormalizerAwareInterface::class, DenormalizerAwareInterface::class);
                }
                $encoder->setSerializer($this);
            }
            if ($encoder instanceof NormalizerAwareInterface) {
                $encoder->setNormalizer($this->normalizer);
            }
            if ($encoder instanceof DenormalizerAwareInterface) {
                $encoder->setDenormalizer($this->denormalizer);
            }
            if ($encoder instanceof DecoderInterface) {
                $decoders[] = $encoder;
            }
            if ($encoder instanceof EncoderInterface) {
                $realEncoders[] = $encoder;
            }

            if (!($encoder instanceof EncoderInterface || $encoder instanceof DecoderInterface)) {
                throw new InvalidArgumentException(sprintf('The class "%s" neither implements "%s" nor "%s".', get_debug_type($encoder), EncoderInterface::class, DecoderInterface::class));
            }
        }
        $this->encoder = new ChainEncoder($realEncoders);
        $this->decoder = new ChainDecoder($decoders);

        // This code must exist as long as we support SerializerAwareInterface
        if ($this->normalizer instanceof SerializerAwareInterface) {
            $this->normalizer->setSerializer($this);
        }
        if ($this->denormalizer instanceof SerializerAwareInterface) {
            $this->denormalizer->setSerializer($this);
        }
    }

    final public function serialize(mixed $data, string $format, array $context = []): string
    {
        if (!$this->supportsEncoding($format, $context)) {
            throw new UnsupportedFormatException(sprintf('Serialization for the format "%s" is not supported.', $format));
        }

        if ($this->encoder->needsNormalization($format, $context)) {
            $data = $this->normalize($data, $format, $context);
        }

        return $this->encode($data, $format, $context);
    }

    final public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        if (!$this->supportsDecoding($format, $context)) {
            throw new UnsupportedFormatException(sprintf('Deserialization for the format "%s" is not supported.', $format));
        }

        $data = $this->decode($data, $format, $context);

        return $this->denormalize($data, $type, $format, $context);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $this->normalizer->normalize($data, $format, $context);
    }

    /**
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException Occurs when one or more properties of $type fails to denormalize
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (isset($context[DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS])) {
            unset($context[DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS]);
            $context['not_normalizable_value_exceptions'] = [];
            $errors = &$context['not_normalizable_value_exceptions'];
            $denormalized = $this->denormalizer->denormalize($data, $type, $format, $context);

            if ($errors) {
                // merge errors so that one path has only one error
                $uniqueErrors = [];
                foreach ($errors as $error) {
                    if (null === $error->getPath()) {
                        $uniqueErrors[] = $error;
                        continue;
                    }

                    $uniqueErrors[$error->getPath()] = $uniqueErrors[$error->getPath()] ?? $error;
                }

                throw new PartialDenormalizationException($denormalized, array_values($uniqueErrors));
            }

            return $denormalized;
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->normalizer->supportsNormalization($data, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->denormalizer->supportsDenormalization($data, $type, $format, $context);
    }

    final public function encode(mixed $data, string $format, array $context = []): string
    {
        return $this->encoder->encode($data, $format, $context);
    }

    final public function decode(string $data, string $format, array $context = []): mixed
    {
        return $this->decoder->decode($data, $format, $context);
    }

    public function supportsEncoding(string $format, array $context = []): bool
    {
        return $this->encoder->supportsEncoding($format, $context);
    }

    public function supportsDecoding(string $format, array $context = []): bool
    {
        return $this->decoder->supportsDecoding($format, $context);
    }
}
