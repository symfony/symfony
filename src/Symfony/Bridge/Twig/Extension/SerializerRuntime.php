<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * @author Jesse Rushlow <jr@rushlow.dev>
 */
final class SerializerRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private SerializerInterface|NormalizerInterface $serializer,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$this->serializer instanceof NormalizerInterface) {
            throw new \LogicException(\sprintf('The "normalize" filter requires a serializer implementing "%s", but "%s" does not.', NormalizerInterface::class, get_debug_type($this->serializer)));
        }

        return $this->serializer->normalize($data, $format, $context);
    }

    public function serialize(mixed $data, string $format = 'json', array $context = []): string
    {
        if (!$this->serializer instanceof SerializerInterface) {
            throw new \LogicException(\sprintf('The "serialize" filter requires a serializer implementing "%s", but "%s" does not.', SerializerInterface::class, get_debug_type($this->serializer)));
        }

        return $this->serializer->serialize($data, $format, $context);
    }
}
