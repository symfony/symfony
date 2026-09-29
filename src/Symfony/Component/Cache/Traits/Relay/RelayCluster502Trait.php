<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits\Relay;

if (version_compare(phpversion('relay'), '0.50.2', '>=')) {
    /**
     * @internal
     */
    trait RelayCluster502Trait
    {
        public function expireat($key, $timestamp, $mode = null): \Relay\Cluster|bool
        {
            return $this->initializeLazyObject()->expireat(...\func_get_args());
        }

        public function lpop($key, $count = 0): mixed
        {
            return $this->initializeLazyObject()->lpop(...\func_get_args());
        }

        public function pexpire($key, $milliseconds, $mode = null): \Relay\Cluster|bool
        {
            return $this->initializeLazyObject()->pexpire(...\func_get_args());
        }

        public function pexpireat($key, $timestamp_ms, $mode = null): \Relay\Cluster|bool
        {
            return $this->initializeLazyObject()->pexpireat(...\func_get_args());
        }

        public function rpop($key, $count = 0): mixed
        {
            return $this->initializeLazyObject()->rpop(...\func_get_args());
        }

        public function sort($key, $options = null): \Relay\Cluster|array|false|int
        {
            return $this->initializeLazyObject()->sort(...\func_get_args());
        }

        public function sort_ro($key, $options = null): \Relay\Cluster|array|false|int
        {
            return $this->initializeLazyObject()->sort_ro(...\func_get_args());
        }

        public function spop($key, $count = 0): mixed
        {
            return $this->initializeLazyObject()->spop(...\func_get_args());
        }

        public function srandmember($key, $count = 0): mixed
        {
            return $this->initializeLazyObject()->srandmember(...\func_get_args());
        }

        public function vlinks($key, $element, $withscores = false): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->vlinks(...\func_get_args());
        }

        public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Relay\Cluster|array|bool|null
        {
            return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
        }

        public function zpopmax($key, $count = null): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->zpopmax(...\func_get_args());
        }

        public function zpopmin($key, $count = null): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->zpopmin(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RelayCluster502Trait
    {
        public function expireat($key, $timestamp): \Relay\Cluster|bool
        {
            return $this->initializeLazyObject()->expireat(...\func_get_args());
        }

        public function lpop($key, $count = 1): mixed
        {
            return $this->initializeLazyObject()->lpop(...\func_get_args());
        }

        public function pexpire($key, $milliseconds): \Relay\Cluster|bool
        {
            return $this->initializeLazyObject()->pexpire(...\func_get_args());
        }

        public function pexpireat($key, $timestamp_ms): \Relay\Cluster|bool
        {
            return $this->initializeLazyObject()->pexpireat(...\func_get_args());
        }

        public function rpop($key, $count = 1): mixed
        {
            return $this->initializeLazyObject()->rpop(...\func_get_args());
        }

        public function sort($key, $options = []): \Relay\Cluster|array|false|int
        {
            return $this->initializeLazyObject()->sort(...\func_get_args());
        }

        public function sort_ro($key, $options = []): \Relay\Cluster|array|false|int
        {
            return $this->initializeLazyObject()->sort_ro(...\func_get_args());
        }

        public function spop($key, $count = 1): mixed
        {
            return $this->initializeLazyObject()->spop(...\func_get_args());
        }

        public function srandmember($key, $count = 1): mixed
        {
            return $this->initializeLazyObject()->srandmember(...\func_get_args());
        }

        public function vlinks($key, $element, $withscores): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->vlinks(...\func_get_args());
        }

        public function xreadgroup($key, $consumer, $streams, $count = 1, $block = 1): \Relay\Cluster|array|bool|null
        {
            return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
        }

        public function zpopmax($key, $count = 1): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->zpopmax(...\func_get_args());
        }

        public function zpopmin($key, $count = 1): \Relay\Cluster|array|false
        {
            return $this->initializeLazyObject()->zpopmin(...\func_get_args());
        }
    }
}
