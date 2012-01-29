<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Cache;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Cache\CacheInterface as SymfonyCache;

/**
 * Persists ClassMetadata instances with Symfony Cache Component
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class SymfonyCache implements CacheInterface
{
    private $cache;
    private $prefix;

    public function __construct(SymfonyCache $cache, $prefix)
    {
        $this->cache = $cache;
        $this->prefix = $prefix;
    }

    /**
     * Returns whether metadata for the given class exists in the cache
     *
     * @param string $class
     */
    public function has($class)
    {
        return $this->cache->contains($this->prefix.$class);
    }

    /**
     * Returns the metadata for the given class from the cache
     *
     * @param string $class Class Name
     *
     * @return ClassMetadata
     */
    public function read($class)
    {
        return $this->cache->fetch($this->prefix.$class);
    }

    /**
     * Stores a class metadata in the cache
     *
     * @param ClassMetadata $metadata A Class Metadata
     */
    public function write(ClassMetadata $metadata)
    {
        $this->cache->save($this->prefix.$metadata->getClassName(), $metadata);
    }
}

