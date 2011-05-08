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
 */
interface SerializerInterface
{
    /**
     * Serializes data in the appropriate format
     *
     * @param mixed $data any data
     * @param string $format format name
     * @return string
     * @api
     */
    function serialize($data, $format);

    /**
     * Deserializes data into the given type.
     *
     * @param mixed $data
     * @param string $type
     * @param string $format
     */
    function deserialize($data, $type, $format);

    /**
     * Normalizes any data into a set of arrays/scalars
     *
     * @param mixed $data data to normalize
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return array|scalar
     * @api
     */
    function normalize($data, $format = null);

    /**
     * Denormalizes data into the given type.
     *
     * @param mixed $data
     * @param string $type
     * @param string $format
     *
     * @return mixed
     */
    function denormalize($data, $type, $format = null);

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
