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
    const FORMAT = 'json';
    
    /**
     * @var JsonEncode
     */
    protected $encodingImpl;
    /**
     * @var JsonDecode
     */
    protected $decodingImpl;

    public function __construct(JsonEncode $encodingImpl = null, JsonDecode $decodingImpl = null)
    {
        if (null === $encodingImpl) {
            $encodingImpl = new JsonEncode;
        }
        $this->encodingImpl = $encodingImpl;
        if (null === $decodingImpl) {
            $decodingImpl = new JsonDecode(true);
        }
        $this->decodingImpl = $decodingImpl;
    }
    
    /**
     * Returns the last encoding error (if any)
     * 
     * @return int 
     */
    public function getLastEncodingError()
    {
        return $this->encodingImpl->getLastError();
    }
    
    /**
     * Returns the last decoding error (if any)
     * 
     * @return int 
     */
    public function getLastDecodingError()
    {
        return $this->decodingImpl->getLastError();
    }
    
    /**
     * {@inheritdoc}
     */
    public function encode($data, $format)
    {
        return $this->encodingImpl->encode($data, self::FORMAT);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format)
    {
        return $this->decodingImpl->decode($data, self::FORMAT);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        return self::FORMAT === $format;
    }
}