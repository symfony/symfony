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
 */
class PropertyInfoCacheExtractor implements PropertyInfoExtractorInterface
{
    /**
     * @var PropertyInfoExtractorInterface
     */
    private $propertyInfoExtractor;

    /**
     * @var CacheItemPoolInterface
     */
    private $cacheItemPool;

    /**
     * @var array
     */
    private $arrayCache = array();

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, CacheItemPoolInterface $cacheItemPool)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->cacheItemPool = $cacheItemPool;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = array())
    {
        return $this->extract('isReadable', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = array())
    {
        return $this->extract('isWritable', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        return $this->extract('getShortDescription', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        return $this->extract('getLongDescription', array($class, $property, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        return $this->extract('getProperties', array($class, $context));
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        return $this->extract('getTypes', array($class, $context));
    }

    /**
     * Retrieves the cached data if applicable or delegates to the decorated extractor.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    private function extract($method, array $arguments)
    {
        try {
            $serializedArguments = serialize($arguments);
        } catch (\Exception $exception) {
            // If arguments are not serializable, skip the cache
            return call_user_func_array(array($this->propertyInfoExtractor, $method), $arguments);
        }

        $key = $this->escape($method.'.'.$serializedArguments);

        if (isset($this->arrayCache[$key])) {
            return $this->arrayCache[$key];
        }

        if ($value = $this->cacheItemPool->getItem($key)) {
            return $this->arrayCache[$key] = $value;
        }

        $value = call_user_func_array(array($this->propertyInfoExtractor, $method), $arguments);
        $this->cacheItemPool->save($key, $value);

        return $this->arrayCache[$key] = $value;
    }

    private function escape($key)
    {
        str_replace(
            array('_', '{', '}', '(', ')', '/', '\\', '@', ':'),
            array('_95', '_123', '_125', '_40', '_41', '_47', '_92', '_64', '_58'),
            $key
        );
    }
}
