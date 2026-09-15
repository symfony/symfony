<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\NameConverter;

use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class MetadataAwareNameConverter implements NameConverterInterface
{
    /**
     * @var array<string, array<string, string|null>>
     */
    private array $normalizeCache = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    private array $denormalizeCache = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $attributesMetadataCache = [];

    public function __construct(
        private readonly ClassMetadataFactoryInterface $metadataFactory,
        private readonly ?NameConverterInterface $fallbackNameConverter = null,
    ) {
    }

    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $class) {
            return $this->normalizeFallback($propertyName, $class, $format, $context);
        }

        if (!\array_key_exists($class, $this->normalizeCache) || !\array_key_exists($propertyName, $this->normalizeCache[$class])) {
            $this->normalizeCache[$class][$propertyName] = $this->getCacheValueForNormalization($propertyName, $class);
        }

        return $this->normalizeCache[$class][$propertyName] ?? $this->normalizeFallback($propertyName, $class, $format, $context);
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $class) {
            return $this->denormalizeFallback($propertyName, $class, $format, $context);
        }

        $cacheKey = $this->getCacheKey($class, $context);
        if (!\array_key_exists($cacheKey, $this->denormalizeCache) || !\array_key_exists($propertyName, $this->denormalizeCache[$cacheKey])) {
            $this->denormalizeCache[$cacheKey][$propertyName] = $this->getCacheValueForDenormalization($propertyName, $class, $context);
        }

        return $this->denormalizeCache[$cacheKey][$propertyName] ?? $this->denormalizeFallback($propertyName, $class, $format, $context);
    }

    private function getCacheValueForNormalization(string $propertyName, string $class): ?string
    {
        if (!$this->metadataFactory->hasMetadataFor($class)) {
            return null;
        }

        $attributesMetadata = $this->metadataFactory->getMetadataFor($class)->getAttributesMetadata();
        if (!\array_key_exists($propertyName, $attributesMetadata)) {
            return null;
        }

        if (null !== $attributesMetadata[$propertyName]->getSerializedName() && null !== $attributesMetadata[$propertyName]->getSerializedPath()) {
            throw new LogicException(\sprintf('Found SerializedName and SerializedPath attributes on property "%s" of class "%s".', $propertyName, $class));
        }

        return $attributesMetadata[$propertyName]->getSerializedName() ?? null;
    }

    private function normalizeFallback(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        return $this->fallbackNameConverter ? $this->fallbackNameConverter->normalize($propertyName, $class, $format, $context) : $propertyName;
    }

    private function getCacheValueForDenormalization(string $propertyName, string $class, array $context): ?string
    {
        $cacheKey = $this->getCacheKey($class, $context);
        if (!\array_key_exists($cacheKey, $this->attributesMetadataCache)) {
            $this->attributesMetadataCache[$cacheKey] = $this->getCacheValueForAttributesMetadata($class, $context);
        }

        return $this->attributesMetadataCache[$cacheKey][$propertyName] ?? null;
    }

    private function denormalizeFallback(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        return $this->fallbackNameConverter ? $this->fallbackNameConverter->denormalize($propertyName, $class, $format, $context) : $propertyName;
    }

    /**
     * @return array<string, string>
     */
    private function getCacheValueForAttributesMetadata(string $class, array $context): array
    {
        if (!$this->metadataFactory->hasMetadataFor($class)) {
            return [];
        }

        $attributesMetadata = $this->metadataFactory->getMetadataFor($class)->getAttributesMetadata();
        $contextGroups = (array) ($context[AbstractNormalizer::GROUPS] ?? []);

        $cache = [];
        foreach ($attributesMetadata as $name => $metadata) {
            if (null === $serializedName = $metadata->getSerializedName()) {
                continue;
            }

            if (null !== $metadata->getSerializedPath()) {
                throw new LogicException(\sprintf('Found SerializedName and SerializedPath attributes on property "%s" of class "%s".', $name, $class));
            }

            $metadataGroups = $metadata->getGroups();

            if (!$contextGroups) {
                $sameNameMetadata = $attributesMetadata[$serializedName] ?? null;

                // without groups to tell them apart, the attribute using that name for itself wins
                if ($metadataGroups && $sameNameMetadata && null === $sameNameMetadata->getSerializedName()) {
                    continue;
                }
            } elseif (!$metadataGroups) {
                continue;
            } elseif (!array_intersect($metadataGroups, $contextGroups) && !\in_array('*', $contextGroups, true)) {
                continue;
            }

            $cache[$serializedName] = $name;
        }

        return $cache;
    }

    private function getCacheKey(string $class, array $context): string
    {
        if (isset($context['cache_key'])) {
            return $class.'-'.$context['cache_key'];
        }

        return $class.hash('xxh128', serialize($context[AbstractNormalizer::GROUPS] ?? []));
    }
}
