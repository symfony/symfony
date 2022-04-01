<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Debug;

use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Collects some data about serialization.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @final
 * @internal
 */
class TraceableSerializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface, EncoderInterface, DecoderInterface
{
    public const DEBUG_TRACE_ID = 'debug_trace_id';

    public function __construct(
        private SerializerInterface&NormalizerInterface&DenormalizerInterface&EncoderInterface&DecoderInterface $serializer,
        private SerializerDataCollector $dataCollector,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    final public function serialize(mixed $data, string $format, array $context = []): string
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->serialize($data, $format, $context);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectSerialize($traceId, $data, $format, $context, $time);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    final public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->deserialize($data, $type, $format, $context);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectDeserialize($traceId, $data, $type, $format, $context, $time);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    final public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->normalize($object, $format, $context);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectNormalize($traceId, $object, $format, $context, $time);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    final public function denormalize(mixed $data, string $type, string $format = null, array $context = []): mixed
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->denormalize($data, $type, $format, $context);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectDenormalize($traceId, $data, $type, $format, $context, $time);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    final public function encode(mixed $data, string $format, array $context = []): string
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->encode($data, $format, $context);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectEncode($traceId, $data, $format, $context, $time);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    final public function decode(string $data, string $format, array $context = []): mixed
    {
        $context[self::DEBUG_TRACE_ID] = $traceId = uniqid();

        $startTime = microtime(true);
        $result = $this->serializer->decode($data, $format, $context);
        $time = microtime(true) - $startTime;

        $this->dataCollector->collectDecode($traceId, $data, $format, $context, $time);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context
     */
    final public function supportsNormalization(mixed $data, string $format = null /*, array $context = [] */): bool
    {
        $context = \func_num_args() > 2 ? \func_get_arg(2) : [];

        return $this->serializer->supportsNormalization($data, $format, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context
     */
    final public function supportsDenormalization(mixed $data, string $type, string $format = null /*, array $context = [] */): bool
    {
        $context = \func_num_args() > 3 ? \func_get_arg(3) : [];

        return $this->serializer->supportsDenormalization($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context
     */
    final public function supportsEncoding(string $format /*, array $context = [] */): bool
    {
        $context = \func_num_args() > 1 ? \func_get_arg(1) : [];

        return $this->serializer->supportsEncoding($format, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $context
     */
    final public function supportsDecoding(string $format /*, array $context = [] */): bool
    {
        $context = \func_num_args() > 1 ? \func_get_arg(1) : [];

        return $this->serializer->supportsDecoding($format, $context);
    }
}
