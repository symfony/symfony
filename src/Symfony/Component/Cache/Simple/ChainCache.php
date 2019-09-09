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

use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.3, use "%s" and type-hint for "%s" instead.', ChainCache::class, ChainAdapter::class, CacheInterface::class), E_USER_DEPRECATED);

/**
 * Chains several caches together.
 *
 * Cached items are fetched from the first cache having them in its data store.
 * They are saved and deleted in all caches at once.
 *
 * @deprecated since Symfony 4.3, use ChainAdapter and type-hint for CacheInterface instead.
 */
class ChainCache implements Psr16CacheInterface, PruneableInterface, ResettableInterface
{
    private $miss;
    private $caches = [];
    private $defaultLifetime;
    private $cacheCount;

    /**
     * @param Psr16CacheInterface[] $caches          The ordered list of caches used to fetch cached items
     * @param int                   $defaultLifetime The lifetime of items propagated from lower caches to upper ones
     */
    public function __construct(array $caches, int $defaultLifetime = 0)
    {
        if (!$caches) {
            throw new InvalidArgumentException('At least one cache must be specified.');
        }

        foreach ($caches as $cache) {
            if (!$cache instanceof Psr16CacheInterface) {
                throw new InvalidArgumentException(sprintf('The class "%s" does not implement the "%s" interface.', \get_class($cache), Psr16CacheInterface::class));
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
     *
     * @return iterable
     */
    public function getMultiple($keys, $default = null)
    {
        $miss = null !== $default && \is_object($default) ? $default : $this->miss;

        return $this->generateItems($this->caches[0]->getMultiple($keys, $miss), 0, $miss, $default);
    }

    private function generateItems(iterable $values, int $cacheIndex, $miss, $default): iterable
    {
        $missing = [];
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
     *
     * @return bool
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
     *
     * @return bool
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
     *
     * @return bool
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
     *
     * @return bool
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
     *
     * @return bool
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
     *
     * @return bool
     */
    public function setMultiple($values, $ttl = null)
    {
        if ($values instanceof \Traversable) {
            $valuesIterator = $values;
            $values = function () use ($valuesIterator, &$values) {
                $generatedValues = [];

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
            if ($cache instanceof ResetInterface) {
                $cache->reset();
            }
        }
    }
}
