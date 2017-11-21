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

use Symfony\Component\Cache\Traits\RedisTrait;

class RedisAdapter extends AbstractAdapter
{
    use RedisTrait;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient     The redis client
     * @param string                                          $namespace       The default namespace
     * @param int                                             $defaultLifetime The default lifetime
     */
    public function __construct($redisClient, string $namespace = '', int $defaultLifetime = 0)
    {
        $this->init($redisClient, $namespace, $defaultLifetime);
    }
}
