<?php

namespace Symfony\Component\Serializer;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Defines the interface of the Serializer
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface SerializerInterface
{
    /**
     * Serializes the given data into the requested format.
     *
     * @param mixed  $data
     * @param string $format
     * @api
     */
    function serialize($data, $format);

    /**
     * Unserializes the given data into the requested class
     *
     * @param mixed            $data
     * @param \ReflectionClass $class
     * @param string           $format
     * @api
     */
    function unserialize($data, \ReflectionClass $class, $format);

    /**
     * Normalizes any data into a set of arrays/scalars
     *
     * @param mixed $data data to normalize
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return array|scalar
     * @api
     */
    function normalize($data, $format);

    /**
     * Denormalizes the given data into the requested class
     *
     * @param mixed $data
     * @param \ReflectionClass $class
     * @param string $format
     * @api
     */
    function denormalize($data, \ReflectionClass $class, $format = null);

    /**
     * Encodes data into the given format
     *
     * @param mixed $data data to encode
     * @param string $format format name
     * @return array|scalar
     * @api
     */
    function encode($data, $format);

    /**
     * Decodes a string from the given format back into PHP data
     *
     * @param string $data data to decode
     * @param string $format format name
     * @return mixed
     * @api
     */
    function decode($data, $format);
}