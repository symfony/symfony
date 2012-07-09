<?php

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\NormalizationAwareInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Serializer serializes and deserializes data
 *
 * objects are turned into arrays by normalizers
 * arrays are turned into various output formats by encoders
 *
 * $serializer->serialize($obj, 'xml')
 * $serializer->decode($data, 'xml')
 * $serializer->denormalize($data, 'Class', 'xml')
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class Serializer implements SerializerInterface
{
    protected $normalizers = array();
    protected $encoders = array();
    protected $normalizerCache = array();
    protected $denormalizerCache = array();

    public function __construct(array $normalizers = array(), array $encoders = array())
    {
        foreach ($normalizers as $normalizer) {
            if ($normalizer instanceof SerializerAwareInterface) {
                $normalizer->setSerializer($this);
            }
        }
        $this->normalizers = $normalizers;

        foreach ($encoders as $encoder) {
            if ($encoder instanceof SerializerAwareInterface) {
                $encoder->setSerializer($this);
            }
        }
        $this->encoders = $encoders;
    }

    /**
     * {@inheritdoc}
     */
    final public function serialize($data, $format)
    {
        if (!$this->supportsSerialization($format)) {
            throw new UnexpectedValueException('Serialization for the format '.$format.' is not supported');
        }

        $encoder = $this->getEncoder($format);

        if (!$encoder instanceof NormalizationAwareInterface) {
            $data = $this->normalize($data, $format);
        }

        return $this->encode($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    final public function deserialize($data, $type, $format)
    {
        if (!$this->supportsDeserialization($format)) {
            throw new UnexpectedValueException('Deserialization for the format '.$format.' is not supported');
        }

        $data = $this->decode($data, $format);

        return $this->denormalize($data, $type, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format = null)
    {
        if (null === $data || is_scalar($data)) {
            return $data;
        }
        if (is_object($data) && $this->supportsNormalization($data, $format)) {
            return $this->normalizeObject($data, $format);
        }
        if ($data instanceof \Traversable) {
            $normalized = array();
            foreach ($data as $key => $val) {
                $normalized[$key] = $this->normalize($val, $format);
            }

            return $normalized;
        }
        if (is_object($data)) {
            return $this->normalizeObject($data, $format);
        }
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->normalize($val, $format);
            }

            return $data;
        }
        throw new UnexpectedValueException('An unexpected value could not be normalized: '.var_export($data, true));
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null)
    {
        return $this->denormalizeObject($data, $type, $format);
    }

    /**
     * {@inheritdoc}
     */
    final public function encode($data, $format)
    {
        return $this->getEncoder($format)->encode($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    final public function decode($data, $format)
    {
        return $this->getEncoder($format)->decode($data, $format);
    }

    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param object $object object to normalize
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return array|scalar
     */
    private function normalizeObject($object, $format = null)
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to normalize objects.');
        }

        $class = get_class($object);

        // If normalization is supported, cached normalizer will exist
        if ($this->supportsNormalization($object, $format)) {
            return $this->normalizerCache[$class][$format]->normalize($object, $format);
        }

        throw new UnexpectedValueException('Could not normalize object of type '.$class.', no supporting normalizer found.');
    }

    /**
     * Denormalizes data back into an object of the given class
     *
     * @param mixed  $data   data to restore
     * @param string $class  the expected class to instantiate
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return object
     */
    private function denormalizeObject($data, $class, $format = null)
    {
        if (!$this->normalizers) {
            throw new LogicException('You must register at least one normalizer to be able to denormalize objects.');
        }
        if (isset($this->denormalizerCache[$class][$format])) {
            return $this->denormalizerCache[$class][$format]->denormalize($data, $class, $format);
        }
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supportsDenormalization($data, $class, $format)) {
                $this->denormalizerCache[$class][$format] = $normalizer;

                return $normalizer->denormalize($data, $class, $format);
            }
        }
        throw new UnexpectedValueException('Could not denormalize object of type '.$class.', no supporting normalizer found.');
    }

    /**
     * Check if normalizer cache or normalizers supports provided object, which will then be cached
     *
     * @param object $object Object to test for normalization support
     * @param string $format Format name, needed for normalizers to pivot on
     */
    private function supportsNormalization($object, $format)
    {
        $class = get_class($object);

        if (isset($this->normalizerCache[$class][$format])) {
            return true;
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supportsNormalization($object, $format)) {
                $this->normalizerCache[$class][$format] = $normalizer;

                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSerialization($format)
    {
        return $this->supportsEncoding($format);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDeserialization($format)
    {
        return $this->supportsDecoding($format);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        try {
            $encoder = $this->getEncoder($format);
        } catch (\RuntimeException $e) {
            return false;
        }

        return $encoder instanceof EncoderInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        try {
            $encoder = $this->getEncoder($format);
        } catch (\RuntimeException $e) {
            return false;
        }

        return $encoder instanceof DecoderInterface;
    }

    /**
     * {@inheritdoc}
     */
    public function getEncoder($format)
    {
        if (!isset($this->encoders[$format])) {
            throw new RuntimeException(sprintf('No encoder found for format "%s".', $format));
        }

        return $this->encoders[$format];
    }
}
