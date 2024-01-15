<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ConstructorWithDefaultValue;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Exception\DenormalizingUnionFailedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_ConstructorWithDefaultValue implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = null;
    private null|DenormalizerInterface $denormalizer = null;
    public function getSupportedTypes(?string $format): array
    {
        return [ConstructorWithDefaultValue::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof ConstructorWithDefaultValue;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === ConstructorWithDefaultValue::class;
    }
    /**
    * @param ConstructorWithDefaultValue $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['foo' => $object->getFoo(), 'union' => $this->normalizeChild($object->getUnion(), $format, $context, false)];
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
        if (!array_key_exists('union', $data)) {
            $argument1 = null;
        } else {
            $exceptions = [];
            $argument1HasValue = false;
            foreach ([\Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject::class, \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class] as $class) {
                try {
                    $argument1 = $this->denormalizeChild($data['union'], $class, $format, $context, false);
                    $argument1HasValue = true;
                    break;
                } catch (\Throwable $e) {
                    $exceptions[] = $e;
                }
            }
            if (!$argument1HasValue) {
                throw new DenormalizingUnionFailedException('Failed to denormalize key "union" of class "Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ConstructorWithDefaultValue".', $exceptions);
            }
        }
        $output = new ConstructorWithDefaultValue($data['foo'] ?? 4711, $argument1, $data['x'] ?? new \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject());
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