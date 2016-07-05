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

use Doctrine\Common\Cache\Cache;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Adapts a Doctrine cache to a CacheInterface.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 *
 * @deprecated since 3.2, to be removed in 4.0. Use {@link Psr6Cache}
 *             with {@link DoctrineAdapter} instead.
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
        @trigger_error(sprintf('%s is deprecated since version 3.2 and will be removed in 4.0. Use %s with %s instead.', __CLASS__, Psr6Cache::class, DoctrineAdapter::class), E_USER_DEPRECATED);
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
        $this->cache->save($metadata->getClassName(), $metadata);
    }
}
