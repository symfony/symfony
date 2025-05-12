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

use Relay\Cluster;
use Relay\Relay;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Contracts\Service\ResetInterface;

// Help opcache.preload discover always-needed symbols
class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

/**
 * @internal
 */
class RelayClusterProxy extends Cluster implements ResetInterface, LazyObjectInterface
{
    use RedisProxyTrait {
        resetLazyObject as reset;
    }

    public function __construct(
        string|null $name,
        array|null $seeds = null,
        int|float $connect_timeout = 0,
        int|float $command_timeout = 0,
        bool $persistent = false,
        #[\SensitiveParameter] mixed $auth = null,
        array|null $context = null,
    ) {
        $this->initializeLazyObject()->__construct(...\func_get_args());
    }

    public function close(): bool
    {
        return $this->initializeLazyObject()->close(...\func_get_args());
    }

    public function listen(?callable $callback): bool
    {
        return $this->initializeLazyObject()->listen(...\func_get_args());
    }

    public function onFlushed(?callable $callback): bool
    {
        return $this->initializeLazyObject()->onFlushed(...\func_get_args());
    }

    public function onInvalidated(?callable $callback, ?string $pattern = null): bool
    {
        return $this->initializeLazyObject()->onInvalidated(...\func_get_args());
    }

    public function dispatchEvents(): false|int
    {
        return $this->initializeLazyObject()->dispatchEvents(...\func_get_args());
    }

    public function dump(mixed $key): Cluster|string|false
    {
        return $this->initializeLazyObject()->dump(...\func_get_args());
    }

    public function getOption(int $option): mixed
    {
        return $this->initializeLazyObject()->getOption(...\func_get_args());
    }

    public function setOption(int $option, mixed $value): bool
    {
        return $this->initializeLazyObject()->setOption(...\func_get_args());
    }

    public function getTransferredBytes(): array|false
    {
        return $this->initializeLazyObject()->getTransferredBytes(...\func_get_args());
    }

    public function getrange(mixed $key, int $start, int $end): Cluster|string|false
    {
        return $this->initializeLazyObject()->getrange(...\func_get_args());
    }

    public function addIgnorePatterns(string ...$pattern): int
    {
        return $this->initializeLazyObject()->addIgnorePatterns(...\func_get_args());
    }

    public function addAllowPatterns(string ...$pattern): int
    {
        return $this->initializeLazyObject()->addAllowPatterns(...\func_get_args());
    }

    public function _serialize(mixed $value): string
    {
        return $this->initializeLazyObject()->_serialize(...\func_get_args());
    }

    public function _unserialize(string $value): mixed
    {
        return $this->initializeLazyObject()->_unserialize(...\func_get_args());
    }

    public function _compress(string $value): string
    {
        return $this->initializeLazyObject()->_compress(...\func_get_args());
    }

    public function _uncompress(string $value): string
    {
        return $this->initializeLazyObject()->_uncompress(...\func_get_args());
    }

    public function _pack(mixed $value): string
    {
        return $this->initializeLazyObject()->_pack(...\func_get_args());
    }

    public function _unpack(string $value): mixed
    {
        return $this->initializeLazyObject()->_unpack(...\func_get_args());
    }

    public function _prefix(mixed $value): string
    {
        return $this->initializeLazyObject()->_prefix(...\func_get_args());
    }

    public function getLastError(): ?string
    {
        return $this->initializeLazyObject()->getLastError(...\func_get_args());
    }

    public function clearLastError(): bool
    {
        return $this->initializeLazyObject()->clearLastError(...\func_get_args());
    }

    public function clearTransferredBytes(): bool
    {
        return $this->initializeLazyObject()->clearTransferredBytes(...\func_get_args());
    }

    public function endpointId(): array|false
    {
        return $this->initializeLazyObject()->endpointId(...\func_get_args());
    }

    public function rawCommand(array|string $key_or_address, string $cmd, mixed ...$args): mixed
    {
        return $this->initializeLazyObject()->rawCommand(...\func_get_args());
    }

    public function cluster(array|string $key_or_address, string $operation, mixed ...$args): mixed
    {
        return $this->initializeLazyObject()->cluster(...\func_get_args());
    }

    public function info(array|string $key_or_address, string ...$sections): Cluster|array|false
    {
        return $this->initializeLazyObject()->info(...\func_get_args());
    }

    public function flushdb(array|string $key_or_address, bool|null $sync = null): Cluster|bool
    {
        return $this->initializeLazyObject()->flushdb(...\func_get_args());
    }

    public function flushall(array|string $key_or_address, bool|null $sync = null): Cluster|bool
    {
        return $this->initializeLazyObject()->flushall(...\func_get_args());
    }

    public function dbsize(array|string $key_or_address): Cluster|int|false
    {
        return $this->initializeLazyObject()->dbsize(...\func_get_args());
    }

    public function waitaof(array|string $key_or_address, int $numlocal, int $numremote, int $timeout): Relay|array|false
    {
        return $this->initializeLazyObject()->waitaof(...\func_get_args());
    }

    public function restore(mixed $key, int $ttl, string $value, array|null $options = null): Cluster|bool
    {
        return $this->initializeLazyObject()->restore(...\func_get_args());
    }

    public function echo(array|string $key_or_address, string $message): Cluster|string|false
    {
        return $this->initializeLazyObject()->echo(...\func_get_args());
    }

    public function ping(array|string $key_or_address, string|null $message = null): Cluster|bool|string
    {
        return $this->initializeLazyObject()->ping(...\func_get_args());
    }

    public function idleTime(): int
    {
        return $this->initializeLazyObject()->idleTime(...\func_get_args());
    }

    public function randomkey(array|string $key_or_address): Cluster|bool|string
    {
        return $this->initializeLazyObject()->randomkey(...\func_get_args());
    }

    public function time(array|string $key_or_address): Cluster|array|false
    {
        return $this->initializeLazyObject()->time(...\func_get_args());
    }

    public function bgrewriteaof(array|string $key_or_address): Cluster|bool
    {
        return $this->initializeLazyObject()->bgrewriteaof(...\func_get_args());
    }

    public function lastsave(array|string $key_or_address): Cluster|false|int
    {
        return $this->initializeLazyObject()->lastsave(...\func_get_args());
    }

    public function lcs(mixed $key1, mixed $key2, array|null $options = null): mixed
    {
        return $this->initializeLazyObject()->lcs(...\func_get_args());
    }

    public function bgsave(array|string $key_or_address, bool $schedule = false): Cluster|bool
    {
        return $this->initializeLazyObject()->bgsave(...\func_get_args());
    }

    public function save(array|string $key_or_address): Cluster|bool
    {
        return $this->initializeLazyObject()->save(...\func_get_args());
    }

    public function role(array|string $key_or_address): Cluster|array|false
    {
        return $this->initializeLazyObject()->role(...\func_get_args());
    }

    public function ttl(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->ttl(...\func_get_args());
    }

    public function pttl(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->pttl(...\func_get_args());
    }

    public function exists(mixed ...$keys): Cluster|bool|int
    {
        return $this->initializeLazyObject()->exists(...\func_get_args());
    }

    public function eval(mixed $script, array $args = [], int $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval(...\func_get_args());
    }

    public function eval_ro(mixed $script, array $args = [], int $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval_ro(...\func_get_args());
    }

    public function evalsha(string $sha, array $args = [], int $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha(...\func_get_args());
    }

    public function evalsha_ro(string $sha, array $args = [], int $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha_ro(...\func_get_args());
    }

    public function client(array|string $key_or_address, string $operation, mixed ...$args): mixed
    {
        return $this->initializeLazyObject()->client(...\func_get_args());
    }

    public function geoadd(mixed $key, float $lng, float $lat, string $member, mixed ...$other_triples_and_options): Cluster|false|int
    {
        return $this->initializeLazyObject()->geoadd(...\func_get_args());
    }

    public function geodist(mixed $key, string $src, string $dst, string|null $unit = null): Cluster|float|false
    {
        return $this->initializeLazyObject()->geodist(...\func_get_args());
    }

    public function geohash(mixed $key, string $member, string ...$other_members): Cluster|array|false
    {
        return $this->initializeLazyObject()->geohash(...\func_get_args());
    }

    public function georadius(mixed $key, float $lng, float $lat, float $radius, string $unit, array $options = []): mixed
    {
        return $this->initializeLazyObject()->georadius(...\func_get_args());
    }

    public function georadiusbymember(mixed $key, string $member, float $radius, string $unit, array $options = []): mixed
    {
        return $this->initializeLazyObject()->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro(mixed $key, string $member, float $radius, string $unit, array $options = []): mixed
    {
        return $this->initializeLazyObject()->georadiusbymember_ro(...\func_get_args());
    }

    public function georadius_ro(mixed $key, float $lng, float $lat, float $radius, string $unit, array $options = []): mixed
    {
        return $this->initializeLazyObject()->georadius_ro(...\func_get_args());
    }

    public function geosearchstore(mixed $dstkey, mixed $srckey, array|string $position, array|int|float $shape, string $unit, array $options = []): Cluster|false|int
    {
        return $this->initializeLazyObject()->geosearchstore(...\func_get_args());
    }

    public function geosearch(mixed $key, array|string $position, array|int|float $shape, string $unit, array $options = []): Cluster|array|false
    {
        return $this->initializeLazyObject()->geosearch(...\func_get_args());
    }

    public function get(mixed $key): mixed
    {
        return $this->initializeLazyObject()->get(...\func_get_args());
    }

    public function getset(mixed $key, mixed $value): mixed
    {
        return $this->initializeLazyObject()->getset(...\func_get_args());
    }

    public function setrange(mixed $key, int $start, mixed $value): Cluster|false|int
    {
        return $this->initializeLazyObject()->setrange(...\func_get_args());
    }

    public function getbit(mixed $key, int $pos): Cluster|false|int
    {
        return $this->initializeLazyObject()->getbit(...\func_get_args());
    }

    public function bitcount(mixed $key, int $start = 0, int $end = -1, bool $by_bit = false): Cluster|false|int
    {
        return $this->initializeLazyObject()->bitcount(...\func_get_args());
    }

    public function config(array|string $key_or_address, string $operation, mixed ...$args): mixed
    {
        return $this->initializeLazyObject()->config(...\func_get_args());
    }

    public function command(mixed ...$args): Cluster|array|false|int
    {
        return $this->initializeLazyObject()->command(...\func_get_args());
    }

    public function bitop(string $operation, string $dstkey, string $srckey, string ...$other_keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->bitop(...\func_get_args());
    }

    public function bitpos(mixed $key, int $bit, ?int $start = null, ?int $end = null, bool $by_bit = false): Cluster|false|int
    {
        return $this->initializeLazyObject()->bitpos(...\func_get_args());
    }

    public function blmove(mixed $srckey, mixed $dstkey, string $srcpos, string $dstpos, float $timeout): Cluster|string|false|null
    {
        return $this->initializeLazyObject()->blmove(...\func_get_args());
    }

    public function lmove(mixed $srckey, mixed $dstkey, string $srcpos, string $dstpos): Cluster|string|false|null
    {
        return $this->initializeLazyObject()->lmove(...\func_get_args());
    }

    public function setbit(mixed $key, int $pos, int $value): Cluster|false|int
    {
        return $this->initializeLazyObject()->setbit(...\func_get_args());
    }

    public function acl(array|string $key_or_address, string $operation, string ...$args): mixed
    {
        return $this->initializeLazyObject()->acl(...\func_get_args());
    }

    public function append(mixed $key, mixed $value): Cluster|false|int
    {
        return $this->initializeLazyObject()->append(...\func_get_args());
    }

    public function set(mixed $key, mixed $value, mixed $options = null): Cluster|string|bool
    {
        return $this->initializeLazyObject()->set(...\func_get_args());
    }

    public function getex(mixed $key, ?array $options = null): mixed
    {
        return $this->initializeLazyObject()->getex(...\func_get_args());
    }

    public function setex(mixed $key, int $seconds, mixed $value): Cluster|bool
    {
        return $this->initializeLazyObject()->setex(...\func_get_args());
    }

    public function pfadd(mixed $key, array $elements): Cluster|false|int
    {
        return $this->initializeLazyObject()->pfadd(...\func_get_args());
    }

    public function pfcount(mixed $key): Cluster|int|false
    {
        return $this->initializeLazyObject()->pfcount(...\func_get_args());
    }

    public function pfmerge(string $dstkey, array $srckeys): Cluster|bool
    {
        return $this->initializeLazyObject()->pfmerge(...\func_get_args());
    }

    public function psetex(mixed $key, int $milliseconds, mixed $value): Cluster|bool
    {
        return $this->initializeLazyObject()->psetex(...\func_get_args());
    }

    public function publish(string $channel, string $message): Cluster|false|int
    {
        return $this->initializeLazyObject()->publish(...\func_get_args());
    }

    public function pubsub(array|string $key_or_address, string $operation, mixed ...$args): mixed
    {
        return $this->initializeLazyObject()->pubsub(...\func_get_args());
    }

    public function setnx(mixed $key, mixed $value): Cluster|bool
    {
        return $this->initializeLazyObject()->setnx(...\func_get_args());
    }

    public function mget(array $keys): Cluster|array|false
    {
        return $this->initializeLazyObject()->mget(...\func_get_args());
    }

    public function mset(array $kvals): Cluster|array|bool
    {
        return $this->initializeLazyObject()->mset(...\func_get_args());
    }

    public function msetnx(array $kvals): Cluster|array|bool
    {
        return $this->initializeLazyObject()->msetnx(...\func_get_args());
    }

    public function rename(mixed $key, mixed $newkey): Cluster|bool
    {
        return $this->initializeLazyObject()->rename(...\func_get_args());
    }

    public function renamenx(mixed $key, mixed $newkey): Cluster|bool
    {
        return $this->initializeLazyObject()->renamenx(...\func_get_args());
    }

    public function del(mixed ...$keys): Cluster|bool|int
    {
        return $this->initializeLazyObject()->del(...\func_get_args());
    }

    public function unlink(mixed ...$keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->unlink(...\func_get_args());
    }

    public function expire(mixed $key, int $seconds, string|null $mode = null): Cluster|bool
    {
        return $this->initializeLazyObject()->expire(...\func_get_args());
    }

    public function pexpire(mixed $key, int $milliseconds): Cluster|bool
    {
        return $this->initializeLazyObject()->pexpire(...\func_get_args());
    }

    public function expireat(mixed $key, int $timestamp): Cluster|bool
    {
        return $this->initializeLazyObject()->expireat(...\func_get_args());
    }

    public function expiretime(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->expiretime(...\func_get_args());
    }

    public function pexpireat(mixed $key, int $timestamp_ms): Cluster|bool
    {
        return $this->initializeLazyObject()->pexpireat(...\func_get_args());
    }

    public static function flushMemory(?string $endpointId = null, ?int $db = null): bool
    {
        return Cluster::flushMemory(...\func_get_args());
    }

    public function pexpiretime(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->pexpiretime(...\func_get_args());
    }

    public function persist(mixed $key): Cluster|bool
    {
        return $this->initializeLazyObject()->persist(...\func_get_args());
    }

    public function type(mixed $key): Cluster|bool|int|string
    {
        return $this->initializeLazyObject()->type(...\func_get_args());
    }

    public function lrange(mixed $key, int $start, int $stop): Cluster|array|false
    {
        return $this->initializeLazyObject()->lrange(...\func_get_args());
    }

    public function lpush(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->lpush(...\func_get_args());
    }

    public function rpush(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->rpush(...\func_get_args());
    }

    public function lpushx(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->lpushx(...\func_get_args());
    }

    public function rpushx(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->rpushx(...\func_get_args());
    }

    public function lset(mixed $key, int $index, mixed $member): Cluster|bool
    {
        return $this->initializeLazyObject()->lset(...\func_get_args());
    }

    public function lpop(mixed $key, int $count = 1): mixed
    {
        return $this->initializeLazyObject()->lpop(...\func_get_args());
    }

    public function lpos(mixed $key, mixed $value, array|null $options = null): mixed
    {
        return $this->initializeLazyObject()->lpos(...\func_get_args());
    }

    public function rpop(mixed $key, int $count = 1): mixed
    {
        return $this->initializeLazyObject()->rpop(...\func_get_args());
    }

    public function rpoplpush(mixed $srckey, mixed $dstkey): mixed
    {
        return $this->initializeLazyObject()->rpoplpush(...\func_get_args());
    }

    public function brpoplpush(mixed $srckey, mixed $dstkey, float $timeout): mixed
    {
        return $this->initializeLazyObject()->brpoplpush(...\func_get_args());
    }

    public function blpop(string|array $key, string|float $timeout_or_key, mixed ...$extra_args): Cluster|array|false|null
    {
        return $this->initializeLazyObject()->blpop(...\func_get_args());
    }

    public function blmpop(float $timeout, array $keys, string $from, int $count = 1): mixed
    {
        return $this->initializeLazyObject()->blmpop(...\func_get_args());
    }

    public function bzmpop(float $timeout, array $keys, string $from, int $count = 1): Cluster|array|false|null
    {
        return $this->initializeLazyObject()->bzmpop(...\func_get_args());
    }

    public function lmpop(array $keys, string $from, int $count = 1): mixed
    {
        return $this->initializeLazyObject()->lmpop(...\func_get_args());
    }

    public function zmpop(array $keys, string $from, int $count = 1): Cluster|array|false|null
    {
        return $this->initializeLazyObject()->zmpop(...\func_get_args());
    }

    public function brpop(string|array $key, string|float $timeout_or_key, mixed ...$extra_args): Cluster|array|false|null
    {
        return $this->initializeLazyObject()->brpop(...\func_get_args());
    }

    public function bzpopmax(string|array $key, string|float $timeout_or_key, mixed ...$extra_args): Cluster|array|false|null
    {
        return $this->initializeLazyObject()->bzpopmax(...\func_get_args());
    }

    public function bzpopmin(string|array $key, string|float $timeout_or_key, mixed ...$extra_args): Cluster|array|false|null
    {
        return $this->initializeLazyObject()->bzpopmin(...\func_get_args());
    }

    public function object(string $op, mixed $key): mixed
    {
        return $this->initializeLazyObject()->object(...\func_get_args());
    }

    public function geopos(mixed $key, mixed ...$members): Cluster|array|false
    {
        return $this->initializeLazyObject()->geopos(...\func_get_args());
    }

    public function lrem(mixed $key, mixed $member, int $count = 0): Cluster|false|int
    {
        return $this->initializeLazyObject()->lrem(...\func_get_args());
    }

    public function lindex(mixed $key, int $index): mixed
    {
        return $this->initializeLazyObject()->lindex(...\func_get_args());
    }

    public function linsert(mixed $key, string $op, mixed $pivot, mixed $element): Cluster|false|int
    {
        return $this->initializeLazyObject()->linsert(...\func_get_args());
    }

    public function ltrim(mixed $key, int $start, int $end): Cluster|bool
    {
        return $this->initializeLazyObject()->ltrim(...\func_get_args());
    }

    public static function maxMemory(): int
    {
        return Cluster::maxMemory();
    }

    public function hget(mixed $key, mixed $member): mixed
    {
        return $this->initializeLazyObject()->hget(...\func_get_args());
    }

    public function hstrlen(mixed $key, mixed $member): Cluster|false|int
    {
        return $this->initializeLazyObject()->hstrlen(...\func_get_args());
    }

    public function hgetall(mixed $key): Cluster|array|false
    {
        return $this->initializeLazyObject()->hgetall(...\func_get_args());
    }

    public function hkeys(mixed $key): Cluster|array|false
    {
        return $this->initializeLazyObject()->hkeys(...\func_get_args());
    }

    public function hvals(mixed $key): Cluster|array|false
    {
        return $this->initializeLazyObject()->hvals(...\func_get_args());
    }

    public function hmget(mixed $key, array $members): Cluster|array|false
    {
        return $this->initializeLazyObject()->hmget(...\func_get_args());
    }

    public function hmset(mixed $key, array $members): Cluster|bool
    {
        return $this->initializeLazyObject()->hmset(...\func_get_args());
    }

    public function hexists(mixed $key, mixed $member): Cluster|bool
    {
        return $this->initializeLazyObject()->hexists(...\func_get_args());
    }

    public function hrandfield(mixed $key, array|null $options = null): Cluster|array|string|false
    {
        return $this->initializeLazyObject()->hrandfield(...\func_get_args());
    }

    public function hsetnx(mixed $key, mixed $member, mixed $value): Cluster|bool
    {
        return $this->initializeLazyObject()->hsetnx(...\func_get_args());
    }

    public function hset(mixed $key, mixed ...$keys_and_vals): Cluster|int|false
    {
        return $this->initializeLazyObject()->hset(...\func_get_args());
    }

    public function hdel(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->hdel(...\func_get_args());
    }

    public function hincrby(mixed $key, mixed $member, int $value): Cluster|false|int
    {
        return $this->initializeLazyObject()->hincrby(...\func_get_args());
    }

    public function hincrbyfloat(mixed $key, mixed $member, float $value): Cluster|bool|float
    {
        return $this->initializeLazyObject()->hincrbyfloat(...\func_get_args());
    }

    public function incr(mixed $key, int $by = 1): Cluster|false|int
    {
        return $this->initializeLazyObject()->incr(...\func_get_args());
    }

    public function decr(mixed $key, int $by = 1): Cluster|false|int
    {
        return $this->initializeLazyObject()->decr(...\func_get_args());
    }

    public function incrby(mixed $key, int $value): Cluster|false|int
    {
        return $this->initializeLazyObject()->incrby(...\func_get_args());
    }

    public function decrby(mixed $key, int $value): Cluster|false|int
    {
        return $this->initializeLazyObject()->decrby(...\func_get_args());
    }

    public function incrbyfloat(mixed $key, float $value): Cluster|false|float
    {
        return $this->initializeLazyObject()->incrbyfloat(...\func_get_args());
    }

    public function sdiff(mixed $key, mixed ...$other_keys): Cluster|array|false
    {
        return $this->initializeLazyObject()->sdiff(...\func_get_args());
    }

    public function sdiffstore(mixed $key, mixed ...$other_keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->sdiffstore(...\func_get_args());
    }

    public function sinter(mixed $key, mixed ...$other_keys): Cluster|array|false
    {
        return $this->initializeLazyObject()->sinter(...\func_get_args());
    }

    public function sintercard(array $keys, int $limit = -1): Cluster|false|int
    {
        return $this->initializeLazyObject()->sintercard(...\func_get_args());
    }

    public function sinterstore(mixed $key, mixed ...$other_keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->sinterstore(...\func_get_args());
    }

    public function sunion(mixed $key, mixed ...$other_keys): Cluster|array|false
    {
        return $this->initializeLazyObject()->sunion(...\func_get_args());
    }

    public function sunionstore(mixed $key, mixed ...$other_keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->sunionstore(...\func_get_args());
    }

    public function subscribe(array $channels, callable $callback): bool
    {
        return $this->initializeLazyObject()->subscribe(...\func_get_args());
    }

    public function unsubscribe(array $channels = []): bool
    {
        return $this->initializeLazyObject()->unsubscribe(...\func_get_args());
    }

    public function psubscribe(array $patterns, callable $callback): bool
    {
        return $this->initializeLazyObject()->psubscribe(...\func_get_args());
    }

    public function punsubscribe(array $patterns = []): bool
    {
        return $this->initializeLazyObject()->punsubscribe(...\func_get_args());
    }

    public function ssubscribe(array $channels, callable $callback): bool
    {
        return $this->initializeLazyObject()->ssubscribe(...\func_get_args());
    }

    public function sunsubscribe(array $channels = []): bool
    {
        return $this->initializeLazyObject()->sunsubscribe(...\func_get_args());
    }

    public function touch(array|string $key_or_array, mixed ...$more_keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->touch(...\func_get_args());
    }

    public function multi(int $mode = Relay::MULTI): Cluster|bool
    {
        return $this->initializeLazyObject()->multi(...\func_get_args());
    }

    public function exec(): array|false
    {
        return $this->initializeLazyObject()->exec(...\func_get_args());
    }

    public function watch(mixed $key, mixed ...$other_keys): Cluster|bool
    {
        return $this->initializeLazyObject()->watch(...\func_get_args());
    }

    public function unwatch(): Cluster|bool
    {
        return $this->initializeLazyObject()->unwatch(...\func_get_args());
    }

    public function discard(): bool
    {
        return $this->initializeLazyObject()->discard(...\func_get_args());
    }

    public function getMode(bool $masked = false): int
    {
        return $this->initializeLazyObject()->getMode(...\func_get_args());
    }

    public function scan(mixed &$iterator, array|string $key_or_address, mixed $match = null, int $count = 0, string|null $type = null): array|false
    {
        return $this->initializeLazyObject()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function hscan(mixed $key, mixed &$iterator, mixed $match = null, int $count = 0): array|false
    {
        return $this->initializeLazyObject()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function sscan(mixed $key, mixed &$iterator, mixed $match = null, int $count = 0): array|false
    {
        return $this->initializeLazyObject()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zscan(mixed $key, mixed &$iterator, mixed $match = null, int $count = 0): array|false
    {
        return $this->initializeLazyObject()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zscore(mixed $key, mixed $member): Cluster|float|false
    {
        return $this->initializeLazyObject()->zscore(...\func_get_args());
    }

    public function keys(mixed $pattern): Cluster|array|false
    {
        return $this->initializeLazyObject()->keys(...\func_get_args());
    }

    public function slowlog(array|string $key_or_address, string $operation, mixed ...$args): Cluster|array|bool|int
    {
        return $this->initializeLazyObject()->slowlog(...\func_get_args());
    }

    public function xadd(mixed $key, string $id, array $values, int $maxlen = 0, bool $approx = false, bool $nomkstream = false): Cluster|string|false
    {
        return $this->initializeLazyObject()->xadd(...\func_get_args());
    }

    public function smembers(mixed $key): Cluster|array|false
    {
        return $this->initializeLazyObject()->smembers(...\func_get_args());
    }

    public function sismember(mixed $key, mixed $member): Cluster|bool
    {
        return $this->initializeLazyObject()->sismember(...\func_get_args());
    }

    public function smismember(mixed $key, mixed ...$members): Cluster|array|false
    {
        return $this->initializeLazyObject()->smismember(...\func_get_args());
    }

    public function srem(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->srem(...\func_get_args());
    }

    public function sadd(mixed $key, mixed $member, mixed ...$members): Cluster|false|int
    {
        return $this->initializeLazyObject()->sadd(...\func_get_args());
    }

    public function sort(mixed $key, array $options = []): Cluster|array|false|int
    {
        return $this->initializeLazyObject()->sort(...\func_get_args());
    }

    public function sort_ro(mixed $key, array $options = []): Cluster|array|false|int
    {
        return $this->initializeLazyObject()->sort_ro(...\func_get_args());
    }

    public function smove(mixed $srckey, mixed $dstkey, mixed $member): Cluster|bool
    {
        return $this->initializeLazyObject()->smove(...\func_get_args());
    }

    public function spop(mixed $key, int $count = 1): mixed
    {
        return $this->initializeLazyObject()->spop(...\func_get_args());
    }

    public function srandmember(mixed $key, int $count = 1): mixed
    {
        return $this->initializeLazyObject()->srandmember(...\func_get_args());
    }

    public function scard(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->scard(...\func_get_args());
    }

    public function script(array|string $key_or_address, string $operation, string ...$args): mixed
    {
        return $this->initializeLazyObject()->script(...\func_get_args());
    }

    public function strlen(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->strlen(...\func_get_args());
    }

    public function hlen(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->hlen(...\func_get_args());
    }

    public function llen(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->llen(...\func_get_args());
    }

    public function xack(mixed $key, string $group, array $ids): Cluster|false|int
    {
        return $this->initializeLazyObject()->xack(...\func_get_args());
    }

    public function xclaim(mixed $key, string $group, string $consumer, int $min_idle, array $ids, array $options): Cluster|array|bool
    {
        return $this->initializeLazyObject()->xclaim(...\func_get_args());
    }

    public function xautoclaim(mixed $key, string $group, string $consumer, int $min_idle, string $start, int $count = -1, bool $justid = false): Cluster|array|bool
    {
        return $this->initializeLazyObject()->xautoclaim(...\func_get_args());
    }

    public function xlen(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->xlen(...\func_get_args());
    }

    public function xgroup(string $operation, mixed $key = null, ?string $group = null, ?string $id_or_consumer = null, bool $mkstream = false, int $entries_read = -2): mixed
    {
        return $this->initializeLazyObject()->xgroup(...\func_get_args());
    }

    public function xdel(mixed $key, array $ids): Cluster|false|int
    {
        return $this->initializeLazyObject()->xdel(...\func_get_args());
    }

    public function xinfo(string $operation, string|null $arg1 = null, string|null $arg2 = null, int $count = -1): mixed
    {
        return $this->initializeLazyObject()->xinfo(...\func_get_args());
    }

    public function xpending(mixed $key, string $group, string|null $start = null, string|null $end = null, int $count = -1, string|null $consumer = null, int $idle = 0): Cluster|array|false
    {
        return $this->initializeLazyObject()->xpending(...\func_get_args());
    }

    public function xrange(mixed $key, string $start, string $end, int $count = -1): Cluster|array|false
    {
        return $this->initializeLazyObject()->xrange(...\func_get_args());
    }

    public function xread(array $streams, int $count = -1, int $block = -1): Cluster|array|bool|null
    {
        return $this->initializeLazyObject()->xread(...\func_get_args());
    }

    public function xreadgroup(mixed $key, string $consumer, array $streams, int $count = 1, int $block = 1): Cluster|array|bool|null
    {
        return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
    }

    public function xrevrange(mixed $key, string $end, string $start, int $count = -1): Cluster|array|bool
    {
        return $this->initializeLazyObject()->xrevrange(...\func_get_args());
    }

    public function xtrim(mixed $key, string $threshold, bool $approx = false, bool $minid = false, int $limit = -1): Cluster|false|int
    {
        return $this->initializeLazyObject()->xtrim(...\func_get_args());
    }

    public function zadd(mixed $key, mixed ...$args): mixed
    {
        return $this->initializeLazyObject()->zadd(...\func_get_args());
    }

    public function zrandmember(mixed $key, array|null $options = null): mixed
    {
        return $this->initializeLazyObject()->zrandmember(...\func_get_args());
    }

    public function zrange(mixed $key, string $start, string $end, mixed $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zrange(...\func_get_args());
    }

    public function zrevrange(mixed $key, int $start, int $end, mixed $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zrevrange(...\func_get_args());
    }

    public function zrangebyscore(mixed $key, mixed $start, mixed $end, mixed $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zrangebyscore(...\func_get_args());
    }

    public function zrevrangebyscore(mixed $key, mixed $start, mixed $end, mixed $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank(mixed $key, mixed $rank, bool $withscore = false): Cluster|array|int|false
    {
        return $this->initializeLazyObject()->zrevrank(...\func_get_args());
    }

    public function zrangestore(mixed $dstkey, mixed $srckey, mixed $start, mixed $end, mixed $options = null): Cluster|false|int
    {
        return $this->initializeLazyObject()->zrangestore(...\func_get_args());
    }

    public function zrank(mixed $key, mixed $rank, bool $withscore = false): Cluster|array|int|false
    {
        return $this->initializeLazyObject()->zrank(...\func_get_args());
    }

    public function zrangebylex(mixed $key, mixed $min, mixed $max, int $offset = -1, int $count = -1): Cluster|array|false
    {
        return $this->initializeLazyObject()->zrangebylex(...\func_get_args());
    }

    public function zrevrangebylex(mixed $key, mixed $max, mixed $min, int $offset = -1, int $count = -1): Cluster|array|false
    {
        return $this->initializeLazyObject()->zrevrangebylex(...\func_get_args());
    }

    public function zrem(mixed $key, mixed ...$args): Cluster|false|int
    {
        return $this->initializeLazyObject()->zrem(...\func_get_args());
    }

    public function zremrangebylex(mixed $key, mixed $min, mixed $max): Cluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank(mixed $key, int $start, int $end): Cluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore(mixed $key, mixed $min, mixed $max): Cluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebyscore(...\func_get_args());
    }

    public function zcard(mixed $key): Cluster|false|int
    {
        return $this->initializeLazyObject()->zcard(...\func_get_args());
    }

    public function zcount(mixed $key, mixed $min, mixed $max): Cluster|false|int
    {
        return $this->initializeLazyObject()->zcount(...\func_get_args());
    }

    public function zdiff(array $keys, array|null $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zdiff(...\func_get_args());
    }

    public function zdiffstore(mixed $dstkey, array $keys): Cluster|false|int
    {
        return $this->initializeLazyObject()->zdiffstore(...\func_get_args());
    }

    public function zincrby(mixed $key, float $score, mixed $member): Cluster|false|float
    {
        return $this->initializeLazyObject()->zincrby(...\func_get_args());
    }

    public function zlexcount(mixed $key, mixed $min, mixed $max): Cluster|false|int
    {
        return $this->initializeLazyObject()->zlexcount(...\func_get_args());
    }

    public function zmscore(mixed $key, mixed ...$members): Cluster|array|false
    {
        return $this->initializeLazyObject()->zmscore(...\func_get_args());
    }

    public function zinter(array $keys, array|null $weights = null, mixed $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zinter(...\func_get_args());
    }

    public function zintercard(array $keys, int $limit = -1): Cluster|false|int
    {
        return $this->initializeLazyObject()->zintercard(...\func_get_args());
    }

    public function zinterstore(mixed $dstkey, array $keys, array|null $weights = null, mixed $options = null): Cluster|false|int
    {
        return $this->initializeLazyObject()->zinterstore(...\func_get_args());
    }

    public function zunion(array $keys, array|null $weights = null, mixed $options = null): Cluster|array|false
    {
        return $this->initializeLazyObject()->zunion(...\func_get_args());
    }

    public function zunionstore(mixed $dstkey, array $keys, array|null $weights = null, mixed $options = null): Cluster|false|int
    {
        return $this->initializeLazyObject()->zunionstore(...\func_get_args());
    }

    public function zpopmin(mixed $key, int $count = 1): Cluster|array|false
    {
        return $this->initializeLazyObject()->zpopmin(...\func_get_args());
    }

    public function zpopmax(mixed $key, int $count = 1): Cluster|array|false
    {
        return $this->initializeLazyObject()->zpopmax(...\func_get_args());
    }

    public function _getKeys(): array|false
    {
        return $this->initializeLazyObject()->_getKeys(...\func_get_args());
    }

    public function _masters(): array
    {
        return $this->initializeLazyObject()->_masters(...\func_get_args());
    }

    public function copy(mixed $srckey, mixed $dstkey, array|null $options = null): Cluster|bool
    {
        return $this->initializeLazyObject()->copy(...\func_get_args());
    }
}
