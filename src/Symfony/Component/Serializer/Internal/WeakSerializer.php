<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Internal;

use Symfony\Component\Serializer\Encoder\ContextAwareDecoderInterface;
use Symfony\Component\Serializer\Encoder\ContextAwareEncoderInterface;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Forwards every call to a serializer, without preventing PHP from freeing it.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class WeakSerializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface, ContextAwareEncoderInterface, ContextAwareDecoderInterface
{
    /**
     * @var \WeakReference<Serializer>
     */
    private \WeakReference $serializer;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = \WeakReference::create($serializer);
    }

    public function serialize(mixed $data, string $format, array $context = []): string
    {
        return $this->getSerializer()->serialize($data, $format, $context);
    }

    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        return $this->getSerializer()->deserialize($data, $type, $format, $context);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $this->getSerializer()->normalize($data, $format, $context);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->getSerializer()->denormalize($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->getSerializer()->getSupportedTypes($format);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->getSerializer()->supportsNormalization($data, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->getSerializer()->supportsDenormalization($data, $type, $format, $context);
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        return $this->getSerializer()->encode($data, $format, $context);
    }

    public function decode(string $data, string $format, array $context = []): mixed
    {
        return $this->getSerializer()->decode($data, $format, $context);
    }

    public function supportsEncoding(string $format, array $context = []): bool
    {
        return $this->getSerializer()->supportsEncoding($format, $context);
    }

    public function supportsDecoding(string $format, array $context = []): bool
    {
        return $this->getSerializer()->supportsDecoding($format, $context);
    }

    private function getSerializer(): Serializer
    {
        return $this->serializer->get() ?? throw new LogicException('The serializer was released when the kernel shut down.');
    }
}
