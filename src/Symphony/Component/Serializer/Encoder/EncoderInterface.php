<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Encoder;

use Symphony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Defines the interface of encoders.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface EncoderInterface
{
    /**
     * Encodes data into the given format.
     *
     * @param mixed  $data    Data to encode
     * @param string $format  Format name
     * @param array  $context Options that normalizers/encoders have access to
     *
     * @return string|int|float|bool
     *
     * @throws UnexpectedValueException
     */
    public function encode($data, $format, array $context = array());

    /**
     * Checks whether the serializer can encode to given format.
     *
     * @param string $format Format name
     *
     * @return bool
     */
    public function supportsEncoding($format);
}
