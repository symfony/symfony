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

use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Component\VarExporter\LazyProxyTrait;
use Symfony\Contracts\Service\ResetInterface;

// Help opcache.preload discover always-needed symbols
class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

/**
 * @internal
 */
class RedisCluster6Proxy extends \RedisCluster implements ResetInterface, LazyObjectInterface
{
    use LazyProxyTrait {
        resetLazyObject as reset;
    }

    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

    public function __construct($name, $seeds = null, $timeout = 0, $read_timeout = 0, $persistent = false, #[\SensitiveParameter] $auth = null, $context = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct($name, $seeds, $timeout, $read_timeout, $persistent, $auth, $context);
    }

    public function _compress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress($value);
    }

    public function _uncompress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress($value);
    }

    public function _serialize($value): bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize($value);
    }

    public function _unserialize($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize($value);
    }

    public function _pack($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack($value);
    }

    public function _unpack($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack($value);
    }

    public function _prefix($key): bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix($key);
    }

    public function _masters(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_masters();
    }

    public function _redir(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_redir();
    }

    public function acl($key_or_address, $subcmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl($key_or_address, $subcmd, ...$args);
    }

    public function append($key, $value): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append($key, $value);
    }

    public function bgrewriteaof($key_or_address): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof($key_or_address);
    }

    public function bgsave($key_or_address): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgsave($key_or_address);
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount($key, $start, $end, $bybit);
    }

    public function bitop($operation, $deskey, $srckey, ...$otherkeys): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop($operation, $deskey, $srckey, ...$otherkeys);
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos($key, $bit, $start, $end, $bybit);
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($srckey, $deskey, $timeout): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush($srckey, $deskey, $timeout);
    }

    public function lmove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove($src, $dst, $wherefrom, $whereto);
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove($src, $dst, $wherefrom, $whereto, $timeout);
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmin($key, $timeout_or_key, ...$extra_args);
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzmpop($timeout, $keys, $from, $count);
    }

    public function zmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmpop($keys, $from, $count);
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmpop($timeout, $keys, $from, $count);
    }

    public function lmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmpop($keys, $from, $count);
    }

    public function clearlasterror(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearlasterror();
    }

    public function client($key_or_address, $subcommand, $arg = null): array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client($key_or_address, $subcommand, $arg);
    }

    public function close(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close();
    }

    public function cluster($key_or_address, $command, ...$extra_args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->cluster($key_or_address, $command, ...$extra_args);
    }

    public function command(...$extra_args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...$extra_args);
    }

    public function config($key_or_address, $subcommand, ...$extra_args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config($key_or_address, $subcommand, ...$extra_args);
    }

    public function dbsize($key_or_address): \RedisCluster|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbsize($key_or_address);
    }

    public function copy($src, $dst, $options = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->copy($src, $dst, $options);
    }

    public function decr($key, $by = 1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr($key, $by);
    }

    public function decrby($key, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrby($key, $value);
    }

    public function decrbyfloat($key, $value): float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrbyfloat($key, $value);
    }

    public function del($key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del($key, ...$other_keys);
    }

    public function discard(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard();
    }

    public function dump($key): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump($key);
    }

    public function echo($key_or_address, $msg): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo($key_or_address, $msg);
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval($script, $args, $num_keys);
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval_ro($script, $args, $num_keys);
    }

    public function evalsha($script_sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha($script_sha, $args, $num_keys);
    }

    public function evalsha_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha_ro($script_sha, $args, $num_keys);
    }

    public function exec(): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec();
    }

    public function exists($key, ...$other_keys): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists($key, ...$other_keys);
    }

    public function touch($key, ...$other_keys): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->touch($key, ...$other_keys);
    }

    public function expire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire($key, $timeout, $mode);
    }

    public function expireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat($key, $timestamp, $mode);
    }

    public function expiretime($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expiretime($key);
    }

    public function pexpiretime($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpiretime($key);
    }

    public function flushall($key_or_address, $async = false): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushall($key_or_address, $async);
    }

    public function flushdb($key_or_address, $async = false): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushdb($key_or_address, $async);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd($key, $lng, $lat, $member, ...$other_triples_and_options);
    }

    public function geodist($key, $src, $dest, $unit = null): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geodist($key, $src, $dest, $unit);
    }

    public function geohash($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash($key, $member, ...$other_members);
    }

    public function geopos($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geopos($key, $member, ...$other_members);
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius($key, $lng, $lat, $radius, $unit, $options);
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius_ro($key, $lng, $lat, $radius, $unit, $options);
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember($key, $member, $radius, $unit, $options);
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember_ro($key, $member, $radius, $unit, $options);
    }

    public function geosearch($key, $position, $shape, $unit, $options = []): \RedisCluster|array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearch($key, $position, $shape, $unit, $options);
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \RedisCluster|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearchstore($dst, $src, $position, $shape, $unit, $options);
    }

    public function get($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get($key);
    }

    public function getbit($key, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getbit($key, $value);
    }

    public function getlasterror(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getlasterror();
    }

    public function getmode(): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getmode();
    }

    public function getoption($option): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getoption($option);
    }

    public function getrange($key, $start, $end): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange($key, $start, $end);
    }

    public function lcs($key1, $key2, $options = null): \RedisCluster|array|false|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lcs($key1, $key2, $options);
    }

    public function getset($key, $value): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset($key, $value);
    }

    public function gettransferredbytes(): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->gettransferredbytes();
    }

    public function cleartransferredbytes(): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->cleartransferredbytes();
    }

    public function hdel($key, $member, ...$other_members): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hdel($key, $member, ...$other_members);
    }

    public function hexists($key, $member): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists($key, $member);
    }

    public function hget($key, $member): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget($key, $member);
    }

    public function hgetall($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall($key);
    }

    public function hincrby($key, $member, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrby($key, $member, $value);
    }

    public function hincrbyfloat($key, $member, $value): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrbyfloat($key, $member, $value);
    }

    public function hkeys($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys($key);
    }

    public function hlen($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hlen($key);
    }

    public function hmget($key, $keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget($key, $keys);
    }

    public function hmset($key, $key_values): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset($key, $key_values);
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($key, $iterator, $pattern, $count);
    }

    public function hrandfield($key, $options = null): \RedisCluster|array|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hrandfield($key, $options);
    }

    public function hset($key, $member, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hset($key, $member, $value);
    }

    public function hsetnx($key, $member, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx($key, $member, $value);
    }

    public function hstrlen($key, $field): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen($key, $field);
    }

    public function hvals($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals($key);
    }

    public function incr($key, $by = 1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr($key, $by);
    }

    public function incrby($key, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrby($key, $value);
    }

    public function incrbyfloat($key, $value): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrbyfloat($key, $value);
    }

    public function info($key_or_address, ...$sections): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info($key_or_address, ...$sections);
    }

    public function keys($pattern): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys($pattern);
    }

    public function lastsave($key_or_address): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastsave($key_or_address);
    }

    public function lget($key, $index): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lget($key, $index);
    }

    public function lindex($key, $index): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex($key, $index);
    }

    public function linsert($key, $pos, $pivot, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->linsert($key, $pos, $pivot, $value);
    }

    public function llen($key): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->llen($key);
    }

    public function lpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop($key, $count);
    }

    public function lpos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpos($key, $value, $options);
    }

    public function lpush($key, $value, ...$other_values): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpush($key, $value, ...$other_values);
    }

    public function lpushx($key, $value): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpushx($key, $value);
    }

    public function lrange($key, $start, $end): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange($key, $start, $end);
    }

    public function lrem($key, $value, $count = 0): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem($key, $value, $count);
    }

    public function lset($key, $index, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lset($key, $index, $value);
    }

    public function ltrim($key, $start, $end): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim($key, $start, $end);
    }

    public function mget($keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget($keys);
    }

    public function mset($key_values): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset($key_values);
    }

    public function msetnx($key_values): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx($key_values);
    }

    public function multi($value = \Redis::MULTI): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi($value);
    }

    public function object($subcommand, $key): \RedisCluster|false|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object($subcommand, $key);
    }

    public function persist($key): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist($key);
    }

    public function pexpire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire($key, $timeout, $mode);
    }

    public function pexpireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat($key, $timestamp, $mode);
    }

    public function pfadd($key, $elements): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd($key, $elements);
    }

    public function pfcount($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount($key);
    }

    public function pfmerge($key, $keys): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge($key, $keys);
    }

    public function ping($key_or_address, $message = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping($key_or_address, $message);
    }

    public function psetex($key, $timeout, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex($key, $timeout, $value);
    }

    public function psubscribe($patterns, $callback): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psubscribe($patterns, $callback);
    }

    public function pttl($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl($key);
    }

    public function publish($channel, $message): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish($channel, $message);
    }

    public function pubsub($key_or_address, ...$values): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub($key_or_address, ...$values);
    }

    public function punsubscribe($pattern, ...$other_patterns): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe($pattern, ...$other_patterns);
    }

    public function randomkey($key_or_address): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomkey($key_or_address);
    }

    public function rawcommand($key_or_address, $command, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand($key_or_address, $command, ...$args);
    }

    public function rename($key_src, $key_dst): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename($key_src, $key_dst);
    }

    public function renamenx($key, $newkey): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renamenx($key, $newkey);
    }

    public function restore($key, $timeout, $value, $options = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore($key, $timeout, $value, $options);
    }

    public function role($key_or_address): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role($key_or_address);
    }

    public function rpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop($key, $count);
    }

    public function rpoplpush($src, $dst): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush($src, $dst);
    }

    public function rpush($key, ...$elements): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpush($key, ...$elements);
    }

    public function rpushx($key, $value): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpushx($key, $value);
    }

    public function sadd($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sadd($key, $value, ...$other_values);
    }

    public function saddarray($key, $values): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->saddarray($key, $values);
    }

    public function save($key_or_address): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save($key_or_address);
    }

    public function scan(&$iterator, $key_or_address, $pattern = null, $count = 0): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($iterator, $key_or_address, $pattern, $count);
    }

    public function scard($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard($key);
    }

    public function script($key_or_address, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script($key_or_address, ...$args);
    }

    public function sdiff($key, ...$other_keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiff($key, ...$other_keys);
    }

    public function sdiffstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffstore($dst, $key, ...$other_keys);
    }

    public function set($key, $value, $options = null): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set($key, $value, $options);
    }

    public function setbit($key, $offset, $onoff): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setbit($key, $offset, $onoff);
    }

    public function setex($key, $expire, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex($key, $expire, $value);
    }

    public function setnx($key, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx($key, $value);
    }

    public function setoption($option, $value): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setoption($option, $value);
    }

    public function setrange($key, $offset, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setrange($key, $offset, $value);
    }

    public function sinter($key, ...$other_keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinter($key, ...$other_keys);
    }

    public function sintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sintercard($keys, $limit);
    }

    public function sinterstore($key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinterstore($key, ...$other_keys);
    }

    public function sismember($key, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember($key, $value);
    }

    public function smismember($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smismember($key, $member, ...$other_members);
    }

    public function slowlog($key_or_address, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog($key_or_address, ...$args);
    }

    public function smembers($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smembers($key);
    }

    public function smove($src, $dst, $member): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smove($src, $dst, $member);
    }

    public function sort($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort($key, $options);
    }

    public function sort_ro($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro($key, $options);
    }

    public function spop($key, $count = 0): \RedisCluster|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop($key, $count);
    }

    public function srandmember($key, $count = 0): \RedisCluster|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember($key, $count);
    }

    public function srem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem($key, $value, ...$other_values);
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($key, $iterator, $pattern, $count);
    }

    public function strlen($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen($key);
    }

    public function subscribe($channels, $cb): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->subscribe($channels, $cb);
    }

    public function sunion($key, ...$other_keys): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunion($key, ...$other_keys);
    }

    public function sunionstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunionstore($dst, $key, ...$other_keys);
    }

    public function time($key_or_address): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time($key_or_address);
    }

    public function ttl($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl($key);
    }

    public function type($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type($key);
    }

    public function unsubscribe($channels): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe($channels);
    }

    public function unlink($key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink($key, ...$other_keys);
    }

    public function unwatch(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch();
    }

    public function watch($key, ...$other_keys): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch($key, ...$other_keys);
    }

    public function xack($key, $group, $ids): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack($key, $group, $ids);
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xadd($key, $id, $values, $maxlen, $approx);
    }

    public function xclaim($key, $group, $consumer, $min_iddle, $ids, $options): \RedisCluster|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim($key, $group, $consumer, $min_iddle, $ids, $options);
    }

    public function xdel($key, $ids): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel($key, $ids);
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup($operation, $key, $group, $id_or_consumer, $mkstream, $entries_read);
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xautoclaim($key, $group, $consumer, $min_idle, $start, $count, $justid);
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo($operation, $arg1, $arg2, $count);
    }

    public function xlen($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen($key);
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending($key, $group, $start, $end, $count, $consumer);
    }

    public function xrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange($key, $start, $end, $count);
    }

    public function xread($streams, $count = -1, $block = -1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread($streams, $count, $block);
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup($group, $consumer, $streams, $count, $block);
    }

    public function xrevrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange($key, $start, $end, $count);
    }

    public function xtrim($key, $maxlen, $approx = false, $minid = false, $limit = -1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim($key, $maxlen, $approx, $minid, $limit);
    }

    public function zadd($key, $score_or_options, ...$more_scores_and_mems): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zadd($key, $score_or_options, ...$more_scores_and_mems);
    }

    public function zcard($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcard($key);
    }

    public function zcount($key, $start, $end): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcount($key, $start, $end);
    }

    public function zincrby($key, $value, $member): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zincrby($key, $value, $member);
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore($dst, $keys, $weights, $aggregate);
    }

    public function zintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zintercard($keys, $limit);
    }

    public function zlexcount($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zlexcount($key, $min, $max);
    }

    public function zpopmax($key, $value = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax($key, $value);
    }

    public function zpopmin($key, $value = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin($key, $value);
    }

    public function zrange($key, $start, $end, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrange($key, $start, $end, $options);
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangestore($dstkey, $srckey, $start, $end, $options);
    }

    public function zrandmember($key, $options = null): \RedisCluster|array|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrandmember($key, $options);
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebylex($key, $min, $max, $offset, $count);
    }

    public function zrangebyscore($key, $start, $end, $options = []): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebyscore($key, $start, $end, $options);
    }

    public function zrank($key, $member): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrank($key, $member);
    }

    public function zrem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrem($key, $value, ...$other_values);
    }

    public function zremrangebylex($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebylex($key, $min, $max);
    }

    public function zremrangebyrank($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyrank($key, $min, $max);
    }

    public function zremrangebyscore($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyscore($key, $min, $max);
    }

    public function zrevrange($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrange($key, $min, $max, $options);
    }

    public function zrevrangebylex($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex($key, $min, $max, $options);
    }

    public function zrevrangebyscore($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebyscore($key, $min, $max, $options);
    }

    public function zrevrank($key, $member): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrank($key, $member);
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($key, $iterator, $pattern, $count);
    }

    public function zscore($key, $member): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscore($key, $member);
    }

    public function zmscore($key, $member, ...$other_members): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmscore($key, $member, ...$other_members);
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore($dst, $keys, $weights, $aggregate);
    }

    public function zinter($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinter($keys, $weights, $options);
    }

    public function zdiffstore($dst, $keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiffstore($dst, $keys);
    }

    public function zunion($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunion($keys, $weights, $options);
    }

    public function zdiff($keys, $options = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiff($keys, $options);
    }
}
