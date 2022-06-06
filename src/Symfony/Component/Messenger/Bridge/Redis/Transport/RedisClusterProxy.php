<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Transport;

/**
 * Allow to delay connection to Redis Cluster.
 *
 * @author Johann Pardanaud <johann@pardanaud.com>
 *
 * @internal
 */
class RedisClusterProxy
{
    private $redis;
    private $initializer;
    private $ready = false;

    public function __construct(?\RedisCluster $redis, \Closure $initializer)
    {
        $this->redis = $redis;
        $this->initializer = $initializer;
    }

    public function __call(string $method, array $args)
    {
        if (!$this->ready) {
            $this->redis = $this->initializer->__invoke($this->redis);
            $this->ready = true;
        }

        return $this->redis->{$method}(...$args);
    }
}
