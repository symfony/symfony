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
    trait Relay502Trait
    {
        public function __construct($host = null, $port = 6379, $connect_timeout = 0.0, $command_timeout = 0.0, #[\SensitiveParameter] $context = null, $database = 0)
        {
            ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct(...\func_get_args());
        }

        public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = null, $database = 0): bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect(...\func_get_args());
        }

        public function expireat($key, $timestamp, $mode = null): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat(...\func_get_args());
        }

        public function hexists($key, $member): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists(...\func_get_args());
        }

        public function hget($key, $member): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget(...\func_get_args());
        }

        public function hgetall($key): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall(...\func_get_args());
        }

        public function hkeys($key): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys(...\func_get_args());
        }

        public function hmget($key, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget(...\func_get_args());
        }

        public function hmset($key, $members): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset(...\func_get_args());
        }

        public function hsetnx($key, $member, $value): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx(...\func_get_args());
        }

        public function hstrlen($key, $member): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen(...\func_get_args());
        }

        public function hvals($key): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals(...\func_get_args());
        }

        public function lpop($key, $count = 0): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop(...\func_get_args());
        }

        public function multi($mode = \Relay\Relay::MULTI): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi(...\func_get_args());
        }

        public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = null, $database = 0): bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect(...\func_get_args());
        }

        public function pexpire($key, $milliseconds, $mode = null): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire(...\func_get_args());
        }

        public function pexpireat($key, $timestamp_ms, $mode = null): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat(...\func_get_args());
        }

        public function rpop($key, $count = 0): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop(...\func_get_args());
        }

        public function sort($key, $options = null): \Relay\Relay|array|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort(...\func_get_args());
        }

        public function sort_ro($key, $options = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro(...\func_get_args());
        }

        public function spop($set, $count = 0): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop(...\func_get_args());
        }

        public function srandmember($set, $count = 0): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember(...\func_get_args());
        }

        public function zpopmax($key, $count = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax(...\func_get_args());
        }

        public function zpopmin($key, $count = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay502Trait
    {
        public function __construct($host = null, $port = 6379, $connect_timeout = 0.0, $command_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0)
        {
            ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct(...\func_get_args());
        }

        public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect(...\func_get_args());
        }

        public function expireat($key, $timestamp): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat(...\func_get_args());
        }

        public function hexists($hash, $member): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists(...\func_get_args());
        }

        public function hget($hash, $member): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget(...\func_get_args());
        }

        public function hgetall($hash): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall(...\func_get_args());
        }

        public function hkeys($hash): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys(...\func_get_args());
        }

        public function hmget($hash, $members): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget(...\func_get_args());
        }

        public function hmset($hash, $members): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset(...\func_get_args());
        }

        public function hsetnx($hash, $member, $value): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx(...\func_get_args());
        }

        public function hstrlen($hash, $member): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen(...\func_get_args());
        }

        public function hvals($hash): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals(...\func_get_args());
        }

        public function lpop($key, $count = 1): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop(...\func_get_args());
        }

        public function multi($mode = 0): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi(...\func_get_args());
        }

        public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect(...\func_get_args());
        }

        public function pexpire($key, $milliseconds): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire(...\func_get_args());
        }

        public function pexpireat($key, $timestamp_ms): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat(...\func_get_args());
        }

        public function rpop($key, $count = 1): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop(...\func_get_args());
        }

        public function sort($key, $options = []): \Relay\Relay|array|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort(...\func_get_args());
        }

        public function sort_ro($key, $options = []): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro(...\func_get_args());
        }

        public function spop($set, $count = 1): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop(...\func_get_args());
        }

        public function srandmember($set, $count = 1): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember(...\func_get_args());
        }

        public function zpopmax($key, $count = 1): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax(...\func_get_args());
        }

        public function zpopmin($key, $count = 1): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin(...\func_get_args());
        }
    }
}
