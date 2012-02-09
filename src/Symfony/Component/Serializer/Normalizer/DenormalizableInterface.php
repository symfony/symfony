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
 * Defines the most basic interface a class must implement to be denormalizable
 *
 * If a denormalizer is registered for the class and it doesn't implement
 * the Denormalizable interfaces, the normalizer will be used instead
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface DenormalizableInterface
{
    /**
     * Denormalizes the object back from an array of scalars|arrays.
     *
     * It is important to understand that the denormalize() call should denormalize
     * recursively all child objects of the implementor.
     *
     * @param DenormalizerInterface $denormalizer The denormalizer is given so that you
     *   can use it to denormalize objects contained within this object.
     * @param array|scalar $data The data from which to re-create the object.
     * @param string|null $format The format is optionally given to be able to denormalize differently
     *   based on different input formats.
     */
    function denormalize(DenormalizerInterface $denormalizer, $data, $format = null);
}
