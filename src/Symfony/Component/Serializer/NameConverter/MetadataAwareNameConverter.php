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
final class MetadataAwareNameConverter implements AdvancedNameConverterInterface
{
    /**
     * @var array<string, array<string, string|null>>
     */
    private static array $normalizeCache = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    private static array $denormalizeCache = [];

    /**
     * @var array<string, array<string, string>>
     */
    private static array $attributesMetadataCache = [];

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

        $cacheKey = $this->getCacheKey($class, $context);
        if (!\array_key_exists($cacheKey, self::$normalizeCache) || !\array_key_exists($propertyName, self::$normalizeCache[$cacheKey])) {
            self::$normalizeCache[$cacheKey][$propertyName] = $this->getCacheValueForNormalization($propertyName, $class, $context);
        }

        return self::$normalizeCache[$cacheKey][$propertyName] ?? $this->normalizeFallback($propertyName, $class, $format, $context);
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        if (null === $class) {
            return $this->denormalizeFallback($propertyName, $class, $format, $context);
        }

        $cacheKey = $this->getCacheKey($class, $context);
        if (!\array_key_exists($cacheKey, self::$denormalizeCache) || !\array_key_exists($propertyName, self::$denormalizeCache[$cacheKey])) {
            self::$denormalizeCache[$cacheKey][$propertyName] = $this->getCacheValueForDenormalization($propertyName, $class, $context);
        }

        return self::$denormalizeCache[$cacheKey][$propertyName] ?? $this->denormalizeFallback($propertyName, $class, $format, $context);
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
        $contextGroups = array_merge($contextGroups, ['Default', (false !== $nsSep = strrpos($class, '\\')) ? substr($class, $nsSep + 1) : $class]);

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
        if (!\array_key_exists($cacheKey, self::$attributesMetadataCache)) {
            self::$attributesMetadataCache[$cacheKey] = $this->getCacheValueForAttributesMetadata($class, $context);
        }

        return self::$attributesMetadataCache[$cacheKey][$propertyName] ?? null;
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

        $contextGroups = (array) ($context[AbstractNormalizer::GROUPS] ?? []);
        $contextGroupsHasBeenDefined = [] !== $contextGroups;
        $contextGroups = array_merge($contextGroups, ['Default', (false !== $nsSep = strrpos($class, '\\')) ? substr($class, $nsSep + 1) : $class]);

        $cache = [];
        foreach ($classMetadata->getAttributesMetadata() as $name => $metadata) {
            if (null === $serializedName = $metadata->getSerializedName($contextGroups)) {
                continue;
            }

            if (null !== $metadata->getSerializedPath($contextGroups)) {
                throw new LogicException(\sprintf('Found SerializedName and SerializedPath attributes on property "%s" of class "%s".', $name, $class));
            }

            $metadataGroups = $metadata->getGroups();

            if ($contextGroupsHasBeenDefined && !$metadataGroups) {
                continue;
            }

            if ($metadataGroups && !array_intersect(array_merge($metadataGroups, ['*']), $contextGroups)) {
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
