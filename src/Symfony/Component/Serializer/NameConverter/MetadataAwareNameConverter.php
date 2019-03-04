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

use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

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

    /**
     * {@inheritdoc}
     */
    public function normalize($propertyName, string $class = null, string $format = null, array $context = [])
    {
        if (null === $class) {
            return $this->normalizeFallback($propertyName, $class, $format, $context);
        }

        if (!isset(self::$normalizeCache[$class][$propertyName])) {
            self::$normalizeCache[$class][$propertyName] = $this->getCacheValueForNormalization($propertyName, $class);
        }

        return self::$normalizeCache[$class][$propertyName] ?? $this->normalizeFallback($propertyName, $class, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($propertyName, string $class = null, string $format = null, array $context = [])
    {
        if (null === $class) {
            return $this->denormalizeFallback($propertyName, $class, $format, $context);
        }

        if (!isset(self::$denormalizeCache[$class][$propertyName])) {
            self::$denormalizeCache[$class][$propertyName] = $this->getCacheValueForDenormalization($propertyName, $class);
        }

        return self::$denormalizeCache[$class][$propertyName] ?? $this->denormalizeFallback($propertyName, $class, $format, $context);
    }

    private function getCacheValueForNormalization(string $propertyName, string $class): ?string
    {
        if (!$this->metadataFactory->hasMetadataFor($class)) {
            return null;
        }

        $attributesMetadata = $this->metadataFactory->getMetadataFor($class)->getAttributesMetadata();
        if (!isset($attributesMetadata[$propertyName])) {
            return null;
        }

        return $attributesMetadata[$propertyName]->getSerializedName() ?? null;
    }

    private function normalizeFallback(string $propertyName, string $class = null, string $format = null, array $context = []): string
    {
        return $this->fallbackNameConverter ? $this->fallbackNameConverter->normalize($propertyName, $class, $format, $context) : $propertyName;
    }

    private function getCacheValueForDenormalization(string $propertyName, string $class): ?string
    {
        if (!isset(self::$attributesMetadataCache[$class])) {
            self::$attributesMetadataCache[$class] = $this->getCacheValueForAttributesMetadata($class);
        }

        return self::$attributesMetadataCache[$class][$propertyName] ?? null;
    }

    private function denormalizeFallback(string $propertyName, string $class = null, string $format = null, array $context = []): string
    {
        return $this->fallbackNameConverter ? $this->fallbackNameConverter->denormalize($propertyName, $class, $format, $context) : $propertyName;
    }

    private function getCacheValueForAttributesMetadata(string $class): array
    {
        if (!$this->metadataFactory->hasMetadataFor($class)) {
            return [];
        }

        $classMetadata = $this->metadataFactory->getMetadataFor($class);

        $cache = [];
        foreach ($classMetadata->getAttributesMetadata() as $name => $metadata) {
            if (null === $metadata->getSerializedName()) {
                continue;
            }

            $cache[$metadata->getSerializedName()] = $name;
        }

        return $cache;
    }
}
