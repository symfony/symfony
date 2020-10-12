<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
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
    public function encode($data, $format, array $context = []);

    /**
     * Checks whether the serializer can encode to given format.
     *
     * @param string $format Format name
     *
     * @return bool
     */
    public function supportsEncoding($format);
}
