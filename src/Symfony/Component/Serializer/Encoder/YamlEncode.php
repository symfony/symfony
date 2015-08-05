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

use Symfony\Component\Yaml\Dumper;

class YamlEncode implements EncoderInterface
{

    /**
     * @var \Symfony\Component\Yaml\Dumper
     */
    protected $dumper;

    /**
     * Constructs a new YamlEncode instance.
     *
     * @param \Symfony\Component\Yaml\Dumper $dumper
     */
    public function __construct(Dumper $dumper = null)
    {
        $this->dumper = $dumper ?: new Dumper();
    }

    /**
     * {@inheritDoc}
     */
    public function encode($data, $format, array $context = array())
    {
        return $this->dumper->dump((array) $data);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsEncoding($format)
    {
        return YamlEncoder::FORMAT === $format;
    }
}
