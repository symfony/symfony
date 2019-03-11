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

/**
 * Adds a PSR-6 cache layer on top of an extractor.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class PropertyInfoCacheExtractor implements PropertyInfoExtractorInterface, PropertyInitializableExtractorInterface
{
    private $propertyInfoExtractor;
    private $cacheItemPool;

    /**
     * A cache of property information, first keyed by the method called and
     * then by the serialized method arguments.
     *
     * @var array
     */
    private $arrayCache = [];

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, CacheItemPoolInterface $cacheItemPool)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->cacheItemPool = $cacheItemPool;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = [])
    {
        return $this->extract('isReadable', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = [])
    {
        return $this->extract('isWritable', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = [])
    {
        return $this->extract('getShortDescription', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = [])
    {
        return $this->extract('getLongDescription', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = [])
    {
        return $this->extract('getProperties', [$class, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = [])
    {
        return $this->extract('getTypes', [$class, $property, $context]);
    }

    /**
     * {@inheritdoc}
     */
    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        return $this->extract('isInitializable', [$class, $property, $context]);
    }

    /**
     * Retrieves the cached data if applicable or delegates to the decorated extractor.
     *
     * @return mixed
     */
    private function extract(string $method, array $arguments)
    {
        try {
            $serializedArguments = serialize($arguments);
        } catch (\Exception $exception) {
            // If arguments are not serializable, skip the cache
            return $this->propertyInfoExtractor->{$method}(...$arguments);
        }

        // Calling rawurlencode escapes special characters not allowed in PSR-6's keys
        $encodedMethod = \rawurlencode($method);
        if (\array_key_exists($encodedMethod, $this->arrayCache) && \array_key_exists($serializedArguments, $this->arrayCache[$encodedMethod])) {
            return $this->arrayCache[$encodedMethod][$serializedArguments];
        }

        $item = $this->cacheItemPool->getItem($encodedMethod);

        $data = $item->get();
        if ($item->isHit()) {
            $this->arrayCache[$encodedMethod] = $data[$encodedMethod];
            // Only match if the specific arguments have been cached.
            if (\array_key_exists($serializedArguments, $data[$encodedMethod])) {
                return $this->arrayCache[$encodedMethod][$serializedArguments];
            }
        }

        // It's possible that the method has been called, but with different
        // arguments, in which case $data will already be initialized.
        if (!$data) {
            $data = [];
        }

        $value = $this->propertyInfoExtractor->{$method}(...$arguments);
        $data[$encodedMethod][$serializedArguments] = $value;
        $this->arrayCache[$encodedMethod][$serializedArguments] = $value;
        $item->set($data);
        $this->cacheItemPool->save($item);

        return $this->arrayCache[$encodedMethod][$serializedArguments];
    }
}
