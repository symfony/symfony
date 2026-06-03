<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * Adds a PSR-6 cache layer on top of an extractor.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class PropertyInfoCacheExtractor implements PropertyInfoExtractorInterface, PropertyInitializableExtractorInterface
{
    private array $arrayCache = [];

    public function __construct(
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly CacheItemPoolInterface $cacheItemPool,
    ) {
    }

    public function isReadable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract('isReadable', [$class, $property, $context]);
    }

    public function isWritable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract('isWritable', [$class, $property, $context]);
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->extract('getShortDescription', [$class, $property, $context]);
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        return $this->extract('getLongDescription', [$class, $property, $context]);
    }

    public function getProperties(string $class, array $context = []): ?array
    {
        return $this->extract('getProperties', [$class, $context]);
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        try {
            $serializedArguments = serialize([$class, $property, $context]);
        } catch (\Exception) {
            // If arguments are not serializable, skip the cache
            return $this->propertyInfoExtractor->getType($class, $property, $context);
        }

        // Calling rawurlencode escapes special characters not allowed in PSR-6's keys
        $key = rawurlencode('getType.'.$serializedArguments);

        if (\array_key_exists($key, $this->arrayCache)) {
            return $this->arrayCache[$key];
        }

        $item = $this->cacheItemPool->getItem($key);

        if ($item->isHit()) {
            return $this->arrayCache[$key] = $item->get();
        }

        $value = $this->propertyInfoExtractor->getType($class, $property, $context);

        $item->set($value);
        $this->cacheItemPool->save($item);

        return $this->arrayCache[$key] = $value;
    }

    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract('isInitializable', [$class, $property, $context]);
    }

    /**
     * Retrieves the cached data if applicable or delegates to the decorated extractor.
     */
    private function extract(string $method, array $arguments): mixed
    {
        try {
            $serializedArguments = serialize($arguments);
        } catch (\Exception) {
            // If arguments are not serializable, skip the cache
            return $this->propertyInfoExtractor->{$method}(...$arguments);
        }

        // Calling rawurlencode escapes special characters not allowed in PSR-6's keys
        $key = rawurlencode($method.'.'.$serializedArguments);

        if (\array_key_exists($key, $this->arrayCache)) {
            return $this->arrayCache[$key];
        }

        $item = $this->cacheItemPool->getItem($key);

        if ($item->isHit()) {
            return $this->arrayCache[$key] = $item->get();
        }

        $value = $this->propertyInfoExtractor->{$method}(...$arguments);
        $item->set($value);
        $this->cacheItemPool->save($item);

        return $this->arrayCache[$key] = $value;
    }
}
