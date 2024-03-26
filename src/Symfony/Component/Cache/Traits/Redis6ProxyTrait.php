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

if (version_compare(phpversion('redis'), '6.0.2', '>')) {
    /**
     * @internal
     */
    trait Redis6ProxyTrait
    {
        public function dump($key): \Redis|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump(...\func_get_args());
        }

        public function mget($keys): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
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

        public function mget($keys): \Redis|array
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
        }
    }
}
