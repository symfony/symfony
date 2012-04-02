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
 * Encodes JSON data
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class JsonEncoder implements EncoderInterface, DecoderInterface
{
    /**
     * {@inheritdoc}
     */
    public function encode($data, $format)
    {
        return json_encode($data);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format)
    {
        return json_decode($data, true);
    }

    /**
     * Checks whether the serializer can encode to given format
     *
     * @param string $format format name
     * @return Boolean
     */
    public function supportsEncoding($format)
    {
        return 'json' === $format;
    }

    /**
     * Checks whether the serializer can decode from given format
     *
     * @param string $format format name
     * @return Boolean
     */
    public function supportsDecoding($format)
    {
        return 'json' === $format;
    }
}
