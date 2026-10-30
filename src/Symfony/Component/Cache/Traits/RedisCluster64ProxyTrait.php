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

if (method_exists(\RedisCluster::class, 'himport')) {
    /**
     * @internal
     */
    trait RedisCluster64ProxyTrait
    {
        public function _digest($value): string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_digest(...\func_get_args());
        }

        public function blmovem($src, $dst, $wherefrom, $whereto, $timeout, $options = null): \RedisCluster|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmovem(...\func_get_args());
        }

        public function delex($key, $options = null): \RedisCluster|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->delex(...\func_get_args());
        }

        public function digest($key): \RedisCluster|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->digest(...\func_get_args());
        }

        public function gcra($key, $maxBurst, $requestsPerPeriod, $period, $tokens = 0): \RedisCluster|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->gcra(...\func_get_args());
        }

        public function himport($op, $hash, $fieldset = null, $fields = []): \RedisCluster|bool|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->himport(...\func_get_args());
        }

        public function lmovem($src, $dst, $wherefrom, $whereto, $options = null): \RedisCluster|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmovem(...\func_get_args());
        }

        public function msetex($key_vals, $expiry = null): \Redis|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetex(...\func_get_args());
        }

        public function sdiffcard($keys, $options = null): \RedisCluster|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffcard(...\func_get_args());
        }

        public function sunioncard($keys, $options = null): \RedisCluster|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunioncard(...\func_get_args());
        }

        public function wait($key_or_address, $numreplicas, $timeout): \RedisCluster|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->wait(...\func_get_args());
        }

        public function xdelex($key, $ids, $mode = null): \RedisCluster|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdelex(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisCluster64ProxyTrait
    {
    }
}
