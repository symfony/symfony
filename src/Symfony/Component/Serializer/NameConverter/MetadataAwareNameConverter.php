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
    private $metadataFactory;

    /**
     * @var NameConverterInterface|AdvancedNameConverterInterface|null
     */
    private $fallbackNameConverter;

    private static $normalizeCache = [];

    private static $denormalizeCache = [];

    private static $attributesMetadataCache = [];

    public function __construct(ClassMetadataFactoryInterface $metadataFactory, NameConverterInterface $fallbackNameConverter = null)
    {
        $this->metadataFactory = $metadataFactory;
        $this->fallbackNameConverter = $fallbackNameConverter;
    }

    public function normalize(string $propertyName, string $class = null, string $format = null, array $context = []): string
    {
        if (null === $class) {
            return $this->normalizeFallback($propertyName, $class, $format, $context);
        }

        if (!\array_key_exists($class, self::$normalizeCache) || !\array_key_exists($propertyName, self::$normalizeCache[$class])) {
            self::$normalizeCache[$class][$propertyName] = $this->getCacheValueForNormalization($propertyName, $class, $context);
        }

        return self::$normalizeCache[$class][$propertyName] ?? $this->normalizeFallback($propertyName, $class, $format, $context);
    }

    public function denormalize(string $propertyName, string $class = null, string $format = null, array $context = []): string
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

        $groups = (array) ($context[AbstractNormalizer::GROUPS] ?? []);

        $serializedName = $attributesMetadata[$propertyName]->getSerializedName($groups);
        if (null !== $serializedName && null !== $attributesMetadata[$propertyName]->getSerializedPath()) {
            throw new LogicException(sprintf('Found SerializedName and SerializedPath annotations on property "%s" of class "%s".', $propertyName, $class));
        }

        return $serializedName ?? null;
    }

    private function normalizeFallback(string $propertyName, string $class = null, string $format = null, array $context = []): string
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

    private function denormalizeFallback(string $propertyName, string $class = null, string $format = null, array $context = []): string
    {
        return $this->fallbackNameConverter ? $this->fallbackNameConverter->denormalize($propertyName, $class, $format, $context) : $propertyName;
    }

    private function getCacheValueForAttributesMetadata(string $class, array $context): array
    {
        if (!$this->metadataFactory->hasMetadataFor($class)) {
            return [];
        }

        $classMetadata = $this->metadataFactory->getMetadataFor($class);

        $cache = [];
        foreach ($classMetadata->getAttributesMetadata() as $name => $metadata) {
            $contextGroups = (array) ($context[AbstractNormalizer::GROUPS] ?? []);
            if (null === $serializedName = $metadata->getSerializedName($contextGroups)) {
                continue;
            }

            if (null !== $metadata->getSerializedPath()) {
                throw new LogicException(sprintf('Found SerializedName and SerializedPath annotations on property "%s" of class "%s".', $name, $class));
            }

            $groups = $metadata->getGroups();
            if (!$groups && $contextGroups) {
                continue;
            }
            if ($groups && !array_intersect($groups, $contextGroups)) {
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

        return $class.md5(serialize($context[AbstractNormalizer::GROUPS] ?? []));
    }
}
