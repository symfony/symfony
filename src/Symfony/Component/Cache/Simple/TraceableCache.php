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

/**
 * An adapter that collects data about all cache calls.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TraceableCache implements CacheInterface
{
    private $pool;
    private $miss;
    private $calls = array();

    public function __construct(CacheInterface $pool)
    {
        $this->pool = $pool;
        $this->miss = new \stdClass();
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        $miss = null !== $default && is_object($default) ? $default : $this->miss;
        $event = $this->start(__FUNCTION__, compact('key', 'default'));
        try {
            $value = $this->pool->get($key, $miss);
        } finally {
            $event->end = microtime(true);
        }
        if ($miss !== $value) {
            ++$event->hits;
        } else {
            ++$event->misses;
            $value = $default;
        }

        return $event->result = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        $event = $this->start(__FUNCTION__, compact('key'));
        try {
            return $event->result = $this->pool->has($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $event = $this->start(__FUNCTION__, compact('key'));
        try {
            return $event->result = $this->pool->delete($key);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $event = $this->start(__FUNCTION__, compact('key', 'value', 'ttl'));
        try {
            return $event->result = $this->pool->set($key, $value, $ttl);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        $event = $this->start(__FUNCTION__, compact('values', 'ttl'));
        try {
            return $event->result = $this->pool->setMultiple($values, $ttl);
        } finally {
            $event->end = microtime(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        $miss = null !== $default && is_object($default) ? $default : $this->miss;
        $event = $this->start(__FUNCTION__, compact('keys', 'default'));
        try {
            $result = $this->pool->getMultiple($keys, $miss);
        } finally {
            $event->end = microtime(true);
        }
        $f = function () use ($result, $event, $miss, $default) {
            $event->result = array();
            foreach ($result as $key => $value) {
                if ($miss !== $value) {
                    ++$event->hits;
                } else {
                    ++$event->misses;
                    $value = $default;
                }
                yield $key => $event->result[$key] = $value;
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
    public function deleteMultiple($keys)
    {
        $event = $this->start(__FUNCTION__, compact('keys'));
        try {
            return $event->result = $this->pool->deleteMultiple($keys);
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

    private function start($name, array $arguments = null)
    {
        $this->calls[] = $event = new TraceableCacheEvent();
        $event->name = $name;
        $event->arguments = $arguments;
        $event->start = microtime(true);

        return $event;
    }
}

class TraceableCacheEvent
{
    public $name;
    public $arguments;
    public $start;
    public $end;
    public $result;
    public $hits = 0;
    public $misses = 0;
}
