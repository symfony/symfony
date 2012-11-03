<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Bundle\CacheBundle\Factory;

/**
 * This class takes care of creating the cache drivers and instances
 *
 * @author Florin Patan <florinpatan@gmail.com>
 */
class CacheFactory
{
    /**
     * This is the cache manager
     *
     * @var \Symfony\Component\Cache\Cache
     */
    private $manager;

    /**
     * Set the cache manager
     *
     * @param \Symfony\Component\Cache\Cache $cacheManager
     *
     * @return \Symfony\Bundle\CacheBundle\Factory\CacheFactory
     */
    public function setCacheManager($cacheManager)
    {
        $this->manager = $cacheManager;

        return $this;
    }

    /**
     * Add the cache instances
     *
     * @param array $instances
     *
     * @return CacheFactory
     */
    public function addCacheInstances($instances)
    {
        $this->manager->addDriverInstances($instances);

        return $this;
    }

    /**
     * Get the cache driver
     *
     * @param string  $cacheName
     *
     * @return \Symfony\Component\Cache\Drivers\CacheDriverInterface
     *
     * @throws \RuntimeException When invalid caching instance is not specified
     */
    public function get($cacheName)
    {
        // Fetch our instance
        return $this->manager->getDriverInstance($cacheName);
    }
}
