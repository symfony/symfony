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
class Redis6Proxy extends \Redis implements ResetInterface, LazyObjectInterface
{
    use Redis6ProxyTrait;
    use RedisProxyTrait {
        resetLazyObject as reset;
    }

    public function __construct($options = null)
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

    public function _prefix($key): string
    {
        return $this->initializeLazyObject()->_prefix(...\func_get_args());
    }

    public function _serialize($value): string
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

    public function acl($subcmd, ...$args): mixed
    {
        return $this->initializeLazyObject()->acl(...\func_get_args());
    }

    public function append($key, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->append(...\func_get_args());
    }

    public function auth(#[\SensitiveParameter] $credentials): \Redis|bool
    {
        return $this->initializeLazyObject()->auth(...\func_get_args());
    }

    public function bgSave(): \Redis|bool
    {
        return $this->initializeLazyObject()->bgSave(...\func_get_args());
    }

    public function bgrewriteaof(): \Redis|bool
    {
        return $this->initializeLazyObject()->bgrewriteaof(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return $this->initializeLazyObject()->bitcount(...\func_get_args());
    }

    public function bitop($operation, $deskey, $srckey, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return $this->initializeLazyObject()->bitpos(...\func_get_args());
    }

    public function blPop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return $this->initializeLazyObject()->blPop(...\func_get_args());
    }

    public function brPop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return $this->initializeLazyObject()->brPop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout): \Redis|false|string
    {
        return $this->initializeLazyObject()->brpoplpush(...\func_get_args());
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return $this->initializeLazyObject()->bzPopMax(...\func_get_args());
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return $this->initializeLazyObject()->bzPopMin(...\func_get_args());
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initializeLazyObject()->bzmpop(...\func_get_args());
    }

    public function zmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initializeLazyObject()->zmpop(...\func_get_args());
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initializeLazyObject()->blmpop(...\func_get_args());
    }

    public function lmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->initializeLazyObject()->lmpop(...\func_get_args());
    }

    public function clearLastError(): bool
    {
        return $this->initializeLazyObject()->clearLastError(...\func_get_args());
    }

    public function client($opt, ...$args): mixed
    {
        return $this->initializeLazyObject()->client(...\func_get_args());
    }

    public function close(): bool
    {
        return $this->initializeLazyObject()->close(...\func_get_args());
    }

    public function command($opt = null, ...$args): mixed
    {
        return $this->initializeLazyObject()->command(...\func_get_args());
    }

    public function config($operation, $key_or_settings = null, $value = null): mixed
    {
        return $this->initializeLazyObject()->config(...\func_get_args());
    }

    public function connect($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initializeLazyObject()->connect(...\func_get_args());
    }

    public function copy($src, $dst, $options = null): \Redis|bool
    {
        return $this->initializeLazyObject()->copy(...\func_get_args());
    }

    public function dbSize(): \Redis|false|int
    {
        return $this->initializeLazyObject()->dbSize(...\func_get_args());
    }

    public function debug($key): \Redis|string
    {
        return $this->initializeLazyObject()->debug(...\func_get_args());
    }

    public function decr($key, $by = 1): \Redis|false|int
    {
        return $this->initializeLazyObject()->decr(...\func_get_args());
    }

    public function decrBy($key, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->decrBy(...\func_get_args());
    }

    public function del($key, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->del(...\func_get_args());
    }

    public function delete($key, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->delete(...\func_get_args());
    }

    public function discard(): \Redis|bool
    {
        return $this->initializeLazyObject()->discard(...\func_get_args());
    }

    public function echo($str): \Redis|false|string
    {
        return $this->initializeLazyObject()->echo(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval(...\func_get_args());
    }

    public function eval_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->eval_ro(...\func_get_args());
    }

    public function evalsha($sha1, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha(...\func_get_args());
    }

    public function evalsha_ro($sha1, $args = [], $num_keys = 0): mixed
    {
        return $this->initializeLazyObject()->evalsha_ro(...\func_get_args());
    }

    public function exec(): \Redis|array|false
    {
        return $this->initializeLazyObject()->exec(...\func_get_args());
    }

    public function exists($key, ...$other_keys): \Redis|bool|int
    {
        return $this->initializeLazyObject()->exists(...\func_get_args());
    }

    public function expire($key, $timeout, $mode = null): \Redis|bool
    {
        return $this->initializeLazyObject()->expire(...\func_get_args());
    }

    public function expireAt($key, $timestamp, $mode = null): \Redis|bool
    {
        return $this->initializeLazyObject()->expireAt(...\func_get_args());
    }

    public function failover($to = null, $abort = false, $timeout = 0): \Redis|bool
    {
        return $this->initializeLazyObject()->failover(...\func_get_args());
    }

    public function expiretime($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->expiretime(...\func_get_args());
    }

    public function pexpiretime($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->pexpiretime(...\func_get_args());
    }

    public function fcall($fn, $keys = [], $args = []): mixed
    {
        return $this->initializeLazyObject()->fcall(...\func_get_args());
    }

    public function fcall_ro($fn, $keys = [], $args = []): mixed
    {
        return $this->initializeLazyObject()->fcall_ro(...\func_get_args());
    }

    public function flushAll($sync = null): \Redis|bool
    {
        return $this->initializeLazyObject()->flushAll(...\func_get_args());
    }

    public function flushDB($sync = null): \Redis|bool
    {
        return $this->initializeLazyObject()->flushDB(...\func_get_args());
    }

    public function function($operation, ...$args): \Redis|array|bool|string
    {
        return $this->initializeLazyObject()->function(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Redis|false|int
    {
        return $this->initializeLazyObject()->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dst, $unit = null): \Redis|false|float
    {
        return $this->initializeLazyObject()->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initializeLazyObject()->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members): \Redis|array|false
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

    public function geosearch($key, $position, $shape, $unit, $options = []): array
    {
        return $this->initializeLazyObject()->geosearch(...\func_get_args());
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Redis|array|false|int
    {
        return $this->initializeLazyObject()->geosearchstore(...\func_get_args());
    }

    public function get($key): mixed
    {
        return $this->initializeLazyObject()->get(...\func_get_args());
    }

    public function getAuth(): mixed
    {
        return $this->initializeLazyObject()->getAuth(...\func_get_args());
    }

    public function getBit($key, $idx): \Redis|false|int
    {
        return $this->initializeLazyObject()->getBit(...\func_get_args());
    }

    public function getEx($key, $options = []): \Redis|bool|string
    {
        return $this->initializeLazyObject()->getEx(...\func_get_args());
    }

    public function getDBNum(): int
    {
        return $this->initializeLazyObject()->getDBNum(...\func_get_args());
    }

    public function getDel($key): \Redis|bool|string
    {
        return $this->initializeLazyObject()->getDel(...\func_get_args());
    }

    public function getHost(): string
    {
        return $this->initializeLazyObject()->getHost(...\func_get_args());
    }

    public function getLastError(): ?string
    {
        return $this->initializeLazyObject()->getLastError(...\func_get_args());
    }

    public function getMode(): int
    {
        return $this->initializeLazyObject()->getMode(...\func_get_args());
    }

    public function getOption($option): mixed
    {
        return $this->initializeLazyObject()->getOption(...\func_get_args());
    }

    public function getPersistentID(): ?string
    {
        return $this->initializeLazyObject()->getPersistentID(...\func_get_args());
    }

    public function getPort(): int
    {
        return $this->initializeLazyObject()->getPort(...\func_get_args());
    }

    public function getRange($key, $start, $end): \Redis|false|string
    {
        return $this->initializeLazyObject()->getRange(...\func_get_args());
    }

    public function lcs($key1, $key2, $options = null): \Redis|array|false|int|string
    {
        return $this->initializeLazyObject()->lcs(...\func_get_args());
    }

    public function getReadTimeout(): float
    {
        return $this->initializeLazyObject()->getReadTimeout(...\func_get_args());
    }

    public function getset($key, $value): \Redis|false|string
    {
        return $this->initializeLazyObject()->getset(...\func_get_args());
    }

    public function getTimeout(): false|float
    {
        return $this->initializeLazyObject()->getTimeout(...\func_get_args());
    }

    public function getTransferredBytes(): array
    {
        return $this->initializeLazyObject()->getTransferredBytes(...\func_get_args());
    }

    public function clearTransferredBytes(): void
    {
        $this->initializeLazyObject()->clearTransferredBytes(...\func_get_args());
    }

    public function hDel($key, $field, ...$other_fields): \Redis|false|int
    {
        return $this->initializeLazyObject()->hDel(...\func_get_args());
    }

    public function hExists($key, $field): \Redis|bool
    {
        return $this->initializeLazyObject()->hExists(...\func_get_args());
    }

    public function hGet($key, $member): mixed
    {
        return $this->initializeLazyObject()->hGet(...\func_get_args());
    }

    public function hGetAll($key): \Redis|array|false
    {
        return $this->initializeLazyObject()->hGetAll(...\func_get_args());
    }

    public function hIncrBy($key, $field, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->hIncrBy(...\func_get_args());
    }

    public function hIncrByFloat($key, $field, $value): \Redis|false|float
    {
        return $this->initializeLazyObject()->hIncrByFloat(...\func_get_args());
    }

    public function hKeys($key): \Redis|array|false
    {
        return $this->initializeLazyObject()->hKeys(...\func_get_args());
    }

    public function hLen($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->hLen(...\func_get_args());
    }

    public function hMget($key, $fields): \Redis|array|false
    {
        return $this->initializeLazyObject()->hMget(...\func_get_args());
    }

    public function hMset($key, $fieldvals): \Redis|bool
    {
        return $this->initializeLazyObject()->hMset(...\func_get_args());
    }

    public function hSetNx($key, $field, $value): \Redis|bool
    {
        return $this->initializeLazyObject()->hSetNx(...\func_get_args());
    }

    public function hStrLen($key, $field): \Redis|false|int
    {
        return $this->initializeLazyObject()->hStrLen(...\func_get_args());
    }

    public function hVals($key): \Redis|array|false
    {
        return $this->initializeLazyObject()->hVals(...\func_get_args());
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|bool
    {
        return $this->initializeLazyObject()->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function incr($key, $by = 1): \Redis|false|int
    {
        return $this->initializeLazyObject()->incr(...\func_get_args());
    }

    public function incrBy($key, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->incrBy(...\func_get_args());
    }

    public function incrByFloat($key, $value): \Redis|false|float
    {
        return $this->initializeLazyObject()->incrByFloat(...\func_get_args());
    }

    public function info(...$sections): \Redis|array|false
    {
        return $this->initializeLazyObject()->info(...\func_get_args());
    }

    public function isConnected(): bool
    {
        return $this->initializeLazyObject()->isConnected(...\func_get_args());
    }

    public function keys($pattern)
    {
        return $this->initializeLazyObject()->keys(...\func_get_args());
    }

    public function lInsert($key, $pos, $pivot, $value)
    {
        return $this->initializeLazyObject()->lInsert(...\func_get_args());
    }

    public function lLen($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->lLen(...\func_get_args());
    }

    public function lMove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return $this->initializeLazyObject()->lMove(...\func_get_args());
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return $this->initializeLazyObject()->blmove(...\func_get_args());
    }

    public function lPop($key, $count = 0): \Redis|array|bool|string
    {
        return $this->initializeLazyObject()->lPop(...\func_get_args());
    }

    public function lPos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return $this->initializeLazyObject()->lPos(...\func_get_args());
    }

    public function lPush($key, ...$elements): \Redis|false|int
    {
        return $this->initializeLazyObject()->lPush(...\func_get_args());
    }

    public function rPush($key, ...$elements): \Redis|false|int
    {
        return $this->initializeLazyObject()->rPush(...\func_get_args());
    }

    public function lPushx($key, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->lPushx(...\func_get_args());
    }

    public function rPushx($key, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->rPushx(...\func_get_args());
    }

    public function lSet($key, $index, $value): \Redis|bool
    {
        return $this->initializeLazyObject()->lSet(...\func_get_args());
    }

    public function lastSave(): int
    {
        return $this->initializeLazyObject()->lastSave(...\func_get_args());
    }

    public function lindex($key, $index): mixed
    {
        return $this->initializeLazyObject()->lindex(...\func_get_args());
    }

    public function lrange($key, $start, $end): \Redis|array|false
    {
        return $this->initializeLazyObject()->lrange(...\func_get_args());
    }

    public function lrem($key, $value, $count = 0): \Redis|false|int
    {
        return $this->initializeLazyObject()->lrem(...\func_get_args());
    }

    public function ltrim($key, $start, $end): \Redis|bool
    {
        return $this->initializeLazyObject()->ltrim(...\func_get_args());
    }

    public function migrate($host, $port, $key, $dstdb, $timeout, $copy = false, $replace = false, #[\SensitiveParameter] $credentials = null): \Redis|bool
    {
        return $this->initializeLazyObject()->migrate(...\func_get_args());
    }

    public function move($key, $index): \Redis|bool
    {
        return $this->initializeLazyObject()->move(...\func_get_args());
    }

    public function mset($key_values): \Redis|bool
    {
        return $this->initializeLazyObject()->mset(...\func_get_args());
    }

    public function msetnx($key_values): \Redis|bool
    {
        return $this->initializeLazyObject()->msetnx(...\func_get_args());
    }

    public function multi($value = \Redis::MULTI): \Redis|bool
    {
        return $this->initializeLazyObject()->multi(...\func_get_args());
    }

    public function object($subcommand, $key): \Redis|false|int|string
    {
        return $this->initializeLazyObject()->object(...\func_get_args());
    }

    public function open($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initializeLazyObject()->open(...\func_get_args());
    }

    public function pconnect($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initializeLazyObject()->pconnect(...\func_get_args());
    }

    public function persist($key): \Redis|bool
    {
        return $this->initializeLazyObject()->persist(...\func_get_args());
    }

    public function pexpire($key, $timeout, $mode = null): bool
    {
        return $this->initializeLazyObject()->pexpire(...\func_get_args());
    }

    public function pexpireAt($key, $timestamp, $mode = null): \Redis|bool
    {
        return $this->initializeLazyObject()->pexpireAt(...\func_get_args());
    }

    public function pfadd($key, $elements): \Redis|int
    {
        return $this->initializeLazyObject()->pfadd(...\func_get_args());
    }

    public function pfcount($key_or_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->pfcount(...\func_get_args());
    }

    public function pfmerge($dst, $srckeys): \Redis|bool
    {
        return $this->initializeLazyObject()->pfmerge(...\func_get_args());
    }

    public function ping($message = null): \Redis|bool|string
    {
        return $this->initializeLazyObject()->ping(...\func_get_args());
    }

    public function pipeline(): \Redis|bool
    {
        return $this->initializeLazyObject()->pipeline(...\func_get_args());
    }

    public function popen($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
    {
        return $this->initializeLazyObject()->popen(...\func_get_args());
    }

    public function psetex($key, $expire, $value): \Redis|bool
    {
        return $this->initializeLazyObject()->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $cb): bool
    {
        return $this->initializeLazyObject()->psubscribe(...\func_get_args());
    }

    public function pttl($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->pttl(...\func_get_args());
    }

    public function publish($channel, $message): \Redis|false|int
    {
        return $this->initializeLazyObject()->publish(...\func_get_args());
    }

    public function pubsub($command, $arg = null): mixed
    {
        return $this->initializeLazyObject()->pubsub(...\func_get_args());
    }

    public function punsubscribe($patterns): \Redis|array|bool
    {
        return $this->initializeLazyObject()->punsubscribe(...\func_get_args());
    }

    public function rPop($key, $count = 0): \Redis|array|bool|string
    {
        return $this->initializeLazyObject()->rPop(...\func_get_args());
    }

    public function randomKey(): \Redis|false|string
    {
        return $this->initializeLazyObject()->randomKey(...\func_get_args());
    }

    public function rawcommand($command, ...$args): mixed
    {
        return $this->initializeLazyObject()->rawcommand(...\func_get_args());
    }

    public function rename($old_name, $new_name): \Redis|bool
    {
        return $this->initializeLazyObject()->rename(...\func_get_args());
    }

    public function renameNx($key_src, $key_dst): \Redis|bool
    {
        return $this->initializeLazyObject()->renameNx(...\func_get_args());
    }

    public function restore($key, $ttl, $value, $options = null): \Redis|bool
    {
        return $this->initializeLazyObject()->restore(...\func_get_args());
    }

    public function role(): mixed
    {
        return $this->initializeLazyObject()->role(...\func_get_args());
    }

    public function rpoplpush($srckey, $dstkey): \Redis|false|string
    {
        return $this->initializeLazyObject()->rpoplpush(...\func_get_args());
    }

    public function sAdd($key, $value, ...$other_values): \Redis|false|int
    {
        return $this->initializeLazyObject()->sAdd(...\func_get_args());
    }

    public function sAddArray($key, $values): int
    {
        return $this->initializeLazyObject()->sAddArray(...\func_get_args());
    }

    public function sDiff($key, ...$other_keys): \Redis|array|false
    {
        return $this->initializeLazyObject()->sDiff(...\func_get_args());
    }

    public function sDiffStore($dst, $key, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->sDiffStore(...\func_get_args());
    }

    public function sInter($key, ...$other_keys): \Redis|array|false
    {
        return $this->initializeLazyObject()->sInter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->initializeLazyObject()->sintercard(...\func_get_args());
    }

    public function sInterStore($key, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->sInterStore(...\func_get_args());
    }

    public function sMembers($key): \Redis|array|false
    {
        return $this->initializeLazyObject()->sMembers(...\func_get_args());
    }

    public function sMisMember($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initializeLazyObject()->sMisMember(...\func_get_args());
    }

    public function sMove($src, $dst, $value): \Redis|bool
    {
        return $this->initializeLazyObject()->sMove(...\func_get_args());
    }

    public function sPop($key, $count = 0): \Redis|array|false|string
    {
        return $this->initializeLazyObject()->sPop(...\func_get_args());
    }

    public function sUnion($key, ...$other_keys): \Redis|array|false
    {
        return $this->initializeLazyObject()->sUnion(...\func_get_args());
    }

    public function sUnionStore($dst, $key, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->sUnionStore(...\func_get_args());
    }

    public function save(): \Redis|bool
    {
        return $this->initializeLazyObject()->save(...\func_get_args());
    }

    public function scan(&$iterator, $pattern = null, $count = 0, $type = null): array|false
    {
        return $this->initializeLazyObject()->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function scard($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->scard(...\func_get_args());
    }

    public function script($command, ...$args): mixed
    {
        return $this->initializeLazyObject()->script(...\func_get_args());
    }

    public function select($db): \Redis|bool
    {
        return $this->initializeLazyObject()->select(...\func_get_args());
    }

    public function set($key, $value, $options = null): \Redis|bool|string
    {
        return $this->initializeLazyObject()->set(...\func_get_args());
    }

    public function setBit($key, $idx, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->setBit(...\func_get_args());
    }

    public function setRange($key, $index, $value): \Redis|false|int
    {
        return $this->initializeLazyObject()->setRange(...\func_get_args());
    }

    public function setOption($option, $value): bool
    {
        return $this->initializeLazyObject()->setOption(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return $this->initializeLazyObject()->setex(...\func_get_args());
    }

    public function setnx($key, $value): \Redis|bool
    {
        return $this->initializeLazyObject()->setnx(...\func_get_args());
    }

    public function sismember($key, $value): \Redis|bool
    {
        return $this->initializeLazyObject()->sismember(...\func_get_args());
    }

    public function slaveof($host = null, $port = 6379): \Redis|bool
    {
        return $this->initializeLazyObject()->slaveof(...\func_get_args());
    }

    public function replicaof($host = null, $port = 6379): \Redis|bool
    {
        return $this->initializeLazyObject()->replicaof(...\func_get_args());
    }

    public function touch($key_or_array, ...$more_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->touch(...\func_get_args());
    }

    public function slowlog($operation, $length = 0): mixed
    {
        return $this->initializeLazyObject()->slowlog(...\func_get_args());
    }

    public function sort($key, $options = null): mixed
    {
        return $this->initializeLazyObject()->sort(...\func_get_args());
    }

    public function sort_ro($key, $options = null): mixed
    {
        return $this->initializeLazyObject()->sort_ro(...\func_get_args());
    }

    public function sortAsc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initializeLazyObject()->sortAsc(...\func_get_args());
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initializeLazyObject()->sortAscAlpha(...\func_get_args());
    }

    public function sortDesc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initializeLazyObject()->sortDesc(...\func_get_args());
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->initializeLazyObject()->sortDescAlpha(...\func_get_args());
    }

    public function srem($key, $value, ...$other_values): \Redis|false|int
    {
        return $this->initializeLazyObject()->srem(...\func_get_args());
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return $this->initializeLazyObject()->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function ssubscribe($channels, $cb): bool
    {
        return $this->initializeLazyObject()->ssubscribe(...\func_get_args());
    }

    public function strlen($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->strlen(...\func_get_args());
    }

    public function subscribe($channels, $cb): bool
    {
        return $this->initializeLazyObject()->subscribe(...\func_get_args());
    }

    public function sunsubscribe($channels): \Redis|array|bool
    {
        return $this->initializeLazyObject()->sunsubscribe(...\func_get_args());
    }

    public function swapdb($src, $dst): \Redis|bool
    {
        return $this->initializeLazyObject()->swapdb(...\func_get_args());
    }

    public function time(): \Redis|array
    {
        return $this->initializeLazyObject()->time(...\func_get_args());
    }

    public function ttl($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->ttl(...\func_get_args());
    }

    public function type($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->type(...\func_get_args());
    }

    public function unlink($key, ...$other_keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->unlink(...\func_get_args());
    }

    public function unsubscribe($channels): \Redis|array|bool
    {
        return $this->initializeLazyObject()->unsubscribe(...\func_get_args());
    }

    public function unwatch(): \Redis|bool
    {
        return $this->initializeLazyObject()->unwatch(...\func_get_args());
    }

    public function watch($key, ...$other_keys): \Redis|bool
    {
        return $this->initializeLazyObject()->watch(...\func_get_args());
    }

    public function wait($numreplicas, $timeout): false|int
    {
        return $this->initializeLazyObject()->wait(...\func_get_args());
    }

    public function xack($key, $group, $ids): false|int
    {
        return $this->initializeLazyObject()->xack(...\func_get_args());
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Redis|false|string
    {
        return $this->initializeLazyObject()->xadd(...\func_get_args());
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Redis|array|bool
    {
        return $this->initializeLazyObject()->xautoclaim(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Redis|array|bool
    {
        return $this->initializeLazyObject()->xclaim(...\func_get_args());
    }

    public function xdel($key, $ids): \Redis|false|int
    {
        return $this->initializeLazyObject()->xdel(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->initializeLazyObject()->xgroup(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->initializeLazyObject()->xinfo(...\func_get_args());
    }

    public function xlen($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->xlen(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): \Redis|array|bool
    {
        return $this->initializeLazyObject()->xrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): \Redis|array|bool
    {
        return $this->initializeLazyObject()->xread(...\func_get_args());
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Redis|array|bool
    {
        return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
    }

    public function xrevrange($key, $end, $start, $count = -1): \Redis|array|bool
    {
        return $this->initializeLazyObject()->xrevrange(...\func_get_args());
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Redis|false|int
    {
        return $this->initializeLazyObject()->xtrim(...\func_get_args());
    }

    public function zAdd($key, $score_or_options, ...$more_scores_and_mems): \Redis|false|float|int
    {
        return $this->initializeLazyObject()->zAdd(...\func_get_args());
    }

    public function zCard($key): \Redis|false|int
    {
        return $this->initializeLazyObject()->zCard(...\func_get_args());
    }

    public function zCount($key, $start, $end): \Redis|false|int
    {
        return $this->initializeLazyObject()->zCount(...\func_get_args());
    }

    public function zIncrBy($key, $value, $member): \Redis|false|float
    {
        return $this->initializeLazyObject()->zIncrBy(...\func_get_args());
    }

    public function zLexCount($key, $min, $max): \Redis|false|int
    {
        return $this->initializeLazyObject()->zLexCount(...\func_get_args());
    }

    public function zMscore($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->initializeLazyObject()->zMscore(...\func_get_args());
    }

    public function zPopMax($key, $count = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zPopMax(...\func_get_args());
    }

    public function zPopMin($key, $count = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zPopMin(...\func_get_args());
    }

    public function zRange($key, $start, $end, $options = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zRange(...\func_get_args());
    }

    public function zRangeByLex($key, $min, $max, $offset = -1, $count = -1): \Redis|array|false
    {
        return $this->initializeLazyObject()->zRangeByLex(...\func_get_args());
    }

    public function zRangeByScore($key, $start, $end, $options = []): \Redis|array|false
    {
        return $this->initializeLazyObject()->zRangeByScore(...\func_get_args());
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Redis|false|int
    {
        return $this->initializeLazyObject()->zrangestore(...\func_get_args());
    }

    public function zRandMember($key, $options = null): \Redis|array|string
    {
        return $this->initializeLazyObject()->zRandMember(...\func_get_args());
    }

    public function zRank($key, $member): \Redis|false|int
    {
        return $this->initializeLazyObject()->zRank(...\func_get_args());
    }

    public function zRem($key, $member, ...$other_members): \Redis|false|int
    {
        return $this->initializeLazyObject()->zRem(...\func_get_args());
    }

    public function zRemRangeByLex($key, $min, $max): \Redis|false|int
    {
        return $this->initializeLazyObject()->zRemRangeByLex(...\func_get_args());
    }

    public function zRemRangeByRank($key, $start, $end): \Redis|false|int
    {
        return $this->initializeLazyObject()->zRemRangeByRank(...\func_get_args());
    }

    public function zRemRangeByScore($key, $start, $end): \Redis|false|int
    {
        return $this->initializeLazyObject()->zRemRangeByScore(...\func_get_args());
    }

    public function zRevRange($key, $start, $end, $scores = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zRevRange(...\func_get_args());
    }

    public function zRevRangeByLex($key, $max, $min, $offset = -1, $count = -1): \Redis|array|false
    {
        return $this->initializeLazyObject()->zRevRangeByLex(...\func_get_args());
    }

    public function zRevRangeByScore($key, $max, $min, $options = []): \Redis|array|false
    {
        return $this->initializeLazyObject()->zRevRangeByScore(...\func_get_args());
    }

    public function zRevRank($key, $member): \Redis|false|int
    {
        return $this->initializeLazyObject()->zRevRank(...\func_get_args());
    }

    public function zScore($key, $member): \Redis|false|float
    {
        return $this->initializeLazyObject()->zScore(...\func_get_args());
    }

    public function zdiff($keys, $options = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zdiff(...\func_get_args());
    }

    public function zdiffstore($dst, $keys): \Redis|false|int
    {
        return $this->initializeLazyObject()->zdiffstore(...\func_get_args());
    }

    public function zinter($keys, $weights = null, $options = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zinter(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->initializeLazyObject()->zintercard(...\func_get_args());
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return $this->initializeLazyObject()->zinterstore(...\func_get_args());
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|false
    {
        return $this->initializeLazyObject()->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zunion($keys, $weights = null, $options = null): \Redis|array|false
    {
        return $this->initializeLazyObject()->zunion(...\func_get_args());
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return $this->initializeLazyObject()->zunionstore(...\func_get_args());
    }
}
