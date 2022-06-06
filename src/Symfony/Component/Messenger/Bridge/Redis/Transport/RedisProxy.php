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
 * Allow to delay connection to Redis.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
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

    public function __call(string $method, array $args)
    {
        if (!$this->ready) {
            $this->redis = $this->initializer->__invoke($this->redis);
            $this->ready = true;
        }

        return $this->redis->{$method}(...$args);
    }
}
