<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\NonReadableProperty;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;

class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_NonReadableProperty implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = NULL;

    public function getSupportedTypes(?string $format): array
    {
        return [NonReadableProperty::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = NULL, array $context = []): bool
    {
        return $data instanceof NonReadableProperty;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = NULL, array $context = []): bool
    {
        return $type === NonReadableProperty::class;
    }

    /**
     * @param NonReadableProperty $object
     */
    public function normalize(mixed $object, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return [
            'name' => $object->getName(),
            'funnyName' => $this->normalizeChild($object->getFunnyName(), $format, $context, true),
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
        
        $output = new NonReadableProperty(
            $data['name'],
        );
        
        return $output;
    }

}
