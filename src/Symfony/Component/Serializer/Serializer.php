<?php

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\NormalizationAwareInterface;

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
 * $serializer->denormalizeObject($data, 'Class', 'xml')
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Serializer implements SerializerInterface
{
    private $normalizers = array();
    private $encoders = array();
    private $decoders = array();
    protected $normalizerCache = array();
    protected $denormalizerCache = array();

    /**
     * {@inheritdoc}
     */
    public function serialize($data, $format)
    {
        if (!isset($this->encoders[$format])) {
            throw new \UnexpectedValueException('No encoder registered for the '.$format.' format');
        }
        if (!$this->encoders[$format] instanceof NormalizationAwareInterface) {
            $data = $this->normalize($data);
        }
        return $this->encode($data, $format);
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize($data, $type, $format) {
        return $this->denormalize($this->decode($data, $format), $type, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format = null)
    {
        if (null === $data || is_scalar($data)) {
            return $data;
        }
        if ($data instanceof Traversable) {
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
        throw new \UnexpectedValueException('An unexpected value could not be normalized: '.var_export($data, true));
    }

    /**
     * {@inheritDoc}
     */
    public function denormalize($data, $type, $format = null)
    {
        return $this->denormalizeObject($data, $type, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format)
    {
        if (!isset($this->encoders[$format])) {
            throw new \UnexpectedValueException('No encoder registered for the '.$format.' format');
        }
        return $this->encoders[$format]->encode($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format)
    {
        if (!isset($this->decoders[$format])) {
            throw new \UnexpectedValueException('No decoder registered for the '.$format.' format');
        }
        return $this->decoders[$format]->decode($data, $format);
    }

    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param object $object object to normalize
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return array|scalar
     */
    public function normalizeObject($object, $format = null)
    {
        if (!$this->normalizers) {
            throw new \LogicException('You must register at least one normalizer to be able to normalize objects.');
        }
        $class = get_class($object);
        if (isset($this->normalizerCache[$class][$format])) {
            return $this->normalizerCache[$class][$format]->normalize($object, $format);
        }
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supportsNormalization($object, $class, $format)) {
                $this->normalizerCache[$class][$format] = $normalizer;
                return $normalizer->normalize($object, $format);
            }
        }
        throw new \UnexpectedValueException('Could not normalize object of type '.$class.', no supporting normalizer found.');
    }

    /**
     * Denormalizes data back into an object of the given class
     *
     * @param mixed $data data to restore
     * @param string $class the expected class to instantiate
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return object
     */
    public function denormalizeObject($data, $class, $format = null)
    {
        if (!$this->normalizers) {
            throw new \LogicException('You must register at least one normalizer to be able to denormalize objects.');
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
        throw new \UnexpectedValueException('Could not denormalize object of type '.$class.', no supporting normalizer found.');
    }

    /**
     * @param NormalizerInterface $normalizer
     */
    public function addNormalizer(NormalizerInterface $normalizer)
    {
        $this->normalizers[] = $normalizer;
        if ($normalizer instanceof SerializerAwareInterface) {
            $normalizer->setSerializer($this);
        }
    }

    /**
     * @return array[]NormalizerInterface
     */
    public function getNormalizers()
    {
        return $this->normalizers;
    }

    /**
     * @param NormalizerInterface $normalizer
     */
    public function removeNormalizer(NormalizerInterface $normalizer)
    {
        unset($this->normalizers[array_search($normalizer, $this->normalizers, true)]);
    }

    /**
     * @param string           $format  format name
     * @param EncoderInterface $encoder
     */
    public function setEncoder($format, EncoderInterface $encoder)
    {
        $this->encoders[$format] = $encoder;
        if ($encoder instanceof SerializerAwareInterface) {
            $encoder->setSerializer($this);
        }
    }

    /**
     * @param string           $format  format name
     * @param DecoderInterface $decoder
     */
    public function setDecoder($format, DecoderInterface $decoder)
    {
        $this->decoders[$format] = $decoder;
        if ($decoder instanceof SerializerAwareInterface) {
            $decoder->setSerializer($this);
        }
    }

    /**
     * @return array[]EncoderInterface
     */
    public function getEncoders()
    {
        return $this->encoders;
    }

    /**
     * @return array[]DecoderInterface
     */
    public function getDecoders()
    {
        return $this->decoders;
    }

    /**
     * @return EncoderInterface
     */
    public function getEncoder($format)
    {
        return $this->encoders[$format];
    }

    /**
     * @return DecoderInterface
     */
    public function getDecoder($format)
    {
        return $this->decoders[$format];
    }

    /**
     * Checks whether the serializer has an encoder registered for the given format
     *
     * @param string $format format name
     * @return Boolean
     */
    public function hasEncoder($format)
    {
        return isset($this->encoders[$format]);
    }

    /**
     * Checks whether the serializer has a decoder registered for the given format
     *
     * @param string $format format name
     * @return Boolean
     */
    public function hasDecoder($format)
    {
        return isset($this->decoders[$format]);
    }

    /**
     * @param string $format format name
     */
    public function removeEncoder($format)
    {
        unset($this->encoders[$format]);
    }

    /**
     * @param string $format format name
     */
    public function removeDecoder($format)
    {
        unset($this->decoders[$format]);
    }
}
