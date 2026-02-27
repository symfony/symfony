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

use Symfony\Component\Cache\Traits\Relay\RelayCluster20Trait;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Contracts\Service\ResetInterface;

// Help opcache.preload discover always-needed symbols
class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

/**
 * @internal
 */
class RelayClusterProxy extends \Relay\Cluster implements ResetInterface, LazyObjectInterface
{
    use RedisProxyTrait {
        resetLazyObject as reset;
    }
    use RelayCluster20Trait;

    public function __construct($name, $seeds = null, $connect_timeout = 0, $command_timeout = 0, $persistent = false, #[\SensitiveParameter] $auth = null, $context = null)
    {
        $this->initializeLazyObject()->__construct(...\func_get_args());
    }

    public function _compress($value): string
    {
        return $this->initializeLazyObject()->_compress(...\func_get_args());
    }

    public function _getKeys(): array|false
    {
        return $this->initializeLazyObject()->_getKeys(...\func_get_args());
    }

    public function _masters(): array
    {
        return $this->initializeLazyObject()->_masters(...\func_get_args());
    }

    public function _pack($value): string
    {
        return $this->initializeLazyObject()->_pack(...\func_get_args());
    }

    public function _prefix($value): string
    {
        return $this->initializeLazyObject()->_prefix(...\func_get_args());
    }

    public function _serialize($value): string
    {
        return $this->initializeLazyObject()->_serialize(...\func_get_args());
    }

    public function _uncompress($value): string
    {
        return $this->initializeLazyObject()->_uncompress(...\func_get_args());
    }

    public function _unpack($value): mixed
    {
        return $this->initializeLazyObject()->_unpack(...\func_get_args());
    }

    public function _unserialize($value): mixed
    {
        return $this->initializeLazyObject()->_unserialize(...\func_get_args());
    }

    public function acl($key_or_address, $operation, ...$args): mixed
    {
        return $this->initializeLazyObject()->acl(...\func_get_args());
    }

    public function addAllowPatterns(...$pattern): int
    {
        return $this->initializeLazyObject()->addAllowPatterns(...\func_get_args());
    }

    public function addIgnorePatterns(...$pattern): int
    {
        return $this->initializeLazyObject()->addIgnorePatterns(...\func_get_args());
    }

    public function append($key, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address, $schedule = false): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->bgsave(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1, $by_bit = false): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->bitcount(...\func_get_args());
    }

    public function bitop($operation, $dstkey, $srckey, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null, $by_bit = false): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->bitpos(...\func_get_args());
    }

    public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): \Relay\Cluster|false|string|null
    {
        return $this->initializeLazyObject()->blmove(...\func_get_args());
    }

    public function blmpop($timeout, $keys, $from, $count = 1): mixed
    {
        return $this->initializeLazyObject()->blmpop(...\func_get_args());
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initializeLazyObject()->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initializeLazyObject()->brpop(...\func_get_args());
    }

    public function brpoplpush($srckey, $dstkey, $timeout): mixed
    {
        return $this->initializeLazyObject()->brpoplpush(...\func_get_args());
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Relay\Cluster|array|false|null
    {
        return $this->initializeLazyObject()->bzmpop(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initializeLazyObject()->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): \Relay\Cluster|array|false|null
    {
        return $this->initializeLazyObject()->bzpopmin(...\func_get_args());
    }

    public function clearLastError(): bool
    {
        return $this->initializeLazyObject()->clearLastError(...\func_get_args());
    }

    public function clearTransferredBytes(): bool
    {
        return $this->initializeLazyObject()->clearTransferredBytes(...\func_get_args());
    }

    public function client($key_or_address, $operation, ...$args): mixed
    {
        return $this->initializeLazyObject()->client(...\func_get_args());
    }

    public function close(): bool
    {
        return $this->initializeLazyObject()->close(...\func_get_args());
    }

    public function cluster($key_or_address, $operation, ...$args): mixed
    {
        return $this->initializeLazyObject()->cluster(...\func_get_args());
    }

    public function command(...$args): \Relay\Cluster|array|false|int
    {
        return $this->initializeLazyObject()->command(...\func_get_args());
    }

    public function config($key_or_address, $operation, ...$args): mixed
    {
        return $this->initializeLazyObject()->config(...\func_get_args());
    }

    public function copy($srckey, $dstkey, $options = null): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->copy(...\func_get_args());
    }

    public function dbsize($key_or_address): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->dbsize(...\func_get_args());
    }

    public function decr($key, $by = 1): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->decr(...\func_get_args());
    }

    public function decrby($key, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->decrby(...\func_get_args());
    }

    public function del(...$keys): \Relay\Cluster|bool|int
    {
        return $this->initializeLazyObject()->del(...\func_get_args());
    }

    public function delifeq($key, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->delifeq(...\func_get_args());
    }

    public function discard(): bool
    {
        return $this->initializeLazyObject()->discard(...\func_get_args());
    }

    public function dispatchEvents(): false|int
    {
        return $this->initializeLazyObject()->dispatchEvents(...\func_get_args());
    }

    public function dump($key): \Relay\Cluster|false|string
    {
        return $this->initializeLazyObject()->dump(...\func_get_args());
    }

    public function echo($key_or_address, $message): \Relay\Cluster|false|string
    {
        return $this->initializeLazyObject()->echo(...\func_get_args());
    }

    public function endpointId(): array|false
    {
        return $this->initializeLazyObject()->endpointId(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval(...\func_get_args());
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval_ro(...\func_get_args());
    }

    public function evalsha($sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha(...\func_get_args());
    }

    public function evalsha_ro($sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha_ro(...\func_get_args());
    }

    public function exec(): array|false
    {
        return $this->initializeLazyObject()->exec(...\func_get_args());
    }

    public function exists(...$keys): \Relay\Cluster|bool|int
    {
        return $this->initializeLazyObject()->exists(...\func_get_args());
    }

    public function expire($key, $seconds, $mode = null): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->expireat(...\func_get_args());
    }

    public function expiretime($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->expiretime(...\func_get_args());
    }

    public function flushSlotCache(): bool
    {
        return $this->initializeLazyObject()->flushSlotCache(...\func_get_args());
    }

    public function flushall($key_or_address, $sync = null): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->flushall(...\func_get_args());
    }

    public function flushdb($key_or_address, $sync = null): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->flushdb(...\func_get_args());
    }

    public function fullscan($match = null, $count = 0, $type = null): \Generator|false
    {
        return $this->initializeLazyObject()->fullscan(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dst, $unit = null): \Relay\Cluster|false|float
    {
        return $this->initializeLazyObject()->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->geohash(...\func_get_args());
    }

    public function geopos($key, ...$members): \Relay\Cluster|array|false
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

    public function geosearch($key, $position, $shape, $unit, $options = []): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->geosearch(...\func_get_args());
    }

    public function geosearchstore($dstkey, $srckey, $position, $shape, $unit, $options = []): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->geosearchstore(...\func_get_args());
    }

    public function get($key): mixed
    {
        return $this->initializeLazyObject()->get(...\func_get_args());
    }

    public function getLastError(): ?string
    {
        return $this->initializeLazyObject()->getLastError(...\func_get_args());
    }

    public function getMode($masked = false): int
    {
        return $this->initializeLazyObject()->getMode(...\func_get_args());
    }

    public function getOption($option): mixed
    {
        return $this->initializeLazyObject()->getOption(...\func_get_args());
    }

    public function getTransferredBytes(): array|false
    {
        return $this->initializeLazyObject()->getTransferredBytes(...\func_get_args());
    }

    public function getWithMeta($key): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->getWithMeta(...\func_get_args());
    }

    public function getbit($key, $pos): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->getbit(...\func_get_args());
    }

    public function getdel($key): mixed
    {
        return $this->initializeLazyObject()->getdel(...\func_get_args());
    }

    public function getex($key, $options = null): mixed
    {
        return $this->initializeLazyObject()->getex(...\func_get_args());
    }

    public function getrange($key, $start, $end): \Relay\Cluster|false|string
    {
        return $this->initializeLazyObject()->getrange(...\func_get_args());
    }

    public function getset($key, $value): mixed
    {
        return $this->initializeLazyObject()->getset(...\func_get_args());
    }

    public function hdel($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->hdel(...\func_get_args());
    }

    public function hexists($key, $member): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->hexists(...\func_get_args());
    }

    public function hexpire($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hexpire(...\func_get_args());
    }

    public function hexpireat($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hexpireat(...\func_get_args());
    }

    public function hexpiretime($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hexpiretime(...\func_get_args());
    }

    public function hget($key, $member): mixed
    {
        return $this->initializeLazyObject()->hget(...\func_get_args());
    }

    public function hgetWithMeta($hash, $member): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->getWithMeta(...\func_get_args());
    }

    public function hgetall($key): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hgetall(...\func_get_args());
    }

    public function hgetdel($key, $fields): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hgetdel(...\func_get_args());
    }

    public function hgetex($hash, $fields, $expiry = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hgetex(...\func_get_args());
    }

    public function hincrby($key, $member, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value): \Relay\Cluster|bool|float
    {
        return $this->initializeLazyObject()->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hkeys(...\func_get_args());
    }

    public function hlen($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->hlen(...\func_get_args());
    }

    public function hmget($key, $members): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hmget(...\func_get_args());
    }

    public function hmset($key, $members): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->hmset(...\func_get_args());
    }

    public function hpersist($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hpersist(...\func_get_args());
    }

    public function hpexpire($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hpexpire(...\func_get_args());
    }

    public function hpexpireat($hash, $ttl, $fields, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hpexpireat(...\func_get_args());
    }

    public function hpexpiretime($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hpexpiretime(...\func_get_args());
    }

    public function hpttl($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hpttl(...\func_get_args());
    }

    public function hrandfield($key, $options = null): \Relay\Cluster|array|false|string
    {
        return $this->initializeLazyObject()->hrandfield(...\func_get_args());
    }

    public function hscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initializeLazyObject()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function hset($key, ...$keys_and_vals): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->hset(...\func_get_args());
    }

    public function hsetex($key, $fields, $expiry = null): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->hsetex(...\func_get_args());
    }

    public function hsetnx($key, $member, $value): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $member): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->hstrlen(...\func_get_args());
    }

    public function httl($hash, $fields): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->httl(...\func_get_args());
    }

    public function hvals($key): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->hvals(...\func_get_args());
    }

    public function idleTime(): int
    {
        return $this->initializeLazyObject()->idleTime(...\func_get_args());
    }

    public function incr($key, $by = 1): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->incr(...\func_get_args());
    }

    public function incrby($key, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value): \Relay\Cluster|false|float
    {
        return $this->initializeLazyObject()->incrbyfloat(...\func_get_args());
    }

    public function info($key_or_address, ...$sections): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->info(...\func_get_args());
    }

    public function keys($pattern): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->keys(...\func_get_args());
    }

    public function lastsave($key_or_address): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->lastsave(...\func_get_args());
    }

    public function lcs($key1, $key2, $options = null): mixed
    {
        return $this->initializeLazyObject()->lcs(...\func_get_args());
    }

    public function lindex($key, $index): mixed
    {
        return $this->initializeLazyObject()->lindex(...\func_get_args());
    }

    public function linsert($key, $op, $pivot, $element): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->linsert(...\func_get_args());
    }

    public function listen($callback): bool
    {
        return $this->initializeLazyObject()->listen(...\func_get_args());
    }

    public function llen($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->llen(...\func_get_args());
    }

    public function lmove($srckey, $dstkey, $srcpos, $dstpos): \Relay\Cluster|false|string|null
    {
        return $this->initializeLazyObject()->lmove(...\func_get_args());
    }

    public function lmpop($keys, $from, $count = 1): mixed
    {
        return $this->initializeLazyObject()->lmpop(...\func_get_args());
    }

    public function lpop($key, $count = 1): mixed
    {
        return $this->initializeLazyObject()->lpop(...\func_get_args());
    }

    public function lpos($key, $value, $options = null): mixed
    {
        return $this->initializeLazyObject()->lpos(...\func_get_args());
    }

    public function lpush($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->lpush(...\func_get_args());
    }

    public function lpushx($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $stop): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->lrange(...\func_get_args());
    }

    public function lrem($key, $member, $count = 0): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->lrem(...\func_get_args());
    }

    public function lset($key, $index, $member): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $end): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->ltrim(...\func_get_args());
    }

    public function mget($keys): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->mget(...\func_get_args());
    }

    public function mset($kvals): \Relay\Cluster|array|bool
    {
        return $this->initializeLazyObject()->mset(...\func_get_args());
    }

    public function msetnx($kvals): \Relay\Cluster|array|bool
    {
        return $this->initializeLazyObject()->msetnx(...\func_get_args());
    }

    public function multi($mode = \Relay\Relay::MULTI): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->multi(...\func_get_args());
    }

    public function object($op, $key): mixed
    {
        return $this->initializeLazyObject()->object(...\func_get_args());
    }

    public function onFlushed($callback): bool
    {
        return $this->initializeLazyObject()->onFlushed(...\func_get_args());
    }

    public function onInvalidated($callback, $pattern = null): bool
    {
        return $this->initializeLazyObject()->onInvalidated(...\func_get_args());
    }

    public function persist($key): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->persist(...\func_get_args());
    }

    public function pexpire($key, $milliseconds): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp_ms): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->pexpireat(...\func_get_args());
    }

    public function pexpiretime($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->pexpiretime(...\func_get_args());
    }

    public function pfadd($key, $elements): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->pfadd(...\func_get_args());
    }

    public function pfcount($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->pfcount(...\func_get_args());
    }

    public function pfmerge($dstkey, $srckeys): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->pfmerge(...\func_get_args());
    }

    public function ping($key_or_address, $message = null): \Relay\Cluster|bool|string
    {
        return $this->initializeLazyObject()->ping(...\func_get_args());
    }

    public function psetex($key, $milliseconds, $value): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback): bool
    {
        return $this->initializeLazyObject()->psubscribe(...\func_get_args());
    }

    public function pttl($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->pttl(...\func_get_args());
    }

    public function publish($channel, $message): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->publish(...\func_get_args());
    }

    public function pubsub($key_or_address, $operation, ...$args): mixed
    {
        return $this->initializeLazyObject()->pubsub(...\func_get_args());
    }

    public function punsubscribe($patterns = []): bool
    {
        return $this->initializeLazyObject()->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address): \Relay\Cluster|bool|string
    {
        return $this->initializeLazyObject()->randomkey(...\func_get_args());
    }

    public function rawCommand($key_or_address, $cmd, ...$args): mixed
    {
        return $this->initializeLazyObject()->rawCommand(...\func_get_args());
    }

    public function rename($key, $newkey): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->renamenx(...\func_get_args());
    }

    public function restore($key, $ttl, $value, $options = null): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->restore(...\func_get_args());
    }

    public function role($key_or_address): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->role(...\func_get_args());
    }

    public function rpop($key, $count = 1): mixed
    {
        return $this->initializeLazyObject()->rpop(...\func_get_args());
    }

    public function rpoplpush($srckey, $dstkey): mixed
    {
        return $this->initializeLazyObject()->rpoplpush(...\func_get_args());
    }

    public function rpush($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->rpush(...\func_get_args());
    }

    public function rpushx($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->rpushx(...\func_get_args());
    }

    public function sadd($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->sadd(...\func_get_args());
    }

    public function save($key_or_address): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->save(...\func_get_args());
    }

    public function scan(&$iterator, $key_or_address, $match = null, $count = 0, $type = null): array|false
    {
        return $this->initializeLazyObject()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function scard($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->scard(...\func_get_args());
    }

    public function script($key_or_address, $operation, ...$args): mixed
    {
        return $this->initializeLazyObject()->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->sdiff(...\func_get_args());
    }

    public function sdiffstore($key, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->sdiffstore(...\func_get_args());
    }

    public function set($key, $value, $options = null): \Relay\Cluster|bool|string
    {
        return $this->initializeLazyObject()->set(...\func_get_args());
    }

    public function setOption($option, $value): bool
    {
        return $this->initializeLazyObject()->setOption(...\func_get_args());
    }

    public function setbit($key, $pos, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->setbit(...\func_get_args());
    }

    public function setex($key, $seconds, $value): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->setex(...\func_get_args());
    }

    public function setnx($key, $value): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->setnx(...\func_get_args());
    }

    public function setrange($key, $start, $value): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->sinter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->sintercard(...\func_get_args());
    }

    public function sinterstore($key, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->sinterstore(...\func_get_args());
    }

    public function sismember($key, $member): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->sismember(...\func_get_args());
    }

    public function slowlog($key_or_address, $operation, ...$args): \Relay\Cluster|array|bool|int
    {
        return $this->initializeLazyObject()->slowlog(...\func_get_args());
    }

    public function smembers($key): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->smembers(...\func_get_args());
    }

    public function smismember($key, ...$members): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->smismember(...\func_get_args());
    }

    public function smove($srckey, $dstkey, $member): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->smove(...\func_get_args());
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

    public function srem($key, $member, ...$members): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->srem(...\func_get_args());
    }

    public function sscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initializeLazyObject()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function ssubscribe($channels, $callback): bool
    {
        return $this->initializeLazyObject()->ssubscribe(...\func_get_args());
    }

    public function strlen($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->strlen(...\func_get_args());
    }

    public function subscribe($channels, $callback): bool
    {
        return $this->initializeLazyObject()->subscribe(...\func_get_args());
    }

    public function sunion($key, ...$other_keys): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->sunion(...\func_get_args());
    }

    public function sunionstore($key, ...$other_keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->sunionstore(...\func_get_args());
    }

    public function sunsubscribe($channels = []): bool
    {
        return $this->initializeLazyObject()->sunsubscribe(...\func_get_args());
    }

    public function time($key_or_address): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->time(...\func_get_args());
    }

    public function touch($key_or_array, ...$more_keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->touch(...\func_get_args());
    }

    public function ttl($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->ttl(...\func_get_args());
    }

    public function type($key): \Relay\Cluster|bool|int|string
    {
        return $this->initializeLazyObject()->type(...\func_get_args());
    }

    public function unlink(...$keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->unlink(...\func_get_args());
    }

    public function unsubscribe($channels = []): bool
    {
        return $this->initializeLazyObject()->unsubscribe(...\func_get_args());
    }

    public function unwatch(): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->unwatch(...\func_get_args());
    }

    public function vadd($key, $values, $element, $options = null): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->vadd(...\func_get_args());
    }

    public function vcard($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->vcard(...\func_get_args());
    }

    public function vdim($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->vdim(...\func_get_args());
    }

    public function vemb($key, $element, $raw = false): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->vemb(...\func_get_args());
    }

    public function vgetattr($key, $element, $raw = false): \Relay\Cluster|array|false|string
    {
        return $this->initializeLazyObject()->vgetattr(...\func_get_args());
    }

    public function vinfo($key): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->vinfo(...\func_get_args());
    }

    public function vismember($key, $element): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->vismember(...\func_get_args());
    }

    public function vlinks($key, $element, $withscores): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->vlinks(...\func_get_args());
    }

    public function vrandmember($key, $count = 0): \Relay\Cluster|array|false|string
    {
        return $this->initializeLazyObject()->vrandmember(...\func_get_args());
    }

    public function vrange($key, $end, $start, $count = -1): \Relay\Cluster|array|bool
    {
        return $this->initializeLazyObject()->vrange(...\func_get_args());
    }

    public function vrem($key, $element): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->vrem(...\func_get_args());
    }

    public function vsetattr($key, $element, $attributes): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->vsetattr(...\func_get_args());
    }

    public function vsim($key, $member, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->vsim(...\func_get_args());
    }

    public function watch($key, ...$other_keys): \Relay\Cluster|bool
    {
        return $this->initializeLazyObject()->watch(...\func_get_args());
    }

    public function waitaof($key_or_address, $numlocal, $numremote, $timeout): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->waitaof(...\func_get_args());
    }

    public function xack($key, $group, $ids): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->xack(...\func_get_args());
    }

    public function xackdel($key, $group, $ids, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->xackdel(...\func_get_args());
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Cluster|false|string
    {
        return $this->initializeLazyObject()->xadd(...\func_get_args());
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Relay\Cluster|array|bool
    {
        return $this->initializeLazyObject()->xautoclaim(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Relay\Cluster|array|bool
    {
        return $this->initializeLazyObject()->xclaim(...\func_get_args());
    }

    public function xdel($key, $ids): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->xdel(...\func_get_args());
    }

    public function xdelex($key, $ids, $mode = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->xdelex(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->initializeLazyObject()->xgroup(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->initializeLazyObject()->xinfo(...\func_get_args());
    }

    public function xlen($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->xlen(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null, $idle = 0): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->xrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): \Relay\Cluster|array|bool|null
    {
        return $this->initializeLazyObject()->xread(...\func_get_args());
    }

    public function xreadgroup($key, $consumer, $streams, $count = 1, $block = 1): \Relay\Cluster|array|bool|null
    {
        return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
    }

    public function xrevrange($key, $end, $start, $count = -1): \Relay\Cluster|array|bool
    {
        return $this->initializeLazyObject()->xrevrange(...\func_get_args());
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->xtrim(...\func_get_args());
    }

    public function zadd($key, ...$args): mixed
    {
        return $this->initializeLazyObject()->zadd(...\func_get_args());
    }

    public function zcard($key): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zcard(...\func_get_args());
    }

    public function zcount($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zcount(...\func_get_args());
    }

    public function zdiff($keys, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zdiff(...\func_get_args());
    }

    public function zdiffstore($dstkey, $keys): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zdiffstore(...\func_get_args());
    }

    public function zincrby($key, $score, $member): \Relay\Cluster|false|float
    {
        return $this->initializeLazyObject()->zincrby(...\func_get_args());
    }

    public function zinter($keys, $weights = null, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zinter(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zintercard(...\func_get_args());
    }

    public function zinterstore($dstkey, $keys, $weights = null, $options = null): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zinterstore(...\func_get_args());
    }

    public function zlexcount($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zlexcount(...\func_get_args());
    }

    public function zmpop($keys, $from, $count = 1): \Relay\Cluster|array|false|null
    {
        return $this->initializeLazyObject()->zmpop(...\func_get_args());
    }

    public function zmscore($key, ...$members): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zmscore(...\func_get_args());
    }

    public function zpopmax($key, $count = 1): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zpopmax(...\func_get_args());
    }

    public function zpopmin($key, $count = 1): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zpopmin(...\func_get_args());
    }

    public function zrandmember($key, $options = null): mixed
    {
        return $this->initializeLazyObject()->zrandmember(...\func_get_args());
    }

    public function zrange($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zrange(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zrangebyscore(...\func_get_args());
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zrangestore(...\func_get_args());
    }

    public function zrank($key, $rank, $withscore = false): \Relay\Cluster|array|false|int
    {
        return $this->initializeLazyObject()->zrank(...\func_get_args());
    }

    public function zrem($key, ...$args): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $start, $end): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $start, $end, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $rank, $withscore = false): \Relay\Cluster|array|false|int
    {
        return $this->initializeLazyObject()->zrevrank(...\func_get_args());
    }

    public function zscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->initializeLazyObject()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zscore($key, $member): \Relay\Cluster|false|float
    {
        return $this->initializeLazyObject()->zscore(...\func_get_args());
    }

    public function zunion($keys, $weights = null, $options = null): \Relay\Cluster|array|false
    {
        return $this->initializeLazyObject()->zunion(...\func_get_args());
    }

    public function zunionstore($dstkey, $keys, $weights = null, $options = null): \Relay\Cluster|false|int
    {
        return $this->initializeLazyObject()->zunionstore(...\func_get_args());
    }
}
