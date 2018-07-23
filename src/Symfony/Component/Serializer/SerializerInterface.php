<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer;

/**
 * Defines the interface of the Serializer.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SerializerInterface
{
    /**
     * Serializes data in the appropriate format.
     *
     * @param mixed  $data    Any data
     * @param string $format  Format name
     * @param array  $context Options normalizers/encoders have access to
     *
     * @return string
     */
    public function serialize($data, $format, array $context = array());

    /**
     * Deserializes data into the given type.
     *
     * @param mixed  $data
     * @param string $type
     * @param string $format
     * @param array  $context
     *
     * @return object
     */
    public function deserialize($data, $type, $format, array $context = array());
}
