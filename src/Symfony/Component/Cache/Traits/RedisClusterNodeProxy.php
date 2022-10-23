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
 * This file acts as a wrapper to the \RedisCluster implementation so it can accept the same type of calls as
 *  individual \Redis objects.
 *
 * Calls are made to individual nodes via: RedisCluster->{method}($host, ...args)'
 *  according to https://github.com/phpredis/phpredis/blob/develop/cluster.markdown#directed-node-commands
 *
 * @author Jack Thomas <jack.thomas@solidalpha.com>
 *
 * @internal
 */
class RedisClusterNodeProxy
{
    private array $host;
    private \RedisCluster|RedisClusterProxy $redis;

    public function __construct(array $host, \RedisCluster|RedisClusterProxy $redis)
    {
        $this->host = $host;
        $this->redis = $redis;
    }

    public function __call(string $method, array $args)
    {
        return $this->redis->{$method}($this->host, ...$args);
    }

    public function scan(&$iIterator, $strPattern = null, $iCount = null)
    {
        return $this->redis->scan($iIterator, $this->host, $strPattern, $iCount);
    }

    public function getOption($name)
    {
        return $this->redis->getOption($name);
    }
}
