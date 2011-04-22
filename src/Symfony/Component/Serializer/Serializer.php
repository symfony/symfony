<?php

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

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
    private $normalizerCache = array();

    /**
     * @param mixed $value value to test
     * @return Boolean whether the type is a structured type (array + objects)
     */
    public function isStructuredType($value)
    {
        return null !== $value && !is_scalar($value);
    }

    /**
     * {@inheritdoc}
     */
    public function serialize($data, $format)
    {
        return $this->encode($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeObject($object, $format, $properties = null)
    {
        if (!$this->normalizers) {
            throw new \LogicException('You must register at least one normalizer to be able to normalize objects.');
        }
        $class = get_class($object);
        if (isset($this->normalizerCache[$class][$format])) {
            return $this->normalizerCache[$class][$format]->normalize($object, $format, $properties);
        }
        $reflClass = new \ReflectionClass($class);
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($reflClass, $format)) {
                $this->normalizerCache[$class][$format] = $normalizer;
                return $normalizer->normalize($object, $format, $properties);
            }
        }
        throw new \UnexpectedValueException('Could not normalize object of type '.$class.', no supporting normalizer found.');
    }

    /**
     * {@inheritdoc}
     */
    public function denormalizeObject($data, $class, $format = null)
    {
        if (!$this->normalizers) {
            throw new \LogicException('You must register at least one normalizer to be able to denormalize objects.');
        }
        if (isset($this->normalizerCache[$class][$format])) {
            return $this->normalizerCache[$class][$format]->denormalize($data, $format);
        }
        $reflClass = new \ReflectionClass($class);
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($reflClass, $format)) {
                $this->normalizerCache[$class][$format] = $normalizer;
                return $normalizer->denormalize($data, $class, $format);
            }
        }
        throw new \UnexpectedValueException('Could not denormalize object of type '.$class.', no supporting normalizer found.');
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format)
    {
        if (is_array($data)) {
            foreach ($data as $key => $val) {
                $data[$key] = $this->isStructuredType($val) ? $this->normalize($val, $format) : $val;
            }
            return $data;
        }
        if (is_object($data)) {
            return $this->normalizeObject($data, $format);
        }
        throw new \UnexpectedValueException('An unexpected value could not be normalized: '.var_export($data, true));
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format)
    {
        if (!$this->hasEncoder($format)) {
            throw new \UnexpectedValueException('No encoder registered for the '.$format.' format');
        }
        return $this->encoders[$format]->encode($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format)
    {
        if (!$this->hasEncoder($format)) {
            throw new \UnexpectedValueException('No encoder registered to decode the '.$format.' format');
        }
        return $this->encoders[$format]->decode($data, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function addNormalizer(NormalizerInterface $normalizer)
    {
        $this->normalizers[] = $normalizer;
        $normalizer->setSerializer($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getNormalizers()
    {
        return $this->normalizers;
    }

    /**
     * {@inheritdoc}
     */
    public function removeNormalizer(NormalizerInterface $normalizer)
    {
        unset($this->normalizers[array_search($normalizer, $this->normalizers, true)]);
    }

    /**
     * {@inheritdoc}
     */
    public function setEncoder($format, EncoderInterface $encoder)
    {
        $this->encoders[$format] = $encoder;
        $encoder->setSerializer($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getEncoders()
    {
        return $this->encoders;
    }

    /**
     * {@inheritdoc}
     */
    public function getEncoder($format)
    {
        return $this->encoders[$format];
    }

    /**
     * {@inheritdoc}
     */
    public function hasEncoder($format)
    {
        return isset($this->encoders[$format]);
    }

    /**
     * {@inheritdoc}
     */
    public function removeEncoder($format)
    {
        unset($this->encoders[$format]);
    }
}
