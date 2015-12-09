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

use Doctrine\Common\Cache\Cache;

/**
 * Adds a cache layer on top of an extractor.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoExtractorCacheDecorator implements PropertyInfoExtractorInterface
{
    /**
     * @var PropertyInfoExtractorInterface
     */
    private $propertyInfoExtractor;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var array
     */
    private $arrayCache = array();

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, Cache $cache)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->cache = $cache;
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
        $key = $method.serialize($arguments);

        if (isset($this->arrayCache[$key])) {
            return $this->arrayCache[$key];
        }

        if ($value = $this->cache->fetch($key)) {
            return $this->arrayCache[$key] = $value;
        }

        $value = call_user_func_array(array($this->propertyInfoExtractor, $method), $arguments);
        $this->cache->save($key, $value);

        return $this->arrayCache[$key] = $value;
    }
}
