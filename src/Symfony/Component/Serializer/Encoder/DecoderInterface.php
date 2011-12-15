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
 * Defines the interface of decoders
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface DecoderInterface
{
    /**
     * Decodes a string into PHP data
     *
     * @param scalar $data   Data to decode
     * @param string $format Format name
     *
     * @return mixed
     */
    function decode($data, $format);

    /**
     * Checks whether the serializer can decode from given format
     *
     * @param string $format format name
     * @return Boolean
     */
    function supportsDecoding($format);
}
