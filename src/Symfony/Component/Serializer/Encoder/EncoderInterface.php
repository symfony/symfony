<?php

namespace Symfony\Component\Serializer\Encoder;

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
 * Defines the interface of encoders
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface EncoderInterface
{
    /**
     * Encodes data into a string
     *
     * @param mixed $data data to encode
     * @param string $format format to encode to
     * @return string
     * @api
     */
    function encode($data, $format);

    /**
     * Decodes a string into PHP data
     *
     * @param string $data data to decode
     * @param string $format format to decode from
     * @return mixed
     * @api
     */
    function decode($data, $format);

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
