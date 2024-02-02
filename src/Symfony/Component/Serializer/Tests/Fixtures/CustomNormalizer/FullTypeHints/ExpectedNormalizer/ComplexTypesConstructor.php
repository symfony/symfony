<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ComplexTypesConstructor;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Exception\DenormalizingUnionFailedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_ComplexTypesConstructor implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = null;
    private null|DenormalizerInterface $denormalizer = null;
    public function getSupportedTypes(?string $format): array
    {
        return [ComplexTypesConstructor::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof ComplexTypesConstructor;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === ComplexTypesConstructor::class;
    }
    /**
    * @param ComplexTypesConstructor $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['simple' => $this->normalizeChild($object->getSimple(), $format, $context, false), 'simpleArray' => $object->getSimpleArray(), 'array' => $this->normalizeChild($object->getArray(), $format, $context, true), 'union' => $this->normalizeChild($object->getUnion(), $format, $context, false), 'nested' => $this->normalizeChild($object->getNested(), $format, $context, false), 'unionArray' => $this->normalizeChild($object->getUnionArray(), $format, $context, true)];
    }
    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }
    private function normalizeChild(mixed $object, ?string $format, array $context, bool $canBeIterable): mixed
    {
        if (is_scalar($object) || null === $object) {
            return $object;
        }
        if ($canBeIterable && is_iterable($object)) {
            return array_map(fn($item) => $this->normalizeChild($item, $format, $context, true), $object);
        }
        return $this->normalizer->normalize($object, $format, $context);
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $data = (array) $data;
        $argument0 = $this->denormalizeChild($data['simple'], \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class, $format, $context, false);
        $argument2 = $this->denormalizeChild($data['array'], \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class, $format, $context, true);
        $exceptions = [];
        $argument3HasValue = false;
        foreach ([\Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject::class, \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class] as $class) {
            try {
                $argument3 = $this->denormalizeChild($data['union'], $class, $format, $context, false);
                $argument3HasValue = true;
                break;
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }
        if (!$argument3HasValue) {
            throw new DenormalizingUnionFailedException('Failed to denormalize key "union" of class "Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ComplexTypesConstructor".', $exceptions);
        }
        $exceptions = [];
        $argument4HasValue = false;
        foreach ([\Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class, \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject::class] as $class) {
            try {
                $argument4 = $this->denormalizeChild($data['nested'], $class, $format, $context, false);
                $argument4HasValue = true;
                break;
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }
        if (!$argument4HasValue) {
            throw new DenormalizingUnionFailedException('Failed to denormalize key "nested" of class "Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ComplexTypesConstructor".', $exceptions);
        }
        $exceptions = [];
        $argument5HasValue = false;
        foreach ([\Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class, \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject::class] as $class) {
            try {
                $argument5 = $this->denormalizeChild($data['unionArray'], $class, $format, $context, true);
                $argument5HasValue = true;
                break;
            } catch (\Throwable $e) {
                $exceptions[] = $e;
            }
        }
        if (!$argument5HasValue) {
            throw new DenormalizingUnionFailedException('Failed to denormalize key "unionArray" of class "Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ComplexTypesConstructor".', $exceptions);
        }
        $output = new ComplexTypesConstructor($argument0, $data['simpleArray'], $argument2, $argument3, $argument4, $argument5);
        return $output;
    }
    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->denormalizer = $denormalizer;
    }
    private function denormalizeChild(mixed $data, string $type, ?string $format, array $context, bool $canBeIterable): mixed
    {
        if (is_scalar($data) || null === $data) {
            return $data;
        }
        if ($canBeIterable && is_iterable($data)) {
            return array_map(fn($item) => $this->denormalizeChild($item, $type, $format, $context, true), $data);
        }
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}