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
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Serializer serializes and deserializes data.
 *
 * objects are turned into arrays by normalizers.
 * arrays are turned into various output formats by encoders.
 *
 * $serializer->serialize($obj, 'xml')
 * $serializer->decode($data, 'xml')
 * $serializer->denormalize($data, 'Class', 'xml')
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class Serializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface, EncoderInterface, DecoderInterface
{
    /**
     * @var Encoder\ChainEncoder
     */
    protected $encoder;
    /**
     * @var Encoder\ChainDecoder
     */
    protected $decoder;
    /**
     * @var array
     */
    protected $normalizers = array();
    /**
     * @var array
     */
    protected $normalizerCache = array();
    /**
     * @var array
     */
    protected $denormalizerCache = array();

    public function __construct(array $normalizers = array(), array $encoders = array())
    {
        foreach ($normalizers as $normalizer) {
            if ($normalizer instanceof SerializerAwareInterface) {
                $normalizer->setSerializer($this);
            }
        }
        $this->normalizers = $normalizers;

        $decoders = array();
        $realEncoders = array();
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
        }
        $this->encoder = new ChainEncoder($realEncoders);
        $this->decoder = new ChainDecoder($decoders);
    }

    /**
     * {@inheritdoc}
     */
    final public function serialize($data, $format, array $context = array())
    {
        if (!$this->supportsEncoding($format)) {
            throw new UnexpectedValueException(sprintf('Serialization for the format %s is not supported', $format));
        }

        if ($this->encoder->needsNormalization($format)) {
            $data = $this->normalize($data, $format, $context);
        }

        return $this->encode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    final public function deserialize($data, $type, $format, array $context = array())
    {
        if (!$this->supportsDecoding($format)) {
            throw new UnexpectedValueException(sprintf('Deserialization for the format %s is not supported', $format));
        }

        $data = $this->decode($data, $format, $context);

        return $this->denormalize($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format = null, array $context = array())
    {
        if (null === $data || is_scalar($data)) {
            return $data;
        }
        if (is_object($data) && $this->supportsNormalization($data, $format)) {
            return $this->normalizeObject($data, $format, $context);
        }
        if ($data instanceof \Traversable) {
            $normalized = array();
            foreach ($data as $key => $val) {
                $normalized[$key] = $this->normalize($val, $format, $context);
            }

            return $normalized;
        }
        if (is_object($data)) {
            return $this->normalizeObject($data, $format, $context);
        }
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->normalize($val, $format, $context);
            }

            return $data;
        }
        throw new UnexpectedValueException(sprintf('An unexpected value could not be normalized: %s', var_export($data, true)));
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = array())
    {
        return $this->denormalizeObject($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return null !== $this->getNormalizer($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return null !== $this->getDenormalizer($data, $type, $format);
    }

    /**
     * Returns a matching normalizer.
     *
     * @param object $data   The object to get the serializer for
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     *
     * @return NormalizerInterface|null
     */
    private function getNormalizer($data, $format)
    {
        $class = get_class($data);
        if (isset($this->normalizerCache[$class][$format])) {
            return $this->normalizerCache[$class][$format];
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof NormalizerInterface && $normalizer->supportsNormalization($data, $format)) {
                $this->normalizerCache[$class][$format] = $normalizer;

                return $normalizer;
            }
        }
    }

    /**
     * Returns a matching denormalizer.
     *
     * @param mixed  $data   data to restore
     * @param string $class  the expected class to instantiate
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     *
     * @return DenormalizerInterface|null
     */
    private function getDenormalizer($data, $class, $format)
    {
        if (isset($this->denormalizerCache[$class][$format])) {
            return $this->denormalizerCache[$class][$format];
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $class, $format)) {
                $this->denormalizerCache[$class][$format] = $normalizer;

                return $normalizer;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    final public function encode($data, $format, array $context = array())
    {
        return $this->encoder->encode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    final public function decode($data, $format, array $context = array())
    {
        return $this->decoder->decode($data, $format, $context);
    }

    /**
     * Normalizes an object into a set of arrays/scalars.
     *
     * @param object $object  object to normalize
     * @param string $format  format name, present to give the option to normalizers to act differently based on formats
     * @param array  $context The context data for this particular normalization
     *
     * @return array|scalar
     *
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    private function normalizeObject($object, $format, array $context = array())
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to normalize objects.');
        }

        if ($normalizer = $this->getNormalizer($object, $format)) {
            return $normalizer->normalize($object, $format, $context);
        }
        throw new UnexpectedValueException(sprintf('Could not normalize object of type %s, no supporting normalizer found.', get_class($object)));
    }

    /**
     * Denormalizes data back into an object of the given class.
     *
     * @param mixed  $data    data to restore
     * @param string $class   the expected class to instantiate
     * @param string $format  format name, present to give the option to normalizers to act differently based on formats
     * @param array  $context The context data for this particular denormalization
     *
     * @return object
     *
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    private function denormalizeObject($data, $class, $format, array $context = array())
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to denormalize objects.');
        }

        if ($normalizer = $this->getDenormalizer($data, $class, $format)) {
            return $normalizer->denormalize($data, $class, $format, $context);
        }
        throw new UnexpectedValueException(sprintf('Could not denormalize object of type %s, no supporting normalizer found.', $class));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        return $this->encoder->supportsEncoding($format);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        return $this->decoder->supportsDecoding($format);
    }
}
