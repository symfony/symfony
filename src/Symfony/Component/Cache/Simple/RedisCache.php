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

use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Traits\RedisTrait;

class RedisCache extends AbstractCache
{
    use RedisTrait;

    /**
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\Client $redisClient
     * @param string                                          $namespace
     * @param int                                             $defaultLifetime
     */
    public function __construct($redisClient, string $namespace = '', int $defaultLifetime = 0, MarshallerInterface $marshaller = null)
    {
        $this->init($redisClient, $namespace, $defaultLifetime, $marshaller);
    }
}
