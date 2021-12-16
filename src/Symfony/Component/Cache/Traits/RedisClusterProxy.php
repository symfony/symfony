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
 * @author Alessandro Chitolina <alekitto@gmail.com>
 *
 * @internal
 */
class RedisClusterProxy
{
    private $redis;
    private \Closure $initializer;

    public function __construct(\Closure $initializer)
    {
        $this->initializer = $initializer;
    }

    public function __call(string $method, array $args)
    {
        $this->redis ??= ($this->initializer)();

        return $this->redis->{$method}(...$args);
    }

    public function hscan($strKey, &$iIterator, $strPattern = null, $iCount = null)
    {
        $this->redis ??= ($this->initializer)();

        return $this->redis->hscan($strKey, $iIterator, $strPattern, $iCount);
    }

    public function scan(&$iIterator, $strPattern = null, $iCount = null)
    {
        $this->redis ??= ($this->initializer)();

        return $this->redis->scan($iIterator, $strPattern, $iCount);
    }

    public function sscan($strKey, &$iIterator, $strPattern = null, $iCount = null)
    {
        $this->redis ??= ($this->initializer)();

        return $this->redis->sscan($strKey, $iIterator, $strPattern, $iCount);
    }

    public function zscan($strKey, &$iIterator, $strPattern = null, $iCount = null)
    {
        $this->redis ??= ($this->initializer)();

        return $this->redis->zscan($strKey, $iIterator, $strPattern, $iCount);
    }
}
