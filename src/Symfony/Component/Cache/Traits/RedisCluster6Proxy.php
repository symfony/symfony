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
    use RedisCluster6ProxyTrait;
    use RedisProxyTrait {
        resetLazyObject as reset;
    }

    public function __construct($name, $seeds = null, $timeout = 0, $read_timeout = 0, $persistent = false, #[\SensitiveParameter] $auth = null, $context = null)
    {
        $this->initializeLazyObject()->__construct(...\func_get_args());
    }

    public function _compress($value): string
    {
        return $this->initializeLazyObject()->_compress(...\func_get_args());
    }

    public function _uncompress($value): string
    {
        return $this->initializeLazyObject()->_uncompress(...\func_get_args());
    }

    public function _serialize($value): bool|string
    {
        return $this->initializeLazyObject()->_serialize(...\func_get_args());
    }

    public function _unserialize($value): mixed
    {
        return $this->initializeLazyObject()->_unserialize(...\func_get_args());
    }

    public function _pack($value): string
    {
        return $this->initializeLazyObject()->_pack(...\func_get_args());
    }

    public function _unpack($value): mixed
    {
        return $this->initializeLazyObject()->_unpack(...\func_get_args());
    }

    public function _prefix($key): bool|string
    {
        return $this->initializeLazyObject()->_prefix(...\func_get_args());
    }

    public function _masters(): array
    {
        return $this->initializeLazyObject()->_masters(...\func_get_args());
    }

    public function _redir(): ?string
    {
        return $this->initializeLazyObject()->_redir(...\func_get_args());
    }

    public function acl($key_or_address, $subcmd, ...$args): mixed
    {
        return $this->initializeLazyObject()->acl(...\func_get_args());
    }

    public function append($key, $value): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->bgsave(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->bitcount(...\func_get_args());
    }

    public function bitop($operation, $deskey, $srckey, ...$otherkeys): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->bitpos(...\func_get_args());
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return $this->initializeLazyObject()->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return $this->initializeLazyObject()->brpop(...\func_get_args());
    }

    public function brpoplpush($srckey, $deskey, $timeout): mixed
    {
        return $this->initializeLazyObject()->brpoplpush(...\func_get_args());
    }

    public function lmove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return $this->initializeLazyObject()->lmove(...\func_get_args());
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return $this->initializeLazyObject()->blmove(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->initializeLazyObject()->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->initializeLazyObject()->bzpopmin(...\func_get_args());
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->initializeLazyObject()->bzmpop(...\func_get_args());
    }

    public function zmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->initializeLazyObject()->zmpop(...\func_get_args());
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->initializeLazyObject()->blmpop(...\func_get_args());
    }

    public function lmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->initializeLazyObject()->lmpop(...\func_get_args());
    }

    public function clearlasterror(): bool
    {
        return $this->initializeLazyObject()->clearlasterror(...\func_get_args());
    }

    public function client($key_or_address, $subcommand, $arg = null): array|bool|string
    {
        return $this->initializeLazyObject()->client(...\func_get_args());
    }

    public function close(): bool
    {
        return $this->initializeLazyObject()->close(...\func_get_args());
    }

    public function cluster($key_or_address, $command, ...$extra_args): mixed
    {
        return $this->initializeLazyObject()->cluster(...\func_get_args());
    }

    public function command(...$extra_args): mixed
    {
        return $this->initializeLazyObject()->command(...\func_get_args());
    }

    public function config($key_or_address, $subcommand, ...$extra_args): mixed
    {
        return $this->initializeLazyObject()->config(...\func_get_args());
    }

    public function dbsize($key_or_address): \RedisCluster|int
    {
        return $this->initializeLazyObject()->dbsize(...\func_get_args());
    }

    public function copy($src, $dst, $options = null): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->copy(...\func_get_args());
    }

    public function decr($key, $by = 1): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->decr(...\func_get_args());
    }

    public function decrby($key, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->decrby(...\func_get_args());
    }

    public function decrbyfloat($key, $value): float
    {
        return $this->initializeLazyObject()->decrbyfloat(...\func_get_args());
    }

    public function del($key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->del(...\func_get_args());
    }

    public function discard(): bool
    {
        return $this->initializeLazyObject()->discard(...\func_get_args());
    }

    public function dump($key): \RedisCluster|false|string
    {
        return $this->initializeLazyObject()->dump(...\func_get_args());
    }

    public function echo($key_or_address, $msg): \RedisCluster|false|string
    {
        return $this->initializeLazyObject()->echo(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval(...\func_get_args());
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval_ro(...\func_get_args());
    }

    public function evalsha($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha(...\func_get_args());
    }

    public function evalsha_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha_ro(...\func_get_args());
    }

    public function exec(): array|false
    {
        return $this->initializeLazyObject()->exec(...\func_get_args());
    }

    public function exists($key, ...$other_keys): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->exists(...\func_get_args());
    }

    public function touch($key, ...$other_keys): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->touch(...\func_get_args());
    }

    public function expire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->expireat(...\func_get_args());
    }

    public function expiretime($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->expiretime(...\func_get_args());
    }

    public function pexpiretime($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->pexpiretime(...\func_get_args());
    }

    public function flushall($key_or_address, $async = false): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->flushall(...\func_get_args());
    }

    public function flushdb($key_or_address, $async = false): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->flushdb(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dest, $unit = null): \RedisCluster|false|float
    {
        return $this->initializeLazyObject()->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->geopos(...\func_get_args());
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->initializeLazyObject()->georadius(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->initializeLazyObject()->georadius_ro(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->initializeLazyObject()->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->initializeLazyObject()->georadiusbymember_ro(...\func_get_args());
    }

    public function geosearch($key, $position, $shape, $unit, $options = []): \RedisCluster|array
    {
        return $this->initializeLazyObject()->geosearch(...\func_get_args());
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \RedisCluster|array|false|int
    {
        return $this->initializeLazyObject()->geosearchstore(...\func_get_args());
    }

    public function get($key): mixed
    {
        return $this->initializeLazyObject()->get(...\func_get_args());
    }

    public function getbit($key, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->getbit(...\func_get_args());
    }

    public function getlasterror(): ?string
    {
        return $this->initializeLazyObject()->getlasterror(...\func_get_args());
    }

    public function getmode(): int
    {
        return $this->initializeLazyObject()->getmode(...\func_get_args());
    }

    public function getoption($option): mixed
    {
        return $this->initializeLazyObject()->getoption(...\func_get_args());
    }

    public function getrange($key, $start, $end): \RedisCluster|false|string
    {
        return $this->initializeLazyObject()->getrange(...\func_get_args());
    }

    public function lcs($key1, $key2, $options = null): \RedisCluster|array|false|int|string
    {
        return $this->initializeLazyObject()->lcs(...\func_get_args());
    }

    public function getset($key, $value): \RedisCluster|bool|string
    {
        return $this->initializeLazyObject()->getset(...\func_get_args());
    }

    public function gettransferredbytes(): array|false
    {
        return $this->initializeLazyObject()->gettransferredbytes(...\func_get_args());
    }

    public function cleartransferredbytes(): void
    {
        $this->initializeLazyObject()->cleartransferredbytes(...\func_get_args());
    }

    public function hdel($key, $member, ...$other_members): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->hdel(...\func_get_args());
    }

    public function hexists($key, $member): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->hexists(...\func_get_args());
    }

    public function hget($key, $member): mixed
    {
        return $this->initializeLazyObject()->hget(...\func_get_args());
    }

    public function hgetall($key): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->hgetall(...\func_get_args());
    }

    public function hincrby($key, $member, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value): \RedisCluster|false|float
    {
        return $this->initializeLazyObject()->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->hkeys(...\func_get_args());
    }

    public function hlen($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->hlen(...\func_get_args());
    }

    public function hmget($key, $keys): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->hmget(...\func_get_args());
    }

    public function hmset($key, $key_values): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->hmset(...\func_get_args());
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return $this->initializeLazyObject()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function hrandfield($key, $options = null): \RedisCluster|array|string
    {
        return $this->initializeLazyObject()->hrandfield(...\func_get_args());
    }

    public function hset($key, $member, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->hset(...\func_get_args());
    }

    public function hsetnx($key, $member, $value): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $field): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->hstrlen(...\func_get_args());
    }

    public function hvals($key): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->hvals(...\func_get_args());
    }

    public function incr($key, $by = 1): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->incr(...\func_get_args());
    }

    public function incrby($key, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value): \RedisCluster|false|float
    {
        return $this->initializeLazyObject()->incrbyfloat(...\func_get_args());
    }

    public function info($key_or_address, ...$sections): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->info(...\func_get_args());
    }

    public function keys($pattern): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->keys(...\func_get_args());
    }

    public function lastsave($key_or_address): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->lastsave(...\func_get_args());
    }

    public function lget($key, $index): \RedisCluster|bool|string
    {
        return $this->initializeLazyObject()->lget(...\func_get_args());
    }

    public function lindex($key, $index): mixed
    {
        return $this->initializeLazyObject()->lindex(...\func_get_args());
    }

    public function linsert($key, $pos, $pivot, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->linsert(...\func_get_args());
    }

    public function llen($key): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->llen(...\func_get_args());
    }

    public function lpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return $this->initializeLazyObject()->lpop(...\func_get_args());
    }

    public function lpos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return $this->initializeLazyObject()->lpos(...\func_get_args());
    }

    public function lpush($key, $value, ...$other_values): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->lpush(...\func_get_args());
    }

    public function lpushx($key, $value): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $end): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->lrange(...\func_get_args());
    }

    public function lrem($key, $value, $count = 0): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->lrem(...\func_get_args());
    }

    public function lset($key, $index, $value): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $end): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->ltrim(...\func_get_args());
    }

    public function mget($keys): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->mget(...\func_get_args());
    }

    public function mset($key_values): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->mset(...\func_get_args());
    }

    public function msetnx($key_values): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->msetnx(...\func_get_args());
    }

    public function multi($value = \Redis::MULTI): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->multi(...\func_get_args());
    }

    public function object($subcommand, $key): \RedisCluster|false|int|string
    {
        return $this->initializeLazyObject()->object(...\func_get_args());
    }

    public function persist($key): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->persist(...\func_get_args());
    }

    public function pexpire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->pexpireat(...\func_get_args());
    }

    public function pfadd($key, $elements): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->pfadd(...\func_get_args());
    }

    public function pfcount($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->pfcount(...\func_get_args());
    }

    public function pfmerge($key, $keys): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->pfmerge(...\func_get_args());
    }

    public function ping($key_or_address, $message = null): mixed
    {
        return $this->initializeLazyObject()->ping(...\func_get_args());
    }

    public function psetex($key, $timeout, $value): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback): void
    {
        $this->initializeLazyObject()->psubscribe(...\func_get_args());
    }

    public function pttl($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->pttl(...\func_get_args());
    }

    public function pubsub($key_or_address, ...$values): mixed
    {
        return $this->initializeLazyObject()->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns): array|bool
    {
        return $this->initializeLazyObject()->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address): \RedisCluster|bool|string
    {
        return $this->initializeLazyObject()->randomkey(...\func_get_args());
    }

    public function rawcommand($key_or_address, $command, ...$args): mixed
    {
        return $this->initializeLazyObject()->rawcommand(...\func_get_args());
    }

    public function rename($key_src, $key_dst): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->renamenx(...\func_get_args());
    }

    public function restore($key, $timeout, $value, $options = null): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->restore(...\func_get_args());
    }

    public function role($key_or_address): mixed
    {
        return $this->initializeLazyObject()->role(...\func_get_args());
    }

    public function rpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return $this->initializeLazyObject()->rpop(...\func_get_args());
    }

    public function rpoplpush($src, $dst): \RedisCluster|bool|string
    {
        return $this->initializeLazyObject()->rpoplpush(...\func_get_args());
    }

    public function rpush($key, ...$elements): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->rpush(...\func_get_args());
    }

    public function rpushx($key, $value): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->rpushx(...\func_get_args());
    }

    public function sadd($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->sadd(...\func_get_args());
    }

    public function saddarray($key, $values): \RedisCluster|bool|int
    {
        return $this->initializeLazyObject()->saddarray(...\func_get_args());
    }

    public function save($key_or_address): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->save(...\func_get_args());
    }

    public function scan(&$iterator, $key_or_address, $pattern = null, $count = 0): array|bool
    {
        return $this->initializeLazyObject()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function scard($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->scard(...\func_get_args());
    }

    public function script($key_or_address, ...$args): mixed
    {
        return $this->initializeLazyObject()->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->sdiff(...\func_get_args());
    }

    public function sdiffstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->sdiffstore(...\func_get_args());
    }

    public function set($key, $value, $options = null): \RedisCluster|bool|string
    {
        return $this->initializeLazyObject()->set(...\func_get_args());
    }

    public function setbit($key, $offset, $onoff): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->setbit(...\func_get_args());
    }

    public function setex($key, $expire, $value): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->setex(...\func_get_args());
    }

    public function setnx($key, $value): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->setnx(...\func_get_args());
    }

    public function setoption($option, $value): bool
    {
        return $this->initializeLazyObject()->setoption(...\func_get_args());
    }

    public function setrange($key, $offset, $value): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->sinter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->sintercard(...\func_get_args());
    }

    public function sinterstore($key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->sinterstore(...\func_get_args());
    }

    public function sismember($key, $value): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->sismember(...\func_get_args());
    }

    public function smismember($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->smismember(...\func_get_args());
    }

    public function slowlog($key_or_address, ...$args): mixed
    {
        return $this->initializeLazyObject()->slowlog(...\func_get_args());
    }

    public function smembers($key): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->smembers(...\func_get_args());
    }

    public function smove($src, $dst, $member): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->smove(...\func_get_args());
    }

    public function sort($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return $this->initializeLazyObject()->sort(...\func_get_args());
    }

    public function sort_ro($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return $this->initializeLazyObject()->sort_ro(...\func_get_args());
    }

    public function spop($key, $count = 0): \RedisCluster|array|false|string
    {
        return $this->initializeLazyObject()->spop(...\func_get_args());
    }

    public function srandmember($key, $count = 0): \RedisCluster|array|false|string
    {
        return $this->initializeLazyObject()->srandmember(...\func_get_args());
    }

    public function srem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->srem(...\func_get_args());
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return $this->initializeLazyObject()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function strlen($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->strlen(...\func_get_args());
    }

    public function subscribe($channels, $cb): void
    {
        $this->initializeLazyObject()->subscribe(...\func_get_args());
    }

    public function sunion($key, ...$other_keys): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->sunion(...\func_get_args());
    }

    public function sunionstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->sunionstore(...\func_get_args());
    }

    public function time($key_or_address): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->time(...\func_get_args());
    }

    public function ttl($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->ttl(...\func_get_args());
    }

    public function type($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->type(...\func_get_args());
    }

    public function unsubscribe($channels): array|bool
    {
        return $this->initializeLazyObject()->unsubscribe(...\func_get_args());
    }

    public function unlink($key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->unlink(...\func_get_args());
    }

    public function unwatch(): bool
    {
        return $this->initializeLazyObject()->unwatch(...\func_get_args());
    }

    public function watch($key, ...$other_keys): \RedisCluster|bool
    {
        return $this->initializeLazyObject()->watch(...\func_get_args());
    }

    public function xack($key, $group, $ids): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->xack(...\func_get_args());
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false): \RedisCluster|false|string
    {
        return $this->initializeLazyObject()->xadd(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_iddle, $ids, $options): \RedisCluster|array|false|string
    {
        return $this->initializeLazyObject()->xclaim(...\func_get_args());
    }

    public function xdel($key, $ids): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->xdel(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->initializeLazyObject()->xgroup(...\func_get_args());
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->xautoclaim(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->initializeLazyObject()->xinfo(...\func_get_args());
    }

    public function xlen($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->xlen(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->xrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->xread(...\func_get_args());
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
    }

    public function xrevrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->xrevrange(...\func_get_args());
    }

    public function xtrim($key, $maxlen, $approx = false, $minid = false, $limit = -1): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->xtrim(...\func_get_args());
    }

    public function zadd($key, $score_or_options, ...$more_scores_and_mems): \RedisCluster|false|float|int
    {
        return $this->initializeLazyObject()->zadd(...\func_get_args());
    }

    public function zcard($key): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zcard(...\func_get_args());
    }

    public function zcount($key, $start, $end): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zcount(...\func_get_args());
    }

    public function zincrby($key, $value, $member): \RedisCluster|false|float
    {
        return $this->initializeLazyObject()->zincrby(...\func_get_args());
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zinterstore(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zintercard(...\func_get_args());
    }

    public function zlexcount($key, $min, $max): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zlexcount(...\func_get_args());
    }

    public function zpopmax($key, $value = null): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zpopmax(...\func_get_args());
    }

    public function zpopmin($key, $value = null): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zpopmin(...\func_get_args());
    }

    public function zrange($key, $start, $end, $options = null): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zrange(...\func_get_args());
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zrangestore(...\func_get_args());
    }

    public function zrandmember($key, $options = null): \RedisCluster|array|string
    {
        return $this->initializeLazyObject()->zrandmember(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = []): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->zrangebyscore(...\func_get_args());
    }

    public function zrank($key, $member): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zrank(...\func_get_args());
    }

    public function zrem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $min, $max): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $member): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zrevrank(...\func_get_args());
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \RedisCluster|array|bool
    {
        return $this->initializeLazyObject()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zscore($key, $member): \RedisCluster|false|float
    {
        return $this->initializeLazyObject()->zscore(...\func_get_args());
    }

    public function zmscore($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initializeLazyObject()->zmscore(...\func_get_args());
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zunionstore(...\func_get_args());
    }

    public function zinter($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->zinter(...\func_get_args());
    }

    public function zdiffstore($dst, $keys): \RedisCluster|false|int
    {
        return $this->initializeLazyObject()->zdiffstore(...\func_get_args());
    }

    public function zunion($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->zunion(...\func_get_args());
    }

    public function zdiff($keys, $options = null): \RedisCluster|array|false
    {
        return $this->initializeLazyObject()->zdiff(...\func_get_args());
    }
}
