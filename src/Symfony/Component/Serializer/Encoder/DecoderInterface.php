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
     * @param array  $context options that decoders have access to.
     *
     * @return mixed
     */
    public function decode($data, $format, array $context = array());

    /**
     * Checks whether the serializer can decode from given format
     *
     * @param string $format format name
     *
     * @return Boolean
     */
    public function supportsDecoding($format);
}
