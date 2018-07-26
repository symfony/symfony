<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Simple;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;

/**
 * Chains several caches together.
 *
 * Cached items are fetched from the first cache having them in its data store.
 * They are saved and deleted in all caches at once.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ChainCache implements CacheInterface, PruneableInterface, ResettableInterface
{
    private $miss;
    private $caches = array();
    private $defaultLifetime;
    private $cacheCount;

    /**
     * @param CacheInterface[] $caches          The ordered list of caches used to fetch cached items
     * @param int              $defaultLifetime The lifetime of items propagated from lower caches to upper ones
     */
    public function __construct(array $caches, int $defaultLifetime = 0)
    {
        if (!$caches) {
            throw new InvalidArgumentException('At least one cache must be specified.');
        }

        foreach ($caches as $cache) {
            if (!$cache instanceof CacheInterface) {
                throw new InvalidArgumentException(sprintf('The class "%s" does not implement the "%s" interface.', \get_class($cache), CacheInterface::class));
            }
        }

        $this->miss = new \stdClass();
        $this->caches = array_values($caches);
        $this->cacheCount = \count($this->caches);
        $this->defaultLifetime = 0 < $defaultLifetime ? $defaultLifetime : null;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $miss = null !== $default && \is_object($default) ? $default : $this->miss;

        foreach ($this->caches as $i => $cache) {
            $value = $cache->get($key, $miss);

            if ($miss !== $value) {
                while (0 <= --$i) {
                    $this->caches[$i]->set($key, $value, $this->defaultLifetime);
                }

                return $value;
            }
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        $miss = null !== $default && \is_object($default) ? $default : $this->miss;

        return $this->generateItems($this->caches[0]->getMultiple($keys, $miss), 0, $miss, $default);
    }

    private function generateItems($values, $cacheIndex, $miss, $default)
    {
        $missing = array();
        $nextCacheIndex = $cacheIndex + 1;
        $nextCache = isset($this->caches[$nextCacheIndex]) ? $this->caches[$nextCacheIndex] : null;

        foreach ($values as $k => $value) {
            if ($miss !== $value) {
                yield $k => $value;
            } elseif (!$nextCache) {
                yield $k => $default;
            } else {
                $missing[] = $k;
            }
        }

        if ($missing) {
            $cache = $this->caches[$cacheIndex];
            $values = $this->generateItems($nextCache->getMultiple($missing, $miss), $nextCacheIndex, $miss, $default);

            foreach ($values as $k => $value) {
                if ($miss !== $value) {
                    $cache->set($k, $value, $this->defaultLifetime);
                    yield $k => $value;
                } else {
                    yield $k => $default;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        foreach ($this->caches as $cache) {
            if ($cache->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $cleared = true;
        $i = $this->cacheCount;

        while ($i--) {
            $cleared = $this->caches[$i]->clear() && $cleared;
        }

        return $cleared;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $deleted = true;
        $i = $this->cacheCount;

        while ($i--) {
            $deleted = $this->caches[$i]->delete($key) && $deleted;
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }
        $deleted = true;
        $i = $this->cacheCount;

        while ($i--) {
            $deleted = $this->caches[$i]->deleteMultiple($keys) && $deleted;
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $saved = true;
        $i = $this->cacheCount;

        while ($i--) {
            $saved = $this->caches[$i]->set($key, $value, $ttl) && $saved;
        }

        return $saved;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if ($values instanceof \Traversable) {
            $valuesIterator = $values;
            $values = function () use ($valuesIterator, &$values) {
                $generatedValues = array();

                foreach ($valuesIterator as $key => $value) {
                    yield $key => $value;
                    $generatedValues[$key] = $value;
                }

                $values = $generatedValues;
            };
            $values = $values();
        }
        $saved = true;
        $i = $this->cacheCount;

        while ($i--) {
            $saved = $this->caches[$i]->setMultiple($values, $ttl) && $saved;
        }

        return $saved;
    }

    /**
     * {@inheritdoc}
     */
    public function prune()
    {
        $pruned = true;

        foreach ($this->caches as $cache) {
            if ($cache instanceof PruneableInterface) {
                $pruned = $cache->prune() && $pruned;
            }
        }

        return $pruned;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        foreach ($this->caches as $cache) {
            if ($cache instanceof ResettableInterface) {
                $cache->reset();
            }
        }
    }
}
