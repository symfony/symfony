<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Mapping\Cache;

use Doctrine\Common\Cache\Cache;
use Symphony\Component\Validator\Mapping\ClassMetadata;

/**
 * Adapts a Doctrine cache to a CacheInterface.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
final class DoctrineCache implements CacheInterface
{
    private $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

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
        $this->cache->save($metadata->getClassName(), $metadata);
    }
}
