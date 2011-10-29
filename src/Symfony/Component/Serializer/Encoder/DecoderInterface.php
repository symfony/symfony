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
 * Defines the interface of encoders that are able to decode their own format
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface DecoderInterface
{
    /**
     * Decodes a string into PHP data
     *
     * @param string $data data to decode
     * @param string $format format to decode from
     * @return mixed
     */
    function decode($data, $format);
}
