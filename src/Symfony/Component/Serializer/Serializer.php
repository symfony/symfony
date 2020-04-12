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
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
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
class Serializer implements SerializerInterface, ContextAwareNormalizerInterface, ContextAwareDenormalizerInterface, ContextAwareEncoderInterface, ContextAwareDecoderInterface
{
    /**
     * @var Encoder\ChainEncoder
     */
    protected $encoder;

    /**
     * @var Encoder\ChainDecoder
     */
    protected $decoder;

    private $normalizers = [];
    private $denormalizerCache = [];
    private $normalizerCache = [];

    /**
     * @param (NormalizerInterface|DenormalizerInterface)[] $normalizers
     * @param (EncoderInterface|DecoderInterface)[]         $encoders
     */
    public function __construct(array $normalizers = [], array $encoders = [])
    {
        foreach ($normalizers as $normalizer) {
            if ($normalizer instanceof SerializerAwareInterface) {
                $normalizer->setSerializer($this);
            }

            if ($normalizer instanceof DenormalizerAwareInterface) {
                $normalizer->setDenormalizer($this);
            }

            if ($normalizer instanceof NormalizerAwareInterface) {
                $normalizer->setNormalizer($this);
            }

            if (!($normalizer instanceof NormalizerInterface || $normalizer instanceof DenormalizerInterface)) {
                throw new InvalidArgumentException(sprintf('The class "%s" neither implements "%s" nor "%s".', \get_class($normalizer), NormalizerInterface::class, DenormalizerInterface::class));
            }
        }
        $this->normalizers = $normalizers;

        $decoders = [];
        $realEncoders = [];
        foreach ($encoders as $encoder) {
            if ($encoder instanceof SerializerAwareInterface) {
                $encoder->setSerializer($this);
            }
            if ($encoder instanceof DecoderInterface) {
                $decoders[] = $encoder;
            }
            if ($encoder instanceof EncoderInterface) {
                $realEncoders[] = $encoder;
            }

            if (!($encoder instanceof EncoderInterface || $encoder instanceof DecoderInterface)) {
                throw new InvalidArgumentException(sprintf('The class "%s" neither implements "%s" nor "%s".', \get_class($encoder), EncoderInterface::class, DecoderInterface::class));
            }
        }
        $this->encoder = new ChainEncoder($realEncoders);
        $this->decoder = new ChainDecoder($decoders);
    }

    /**
     * {@inheritdoc}
     */
    final public function serialize($data, string $format, array $context = []): string
    {
        if (!$this->supportsEncoding($format, $context)) {
            throw new NotEncodableValueException(sprintf('Serialization for the format "%s" is not supported.', $format));
        }

        if ($this->encoder->needsNormalization($format, $context)) {
            $data = $this->normalize($data, $format, $context);
        }

        return $this->encode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    final public function deserialize($data, string $type, string $format, array $context = [])
    {
        if (!$this->supportsDecoding($format, $context)) {
            throw new NotEncodableValueException(sprintf('Deserialization for the format "%s" is not supported.', $format));
        }

        $data = $this->decode($data, $format, $context);

        return $this->denormalize($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, string $format = null, array $context = [])
    {
        // If a normalizer supports the given data, use it
        if ($normalizer = $this->getNormalizer($data, $format, $context)) {
            return $normalizer->normalize($data, $format, $context);
        }

        if (null === $data || is_scalar($data)) {
            return $data;
        }

        if (\is_array($data) || $data instanceof \Traversable) {
            $normalized = [];
            foreach ($data as $key => $val) {
                $normalized[$key] = $this->normalize($val, $format, $context);
            }

            return $normalized;
        }

        if (\is_object($data)) {
            if (!$this->normalizers) {
                throw new LogicException('You must register at least one normalizer to be able to normalize objects.');
            }

            throw new NotNormalizableValueException(sprintf('Could not normalize object of type "%s", no supporting normalizer found.', \get_class($data)));
        }

        throw new NotNormalizableValueException('An unexpected value could not be normalized: '.(!\is_resource($data) ? var_export($data, true) : sprintf('%s resource', get_resource_type($data))));
    }

    /**
     * {@inheritdoc}
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to denormalize objects.');
        }

        if ($normalizer = $this->getDenormalizer($data, $type, $format, $context)) {
            return $normalizer->denormalize($data, $type, $format, $context);
        }

        throw new NotNormalizableValueException(sprintf('Could not denormalize object of type "%s", no supporting normalizer found.', $type));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, string $format = null, array $context = [])
    {
        return null !== $this->getNormalizer($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null, array $context = [])
    {
        return null !== $this->getDenormalizer($data, $type, $format, $context);
    }

    /**
     * Returns a matching normalizer.
     *
     * @param mixed  $data    Data to get the serializer for
     * @param string $format  Format name, present to give the option to normalizers to act differently based on formats
     * @param array  $context Options available to the normalizer
     */
    private function getNormalizer($data, ?string $format, array $context): ?NormalizerInterface
    {
        $type = \is_object($data) ? \get_class($data) : 'native-'.\gettype($data);

        if (!isset($this->normalizerCache[$format][$type])) {
            $this->normalizerCache[$format][$type] = [];

            foreach ($this->normalizers as $k => $normalizer) {
                if (!$normalizer instanceof NormalizerInterface) {
                    continue;
                }

                if (!$normalizer instanceof CacheableSupportsMethodInterface || !$normalizer->hasCacheableSupportsMethod()) {
                    $this->normalizerCache[$format][$type][$k] = false;
                } elseif ($normalizer->supportsNormalization($data, $format, $context)) {
                    $this->normalizerCache[$format][$type][$k] = true;
                    break;
                }
            }
        }

        foreach ($this->normalizerCache[$format][$type] as $k => $cached) {
            $normalizer = $this->normalizers[$k];
            if ($cached || $normalizer->supportsNormalization($data, $format, $context)) {
                return $normalizer;
            }
        }

        return null;
    }

    /**
     * Returns a matching denormalizer.
     *
     * @param mixed  $data    Data to restore
     * @param string $class   The expected class to instantiate
     * @param string $format  Format name, present to give the option to normalizers to act differently based on formats
     * @param array  $context Options available to the denormalizer
     */
    private function getDenormalizer($data, string $class, ?string $format, array $context): ?DenormalizerInterface
    {
        if (!isset($this->denormalizerCache[$format][$class])) {
            $this->denormalizerCache[$format][$class] = [];

            foreach ($this->normalizers as $k => $normalizer) {
                if (!$normalizer instanceof DenormalizerInterface) {
                    continue;
                }

                if (!$normalizer instanceof CacheableSupportsMethodInterface || !$normalizer->hasCacheableSupportsMethod()) {
                    $this->denormalizerCache[$format][$class][$k] = false;
                } elseif ($normalizer->supportsDenormalization(null, $class, $format, $context)) {
                    $this->denormalizerCache[$format][$class][$k] = true;
                    break;
                }
            }
        }

        foreach ($this->denormalizerCache[$format][$class] as $k => $cached) {
            $normalizer = $this->normalizers[$k];
            if ($cached || $normalizer->supportsDenormalization($data, $class, $format, $context)) {
                return $normalizer;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    final public function encode($data, string $format, array $context = [])
    {
        return $this->encoder->encode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    final public function decode(string $data, string $format, array $context = [])
    {
        return $this->decoder->decode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding(string $format, array $context = [])
    {
        return $this->encoder->supportsEncoding($format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding(string $format, array $context = [])
    {
        return $this->decoder->supportsDecoding($format, $context);
    }
}
