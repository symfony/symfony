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

use BadMethodCallException;
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\TraceableSerializer;

/**
 * Collects some data about encoding.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class TraceableEncoder implements EncoderInterface, DecoderInterface, SerializerAwareInterface
{
    private EncoderInterface|DecoderInterface $encoder;
    private SerializerDataCollector $dataCollector;

    public function __construct(
        EncoderInterface|DecoderInterface $encoder,
        SerializerDataCollector $dataCollector,
    ) {
        $this->encoder = $encoder;
        $this->dataCollector = $dataCollector;
    }

    /**
     * {@inheritDoc}
     */
    public function encode(mixed $data, string $format, array $context = []): string
    {
        if (!$this->encoder instanceof EncoderInterface) {
            throw new BadMethodCallException(sprintf('%s() cannot be called as nested encoder doesn\'t implements "%s"', __METHOD__, EncoderInterface::class));
        }

        $startTime = microtime(true);
        $encoded = $this->encoder->encode($data, $format, $context);
        $time = microtime(true) - $startTime;

        if ($traceId = ($context[TraceableSerializer::DEBUG_TRACE_ID] ?? null)) {
            $this->dataCollector->collectEncoding($traceId, \get_class($this->encoder), $time);
        }

        return $encoded;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsEncoding(string $format): bool
    {
        if (!$this->encoder instanceof EncoderInterface) {
            return false;
        }

        return $this->encoder->supportsEncoding($format);
    }

    /**
     * {@inheritDoc}
     */
    public function decode(string $data, string $format, array $context = [])
    {
        if (!$this->encoder instanceof DecoderInterface) {
            throw new BadMethodCallException(sprintf('%s() cannot be called as nested encoder doesn\'t implements "%s"', __METHOD__, DecoderInterface::class));
        }

        $startTime = microtime(true);
        $encoded = $this->encoder->decode($data, $format, $context);
        $time = microtime(true) - $startTime;

        if ($traceId = ($context[TraceableSerializer::DEBUG_TRACE_ID] ?? null)) {
            $this->dataCollector->collectDecoding($traceId, \get_class($this->encoder), $time);
        }

        return $encoded;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsDecoding(string $format)
    {
        if (!$this->encoder instanceof DecoderInterface) {
            return false;
        }

        return $this->encoder->supportsDecoding($format);
    }

    /**
     * {@inheritDoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if (!$this->encoder instanceof SerializerAwareInterface) {
            return;
        }

        $this->encoder->setSerializer($serializer);
    }

    public function needsNormalization(): bool
    {
        return $this->encoder instanceof NormalizerAwareInterface;
    }
}

