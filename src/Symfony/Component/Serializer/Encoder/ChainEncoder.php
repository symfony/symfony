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

use Symfony\Component\Serializer\Exception\RuntimeException;

/**
 * Encoder delegating the decoding to a chain of encoders.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * @final since version 3.3.
 */
class ChainEncoder implements EncoderInterface /*, ContextAwareEncoderInterface*/
{
    protected $encoders = [];
    protected $encoderByFormat = [];

    public function __construct(array $encoders = [])
    {
        $this->encoders = $encoders;
    }

    /**
     * {@inheritdoc}
     */
    final public function encode($data, $format, array $context = [])
    {
        return $this->getEncoder($format, $context)->encode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format/*, array $context = []*/)
    {
        $context = \func_num_args() > 1 ? func_get_arg(1) : [];

        try {
            $this->getEncoder($format, $context);
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the normalization is needed for the given format.
     *
     * @param string $format
     *
     * @return bool
     */
    public function needsNormalization($format/*, array $context = []*/)
    {
        $context = \func_num_args() > 1 ? func_get_arg(1) : [];
        $encoder = $this->getEncoder($format, $context);

        if (!$encoder instanceof NormalizationAwareInterface) {
            return true;
        }

        if ($encoder instanceof self) {
            return $encoder->needsNormalization($format, $context);
        }

        return false;
    }

    /**
     * Gets the encoder supporting the format.
     *
     * @param string $format
     *
     * @return EncoderInterface
     *
     * @throws RuntimeException if no encoder is found
     */
    private function getEncoder($format, array $context)
    {
        if (isset($this->encoderByFormat[$format])
            && isset($this->encoders[$this->encoderByFormat[$format]])
        ) {
            return $this->encoders[$this->encoderByFormat[$format]];
        }

        foreach ($this->encoders as $i => $encoder) {
            if ($encoder->supportsEncoding($format, $context)) {
                $this->encoderByFormat[$format] = $i;

                return $encoder;
            }
        }

        throw new RuntimeException(sprintf('No encoder found for format "%s".', $format));
    }
}
