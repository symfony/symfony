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
     * @var array<string, array<string, string|false>>
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

        $name = $this->normalizeCache[$this->getCacheKey($class, $context)][$propertyName] ??= $this->getCacheValueForNormalization($propertyName, $class, $context) ?? false;

        return false !== $name ? $name : $this->normalizeFallback($propertyName, $class, $format, $context);
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

    private function getCacheValueForNormalization(string $propertyName, string $class, array $context): ?string
    {
        if (!$this->metadataFactory->hasMetadataFor($class)) {
            return null;
        }

        $attributesMetadata = $this->metadataFactory->getMetadataFor($class)->getAttributesMetadata();
        if (!\array_key_exists($propertyName, $attributesMetadata)) {
            return null;
        }

        $contextGroups = (array) ($context[AbstractNormalizer::GROUPS] ?? []);

        if ($context[AbstractNormalizer::ENABLE_DEFAULT_GROUPS] ?? false) {
            $defaultGroups = ['Default', (false !== $nsSep = strrpos($class, '\\')) ? substr($class, $nsSep + 1) : $class];
            if (array_intersect($contextGroups, $defaultGroups)) {
                $contextGroups = array_merge($contextGroups, $defaultGroups);
            }
        }

        if (null !== $attributesMetadata[$propertyName]->getSerializedName($contextGroups) && null !== $attributesMetadata[$propertyName]->getSerializedPath($contextGroups)) {
            throw new LogicException(\sprintf('Found SerializedName and SerializedPath attributes on property "%s" of class "%s".', $propertyName, $class));
        }

        return $attributesMetadata[$propertyName]->getSerializedName($contextGroups) ?? null;
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

        $classMetadata = $this->metadataFactory->getMetadataFor($class);

        $enableDefaultGroups = $context[AbstractNormalizer::ENABLE_DEFAULT_GROUPS] ?? false;

        $groups = (array) ($context[AbstractNormalizer::GROUPS] ?? []);
        $defaultGroups = ['Default', (false !== $nsSep = strrpos($class, '\\')) ? substr($class, $nsSep + 1) : $class];

        if ($enableDefaultGroups && array_intersect($groups, $defaultGroups)) {
            $groups = array_merge($groups, $defaultGroups);
        }

        $cache = [];
        foreach ($classMetadata->getAttributesMetadata() as $name => $metadata) {
            if (null === $serializedName = $metadata->getSerializedName($groups)) {
                continue;
            }

            if (null !== $metadata->getSerializedPath($groups)) {
                throw new LogicException(\sprintf('Found SerializedName and SerializedPath attributes on property "%s" of class "%s".', $name, $class));
            }

            if (!$groups) {
                $sameNameMetadata = $classMetadata->getAttributesMetadata()[$serializedName] ?? null;

                // without groups to tell them apart, the attribute using that name for itself wins
                if ($metadata->getGroups() && $sameNameMetadata && null === $sameNameMetadata->getSerializedName($groups)) {
                    continue;
                }
            } else {
                // without the flag, ungrouped attributes are skipped as soon as groups are requested, even with '*'
                $metadataGroups = $metadata->getGroups() ?: ($enableDefaultGroups ? $defaultGroups : []);

                if (!$metadataGroups || !array_intersect(array_merge($metadataGroups, ['*']), $groups)) {
                    continue;
                }
            }

            $cache[$serializedName] = $name;
        }

        return $cache;
    }

    private function getCacheKey(string $class, array $context): string
    {
        if ($context['cache_key'] ?? false) {
            return $class.'-'.$context['cache_key'];
        }

        return $class.hash('xxh128', serialize($context[AbstractNormalizer::GROUPS] ?? []).serialize($context[AbstractNormalizer::ENABLE_DEFAULT_GROUPS] ?? false));
    }
}
