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
 *
 * @since v2.2.0
 */
class JsonEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'json';

    /**
     * @var JsonEncode
     */
    protected $encodingImpl;

    /**
     * @var JsonDecode
     */
    protected $decodingImpl;

    /**
     * @since v2.1.0
     */
    public function __construct(JsonEncode $encodingImpl = null, JsonDecode $decodingImpl = null)
    {
        $this->encodingImpl = null === $encodingImpl ? new JsonEncode() : $encodingImpl;
        $this->decodingImpl = null === $decodingImpl ? new JsonDecode(true) : $decodingImpl;
    }

    /**
     * Returns the last encoding error (if any)
     *
     * @return integer
     *
     * @since v2.1.0
     */
    public function getLastEncodingError()
    {
        return $this->encodingImpl->getLastError();
    }

    /**
     * Returns the last decoding error (if any)
     *
     * @return integer
     *
     * @since v2.1.0
     */
    public function getLastDecodingError()
    {
        return $this->decodingImpl->getLastError();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function encode($data, $format, array $context = array())
    {
        return $this->encodingImpl->encode($data, self::FORMAT, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.2.0
     */
    public function decode($data, $format, array $context = array())
    {
        return $this->decodingImpl->decode($data, self::FORMAT, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function supportsEncoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function supportsDecoding($format)
    {
        return self::FORMAT === $format;
    }
}
