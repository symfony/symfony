<?php

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\Exception\UnsupportedException;

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
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Serializer implements SerializerInterface
{
    private $normalizers = array();
    private $encoders = array();
    private $normalizersCache = array();
    private $denormalizersCache = array();

    /**
     * {@inheritDoc}
     */
    public function serialize($data, $format)
    {
        $data = $this->normalize($data, $format);

        return $this->encode($data, $format);
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize($data, $type, $format)
    {
        $data = $this->decode($data, $format);

        return $this->denormalize($data, $type, $format);
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($data, $format = null)
    {
        if (is_array($data) || $data instanceof \Traversable) {
            $normalized = array();
            foreach ($data as $k => $v) {
                $normalized[$k] = $this->normalize($v, $format);
            }

            return $normalized;
        } else if (null === $data || is_scalar($data)) {
            return $data;
        } else if (!is_object($data)) {
            throw new \RuntimeException('Cannot normalize value of type: '.gettype($data));
        }

        $class = get_class($data);
        if (isset($this->normalizersCache[$class][$format])) {
            return $this->normalizersCache[$class][$format]->normalize($data, $format);
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supportsNormalization($data, $format)) {
                $this->normalizersCache[$class][$format] = $normalizer;

                return $normalizer->normalize($data, $format);
            }
        }

        throw new \RuntimeException('No normalizer was able to process: '.json_encode($data));
    }

    /**
     * {@inheritDoc}
     */
    public function denormalize($data, $type, $format = null)
    {
        if (!$this->normalizers) {
            throw new \LogicException('You must register at least one normalizer to be able to denormalize.');
        }

        if (isset($this->normalizersCache[$type][$format])) {
            return $this->denormalizersCache[$type][$format]->denormalize($type, $format);
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supportsDenormalization($data, $type, $format)) {
                $this->denormalizersCache[$type][$format] = $normalizer;

                return $normalizer->denormalize($data, $class, $format);
            }
        }

        throw new \RuntimeException('No normalizer was able to process: '.json_encode($data));
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

        if ($normalizer instanceof SerializerAwareInterface) {
            $normalizer->setSerializer($this);
        }
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

        if ($encoder instanceof SerializerAwareInterface) {
            $encoder->setSerializer($this);
        }
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