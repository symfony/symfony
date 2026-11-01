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

if (method_exists(\RedisCluster::class, 'zrevrangebylex') && 5 === (new \ReflectionMethod(\RedisCluster::class, 'zrevrangebylex'))->getNumberOfParameters()) {
    /**
     * @internal
     */
    trait RedisCluster6ZRevRangeByLexProxyTrait
    {
        public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \RedisCluster|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisCluster6ZRevRangeByLexProxyTrait
    {
        public function zrevrangebylex($key, $min, $max, $options = null): \RedisCluster|array|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex(...\func_get_args());
        }
    }
}
