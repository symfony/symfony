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

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
        "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
    ];

    public function __construct($name, $seeds = null, $timeout = 0, $read_timeout = 0, $persistent = false, #[\SensitiveParameter] $auth = null, $context = null)
    {
        return $this->lazyObjectReal->__construct($name, $seeds, $timeout, $read_timeout, $persistent, $auth, $context);
    }

    public function _compress($value): string
    {
        return $this->lazyObjectReal->_compress($value);
    }

    public function _uncompress($value): string
    {
        return $this->lazyObjectReal->_uncompress($value);
    }

    public function _serialize($value): bool|string
    {
        return $this->lazyObjectReal->_serialize($value);
    }

    public function _unserialize($value): mixed
    {
        return $this->lazyObjectReal->_unserialize($value);
    }

    public function _pack($value): string
    {
        return $this->lazyObjectReal->_pack($value);
    }

    public function _unpack($value): mixed
    {
        return $this->lazyObjectReal->_unpack($value);
    }

    public function _prefix($key): bool|string
    {
        return $this->lazyObjectReal->_prefix($key);
    }

    public function _masters(): array
    {
        return $this->lazyObjectReal->_masters();
    }

    public function _redir(): ?string
    {
        return $this->lazyObjectReal->_redir();
    }

    public function acl($key_or_address, $subcmd, ...$args): mixed
    {
        return $this->lazyObjectReal->acl($key_or_address, $subcmd, ...$args);
    }

    public function append($key, $value): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->append($key, $value);
    }

    public function bgrewriteaof($key_or_address): \RedisCluster|bool
    {
        return $this->lazyObjectReal->bgrewriteaof($key_or_address);
    }

    public function bgsave($key_or_address): \RedisCluster|bool
    {
        return $this->lazyObjectReal->bgsave($key_or_address);
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->bitcount($key, $start, $end, $bybit);
    }

    public function bitop($operation, $deskey, $srckey, ...$otherkeys): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->bitop($operation, $deskey, $srckey, ...$otherkeys);
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->bitpos($key, $bit, $start, $end, $bybit);
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return $this->lazyObjectReal->blpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \RedisCluster|array|false|null
    {
        return $this->lazyObjectReal->brpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($srckey, $deskey, $timeout): mixed
    {
        return $this->lazyObjectReal->brpoplpush($srckey, $deskey, $timeout);
    }

    public function lmove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return $this->lazyObjectReal->lmove($src, $dst, $wherefrom, $whereto);
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return $this->lazyObjectReal->blmove($src, $dst, $wherefrom, $whereto, $timeout);
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->lazyObjectReal->bzpopmax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->lazyObjectReal->bzpopmin($key, $timeout_or_key, ...$extra_args);
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->lazyObjectReal->bzmpop($timeout, $keys, $from, $count);
    }

    public function zmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->lazyObjectReal->zmpop($keys, $from, $count);
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->lazyObjectReal->blmpop($timeout, $keys, $from, $count);
    }

    public function lmpop($keys, $from, $count = 1): \RedisCluster|array|false|null
    {
        return $this->lazyObjectReal->lmpop($keys, $from, $count);
    }

    public function clearlasterror(): bool
    {
        return $this->lazyObjectReal->clearlasterror();
    }

    public function client($key_or_address, $subcommand, $arg = null): array|bool|string
    {
        return $this->lazyObjectReal->client($key_or_address, $subcommand, $arg);
    }

    public function close(): bool
    {
        return $this->lazyObjectReal->close();
    }

    public function cluster($key_or_address, $command, ...$extra_args): mixed
    {
        return $this->lazyObjectReal->cluster($key_or_address, $command, ...$extra_args);
    }

    public function command(...$extra_args): mixed
    {
        return $this->lazyObjectReal->command(...$extra_args);
    }

    public function config($key_or_address, $subcommand, ...$extra_args): mixed
    {
        return $this->lazyObjectReal->config($key_or_address, $subcommand, ...$extra_args);
    }

    public function dbsize($key_or_address): \RedisCluster|int
    {
        return $this->lazyObjectReal->dbsize($key_or_address);
    }

    public function copy($src, $dst, $options = null): \RedisCluster|bool
    {
        return $this->lazyObjectReal->copy($src, $dst, $options);
    }

    public function decr($key, $by = 1): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->decr($key, $by);
    }

    public function decrby($key, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->decrby($key, $value);
    }

    public function decrbyfloat($key, $value): float
    {
        return $this->lazyObjectReal->decrbyfloat($key, $value);
    }

    public function del($key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->del($key, ...$other_keys);
    }

    public function discard(): bool
    {
        return $this->lazyObjectReal->discard();
    }

    public function dump($key): \RedisCluster|false|string
    {
        return $this->lazyObjectReal->dump($key);
    }

    public function echo($key_or_address, $msg): \RedisCluster|false|string
    {
        return $this->lazyObjectReal->echo($key_or_address, $msg);
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval($script, $args, $num_keys);
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval_ro($script, $args, $num_keys);
    }

    public function evalsha($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha($script_sha, $args, $num_keys);
    }

    public function evalsha_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha_ro($script_sha, $args, $num_keys);
    }

    public function exec(): array|false
    {
        return $this->lazyObjectReal->exec();
    }

    public function exists($key, ...$other_keys): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->exists($key, ...$other_keys);
    }

    public function touch($key, ...$other_keys): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->touch($key, ...$other_keys);
    }

    public function expire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return $this->lazyObjectReal->expire($key, $timeout, $mode);
    }

    public function expireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return $this->lazyObjectReal->expireat($key, $timestamp, $mode);
    }

    public function expiretime($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->expiretime($key);
    }

    public function pexpiretime($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->pexpiretime($key);
    }

    public function flushall($key_or_address, $async = false): \RedisCluster|bool
    {
        return $this->lazyObjectReal->flushall($key_or_address, $async);
    }

    public function flushdb($key_or_address, $async = false): \RedisCluster|bool
    {
        return $this->lazyObjectReal->flushdb($key_or_address, $async);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->geoadd($key, $lng, $lat, $member, ...$other_triples_and_options);
    }

    public function geodist($key, $src, $dest, $unit = null): \RedisCluster|false|float
    {
        return $this->lazyObjectReal->geodist($key, $src, $dest, $unit);
    }

    public function geohash($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->geohash($key, $member, ...$other_members);
    }

    public function geopos($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->geopos($key, $member, ...$other_members);
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadius($key, $lng, $lat, $radius, $unit, $options);
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadius_ro($key, $lng, $lat, $radius, $unit, $options);
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadiusbymember($key, $member, $radius, $unit, $options);
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadiusbymember_ro($key, $member, $radius, $unit, $options);
    }

    public function geosearch($key, $position, $shape, $unit, $options = []): \RedisCluster|array
    {
        return $this->lazyObjectReal->geosearch($key, $position, $shape, $unit, $options);
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \RedisCluster|array|false|int
    {
        return $this->lazyObjectReal->geosearchstore($dst, $src, $position, $shape, $unit, $options);
    }

    public function get($key): mixed
    {
        return $this->lazyObjectReal->get($key);
    }

    public function getbit($key, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->getbit($key, $value);
    }

    public function getlasterror(): ?string
    {
        return $this->lazyObjectReal->getlasterror();
    }

    public function getmode(): int
    {
        return $this->lazyObjectReal->getmode();
    }

    public function getoption($option): mixed
    {
        return $this->lazyObjectReal->getoption($option);
    }

    public function getrange($key, $start, $end): \RedisCluster|false|string
    {
        return $this->lazyObjectReal->getrange($key, $start, $end);
    }

    public function lcs($key1, $key2, $options = null): \RedisCluster|array|false|int|string
    {
        return $this->lazyObjectReal->lcs($key1, $key2, $options);
    }

    public function getset($key, $value): \RedisCluster|bool|string
    {
        return $this->lazyObjectReal->getset($key, $value);
    }

    public function gettransferredbytes(): array|false
    {
        return $this->lazyObjectReal->gettransferredbytes();
    }

    public function cleartransferredbytes(): void
    {
        $this->lazyObjectReal->cleartransferredbytes();
    }

    public function hdel($key, $member, ...$other_members): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->hdel($key, $member, ...$other_members);
    }

    public function hexists($key, $member): \RedisCluster|bool
    {
        return $this->lazyObjectReal->hexists($key, $member);
    }

    public function hget($key, $member): mixed
    {
        return $this->lazyObjectReal->hget($key, $member);
    }

    public function hgetall($key): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->hgetall($key);
    }

    public function hincrby($key, $member, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->hincrby($key, $member, $value);
    }

    public function hincrbyfloat($key, $member, $value): \RedisCluster|false|float
    {
        return $this->lazyObjectReal->hincrbyfloat($key, $member, $value);
    }

    public function hkeys($key): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->hkeys($key);
    }

    public function hlen($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->hlen($key);
    }

    public function hmget($key, $keys): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->hmget($key, $keys);
    }

    public function hmset($key, $key_values): \RedisCluster|bool
    {
        return $this->lazyObjectReal->hmset($key, $key_values);
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return $this->lazyObjectReal->hscan($key, $iterator, $pattern, $count);
    }

    public function hrandfield($key, $options = null): \RedisCluster|array|string
    {
        return $this->lazyObjectReal->hrandfield($key, $options);
    }

    public function hset($key, $member, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->hset($key, $member, $value);
    }

    public function hsetnx($key, $member, $value): \RedisCluster|bool
    {
        return $this->lazyObjectReal->hsetnx($key, $member, $value);
    }

    public function hstrlen($key, $field): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->hstrlen($key, $field);
    }

    public function hvals($key): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->hvals($key);
    }

    public function incr($key, $by = 1): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->incr($key, $by);
    }

    public function incrby($key, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->incrby($key, $value);
    }

    public function incrbyfloat($key, $value): \RedisCluster|false|float
    {
        return $this->lazyObjectReal->incrbyfloat($key, $value);
    }

    public function info($key_or_address, ...$sections): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->info($key_or_address, ...$sections);
    }

    public function keys($pattern): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->keys($pattern);
    }

    public function lastsave($key_or_address): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->lastsave($key_or_address);
    }

    public function lget($key, $index): \RedisCluster|bool|string
    {
        return $this->lazyObjectReal->lget($key, $index);
    }

    public function lindex($key, $index): mixed
    {
        return $this->lazyObjectReal->lindex($key, $index);
    }

    public function linsert($key, $pos, $pivot, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->linsert($key, $pos, $pivot, $value);
    }

    public function llen($key): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->llen($key);
    }

    public function lpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return $this->lazyObjectReal->lpop($key, $count);
    }

    public function lpos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return $this->lazyObjectReal->lpos($key, $value, $options);
    }

    public function lpush($key, $value, ...$other_values): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->lpush($key, $value, ...$other_values);
    }

    public function lpushx($key, $value): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->lpushx($key, $value);
    }

    public function lrange($key, $start, $end): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->lrange($key, $start, $end);
    }

    public function lrem($key, $value, $count = 0): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->lrem($key, $value, $count);
    }

    public function lset($key, $index, $value): \RedisCluster|bool
    {
        return $this->lazyObjectReal->lset($key, $index, $value);
    }

    public function ltrim($key, $start, $end): \RedisCluster|bool
    {
        return $this->lazyObjectReal->ltrim($key, $start, $end);
    }

    public function mget($keys): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->mget($keys);
    }

    public function mset($key_values): \RedisCluster|bool
    {
        return $this->lazyObjectReal->mset($key_values);
    }

    public function msetnx($key_values): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->msetnx($key_values);
    }

    public function multi($value = \Redis::MULTI): \RedisCluster|bool
    {
        return $this->lazyObjectReal->multi($value);
    }

    public function object($subcommand, $key): \RedisCluster|false|int|string
    {
        return $this->lazyObjectReal->object($subcommand, $key);
    }

    public function persist($key): \RedisCluster|bool
    {
        return $this->lazyObjectReal->persist($key);
    }

    public function pexpire($key, $timeout, $mode = null): \RedisCluster|bool
    {
        return $this->lazyObjectReal->pexpire($key, $timeout, $mode);
    }

    public function pexpireat($key, $timestamp, $mode = null): \RedisCluster|bool
    {
        return $this->lazyObjectReal->pexpireat($key, $timestamp, $mode);
    }

    public function pfadd($key, $elements): \RedisCluster|bool
    {
        return $this->lazyObjectReal->pfadd($key, $elements);
    }

    public function pfcount($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->pfcount($key);
    }

    public function pfmerge($key, $keys): \RedisCluster|bool
    {
        return $this->lazyObjectReal->pfmerge($key, $keys);
    }

    public function ping($key_or_address, $message = null): mixed
    {
        return $this->lazyObjectReal->ping($key_or_address, $message);
    }

    public function psetex($key, $timeout, $value): \RedisCluster|bool
    {
        return $this->lazyObjectReal->psetex($key, $timeout, $value);
    }

    public function psubscribe($patterns, $callback): void
    {
        $this->lazyObjectReal->psubscribe($patterns, $callback);
    }

    public function pttl($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->pttl($key);
    }

    public function publish($channel, $message): \RedisCluster|bool
    {
        return $this->lazyObjectReal->publish($channel, $message);
    }

    public function pubsub($key_or_address, ...$values): mixed
    {
        return $this->lazyObjectReal->pubsub($key_or_address, ...$values);
    }

    public function punsubscribe($pattern, ...$other_patterns): array|bool
    {
        return $this->lazyObjectReal->punsubscribe($pattern, ...$other_patterns);
    }

    public function randomkey($key_or_address): \RedisCluster|bool|string
    {
        return $this->lazyObjectReal->randomkey($key_or_address);
    }

    public function rawcommand($key_or_address, $command, ...$args): mixed
    {
        return $this->lazyObjectReal->rawcommand($key_or_address, $command, ...$args);
    }

    public function rename($key_src, $key_dst): \RedisCluster|bool
    {
        return $this->lazyObjectReal->rename($key_src, $key_dst);
    }

    public function renamenx($key, $newkey): \RedisCluster|bool
    {
        return $this->lazyObjectReal->renamenx($key, $newkey);
    }

    public function restore($key, $timeout, $value, $options = null): \RedisCluster|bool
    {
        return $this->lazyObjectReal->restore($key, $timeout, $value, $options);
    }

    public function role($key_or_address): mixed
    {
        return $this->lazyObjectReal->role($key_or_address);
    }

    public function rpop($key, $count = 0): \RedisCluster|array|bool|string
    {
        return $this->lazyObjectReal->rpop($key, $count);
    }

    public function rpoplpush($src, $dst): \RedisCluster|bool|string
    {
        return $this->lazyObjectReal->rpoplpush($src, $dst);
    }

    public function rpush($key, ...$elements): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->rpush($key, ...$elements);
    }

    public function rpushx($key, $value): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->rpushx($key, $value);
    }

    public function sadd($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->sadd($key, $value, ...$other_values);
    }

    public function saddarray($key, $values): \RedisCluster|bool|int
    {
        return $this->lazyObjectReal->saddarray($key, $values);
    }

    public function save($key_or_address): \RedisCluster|bool
    {
        return $this->lazyObjectReal->save($key_or_address);
    }

    public function scan(&$iterator, $key_or_address, $pattern = null, $count = 0): array|bool
    {
        return $this->lazyObjectReal->scan($iterator, $key_or_address, $pattern, $count);
    }

    public function scard($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->scard($key);
    }

    public function script($key_or_address, ...$args): mixed
    {
        return $this->lazyObjectReal->script($key_or_address, ...$args);
    }

    public function sdiff($key, ...$other_keys): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->sdiff($key, ...$other_keys);
    }

    public function sdiffstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->sdiffstore($dst, $key, ...$other_keys);
    }

    public function set($key, $value, $options = null): \RedisCluster|bool|string
    {
        return $this->lazyObjectReal->set($key, $value, $options);
    }

    public function setbit($key, $offset, $onoff): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->setbit($key, $offset, $onoff);
    }

    public function setex($key, $expire, $value): \RedisCluster|bool
    {
        return $this->lazyObjectReal->setex($key, $expire, $value);
    }

    public function setnx($key, $value): \RedisCluster|bool
    {
        return $this->lazyObjectReal->setnx($key, $value);
    }

    public function setoption($option, $value): bool
    {
        return $this->lazyObjectReal->setoption($option, $value);
    }

    public function setrange($key, $offset, $value): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->setrange($key, $offset, $value);
    }

    public function sinter($key, ...$other_keys): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->sinter($key, ...$other_keys);
    }

    public function sintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->sintercard($keys, $limit);
    }

    public function sinterstore($key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->sinterstore($key, ...$other_keys);
    }

    public function sismember($key, $value): \RedisCluster|bool
    {
        return $this->lazyObjectReal->sismember($key, $value);
    }

    public function smismember($key, $member, ...$other_members): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->smismember($key, $member, ...$other_members);
    }

    public function slowlog($key_or_address, ...$args): mixed
    {
        return $this->lazyObjectReal->slowlog($key_or_address, ...$args);
    }

    public function smembers($key): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->smembers($key);
    }

    public function smove($src, $dst, $member): \RedisCluster|bool
    {
        return $this->lazyObjectReal->smove($src, $dst, $member);
    }

    public function sort($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return $this->lazyObjectReal->sort($key, $options);
    }

    public function sort_ro($key, $options = null): \RedisCluster|array|bool|int|string
    {
        return $this->lazyObjectReal->sort_ro($key, $options);
    }

    public function spop($key, $count = 0): \RedisCluster|array|false|string
    {
        return $this->lazyObjectReal->spop($key, $count);
    }

    public function srandmember($key, $count = 0): \RedisCluster|array|false|string
    {
        return $this->lazyObjectReal->srandmember($key, $count);
    }

    public function srem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->srem($key, $value, ...$other_values);
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return $this->lazyObjectReal->sscan($key, $iterator, $pattern, $count);
    }

    public function strlen($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->strlen($key);
    }

    public function subscribe($channels, $cb): void
    {
        $this->lazyObjectReal->subscribe($channels, $cb);
    }

    public function sunion($key, ...$other_keys): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->sunion($key, ...$other_keys);
    }

    public function sunionstore($dst, $key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->sunionstore($dst, $key, ...$other_keys);
    }

    public function time($key_or_address): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->time($key_or_address);
    }

    public function ttl($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->ttl($key);
    }

    public function type($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->type($key);
    }

    public function unsubscribe($channels): array|bool
    {
        return $this->lazyObjectReal->unsubscribe($channels);
    }

    public function unlink($key, ...$other_keys): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->unlink($key, ...$other_keys);
    }

    public function unwatch(): bool
    {
        return $this->lazyObjectReal->unwatch();
    }

    public function watch($key, ...$other_keys): \RedisCluster|bool
    {
        return $this->lazyObjectReal->watch($key, ...$other_keys);
    }

    public function xack($key, $group, $ids): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->xack($key, $group, $ids);
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false): \RedisCluster|false|string
    {
        return $this->lazyObjectReal->xadd($key, $id, $values, $maxlen, $approx);
    }

    public function xclaim($key, $group, $consumer, $min_iddle, $ids, $options): \RedisCluster|array|false|string
    {
        return $this->lazyObjectReal->xclaim($key, $group, $consumer, $min_iddle, $ids, $options);
    }

    public function xdel($key, $ids): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->xdel($key, $ids);
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->lazyObjectReal->xgroup($operation, $key, $group, $id_or_consumer, $mkstream, $entries_read);
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->xautoclaim($key, $group, $consumer, $min_idle, $start, $count, $justid);
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->lazyObjectReal->xinfo($operation, $arg1, $arg2, $count);
    }

    public function xlen($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->xlen($key);
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->xpending($key, $group, $start, $end, $count, $consumer);
    }

    public function xrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->xrange($key, $start, $end, $count);
    }

    public function xread($streams, $count = -1, $block = -1): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->xread($streams, $count, $block);
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->xreadgroup($group, $consumer, $streams, $count, $block);
    }

    public function xrevrange($key, $start, $end, $count = -1): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->xrevrange($key, $start, $end, $count);
    }

    public function xtrim($key, $maxlen, $approx = false, $minid = false, $limit = -1): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->xtrim($key, $maxlen, $approx, $minid, $limit);
    }

    public function zadd($key, $score_or_options, ...$more_scores_and_mems): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zadd($key, $score_or_options, ...$more_scores_and_mems);
    }

    public function zcard($key): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zcard($key);
    }

    public function zcount($key, $start, $end): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zcount($key, $start, $end);
    }

    public function zincrby($key, $value, $member): \RedisCluster|false|float
    {
        return $this->lazyObjectReal->zincrby($key, $value, $member);
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zinterstore($dst, $keys, $weights, $aggregate);
    }

    public function zintercard($keys, $limit = -1): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zintercard($keys, $limit);
    }

    public function zlexcount($key, $min, $max): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zlexcount($key, $min, $max);
    }

    public function zpopmax($key, $value = null): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zpopmax($key, $value);
    }

    public function zpopmin($key, $value = null): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zpopmin($key, $value);
    }

    public function zrange($key, $start, $end, $options = null): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zrange($key, $start, $end, $options);
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zrangestore($dstkey, $srckey, $start, $end, $options);
    }

    public function zrandmember($key, $options = null): \RedisCluster|array|string
    {
        return $this->lazyObjectReal->zrandmember($key, $options);
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->zrangebylex($key, $min, $max, $offset, $count);
    }

    public function zrangebyscore($key, $start, $end, $options = []): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->zrangebyscore($key, $start, $end, $options);
    }

    public function zrank($key, $member): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zrank($key, $member);
    }

    public function zrem($key, $value, ...$other_values): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zrem($key, $value, ...$other_values);
    }

    public function zremrangebylex($key, $min, $max): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zremrangebylex($key, $min, $max);
    }

    public function zremrangebyrank($key, $min, $max): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zremrangebyrank($key, $min, $max);
    }

    public function zremrangebyscore($key, $min, $max): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zremrangebyscore($key, $min, $max);
    }

    public function zrevrange($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zrevrange($key, $min, $max, $options);
    }

    public function zrevrangebylex($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zrevrangebylex($key, $min, $max, $options);
    }

    public function zrevrangebyscore($key, $min, $max, $options = null): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zrevrangebyscore($key, $min, $max, $options);
    }

    public function zrevrank($key, $member): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zrevrank($key, $member);
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \RedisCluster|array|bool
    {
        return $this->lazyObjectReal->zscan($key, $iterator, $pattern, $count);
    }

    public function zscore($key, $member): \RedisCluster|false|float
    {
        return $this->lazyObjectReal->zscore($key, $member);
    }

    public function zmscore($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->lazyObjectReal->zmscore($key, $member, ...$other_members);
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zunionstore($dst, $keys, $weights, $aggregate);
    }

    public function zinter($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->zinter($keys, $weights, $options);
    }

    public function zdiffstore($dst, $keys): \RedisCluster|false|int
    {
        return $this->lazyObjectReal->zdiffstore($dst, $keys);
    }

    public function zunion($keys, $weights = null, $options = null): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->zunion($keys, $weights, $options);
    }

    public function zdiff($keys, $options = null): \RedisCluster|array|false
    {
        return $this->lazyObjectReal->zdiff($keys, $options);
    }
}
