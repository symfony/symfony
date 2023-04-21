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
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct(...\func_get_args());
    }

    public function _compress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress(...\func_get_args());
    }

    public function _uncompress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress(...\func_get_args());
    }

    public function _serialize($value): bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize(...\func_get_args());
    }

    public function _unserialize($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize(...\func_get_args());
    }

    public function _pack($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack(...\func_get_args());
    }

    public function _unpack($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack(...\func_get_args());
    }

    public function _prefix($key): bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix(...\func_get_args());
    }

    public function _masters(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_masters(...\func_get_args());
    }

    public function _redir(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_redir(...\func_get_args());
    }

    public function acl($key_or_address, $subcmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl(...\func_get_args());
    }

    public function append($key, $value): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgsave(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount(...\func_get_args());
    }

    public function bitop($operation, $deskey, $srckey, ...$otherkeys): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos(...\func_get_args());
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpop(...\func_get_args());
    }

    public function brpoplpush($srckey, $deskey, $timeout): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush(...\func_get_args());
    }

    public function lmove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove(...\func_get_args());
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmin(...\func_get_args());
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzmpop(...\func_get_args());
    }

    public function zmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmpop(...\func_get_args());
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmpop(...\func_get_args());
    }

    public function lmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmpop(...\func_get_args());
    }

    public function clearlasterror(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearlasterror(...\func_get_args());
    }

    public function client($key_or_address, $subcommand, $arg = null): array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client(...\func_get_args());
    }

    public function close(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close(...\func_get_args());
    }

    public function cluster($key_or_address, $command, ...$extra_args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->cluster(...\func_get_args());
    }

    public function command(...$extra_args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...\func_get_args());
    }

    public function config($key_or_address, $subcommand, ...$extra_args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config(...\func_get_args());
    }

    public function dbsize($key_or_address): \RedisCluster|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbsize(...\func_get_args());
    }

    public function copy($src, $dst, $options = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->copy(...\func_get_args());
    }

    public function decr($key, $by = 1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr(...\func_get_args());
    }

    public function decrby($key, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrby(...\func_get_args());
    }

    public function decrbyfloat($key, $value): float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrbyfloat(...\func_get_args());
    }

    public function del($key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del(...\func_get_args());
    }

    public function discard(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard(...\func_get_args());
    }

    public function dump($key): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump(...\func_get_args());
    }

    public function echo($key_or_address, $msg): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval(...\func_get_args());
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval_ro(...\func_get_args());
    }

    public function evalsha($script_sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha(...\func_get_args());
    }

    public function evalsha_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha_ro(...\func_get_args());
    }

    public function exec(): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec(...\func_get_args());
    }

    public function exists($key, ...$other_keys): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists(...\func_get_args());
    }

    public function touch($key, ...$other_keys): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->touch(...\func_get_args());
    }

    public function expire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat(...\func_get_args());
    }

    public function expiretime($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expiretime(...\func_get_args());
    }

    public function pexpiretime($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpiretime(...\func_get_args());
    }

    public function flushall($key_or_address, $async = false): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushall(...\func_get_args());
    }

    public function flushdb($key_or_address, $async = false): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushdb(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dest, $unit = null): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geopos(...\func_get_args());
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius_ro(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember_ro(...\func_get_args());
    }

    public function geosearch($key, $position, $shape, $unit, $options = []): \RedisCluster|array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearch(...\func_get_args());
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \RedisCluster|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearchstore(...\func_get_args());
    }

    public function get($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get(...\func_get_args());
    }

    public function getbit($key, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getbit(...\func_get_args());
    }

    public function getlasterror(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getlasterror(...\func_get_args());
    }

    public function getmode(): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getmode(...\func_get_args());
    }

    public function getoption($option): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getoption(...\func_get_args());
    }

    public function getrange($key, $start, $end): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange(...\func_get_args());
    }

    public function lcs($key1, $key2, $options = null): \RedisCluster|array|false|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lcs(...\func_get_args());
    }

    public function getset($key, $value): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset(...\func_get_args());
    }

    public function gettransferredbytes(): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->gettransferredbytes(...\func_get_args());
    }

    public function cleartransferredbytes(): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->cleartransferredbytes(...\func_get_args());
    }

    public function hdel($key, $member, ...$other_members): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hdel(...\func_get_args());
    }

    public function hexists($key, $member): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists(...\func_get_args());
    }

    public function hget($key, $member): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget(...\func_get_args());
    }

    public function hgetall($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall(...\func_get_args());
    }

    public function hincrby($key, $member, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys(...\func_get_args());
    }

    public function hlen($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hlen(...\func_get_args());
    }

    public function hmget($key, $keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget(...\func_get_args());
    }

    public function hmset($key, $key_values): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset(...\func_get_args());
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($key, $iterator, $pattern, $count, ...\array_slice(\func_get_args(), 4));
    }

    public function hrandfield($key, $options = null): \RedisCluster|array|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hrandfield(...\func_get_args());
    }

    public function hset($key, $member, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hset(...\func_get_args());
    }

    public function hsetnx($key, $member, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $field): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen(...\func_get_args());
    }

    public function hvals($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals(...\func_get_args());
    }

    public function incr($key, $by = 1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr(...\func_get_args());
    }

    public function incrby($key, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrbyfloat(...\func_get_args());
    }

    public function info($key_or_address, ...$sections): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info(...\func_get_args());
    }

    public function keys($pattern): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys(...\func_get_args());
    }

    public function lastsave($key_or_address): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastsave(...\func_get_args());
    }

    public function lget($key, $index): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lget(...\func_get_args());
    }

    public function lindex($key, $index): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex(...\func_get_args());
    }

    public function linsert($key, $pos, $pivot, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->linsert(...\func_get_args());
    }

    public function llen($key): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->llen(...\func_get_args());
    }

    public function lpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop(...\func_get_args());
    }

    public function lpos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpos(...\func_get_args());
    }

    public function lpush($key, $value, ...$other_values): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpush(...\func_get_args());
    }

    public function lpushx($key, $value): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $end): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange(...\func_get_args());
    }

    public function lrem($key, $value, $count = 0): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem(...\func_get_args());
    }

    public function lset($key, $index, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $end): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim(...\func_get_args());
    }

    public function mget($keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
    }

    public function mset($key_values): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset(...\func_get_args());
    }

    public function msetnx($key_values): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx(...\func_get_args());
    }

    public function multi($value = \Redis::MULTI): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi(...\func_get_args());
    }

    public function object($subcommand, $key): \RedisCluster|false|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object(...\func_get_args());
    }

    public function persist($key): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist(...\func_get_args());
    }

    public function pexpire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat(...\func_get_args());
    }

    public function pfadd($key, $elements): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd(...\func_get_args());
    }

    public function pfcount($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount(...\func_get_args());
    }

    public function pfmerge($key, $keys): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge(...\func_get_args());
    }

    public function ping($key_or_address, $message = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping(...\func_get_args());
    }

    public function psetex($key, $timeout, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psubscribe(...\func_get_args());
    }

    public function pttl($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl(...\func_get_args());
    }

    public function publish($channel, $message): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish(...\func_get_args());
    }

    public function pubsub($key_or_address, ...$values): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomkey(...\func_get_args());
    }

    public function rawcommand($key_or_address, $command, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand(...\func_get_args());
    }

    public function rename($key_src, $key_dst): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renamenx(...\func_get_args());
    }

    public function restore($key, $timeout, $value, $options = null): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore(...\func_get_args());
    }

    public function role($key_or_address): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role(...\func_get_args());
    }

    public function rpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop(...\func_get_args());
    }

    public function rpoplpush($src, $dst): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush(...\func_get_args());
    }

    public function rpush($key, ...$elements): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpush(...\func_get_args());
    }

    public function rpushx($key, $value): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpushx(...\func_get_args());
    }

    public function sadd($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sadd(...\func_get_args());
    }

    public function saddarray($key, $values): \RedisCluster|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->saddarray(...\func_get_args());
    }

    public function save($key_or_address): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save(...\func_get_args());
    }

    public function scan(&$iterator, $key_or_address, $pattern = null, $count = 0): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($iterator, $key_or_address, $pattern, $count, ...\array_slice(\func_get_args(), 4));
    }

    public function scard($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard(...\func_get_args());
    }

    public function script($key_or_address, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiff(...\func_get_args());
    }

    public function sdiffstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffstore(...\func_get_args());
    }

    public function set($key, $value, $options = null): \RedisCluster|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set(...\func_get_args());
    }

    public function setbit($key, $offset, $onoff): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setbit(...\func_get_args());
    }

    public function setex($key, $expire, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex(...\func_get_args());
    }

    public function setnx($key, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx(...\func_get_args());
    }

    public function setoption($option, $value): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setoption(...\func_get_args());
    }

    public function setrange($key, $offset, $value): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sintercard(...\func_get_args());
    }

    public function sinterstore($key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinterstore(...\func_get_args());
    }

    public function sismember($key, $value): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember(...\func_get_args());
    }

    public function smismember($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smismember(...\func_get_args());
    }

    public function slowlog($key_or_address, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog(...\func_get_args());
    }

    public function smembers($key): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smembers(...\func_get_args());
    }

    public function smove($src, $dst, $member): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smove(...\func_get_args());
    }

    public function sort($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort(...\func_get_args());
    }

    public function sort_ro($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro(...\func_get_args());
    }

    public function spop($key, $count = 0): \RedisCluster|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop(...\func_get_args());
    }

    public function srandmember($key, $count = 0): \RedisCluster|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember(...\func_get_args());
    }

    public function srem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem(...\func_get_args());
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($key, $iterator, $pattern, $count, ...\array_slice(\func_get_args(), 4));
    }

    public function strlen($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen(...\func_get_args());
    }

    public function subscribe($channels, $cb): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->subscribe(...\func_get_args());
    }

    public function sunion($key, ...$other_keys): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunion(...\func_get_args());
    }

    public function sunionstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunionstore(...\func_get_args());
    }

    public function time($key_or_address): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time(...\func_get_args());
    }

    public function ttl($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl(...\func_get_args());
    }

    public function type($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type(...\func_get_args());
    }

    public function unsubscribe($channels): array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe(...\func_get_args());
    }

    public function unlink($key, ...$other_keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink(...\func_get_args());
    }

    public function unwatch(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch(...\func_get_args());
    }

    public function watch($key, ...$other_keys): \RedisCluster|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch(...\func_get_args());
    }

    public function xack($key, $group, $ids): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack(...\func_get_args());
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false): \RedisCluster|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xadd(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_iddle, $ids, $options): \RedisCluster|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim(...\func_get_args());
    }

    public function xdel($key, $ids): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup(...\func_get_args());
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xautoclaim(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo(...\func_get_args());
    }

    public function xlen($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread(...\func_get_args());
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup(...\func_get_args());
    }

    public function xrevrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange(...\func_get_args());
    }

    public function xtrim($key, $maxlen, $approx = false, $minid = false, $limit = -1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim(...\func_get_args());
    }

    public function zadd($key, $score_or_options, ...$more_scores_and_mems): \RedisCluster|false|float|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zadd(...\func_get_args());
    }

    public function zcard($key): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcard(...\func_get_args());
    }

    public function zcount($key, $start, $end): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcount(...\func_get_args());
    }

    public function zincrby($key, $value, $member): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zincrby(...\func_get_args());
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zintercard(...\func_get_args());
    }

    public function zlexcount($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zlexcount(...\func_get_args());
    }

    public function zpopmax($key, $value = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax(...\func_get_args());
    }

    public function zpopmin($key, $value = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin(...\func_get_args());
    }

    public function zrange($key, $start, $end, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrange(...\func_get_args());
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangestore(...\func_get_args());
    }

    public function zrandmember($key, $options = null): \RedisCluster|array|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrandmember(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = []): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebyscore(...\func_get_args());
    }

    public function zrank($key, $member): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrank(...\func_get_args());
    }

    public function zrem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $member): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrank(...\func_get_args());
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \RedisCluster|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($key, $iterator, $pattern, $count, ...\array_slice(\func_get_args(), 4));
    }

    public function zscore($key, $member): \RedisCluster|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscore(...\func_get_args());
    }

    public function zmscore($key, $member, ...$other_members): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmscore(...\func_get_args());
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore(...\func_get_args());
    }

    public function zinter($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinter(...\func_get_args());
    }

    public function zdiffstore($dst, $keys): \RedisCluster|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiffstore(...\func_get_args());
    }

    public function zunion($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunion(...\func_get_args());
    }

    public function zdiff($keys, $options = null): \RedisCluster|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiff(...\func_get_args());
    }
}
