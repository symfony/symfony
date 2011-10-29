<?php

namespace Symfony\Component\Serializer\Normalizer;


/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Defines the interface of normalizers.
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
     * @return array|scalar
     */
    function normalize($object, $format = null);

    /**
     * Denormalizes data back into an object of the given class
     *
     * @param mixed $data data to restore
     * @param string $class the expected class to instantiate
     * @param string $format format the given data was extracted from
     * @return object
     */
    function denormalize($data, $class, $format = null);

    /**
     * Checks whether the given class is supported for normalization by this normalizer
     *
     * @param mixed   $data   Data to normalize.
     * @param string  $format The format being (de-)serialized from or into.
     * @return Boolean
     */
    function supportsNormalization($data, $format = null);

    /**
     * Checks whether the given class is supported for denormalization by this normalizer
     *
     * @param mixed   $data   Data to denormalize from.
     * @param string  $type   The class to which the data should be denormalized.
     * @param string  $format The format being deserialized from.
     * @return Boolean
     */
    function supportsDenormalization($data, $type, $format = null);
}
