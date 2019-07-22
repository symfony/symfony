<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class RedisProxy
{
    private $redis;
    private $initializer;
    private $ready = false;

    public function __construct(\Redis $redis, \Closure $initializer)
    {
        $this->redis = $redis;
        $this->initializer = $initializer;
    }

    public function __call($method, array $args)
    {
        $this->ready ?: $this->ready = $this->initializer->__invoke($this->redis);

        return $this->redis->{$method}(...$args);
    }

    public function hscan($strKey, &$iIterator, $strPattern = null, $iCount = null)
    {
        $this->ready ?: $this->ready = $this->initializer->__invoke($this->redis);

        return $this->redis->hscan($strKey, $iIterator, $strPattern, $iCount);
    }

    public function scan(&$iIterator, $strPattern = null, $iCount = null)
    {
        $this->ready ?: $this->ready = $this->initializer->__invoke($this->redis);

        return $this->redis->scan($iIterator, $strPattern, $iCount);
    }

    public function sscan($strKey, &$iIterator, $strPattern = null, $iCount = null)
    {
        $this->ready ?: $this->ready = $this->initializer->__invoke($this->redis);

        return $this->redis->sscan($strKey, $iIterator, $strPattern, $iCount);
    }

    public function zscan($strKey, &$iIterator, $strPattern = null, $iCount = null)
    {
        $this->ready ?: $this->ready = $this->initializer->__invoke($this->redis);

        return $this->redis->zscan($strKey, $iIterator, $strPattern, $iCount);
    }
}
