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

/**
 * An adapter that collects data about all cache calls.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TraceableAdapter implements AdapterInterface
{
    protected $pool;
    private $calls = array();

    public function __construct(AdapterInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $event = $this->start(__FUNCTION__);
        try {
            $item = $this->pool->getItem($key);
        } finally {
            $event->end = microtime(true);
        }
        if ($event->result[$key] = $item->isHit()) {
            ++$event->hits;
        } else {
            ++$event->misses;
        }

        return $item;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->hasItem($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$key] = $this->pool->deleteItem($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->getKey()] = $this->pool->save($item);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result[$item->getKey()] = $this->pool->saveDeferred($item);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        $event = $this->start(__FUNCTION__);
        try {
            $result = $this->pool->getItems($keys);
        } finally {
            $event->end = microtime(true);
        }
        $f = function () use ($result, $event) {
            $event->result = array();
            foreach ($result as $key => $item) {
                if ($event->result[$key] = $item->isHit()) {
                    ++$event->hits;
                } else {
                    ++$event->misses;
                }
                yield $key => $item;
            }
        };

        return $f();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->clear();
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $event = $this->start(__FUNCTION__);
        $event->result['keys'] = $keys;
        try {
            return $event->result['result'] = $this->pool->deleteItems($keys);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->commit();
        } finally {
            $event->end = microtime(true);
        }
    }

    public function getCalls()
    {
        try {
            return $this->calls;
        } finally {
            $this->calls = array();
        }
    }

    protected function start($name)
    {
        $this->calls[] = $event = new TraceableAdapterEvent();
        $event->name = $name;
        $event->start = microtime(true);

        return $event;
    }
}

class TraceableAdapterEvent
{
    public $name;
    public $start;
    public $end;
    public $result;
    public $hits = 0;
    public $misses = 0;
}
