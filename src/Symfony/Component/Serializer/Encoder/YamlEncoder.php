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

class YamlEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'yaml';

    /**
     * @var \QEResolv\Serializer\Encoder\Decode
     */
    protected $decodeImpl;
    
    /**
     * @var \QEResolv\Serializer\Encoder\Decode
     */
    protected $encodeImpl;

    /**
     * Constructs a new Yaml Encoder instance.
     *
     * @param \Symfony\Serializer\Encoder\YamlEncode $encoder
     * @param \Symfony\Serializer\Encoder\YamlDecode $decoder
     */
    public function __construct(YamlEncode $encodeImpl = null, YamlDecode $decodeImpl = null)
    {
        $this->encodeImpl = $encodeImpl ?: new YamlEncode();
        $this->decodeImpl = $decodeImpl ?: new YamlDecode();
    }

    /**
     * {@inheritDoc}
     */
    public function encode($data, $format, array $context = array())
    {
        return $this->encodeImpl->encode($data, self::FORMAT, $context);
    }
    
    /**
     * {@inheritDoc}
     */
    public function decode($data, $format, array $context = array())
    {
        return $this->decodeImpl->decode($data, self::FORMAT, $context);
    }

    /**
     * {@inheritDoc}
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
