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
    private null|NormalizerInterface $normalizer = NULL;
    private null|DenormalizerInterface $denormalizer = NULL;

    public function getSupportedTypes(?string $format): array
    {
        return [ConstructorWithDefaultValue::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = NULL, array $context = []): bool
    {
        return $data instanceof ConstructorWithDefaultValue;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = NULL, array $context = []): bool
    {
        return $type === ConstructorWithDefaultValue::class;
    }

    /**
     * @param ConstructorWithDefaultValue $object
     */
    public function normalize(mixed $object, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return [
            'foo' => $object->getFoo(),
            'union' => $this->normalizeChild($object->getUnion(), $format, $context, false),
        ];
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
        
        if ($canBeIterable === true && is_iterable($object)) {
            return array_map(fn($item) => $this->normalizeChild($item, $format, $context, true), $object);
        }
        
        return $this->normalizer->normalize($object, $format, $context);
        
    }

    public function denormalize(mixed $data, string $type, ?string $format = NULL, array $context = []): mixed
    {
        if (!array_key_exists('union', $data)) {
            $argument1 = NULL;
        } else {
            $exceptions = [];
        $argument1HasValue = false;
        foreach (array (  0 => 'Symfony\\Component\\Serializer\\Tests\\Fixtures\\CustomNormalizer\\FullTypeHints\\SmartObject',  1 => 'Symfony\\Component\\Serializer\\Tests\\Fixtures\\CustomNormalizer\\FullTypeHints\\DummyObject',) as $class) {
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
        $output = new ConstructorWithDefaultValue(
            $data['foo'] ?? 4711,
            $argument1,
            $data['x'] ?? \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SmartObject::__set_state(array(
        )),
        );
        
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
        
        if ($canBeIterable === true && is_iterable($data)) {
            return array_map(fn($item) => $this->denormalizeChild($item, $type, $format, $context, true), $data);
        }
        
        return $this->denormalizer->denormalize($data, $type, $format, $context);
        
    }

}
