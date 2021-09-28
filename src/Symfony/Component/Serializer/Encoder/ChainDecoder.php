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
 * Decoder delegating the decoding to a chain of decoders.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 *
 * @final
 */
class ChainDecoder implements ContextAwareDecoderInterface
{
    protected $decoders = [];
    protected $decoderByFormat = [];

    public function __construct(array $decoders = [])
    {
        $this->decoders = $decoders;
    }

    /**
     * {@inheritdoc}
     */
    final public function decode($data, $format, array $context = [])
    {
        return $this->getDecoder($format, $context)->decode($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format, array $context = []): bool
    {
        try {
            $this->getDecoder($format, $context);
        } catch (RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * Gets the decoder supporting the format.
     *
     * @throws RuntimeException if no decoder is found
     */
    private function getDecoder(string $format, array $context): DecoderInterface
    {
        if (isset($this->decoderByFormat[$format])
            && isset($this->decoders[$this->decoderByFormat[$format]])
        ) {
            return $this->decoders[$this->decoderByFormat[$format]];
        }

        $cache = true;
        foreach ($this->decoders as $i => $decoder) {
            $cache = $cache && !$decoder instanceof ContextAwareDecoderInterface;
            if ($decoder->supportsDecoding($format, $context)) {
                if ($cache) {
                    $this->decoderByFormat[$format] = $i;
                }

                return $decoder;
            }
        }

        throw new RuntimeException(sprintf('No decoder found for format "%s".', $format));
    }
}
