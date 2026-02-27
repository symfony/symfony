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

if (version_compare(phpversion('redis'), '6.2.0', '>=')) {
    /**
     * @internal
     */
    trait RedisCluster62ProxyTrait
    {
        public function expiremember($key, $field, $ttl, $unit = null): \Redis|false|int
        {
            return $this->initializeLazyObject()->expiremember(...\func_get_args());
        }

        public function expirememberat($key, $field, $timestamp): \Redis|false|int
        {
            return $this->initializeLazyObject()->expirememberat(...\func_get_args());
        }

        public function getdel($key): mixed
        {
            return $this->initializeLazyObject()->getdel(...\func_get_args());
        }

        public function getWithMeta($key): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->getWithMeta(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisCluster62ProxyTrait
    {
    }
}
