<?php

namespace Symfony\Component\Serializer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
     */
    function serialize($data, $format);

    /**
     * Normalizes any data into a set of arrays/scalars
     *
     * @param mixed $data data to normalize
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return array|scalar
     */
    function normalize($data, $format);

    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param object $object object to normalize
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @param array $properties a list of properties to extract, if null all properties are returned
     * @return array|scalar
     */
    function normalizeObject($object, $format, $properties = null);

    /**
     * Denormalizes data back into an object of the given class
     *
     * @param mixed $data data to restore
     * @param string $class the expected class to instantiate
     * @param string $format format name, present to give the option to normalizers to act differently based on formats
     * @return object
     */
    function denormalizeObject($data, $class, $format = null);

    /**
     * Encodes data into the given format
     *
     * @param mixed $data data to encode
     * @param string $format format name
     * @return array|scalar
     */
    function encode($data, $format);

    /**
     * Decodes a string from the given format back into PHP data
     *
     * @param string $data data to decode
     * @param string $format format name
     * @return mixed
     */
    function decode($data, $format);
}
