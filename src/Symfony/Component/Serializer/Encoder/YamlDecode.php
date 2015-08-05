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

use Symfony\Component\Yaml\Parser;

class YamlDecode implements DecoderInterface
{

    /**
     * @var \Symfony\Component\Yaml\Parser
     */
    protected $parser;

    /**
     * Constructs a new YamlDecode instance.
     *
     * @param \Symfony\Component\Yaml\Parser $parser
     */
    public function __construct(Parser $parser = null)
    {
        $this->parser = $parser ?: new Parser();
    }

    /**
     * {@inheritDoc}
     */
    public function decode($data, $format, array $context = array())
    {
        return $this->parser->parse($data);
    }


    /**
     * {@inheritDoc}
     */
    public function supportsDecoding($format)
    {
        return YamlEncoder::FORMAT === $format;
    }
}
