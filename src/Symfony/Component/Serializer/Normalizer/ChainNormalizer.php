<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This holds a collection of normalizers. It tries to be smart how it selects
 * the correct Normalizer.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ChainNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    /**
     * @deprecated since Symfony 7.2
     */
    private ?SerializerInterface $serializer = null;

    /**
     * @var NormalizerInterface[]
     */
    private array $normalizers = [];

    /**
     * @var array<string, array<string, array<bool>>>
     */
    private array $normalizerCache = [];

    /**
     * @var array<string, array<class-string|'*'|'object'|string, bool|null>>
     */
    private array $supportedCache = [];

    /**
     * @param NormalizerInterface[] $normalizers
     */
    public function __construct(array $normalizers = [])
    {
        foreach ($normalizers as $normalizer) {
            $this->addNormalizer($normalizer);
        }
    }

    /**
     * Add Normalizer last in the line.
     */
    public function addNormalizer(NormalizerInterface $normalizer): void
    {
        if ($normalizer instanceof NormalizerAwareInterface) {
            $normalizer->setNormalizer($this);
        }
        if (null !== $this->serializer && $normalizer instanceof SerializerAwareInterface) {
            $normalizer->setSerializer($this->serializer);
        }

        $this->normalizers[] = $normalizer;
        $this->normalizerCache = [];
        $this->supportedCache = [];
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        // If a normalizer supports the given data, use it
        if ($normalizer = $this->getNormalizer($object, $format, $context)) {
            return $normalizer->normalize($object, $format, $context);
        }

        if (null === $object || \is_scalar($object)) {
            return $object;
        }

        if (\is_array($object) && !$object && ($context[Serializer::EMPTY_ARRAY_AS_OBJECT] ?? false)) {
            return new \ArrayObject();
        }

        if (is_iterable($object)) {
            if ($object instanceof \Countable && ($context[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS] ?? false) && !\count($object)) {
                return new \ArrayObject();
            }

            $normalized = [];
            foreach ($object as $key => $val) {
                $normalized[$key] = $this->normalize($val, $format, $context);
            }

            return $normalized;
        }

        if (\is_object($object)) {
            if ([] === $this->normalizers) {
                throw new LogicException('You must register at least one normalizer to be able to normalize objects.');
            }

            throw new NotNormalizableValueException(sprintf('Could not normalize object of type "%s", no supporting normalizer found.', get_debug_type($object)));
        }

        throw new NotNormalizableValueException('An unexpected value could not be normalized: '.(!\is_resource($object) ? var_export($object, true) : sprintf('"%s" resource', get_resource_type($object))));
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return null !== $this->getNormalizer($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        if (null === $format) {
            $format = '__null__';
        }

        if (!isset($this->supportedCache[$format])) {
            foreach ($this->normalizers as $normalizer) {
                $this->supportedCache + $normalizer->getSupportedTypes($format);
            }
        }

        return $this->supportedCache[$format];
    }

    /**
     * Returns a matching normalizer.
     *
     * @param mixed       $data    Data to get the serializer for
     * @param string|null $format  Format name, present to give the option to normalizers to act differently based on formats
     * @param array       $context Options available to the normalizer
     */
    private function getNormalizer(mixed $data, ?string $format, array $context): ?NormalizerInterface
    {
        if (\is_object($data)) {
            $type = $data::class;
            $genericType = 'object';
        } else {
            $type = 'native-'.\gettype($data);
            $genericType = '*';
        }

        if (!isset($this->normalizerCache[$format][$type])) {
            $this->normalizerCache[$format][$type] = [];

            foreach ($this->normalizers as $k => $normalizer) {
                if (!$normalizer instanceof NormalizerInterface) {
                    continue;
                }

                $supportedTypes = $normalizer->getSupportedTypes($format);

                foreach ($supportedTypes as $supportedType => $isCacheable) {
                    if (\in_array($supportedType, ['*', 'object'], true)
                        || $type !== $supportedType && ('object' !== $genericType || !is_subclass_of($type, $supportedType))
                    ) {
                        continue;
                    }

                    if (null === $isCacheable) {
                        unset($supportedTypes['*'], $supportedTypes['object']);
                    } elseif ($this->normalizerCache[$format][$type][$k] = $isCacheable && $normalizer->supportsNormalization($data, $format, $context)) {
                        break 2;
                    }

                    break;
                }

                if (null === $isCacheable = $supportedTypes[\array_key_exists($genericType, $supportedTypes) ? $genericType : '*'] ?? null) {
                    continue;
                }

                if ($this->normalizerCache[$format][$type][$k] ??= $isCacheable && $normalizer->supportsNormalization($data, $format, $context)) {
                    break;
                }
            }
        }

        foreach ($this->normalizerCache[$format][$type] as $k => $cached) {
            $normalizer = $this->normalizers[$k];
            if ($cached || $normalizer->supportsNormalization($data, $format, $context)) {
                return $normalizer;
            }
        }

        return null;
    }

    /**
     * This method exists only for BC reasons. Will be removed in 8.0.
     *
     * @internal
     *
     * @deprecated
     */
    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof SerializerAwareInterface) {
                $normalizer->setSerializer($serializer);
            }
        }
    }
}
