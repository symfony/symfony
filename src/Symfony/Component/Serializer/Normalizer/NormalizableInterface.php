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
 * Defines the most basic interface a class must implement to be normalizable
 *
 * If a normalizer is registered for the class and it doesn't implement
 * the Normalizable interfaces, the normalizer will be used instead
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface NormalizableInterface
{
    /**
     * Normalizes the object into an array of scalars|arrays.
     *
     * It is important to understand that the normalize() call should normalize
     * recursively all child objects of the implementor.
     *
     * @param SerializerInterface $serializer The serializer is given so that you
     *   can use it to normalize objects contained within this object.
     * @param string|null $format The format is optionally given to be able to normalize differently
     *   based on different output formats.
     * @return array|scalar
     */
    public function normalize(SerializerInterface $serializer, $format = null);

    /**
     * Denormalizes the object back from an array of scalars|arrays.
     *
     * It is important to understand that the normalize() call should denormalize
     * recursively all child objects of the implementor.
     *
     * @param SerializerInterface $serializer The serializer is given so that you
     *   can use it to denormalize objects contained within this object.
     * @param array|scalar $data   The data from which to re-create the object.
     * @param string|null  $format The format is optionally given to be able to denormalize differently
     *   based on different input formats.
     */
    public function denormalize(SerializerInterface $serializer, $data, $format = null);
}
