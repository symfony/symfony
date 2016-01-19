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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Adapts a PSR-6 cache to a CacheInterface.
 *
 * @author David Maicher <mail@dmaicher.de>
 */
class PsrCache implements CacheInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param string $class
     *
     * @return string
     */
    private function getCacheKey($class)
    {
        //backslash is a reserved character and not allowed in PSR-6 cache keys
        return str_replace('\\', '_', $class);
    }

    /**
     * {@inheritdoc}
     */
    public function has($class)
    {
        return $this->cache->hasItem($this->getCacheKey($class));
    }

    /**
     * {@inheritdoc}
     */
    public function read($class)
    {
        $item = $this->cache->getItem($this->getCacheKey($class));

        return $item->isHit() ? $item->get() : false;
    }

    /**
     * {@inheritdoc}
     */
    public function write(ClassMetadata $metadata)
    {
        $item = $this->cache->getItem($this->getCacheKey($metadata->getClassName()));
        $item->set($metadata);
        $this->cache->save($item);
    }
}
