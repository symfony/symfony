<?php

namespace Symfony\Component\Serializer\Encoder;


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
     * @param mixed $data    Data to encode
     * @param string $format Format to encode to
     *
     * @return string
     */
    function encode($data, $format);
}
