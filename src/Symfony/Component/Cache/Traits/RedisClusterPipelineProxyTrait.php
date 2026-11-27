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

if (!method_exists(\RedisCluster::class, 'pipeline')) {
    /**
     * @internal
     */
    trait RedisClusterPipelineProxyTrait
    {
        public function exec(): array|false
        {
            return $this->initializeLazyObject()->exec(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RedisClusterPipelineProxyTrait
    {
        public function exec(): \RedisCluster|array|false
        {
            return $this->initializeLazyObject()->exec(...\func_get_args());
        }

        public function pipeline(): \RedisCluster|bool
        {
            return $this->initializeLazyObject()->pipeline(...\func_get_args());
        }
    }
}
