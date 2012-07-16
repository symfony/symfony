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
 * Defines the interface of the Serializer
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface SerializerInterface
{
    /**
     * Serializes data in the appropriate format
     *
     * @param mixed  $data    any data
     * @param string $format  format name
     * @param array  $options options normalizers/encoders have access to
     *
     * @return string
     */
    function serialize($data, $format, array $options = array());

    /**
     * Deserializes data into the given type.
     *
     * @param mixed  $data
     * @param string $type
     * @param string $format
     * @param array  $options
     *
     * @return mixed
     */
    function deserialize($data, $type, $format, array $options = array());

    /**
     * Get current options of the serializer
     *
     * @return array
     */
    function getOptions();
}
