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

if (version_compare(phpversion('redis'), '6.3.0', '>=')) {
    /**
     * @internal
     */
    trait RedisCluster63ProxyTrait
    {
        public function delifeq($key, $value): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->delifeq(...\func_get_args());
        }

        public function hexpire($key, $ttl, $fields, $mode = null): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hexpire(...\func_get_args());
        }

        public function hexpireat($key, $time, $fields, $mode = null): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hexpireat(...\func_get_args());
        }

        public function hexpiretime($key, $fields): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hexpiretime(...\func_get_args());
        }

        public function hgetdel($key, $fields): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hgetdel(...\func_get_args());
        }

        public function hgetex($key, $fields, $expiry = null): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hgetex(...\func_get_args());
        }

        public function hgetWithMeta($key, $member): mixed
        {
            return $this->initializeLazyObject()->hgetWithMeta(...\func_get_args());
        }

        public function hpersist($key, $fields): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hpersist(...\func_get_args());
        }

        public function hpexpire($key, $ttl, $fields, $mode = null): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hpexpire(...\func_get_args());
        }

        public function hpexpireat($key, $mstime, $fields, $mode = null): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hpexpireat(...\func_get_args());
        }

        public function hpexpiretime($key, $fields): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hpexpiretime(...\func_get_args());
        }

        public function hpttl($key, $fields): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->hpttl(...\func_get_args());
        }

        public function hsetex($key, $fields, $expiry = null): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->hsetex(...\func_get_args());
        }

        public function httl($key, $fields): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->httl(...\func_get_args());
        }

        public function vadd($key, $values, $element, $options = null): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->vadd(...\func_get_args());
        }

        public function vcard($key): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->vcard(...\func_get_args());
        }

        public function vdim($key): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->vdim(...\func_get_args());
        }

        public function vemb($key, $member, $raw = false): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->vemb(...\func_get_args());
        }

        public function vgetattr($key, $member, $decode = true): \RedisCluster|array|string|false
        {
            return $this->initializeLazyObject()->vgetattr(...\func_get_args());
        }

        public function vinfo($key): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->vinfo(...\func_get_args());
        }

        public function vismember($key, $member): \RedisCluster|bool
        {
            return $this->initializeLazyObject()->vismember(...\func_get_args());
        }

        public function vlinks($key, $member, $withscores = false): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->vlinks(...\func_get_args());
        }

        public function vrandmember($key, $count = 0): \RedisCluster|array|string|false
        {
            return $this->initializeLazyObject()->vrandmember(...\func_get_args());
        }

        public function vrange($key, $min, $max, $count = -1): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->vrange(...\func_get_args());
        }

        public function vrem($key, $member): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->vrem(...\func_get_args());
        }

        public function vsetattr($key, $member, $attributes): \RedisCluster|int|false
        {
            return $this->initializeLazyObject()->vsetattr(...\func_get_args());
        }

        public function vsim($key, $member, $options = null): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->vsim(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisCluster63ProxyTrait
    {
    }
}
