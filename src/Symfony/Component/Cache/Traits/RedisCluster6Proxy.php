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

    private int $lazyObjectId;
    private \RedisCluster $lazyObjectReal;

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
        "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
    ];

    public function __construct($name, $seeds = null, $timeout = 0, $read_timeout = 0, $persistent = false, #[\SensitiveParameter] $auth = null, $context = null)
    {
        return $this->lazyObjectReal->__construct(...\func_get_args());
    }

    public function _compress($value): string
    {
        return $this->lazyObjectReal->_compress(...\func_get_args());
    }

    public function _masters(): array
    {
        return $this->lazyObjectReal->_masters(...\func_get_args());
    }

    public function _pack($value): string
    {
        return $this->lazyObjectReal->_pack(...\func_get_args());
    }

    public function _prefix($key): bool|string
    {
        return $this->lazyObjectReal->_prefix(...\func_get_args());
    }

    public function _redir(): ?string
    {
        return $this->lazyObjectReal->_redir(...\func_get_args());
    }

    public function _serialize($value): bool|string
    {
        return $this->lazyObjectReal->_serialize(...\func_get_args());
    }

    public function _uncompress($value): string
    {
        return $this->lazyObjectReal->_uncompress(...\func_get_args());
    }

    public function _unpack($value): mixed
    {
        return $this->lazyObjectReal->_unpack(...\func_get_args());
    }

    public function _unserialize($value): mixed
    {
        return $this->lazyObjectReal->_unserialize(...\func_get_args());
    }

    public function acl($key_or_address, $subcmd, ...$args): mixed
    {
        return $this->lazyObjectReal->acl(...\func_get_args());
    }

    public function append($key, $value): bool|int
    {
        return $this->lazyObjectReal->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address): bool
    {
        return $this->lazyObjectReal->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address): bool
    {
        return $this->lazyObjectReal->bgsave(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1): bool|int
    {
        return $this->lazyObjectReal->bitcount(...\func_get_args());
    }

    public function bitop($operation, $deskey, $srckey, ...$otherkeys): bool|int
    {
        return $this->lazyObjectReal->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null): bool|int
    {
        return $this->lazyObjectReal->bitpos(...\func_get_args());
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->lazyObjectReal->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->lazyObjectReal->brpop(...\func_get_args());
    }

    public function brpoplpush($srckey, $deskey, $timeout): mixed
    {
        return $this->lazyObjectReal->brpoplpush(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->lazyObjectReal->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): array
    {
        return $this->lazyObjectReal->bzpopmin(...\func_get_args());
    }

    public function clearlasterror(): bool
    {
        return $this->lazyObjectReal->clearlasterror(...\func_get_args());
    }

    public function client($node, $subcommand, $arg): array|bool|string
    {
        return $this->lazyObjectReal->client(...\func_get_args());
    }

    public function close(): bool
    {
        return $this->lazyObjectReal->close(...\func_get_args());
    }

    public function cluster($node, $command, ...$extra_args): mixed
    {
        return $this->lazyObjectReal->cluster(...\func_get_args());
    }

    public function command(...$extra_args): mixed
    {
        return $this->lazyObjectReal->command(...\func_get_args());
    }

    public function config($node, $subcommand, ...$extra_args): mixed
    {
        return $this->lazyObjectReal->config(...\func_get_args());
    }

    public function dbsize($key_or_address): int
    {
        return $this->lazyObjectReal->dbsize(...\func_get_args());
    }

    public function decr($key): int
    {
        return $this->lazyObjectReal->decr(...\func_get_args());
    }

    public function decrby($key, $value): int
    {
        return $this->lazyObjectReal->decrby(...\func_get_args());
    }

    public function decrbyfloat($key, $value): float
    {
        return $this->lazyObjectReal->decrbyfloat(...\func_get_args());
    }

    public function del($key, ...$other_keys): array
    {
        return $this->lazyObjectReal->del(...\func_get_args());
    }

    public function discard(): bool
    {
        return $this->lazyObjectReal->discard(...\func_get_args());
    }

    public function dump($key): string
    {
        return $this->lazyObjectReal->dump(...\func_get_args());
    }

    public function echo($node, $msg): string
    {
        return $this->lazyObjectReal->echo(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval(...\func_get_args());
    }

    public function evalsha($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha(...\func_get_args());
    }

    public function exec(): array
    {
        return $this->lazyObjectReal->exec(...\func_get_args());
    }

    public function exists($key): int
    {
        return $this->lazyObjectReal->exists(...\func_get_args());
    }

    public function expire($key, $timeout): bool
    {
        return $this->lazyObjectReal->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp): bool
    {
        return $this->lazyObjectReal->expireat(...\func_get_args());
    }

    public function expiretime($key): \Redis|false|int
    {
        return $this->lazyObjectReal->expiretime(...\func_get_args());
    }

    public function pexpiretime($key): \Redis|false|int
    {
        return $this->lazyObjectReal->pexpiretime(...\func_get_args());
    }

    public function flushall($node, $async = false): bool
    {
        return $this->lazyObjectReal->flushall(...\func_get_args());
    }

    public function flushdb($node, $async = false): bool
    {
        return $this->lazyObjectReal->flushdb(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples): int
    {
        return $this->lazyObjectReal->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dest, $unit = null): array
    {
        return $this->lazyObjectReal->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): array
    {
        return $this->lazyObjectReal->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members): array
    {
        return $this->lazyObjectReal->geopos(...\func_get_args());
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): array
    {
        return $this->lazyObjectReal->georadius(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): array
    {
        return $this->lazyObjectReal->georadius_ro(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): array
    {
        return $this->lazyObjectReal->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): array
    {
        return $this->lazyObjectReal->georadiusbymember_ro(...\func_get_args());
    }

    public function get($key): string
    {
        return $this->lazyObjectReal->get(...\func_get_args());
    }

    public function getbit($key, $value): int
    {
        return $this->lazyObjectReal->getbit(...\func_get_args());
    }

    public function getlasterror(): ?string
    {
        return $this->lazyObjectReal->getlasterror(...\func_get_args());
    }

    public function getmode(): int
    {
        return $this->lazyObjectReal->getmode(...\func_get_args());
    }

    public function getoption($option): mixed
    {
        return $this->lazyObjectReal->getoption(...\func_get_args());
    }

    public function getrange($key, $start, $end): string
    {
        return $this->lazyObjectReal->getrange(...\func_get_args());
    }

    public function lcs($key1, $key2, $options = null): \Redis|array|false|int|string
    {
        return $this->lazyObjectReal->lcs(...\func_get_args());
    }

    public function getset($key, $value): string
    {
        return $this->lazyObjectReal->getset(...\func_get_args());
    }

    public function hdel($key, $member, ...$other_members): int
    {
        return $this->lazyObjectReal->hdel(...\func_get_args());
    }

    public function hexists($key, $member): bool
    {
        return $this->lazyObjectReal->hexists(...\func_get_args());
    }

    public function hget($key, $member): string
    {
        return $this->lazyObjectReal->hget(...\func_get_args());
    }

    public function hgetall($key): array
    {
        return $this->lazyObjectReal->hgetall(...\func_get_args());
    }

    public function hincrby($key, $member, $value): int
    {
        return $this->lazyObjectReal->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value): float
    {
        return $this->lazyObjectReal->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key): array
    {
        return $this->lazyObjectReal->hkeys(...\func_get_args());
    }

    public function hlen($key): int
    {
        return $this->lazyObjectReal->hlen(...\func_get_args());
    }

    public function hmget($key, $members): array
    {
        return $this->lazyObjectReal->hmget(...\func_get_args());
    }

    public function hmset($key, $key_values): bool
    {
        return $this->lazyObjectReal->hmset(...\func_get_args());
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return $this->lazyObjectReal->hscan(...\func_get_args());
    }

    public function hset($key, $member, $value): int
    {
        return $this->lazyObjectReal->hset(...\func_get_args());
    }

    public function hsetnx($key, $member, $value): bool
    {
        return $this->lazyObjectReal->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $field): int
    {
        return $this->lazyObjectReal->hstrlen(...\func_get_args());
    }

    public function hvals($key): array
    {
        return $this->lazyObjectReal->hvals(...\func_get_args());
    }

    public function incr($key): int
    {
        return $this->lazyObjectReal->incr(...\func_get_args());
    }

    public function incrby($key, $value): int
    {
        return $this->lazyObjectReal->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value): float
    {
        return $this->lazyObjectReal->incrbyfloat(...\func_get_args());
    }

    public function info($node, $section = null): array
    {
        return $this->lazyObjectReal->info(...\func_get_args());
    }

    public function keys($pattern): array
    {
        return $this->lazyObjectReal->keys(...\func_get_args());
    }

    public function lastsave($node): int
    {
        return $this->lazyObjectReal->lastsave(...\func_get_args());
    }

    public function lget($key, $index): bool|string
    {
        return $this->lazyObjectReal->lget(...\func_get_args());
    }

    public function lindex($key, $index): bool|string
    {
        return $this->lazyObjectReal->lindex(...\func_get_args());
    }

    public function linsert($key, $pos, $pivot, $value): int
    {
        return $this->lazyObjectReal->linsert(...\func_get_args());
    }

    public function llen($key): bool|int
    {
        return $this->lazyObjectReal->llen(...\func_get_args());
    }

    public function lpop($key, $count = 0): array|bool|string
    {
        return $this->lazyObjectReal->lpop(...\func_get_args());
    }

    public function lpush($key, $value, ...$other_values): bool|int
    {
        return $this->lazyObjectReal->lpush(...\func_get_args());
    }

    public function lpushx($key, $value): bool|int
    {
        return $this->lazyObjectReal->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $end): array
    {
        return $this->lazyObjectReal->lrange(...\func_get_args());
    }

    public function lrem($key, $count, $value): bool|int
    {
        return $this->lazyObjectReal->lrem(...\func_get_args());
    }

    public function lset($key, $index, $value): bool
    {
        return $this->lazyObjectReal->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $end): bool
    {
        return $this->lazyObjectReal->ltrim(...\func_get_args());
    }

    public function mget($keys): array
    {
        return $this->lazyObjectReal->mget(...\func_get_args());
    }

    public function mset($key_values): bool
    {
        return $this->lazyObjectReal->mset(...\func_get_args());
    }

    public function msetnx($key_values): int
    {
        return $this->lazyObjectReal->msetnx(...\func_get_args());
    }

    public function multi(): \RedisCluster|bool
    {
        return $this->lazyObjectReal->multi(...\func_get_args());
    }

    public function object($subcommand, $key): int|string
    {
        return $this->lazyObjectReal->object(...\func_get_args());
    }

    public function persist($key): bool
    {
        return $this->lazyObjectReal->persist(...\func_get_args());
    }

    public function pexpire($key, $timeout): bool
    {
        return $this->lazyObjectReal->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp): bool
    {
        return $this->lazyObjectReal->pexpireat(...\func_get_args());
    }

    public function pfadd($key, $elements): bool
    {
        return $this->lazyObjectReal->pfadd(...\func_get_args());
    }

    public function pfcount($key): int
    {
        return $this->lazyObjectReal->pfcount(...\func_get_args());
    }

    public function pfmerge($key, $keys): bool
    {
        return $this->lazyObjectReal->pfmerge(...\func_get_args());
    }

    public function ping($key_or_address, $message): mixed
    {
        return $this->lazyObjectReal->ping(...\func_get_args());
    }

    public function psetex($key, $timeout, $value): bool
    {
        return $this->lazyObjectReal->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback): void
    {
        $this->lazyObjectReal->psubscribe(...\func_get_args());
    }

    public function pttl($key): int
    {
        return $this->lazyObjectReal->pttl(...\func_get_args());
    }

    public function publish($channel, $message): bool
    {
        return $this->lazyObjectReal->publish(...\func_get_args());
    }

    public function pubsub($key_or_address, ...$values): mixed
    {
        return $this->lazyObjectReal->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns): array|bool
    {
        return $this->lazyObjectReal->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address): bool|string
    {
        return $this->lazyObjectReal->randomkey(...\func_get_args());
    }

    public function rawcommand($key_or_address, $command, ...$args): mixed
    {
        return $this->lazyObjectReal->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey): bool
    {
        return $this->lazyObjectReal->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey): bool
    {
        return $this->lazyObjectReal->renamenx(...\func_get_args());
    }

    public function restore($key, $timeout, $value): bool
    {
        return $this->lazyObjectReal->restore(...\func_get_args());
    }

    public function role($key_or_address): mixed
    {
        return $this->lazyObjectReal->role(...\func_get_args());
    }

    public function rpop($key, $count = 0): array|bool|string
    {
        return $this->lazyObjectReal->rpop(...\func_get_args());
    }

    public function rpoplpush($src, $dst): bool|string
    {
        return $this->lazyObjectReal->rpoplpush(...\func_get_args());
    }

    public function rpush($key, $value, ...$other_values): bool|int
    {
        return $this->lazyObjectReal->rpush(...\func_get_args());
    }

    public function rpushx($key, $value): bool|int
    {
        return $this->lazyObjectReal->rpushx(...\func_get_args());
    }

    public function sadd($key, $value, ...$other_values): bool|int
    {
        return $this->lazyObjectReal->sadd(...\func_get_args());
    }

    public function saddarray($key, $values): bool|int
    {
        return $this->lazyObjectReal->saddarray(...\func_get_args());
    }

    public function save($key_or_address): bool
    {
        return $this->lazyObjectReal->save(...\func_get_args());
    }

    public function scan(&$iterator, $node, $pattern = null, $count = 0): array|bool
    {
        return $this->lazyObjectReal->scan(...\func_get_args());
    }

    public function scard($key): int
    {
        return $this->lazyObjectReal->scard(...\func_get_args());
    }

    public function script($key_or_address, ...$args): mixed
    {
        return $this->lazyObjectReal->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys): array
    {
        return $this->lazyObjectReal->sdiff(...\func_get_args());
    }

    public function sdiffstore($dst, $key, ...$other_keys): int
    {
        return $this->lazyObjectReal->sdiffstore(...\func_get_args());
    }

    public function set($key, $value): bool
    {
        return $this->lazyObjectReal->set(...\func_get_args());
    }

    public function setbit($key, $offset, $onoff): bool
    {
        return $this->lazyObjectReal->setbit(...\func_get_args());
    }

    public function setex($key, $value, $timeout): bool
    {
        return $this->lazyObjectReal->setex(...\func_get_args());
    }

    public function setnx($key, $value, $timeout): bool
    {
        return $this->lazyObjectReal->setnx(...\func_get_args());
    }

    public function setoption($option, $value): bool
    {
        return $this->lazyObjectReal->setoption(...\func_get_args());
    }

    public function setrange($key, $offset, $value): int
    {
        return $this->lazyObjectReal->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys): array
    {
        return $this->lazyObjectReal->sinter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->lazyObjectReal->sintercard(...\func_get_args());
    }

    public function sinterstore($dst, $key, ...$other_keys): bool
    {
        return $this->lazyObjectReal->sinterstore(...\func_get_args());
    }

    public function sismember($key): int
    {
        return $this->lazyObjectReal->sismember(...\func_get_args());
    }

    public function slowlog($key_or_address, ...$args): mixed
    {
        return $this->lazyObjectReal->slowlog(...\func_get_args());
    }

    public function smembers($key): array
    {
        return $this->lazyObjectReal->smembers(...\func_get_args());
    }

    public function smove($src, $dst, $member): bool
    {
        return $this->lazyObjectReal->smove(...\func_get_args());
    }

    public function sort($key, $options): bool|int|string
    {
        return $this->lazyObjectReal->sort(...\func_get_args());
    }

    public function spop($key): array|string
    {
        return $this->lazyObjectReal->spop(...\func_get_args());
    }

    public function srandmember($key, $count = 0): array|string
    {
        return $this->lazyObjectReal->srandmember(...\func_get_args());
    }

    public function srem($key, $value, ...$other_values): int
    {
        return $this->lazyObjectReal->srem(...\func_get_args());
    }

    public function sscan($key, &$iterator, $node, $pattern = null, $count = 0): array|bool
    {
        return $this->lazyObjectReal->sscan(...\func_get_args());
    }

    public function strlen($key): int
    {
        return $this->lazyObjectReal->strlen(...\func_get_args());
    }

    public function subscribe($channels, $cb): void
    {
        $this->lazyObjectReal->subscribe(...\func_get_args());
    }

    public function sunion($key, ...$other_keys): array|bool
    {
        return $this->lazyObjectReal->sunion(...\func_get_args());
    }

    public function sunionstore($dst, $key, ...$other_keys): int
    {
        return $this->lazyObjectReal->sunionstore(...\func_get_args());
    }

    public function time($key_or_address): array|bool
    {
        return $this->lazyObjectReal->time(...\func_get_args());
    }

    public function ttl($key): int
    {
        return $this->lazyObjectReal->ttl(...\func_get_args());
    }

    public function type($key): int
    {
        return $this->lazyObjectReal->type(...\func_get_args());
    }

    public function unsubscribe($channels): array|bool
    {
        return $this->lazyObjectReal->unsubscribe(...\func_get_args());
    }

    public function unlink($key, ...$other_keys): array
    {
        return $this->lazyObjectReal->unlink(...\func_get_args());
    }

    public function unwatch(): bool
    {
        return $this->lazyObjectReal->unwatch(...\func_get_args());
    }

    public function watch($key, ...$other_keys): bool
    {
        return $this->lazyObjectReal->watch(...\func_get_args());
    }

    public function xack($key, $group, $ids): int
    {
        return $this->lazyObjectReal->xack(...\func_get_args());
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false): string
    {
        return $this->lazyObjectReal->xadd(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_iddle, $ids, $options): array|string
    {
        return $this->lazyObjectReal->xclaim(...\func_get_args());
    }

    public function xdel($key, $ids): int
    {
        return $this->lazyObjectReal->xdel(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $arg1 = null, $arg2 = null, $arg3 = false): mixed
    {
        return $this->lazyObjectReal->xgroup(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null): mixed
    {
        return $this->lazyObjectReal->xinfo(...\func_get_args());
    }

    public function xlen($key): int
    {
        return $this->lazyObjectReal->xlen(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): string
    {
        return $this->lazyObjectReal->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): array|bool
    {
        return $this->lazyObjectReal->xrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): array|bool
    {
        return $this->lazyObjectReal->xread(...\func_get_args());
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): array|bool
    {
        return $this->lazyObjectReal->xreadgroup(...\func_get_args());
    }

    public function xrevrange($key, $start, $end, $count = -1): array|bool
    {
        return $this->lazyObjectReal->xrevrange(...\func_get_args());
    }

    public function xtrim($key, $maxlen, $approx = false): int
    {
        return $this->lazyObjectReal->xtrim(...\func_get_args());
    }

    public function zadd($key, $score, $member, ...$extra_args): int
    {
        return $this->lazyObjectReal->zadd(...\func_get_args());
    }

    public function zcard($key): int
    {
        return $this->lazyObjectReal->zcard(...\func_get_args());
    }

    public function zcount($key, $start, $end): int
    {
        return $this->lazyObjectReal->zcount(...\func_get_args());
    }

    public function zincrby($key, $value, $member): float
    {
        return $this->lazyObjectReal->zincrby(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null): int
    {
        return $this->lazyObjectReal->zinterstore(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->lazyObjectReal->zintercard(...\func_get_args());
    }

    public function zlexcount($key, $min, $max): int
    {
        return $this->lazyObjectReal->zlexcount(...\func_get_args());
    }

    public function zpopmax($key, $value = null): array|bool
    {
        return $this->lazyObjectReal->zpopmax(...\func_get_args());
    }

    public function zpopmin($key, $value = null): array|bool
    {
        return $this->lazyObjectReal->zpopmin(...\func_get_args());
    }

    public function zrange($key, $start, $end, $options = null): array|bool
    {
        return $this->lazyObjectReal->zrange(...\func_get_args());
    }

    public function zrangebylex($key, $start, $end, $options = null): array|bool
    {
        return $this->lazyObjectReal->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null): array|bool
    {
        return $this->lazyObjectReal->zrangebyscore(...\func_get_args());
    }

    public function zrank($key, $member): int
    {
        return $this->lazyObjectReal->zrank(...\func_get_args());
    }

    public function zrem($key, $value, ...$other_values): int
    {
        return $this->lazyObjectReal->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max): int
    {
        return $this->lazyObjectReal->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $min, $max): int
    {
        return $this->lazyObjectReal->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max): int
    {
        return $this->lazyObjectReal->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $min, $max, $options = null): array|bool
    {
        return $this->lazyObjectReal->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $min, $max, $options = null): array|bool
    {
        return $this->lazyObjectReal->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $min, $max, $options = null): array|bool
    {
        return $this->lazyObjectReal->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $member): int
    {
        return $this->lazyObjectReal->zrevrank(...\func_get_args());
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): array|bool
    {
        return $this->lazyObjectReal->zscan(...\func_get_args());
    }

    public function zscore($key): float
    {
        return $this->lazyObjectReal->zscore(...\func_get_args());
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null): int
    {
        return $this->lazyObjectReal->zunionstore(...\func_get_args());
    }
}
