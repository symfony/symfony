<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * Chains adapters together.
 *
 * Saves, deletes and clears all registered adapter.
 * Gets data from the first chained adapter having it in cache.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ChainAdapter implements AdapterInterface
{
    private $adapters = array();

    /**
     * @param AdapterInterface[] $adapters
     */
    public function __construct(array $adapters)
    {
        if (2 > count($adapters)) {
            throw new InvalidArgumentException('At least two adapters must be chained.');
        }

        foreach ($adapters as $adapter) {
            if (!$adapter instanceof CacheItemPoolInterface) {
                throw new InvalidArgumentException(sprintf('The class "%s" does not implement the "%s" interface.', get_class($adapter), CacheItemPoolInterface::class));
            }

            if ($adapter instanceof AdapterInterface) {
                $this->adapters[] = $adapter;
            } else {
                $this->adapters[] = new ProxyAdapter($adapter);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        foreach ($this->adapters as $adapter) {
            $item = $adapter->getItem($key);

            if ($item->isHit()) {
                return $item;
            }
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        $items = array();
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->hasItem($key)) {
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

        foreach ($this->adapters as $adapter) {
            $cleared = $adapter->clear() && $cleared;
        }

        return $cleared;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $deleted = true;

        foreach ($this->adapters as $adapter) {
            $deleted = $adapter->deleteItem($key) && $deleted;
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $deleted = true;

        foreach ($this->adapters as $adapter) {
            $deleted = $adapter->deleteItems($keys) && $deleted;
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $saved = true;

        foreach ($this->adapters as $adapter) {
            $saved = $adapter->save($item) && $saved;
        }

        return $saved;
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $saved = true;

        foreach ($this->adapters as $adapter) {
            $saved = $adapter->saveDeferred($item) && $saved;
        }

        return $saved;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $committed = true;

        foreach ($this->adapters as $adapter) {
            $committed = $adapter->commit() && $committed;
        }

        return $committed;
    }
}
