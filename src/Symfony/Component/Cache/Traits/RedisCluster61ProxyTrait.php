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

if (version_compare(phpversion('redis'), '6.1.0-dev', '>')) {
    /**
     * @internal
     */
    trait RedisCluster61ProxyTrait
    {
        public function getex($key, $options = []): \RedisCluster|string|false
        {
            return $this->initializeLazyObject()->getex(...\func_get_args());
        }

        public function publish($channel, $message): \RedisCluster|bool|int
        {
            return $this->initializeLazyObject()->publish(...\func_get_args());
        }

        public function waitaof($key_or_address, $numlocal, $numreplicas, $timeout): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->waitaof(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisCluster61ProxyTrait
    {
        public function publish($channel, $message): \RedisCluster|bool
        {
            return $this->initializeLazyObject()->publish(...\func_get_args());
        }
    }
}
