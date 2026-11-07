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

if (version_compare(phpversion('redis'), '6.3.0', '>=') || method_exists(\Redis::class, 'hgetWithMeta')) {
    /**
     * @internal
     */
    trait Redis63ProxyTrait
    {
        public function delifeq($key, $value): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->delifeq(...\func_get_args());
        }

        public function hexpire($key, $ttl, $fields, $mode = null): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexpire(...\func_get_args());
        }

        public function hexpireat($key, $time, $fields, $mode = null): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexpireat(...\func_get_args());
        }

        public function hexpiretime($key, $fields): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexpiretime(...\func_get_args());
        }

        public function hgetdel($key, $fields): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetdel(...\func_get_args());
        }

        public function hgetex($key, $fields, $expiry = null): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetex(...\func_get_args());
        }

        public function hGetWithMeta($key, $member): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGetWithMeta(...\func_get_args());
        }

        public function hpersist($key, $fields): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hpersist(...\func_get_args());
        }

        public function hpexpire($key, $ttl, $fields, $mode = null): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hpexpire(...\func_get_args());
        }

        public function hpexpireat($key, $mstime, $fields, $mode = null): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hpexpireat(...\func_get_args());
        }

        public function hpexpiretime($key, $fields): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hpexpiretime(...\func_get_args());
        }

        public function hpttl($key, $fields): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hpttl(...\func_get_args());
        }

        public function hsetex($key, $fields, $expiry = null): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetex(...\func_get_args());
        }

        public function httl($key, $fields): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->httl(...\func_get_args());
        }

        public function vadd($key, $values, $element, $options = null): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vadd(...\func_get_args());
        }

        public function vcard($key): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vcard(...\func_get_args());
        }

        public function vdim($key): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vdim(...\func_get_args());
        }

        public function vemb($key, $member, $raw = false): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vemb(...\func_get_args());
        }

        public function vgetattr($key, $member, $decode = true): \Redis|array|string|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vgetattr(...\func_get_args());
        }

        public function vinfo($key): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vinfo(...\func_get_args());
        }

        public function vismember($key, $member): \Redis|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vismember(...\func_get_args());
        }

        public function vlinks($key, $member, $withscores = false): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vlinks(...\func_get_args());
        }

        public function vrandmember($key, $count = 0): \Redis|array|string|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vrandmember(...\func_get_args());
        }

        public function vrange($key, $min, $max, $count = -1): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vrange(...\func_get_args());
        }

        public function vrem($key, $member): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vrem(...\func_get_args());
        }

        public function vsetattr($key, $member, $attributes): \Redis|int|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vsetattr(...\func_get_args());
        }

        public function vsim($key, $member, $options = null): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->vsim(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Redis63ProxyTrait
    {
    }
}
