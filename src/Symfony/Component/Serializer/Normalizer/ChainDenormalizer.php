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
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This holds a collection of denormalizers. It tries to be smart how it selects
 * the correct Denormalizer.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ChainDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    private const SCALAR_TYPES = [
        'int' => true,
        'bool' => true,
        'float' => true,
        'string' => true,
    ];

    /**
     * @deprecated since Symfony 7.2
     */
    private ?SerializerInterface $serializer = null;

    /**
     * @var DenormalizerInterface[]
     */
    private array $denormalizers = [];

    /**
     * @var array<string, array<string, array<bool>>>
     */
    private array $denormalizerCache = [];

    /**
     * @var array<string, array<class-string|'*'|'object'|string, bool|null>>
     */
    private array $supportedCache = [];

    /**
     * @param DenormalizerInterface[] $denormalizers
     */
    public function __construct(array $denormalizers = [])
    {
        foreach ($denormalizers as $denormalizer) {
            $this->addDenormalizer($denormalizer);
        }
    }

    private function addDenormalizer(DenormalizerInterface $denormalizer): void
    {
        if ($denormalizer instanceof DenormalizerAwareInterface) {
            $denormalizer->setDenormalizer($this);
        }

        if (null !== $this->serializer && $denormalizer instanceof SerializerAwareInterface) {
            $denormalizer->setSerializer($this->serializer);
        }

        $this->denormalizers[] = $denormalizer;
        $this->denormalizerCache = [];
        $this->supportedCache = [];
    }

    /**
     * @throws NotNormalizableValueException
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (isset($context[DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS], $context['not_normalizable_value_exceptions'])) {
            throw new LogicException('Passing a value for "not_normalizable_value_exceptions" context key is not allowed.');
        }

        $denormalizer = $this->getDenormalizer($data, $type, $format, $context);

        // Check for a denormalizer first, e.g. the data is wrapped
        if (!$denormalizer && isset(self::SCALAR_TYPES[$type])) {
            if (!('is_'.$type)($data)) {
                throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data expected to be of type "%s" ("%s" given).', $type, get_debug_type($data)), $data, [$type], $context['deserialization_path'] ?? null, true);
            }

            return $data;
        }

        if ([] === $this->denormalizers) {
            throw new LogicException('You must register at least one normalizer to be able to denormalize objects.');
        }

        if (!$denormalizer) {
            throw new NotNormalizableValueException(sprintf('Could not denormalize object of type "%s", no supporting normalizer found.', $type));
        }

        return $denormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * Returns a matching denormalizer.
     *
     * @param mixed       $data    Data to restore
     * @param string      $class   The expected class to instantiate or type to convert to
     * @param string|null $format  Format name, present to give the option to normalizers to act differently based on formats
     * @param array       $context Options available to the denormalizer
     */
    private function getDenormalizer(mixed $data, string $class, ?string $format, array $context): ?DenormalizerInterface
    {
        if (!isset($this->denormalizerCache[$format][$class])) {
            $this->denormalizerCache[$format][$class] = [];
            $genericType = class_exists($class) || interface_exists($class, false) ? 'object' : '*';

            foreach ($this->denormalizers as $k => $denormalizer) {
                $supportedTypes = $denormalizer->getSupportedTypes($format);

                $doesClassRepresentCollection = str_ends_with($class, '[]');

                foreach ($supportedTypes as $supportedType => $isCacheable) {
                    if (\in_array($supportedType, ['*', 'object'], true)
                        || $class !== $supportedType && ('object' !== $genericType || !is_subclass_of($class, $supportedType))
                        && !($doesClassRepresentCollection && str_ends_with($supportedType, '[]') && is_subclass_of(strstr($class, '[]', true), strstr($supportedType, '[]', true)))
                    ) {
                        continue;
                    }

                    if (null === $isCacheable) {
                        unset($supportedTypes['*'], $supportedTypes['object']);
                    } elseif ($this->denormalizerCache[$format][$class][$k] = $isCacheable && $denormalizer->supportsDenormalization(null, $class, $format, $context)) {
                        break 2;
                    }

                    break;
                }

                if (null === $isCacheable = $supportedTypes[\array_key_exists($genericType, $supportedTypes) ? $genericType : '*'] ?? null) {
                    continue;
                }

                if ($this->denormalizerCache[$format][$class][$k] ??= $isCacheable && $denormalizer->supportsDenormalization(null, $class, $format, $context)) {
                    break;
                }
            }
        }

        foreach ($this->denormalizerCache[$format][$class] as $k => $cached) {
            $denormalizer = $this->denormalizers[$k];
            if ($cached || $denormalizer->supportsDenormalization($data, $class, $format, $context)) {
                return $denormalizer;
            }
        }

        return null;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return isset(self::SCALAR_TYPES[$type]) || null !== $this->getDenormalizer($data, $type, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        if (null === $format) {
            $format = '__null__';
        }

        if (!isset($this->supportedCache[$format])) {
            foreach ($this->denormalizers as $denormalizer) {
                foreach($denormalizer->getSupportedTypes($format) as $type => $supported) {
                    $this->supportedCache[$format][$type] = $supported || ($this->supportedCache[$format][$type] ?? false);
                }
            }
        }

        return $this->supportedCache[$format] ?? [];
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
        foreach ($this->denormalizers as $denormalizer) {
            if ($denormalizer instanceof SerializerAwareInterface) {
                $denormalizer->setSerializer($serializer);
            }
        }
    }
}
