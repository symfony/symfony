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

if (version_compare(phpversion('redis'), '6.1.0-dev', '>=')) {
    /**
     * @internal
     */
    trait Redis6ProxyTrait
    {
        public function dump($key): \Redis|string|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump(...\func_get_args());
        }

        public function hRandField($key, $options = null): \Redis|array|string|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hRandField(...\func_get_args());
        }

        public function hSet($key, ...$fields_and_vals): \Redis|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSet(...\func_get_args());
        }

        public function mget($keys): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
        }

        public function sRandMember($key, $count = 0): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRandMember(...\func_get_args());
        }

        public function waitaof($numlocal, $numreplicas, $timeout): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->waitaof(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Redis6ProxyTrait
    {
        public function dump($key): \Redis|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump(...\func_get_args());
        }

        public function hRandField($key, $options = null): \Redis|array|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hRandField(...\func_get_args());
        }

        public function hSet($key, $member, $value): \Redis|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSet(...\func_get_args());
        }

        public function mget($keys): \Redis|array
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
        }

        public function sRandMember($key, $count = 0): \Redis|array|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRandMember(...\func_get_args());
        }
    }
}
