<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Mapping\Cache;

use Doctrine\Common\Cache\Cache;
use Symfony\Component\PropertyAccess\Mapping\ClassMetadata;

/**
 * Adapts a Doctrine cache to a CacheInterface.
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
final class DoctrineCache implements CacheInterface
{
    private $cache;

    /**
     * Creates a new Doctrine cache.
     *
     * @param Cache $cache The cache to adapt
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Sets the cache to adapt.
     *
     * @param Cache $cache The cache to adapt
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function has($class)
    {
        return $this->cache->contains($class);
    }

    /**
     * {@inheritdoc}
     */
    public function read($class)
    {
        return $this->cache->fetch($class);
    }

    /**
     * {@inheritdoc}
     */
    public function write(ClassMetadata $metadata)
    {
        $this->cache->save($metadata->getName(), $metadata);
    }
}
