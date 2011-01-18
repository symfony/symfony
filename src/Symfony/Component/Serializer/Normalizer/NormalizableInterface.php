<?php

namespace Symfony\Component\Serializer\Normalizer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
    function normalize(NormalizerInterface $normalizer, $format, $properties = null);
    function denormalize(NormalizerInterface $normalizer, $data, $format = null);
}
