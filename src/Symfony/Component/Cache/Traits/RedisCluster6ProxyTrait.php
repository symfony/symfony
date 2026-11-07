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

if (method_exists(\RedisCluster::class, 'lmove') && str_contains((string) (new \ReflectionMethod(\RedisCluster::class, 'lmove'))->getReturnType(), 'RedisCluster')) {
    /**
     * @internal
     */
    trait RedisCluster6ProxyTrait
    {
        public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \RedisCluster|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove(...\func_get_args());
        }

        public function lmove($src, $dst, $wherefrom, $whereto): \RedisCluster|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove(...\func_get_args());
        }

        public function lpos($key, $value, $options = null): \RedisCluster|array|bool|int|null
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpos(...\func_get_args());
        }

        public function zmscore($key, $member, ...$other_members): \RedisCluster|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmscore(...\array_slice(\func_get_args(), 0, 2), ...$other_members);
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisCluster6ProxyTrait
    {
        public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove(...\func_get_args());
        }

        public function lmove($src, $dst, $wherefrom, $whereto): \Redis|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove(...\func_get_args());
        }

        public function lpos($key, $value, $options = null): \Redis|array|bool|int|null
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpos(...\func_get_args());
        }

        public function zmscore($key, $member, ...$other_members): \Redis|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmscore(...\func_get_args());
        }
    }
}
