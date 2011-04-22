<?php

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\SerializerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Defines the interface of serializers
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface NormalizerInterface
{
    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param object $object object to normalize
     * @param string $format format the normalization result will be encoded as
     * @param array $properties a list of properties to extract, if null all properties are returned
     * @return array|scalar
     * @api
     */
    function normalize($object, $format, $properties = null);

    /**
     * Denormalizes data back into an object of the given class
     *
     * @param mixed $data data to restore
     * @param string $class the expected class to instantiate
     * @param string $format format the given data was extracted from
     * @return object
     * @api
     */
    function denormalize($data, $class, $format = null);

    /**
     * Checks whether the given class is supported by this normalizer
     *
     * @param ReflectionClass $class
     * @param string $format format the given data was extracted from
     * @return Boolean
     * @api
     */
    function supports(\ReflectionClass $class, $format = null);

    /**
     * Sets the owning Serializer object
     *
     * @param SerializerInterface $serializer
     * @api
     */
    function setSerializer(SerializerInterface $serializer);

    /**
     * Gets the owning Serializer object
     *
     * @return SerializerInterface
     * @api
     */
    function getSerializer();
}
