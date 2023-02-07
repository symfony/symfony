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
class Redis6Proxy extends \Redis implements ResetInterface, LazyObjectInterface
{
    use LazyProxyTrait {
        resetLazyObject as reset;
    }

    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

    public function __construct($options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct($options);
    }

    public function _compress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress($value);
    }

    public function _uncompress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress($value);
    }

    public function _prefix($key): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix($key);
    }

    public function _serialize($value): string
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

    public function acl($subcmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl($subcmd, ...$args);
    }

    public function append($key, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append($key, $value);
    }

    public function auth(#[\SensitiveParameter] $credentials): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->auth($credentials);
    }

    public function bgSave(): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgSave();
    }

    public function bgrewriteaof(): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof();
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount($key, $start, $end, $bybit);
    }

    public function bitop($operation, $deskey, $srckey, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop($operation, $deskey, $srckey, ...$other_keys);
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos($key, $bit, $start, $end, $bybit);
    }

    public function blPop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blPop($key_or_keys, $timeout_or_key, ...$extra_args);
    }

    public function brPop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brPop($key_or_keys, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($src, $dst, $timeout): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush($src, $dst, $timeout);
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzPopMax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzPopMin($key, $timeout_or_key, ...$extra_args);
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzmpop($timeout, $keys, $from, $count);
    }

    public function zmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmpop($keys, $from, $count);
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmpop($timeout, $keys, $from, $count);
    }

    public function lmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmpop($keys, $from, $count);
    }

    public function clearLastError(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearLastError();
    }

    public function client($opt, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client($opt, ...$args);
    }

    public function close(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close();
    }

    public function command($opt = null, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command($opt, ...$args);
    }

    public function config($operation, $key_or_settings = null, $value = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config($operation, $key_or_settings, $value);
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function copy($src, $dst, $options = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->copy($src, $dst, $options);
    }

    public function dbSize(): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbSize();
    }

    public function debug($key): \Redis|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->debug($key);
    }

    public function decr($key, $by = 1): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr($key, $by);
    }

    public function decrBy($key, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrBy($key, $value);
    }

    public function del($key, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del($key, ...$other_keys);
    }

    public function delete($key, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->delete($key, ...$other_keys);
    }

    public function discard(): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard();
    }

    public function dump($key): \Redis|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump($key);
    }

    public function echo($str): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo($str);
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval($script, $args, $num_keys);
    }

    public function eval_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval_ro($script_sha, $args, $num_keys);
    }

    public function evalsha($sha1, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha($sha1, $args, $num_keys);
    }

    public function evalsha_ro($sha1, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha_ro($sha1, $args, $num_keys);
    }

    public function exec(): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec();
    }

    public function exists($key, ...$other_keys): \Redis|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists($key, ...$other_keys);
    }

    public function expire($key, $timeout, $mode = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire($key, $timeout, $mode);
    }

    public function expireAt($key, $timestamp, $mode = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireAt($key, $timestamp, $mode);
    }

    public function failover($to = null, $abort = false, $timeout = 0): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->failover($to, $abort, $timeout);
    }

    public function expiretime($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expiretime($key);
    }

    public function pexpiretime($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpiretime($key);
    }

    public function fcall($fn, $keys = [], $args = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->fcall($fn, $keys, $args);
    }

    public function fcall_ro($fn, $keys = [], $args = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->fcall_ro($fn, $keys, $args);
    }

    public function flushAll($sync = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushAll($sync);
    }

    public function flushDB($sync = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushDB($sync);
    }

    public function function($operation, ...$args): \Redis|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->function($operation, ...$args);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd($key, $lng, $lat, $member, ...$other_triples_and_options);
    }

    public function geodist($key, $src, $dst, $unit = null): \Redis|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geodist($key, $src, $dst, $unit);
    }

    public function geohash($key, $member, ...$other_members): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash($key, $member, ...$other_members);
    }

    public function geopos($key, $member, ...$other_members): \Redis|array|false
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

    public function geosearch($key, $position, $shape, $unit, $options = []): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearch($key, $position, $shape, $unit, $options);
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Redis|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearchstore($dst, $src, $position, $shape, $unit, $options);
    }

    public function get($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get($key);
    }

    public function getAuth(): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getAuth();
    }

    public function getBit($key, $idx): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getBit($key, $idx);
    }

    public function getEx($key, $options = []): \Redis|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getEx($key, $options);
    }

    public function getDBNum(): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getDBNum();
    }

    public function getDel($key): \Redis|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getDel($key);
    }

    public function getHost(): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getHost();
    }

    public function getLastError(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getLastError();
    }

    public function getMode(): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMode();
    }

    public function getOption($option): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getOption($option);
    }

    public function getPersistentID(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPersistentID();
    }

    public function getPort(): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPort();
    }

    public function getRange($key, $start, $end): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getRange($key, $start, $end);
    }

    public function lcs($key1, $key2, $options = null): \Redis|array|false|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lcs($key1, $key2, $options);
    }

    public function getReadTimeout(): float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getReadTimeout();
    }

    public function getset($key, $value): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset($key, $value);
    }

    public function getTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getTimeout();
    }

    public function getTransferredBytes(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getTransferredBytes();
    }

    public function clearTransferredBytes(): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearTransferredBytes();
    }

    public function hDel($key, $field, ...$other_fields): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hDel($key, $field, ...$other_fields);
    }

    public function hExists($key, $field): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hExists($key, $field);
    }

    public function hGet($key, $member): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGet($key, $member);
    }

    public function hGetAll($key): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGetAll($key);
    }

    public function hIncrBy($key, $field, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hIncrBy($key, $field, $value);
    }

    public function hIncrByFloat($key, $field, $value): \Redis|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hIncrByFloat($key, $field, $value);
    }

    public function hKeys($key): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hKeys($key);
    }

    public function hLen($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hLen($key);
    }

    public function hMget($key, $fields): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hMget($key, $fields);
    }

    public function hMset($key, $fieldvals): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hMset($key, $fieldvals);
    }

    public function hRandField($key, $options = null): \Redis|array|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hRandField($key, $options);
    }

    public function hSet($key, $member, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSet($key, $member, $value);
    }

    public function hSetNx($key, $field, $value): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSetNx($key, $field, $value);
    }

    public function hStrLen($key, $field): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hStrLen($key, $field);
    }

    public function hVals($key): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hVals($key);
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($key, $iterator, $pattern, $count);
    }

    public function incr($key, $by = 1): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr($key, $by);
    }

    public function incrBy($key, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrBy($key, $value);
    }

    public function incrByFloat($key, $value): \Redis|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrByFloat($key, $value);
    }

    public function info(...$sections): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info(...$sections);
    }

    public function isConnected(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->isConnected();
    }

    public function keys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys($pattern);
    }

    public function lInsert($key, $pos, $pivot, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lInsert($key, $pos, $pivot, $value);
    }

    public function lLen($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lLen($key);
    }

    public function lMove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lMove($src, $dst, $wherefrom, $whereto);
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove($src, $dst, $wherefrom, $whereto, $timeout);
    }

    public function lPop($key, $count = 0): \Redis|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPop($key, $count);
    }

    public function lPos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPos($key, $value, $options);
    }

    public function lPush($key, ...$elements): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPush($key, ...$elements);
    }

    public function rPush($key, ...$elements): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPush($key, ...$elements);
    }

    public function lPushx($key, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPushx($key, $value);
    }

    public function rPushx($key, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPushx($key, $value);
    }

    public function lSet($key, $index, $value): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lSet($key, $index, $value);
    }

    public function lastSave(): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastSave();
    }

    public function lindex($key, $index): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex($key, $index);
    }

    public function lrange($key, $start, $end): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange($key, $start, $end);
    }

    public function lrem($key, $value, $count = 0): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem($key, $value, $count);
    }

    public function ltrim($key, $start, $end): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim($key, $start, $end);
    }

    public function mget($keys): \Redis|array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget($keys);
    }

    public function migrate($host, $port, $key, $dstdb, $timeout, $copy = false, $replace = false, #[\SensitiveParameter] $credentials = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->migrate($host, $port, $key, $dstdb, $timeout, $copy, $replace, $credentials);
    }

    public function move($key, $index): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->move($key, $index);
    }

    public function mset($key_values): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset($key_values);
    }

    public function msetnx($key_values): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx($key_values);
    }

    public function multi($value = \Redis::MULTI): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi($value);
    }

    public function object($subcommand, $key): \Redis|false|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object($subcommand, $key);
    }

    public function open($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->open($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function persist($key): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist($key);
    }

    public function pexpire($key, $timeout, $mode = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire($key, $timeout, $mode);
    }

    public function pexpireAt($key, $timestamp, $mode = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireAt($key, $timestamp, $mode);
    }

    public function pfadd($key, $elements): \Redis|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd($key, $elements);
    }

    public function pfcount($key_or_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount($key_or_keys);
    }

    public function pfmerge($dst, $srckeys): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge($dst, $srckeys);
    }

    public function ping($message = null): \Redis|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping($message);
    }

    public function pipeline(): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pipeline();
    }

    public function popen($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->popen($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function psetex($key, $expire, $value): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex($key, $expire, $value);
    }

    public function psubscribe($patterns, $cb): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psubscribe($patterns, $cb);
    }

    public function pttl($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl($key);
    }

    public function publish($channel, $message): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish($channel, $message);
    }

    public function pubsub($command, $arg = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub($command, $arg);
    }

    public function punsubscribe($patterns): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe($patterns);
    }

    public function rPop($key, $count = 0): \Redis|array|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPop($key, $count);
    }

    public function randomKey(): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomKey();
    }

    public function rawcommand($command, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand($command, ...$args);
    }

    public function rename($old_name, $new_name): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename($old_name, $new_name);
    }

    public function renameNx($key_src, $key_dst): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renameNx($key_src, $key_dst);
    }

    public function restore($key, $ttl, $value, $options = null): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore($key, $ttl, $value, $options);
    }

    public function role(): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role();
    }

    public function rpoplpush($srckey, $dstkey): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush($srckey, $dstkey);
    }

    public function sAdd($key, $value, ...$other_values): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sAdd($key, $value, ...$other_values);
    }

    public function sAddArray($key, $values): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sAddArray($key, $values);
    }

    public function sDiff($key, ...$other_keys): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sDiff($key, ...$other_keys);
    }

    public function sDiffStore($dst, $key, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sDiffStore($dst, $key, ...$other_keys);
    }

    public function sInter($key, ...$other_keys): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sInter($key, ...$other_keys);
    }

    public function sintercard($keys, $limit = -1): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sintercard($keys, $limit);
    }

    public function sInterStore($key, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sInterStore($key, ...$other_keys);
    }

    public function sMembers($key): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMembers($key);
    }

    public function sMisMember($key, $member, ...$other_members): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMisMember($key, $member, ...$other_members);
    }

    public function sMove($src, $dst, $value): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMove($src, $dst, $value);
    }

    public function sPop($key, $count = 0): \Redis|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sPop($key, $count);
    }

    public function sRandMember($key, $count = 0): \Redis|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRandMember($key, $count);
    }

    public function sUnion($key, ...$other_keys): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sUnion($key, ...$other_keys);
    }

    public function sUnionStore($dst, $key, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sUnionStore($dst, $key, ...$other_keys);
    }

    public function save(): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save();
    }

    public function scan(&$iterator, $pattern = null, $count = 0, $type = null): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($iterator, $pattern, $count, $type);
    }

    public function scard($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard($key);
    }

    public function script($command, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script($command, ...$args);
    }

    public function select($db): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->select($db);
    }

    public function set($key, $value, $options = null): \Redis|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set($key, $value, $options);
    }

    public function setBit($key, $idx, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setBit($key, $idx, $value);
    }

    public function setRange($key, $index, $value): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setRange($key, $index, $value);
    }

    public function setOption($option, $value): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setOption($option, $value);
    }

    public function setex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex($key, $expire, $value);
    }

    public function setnx($key, $value): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx($key, $value);
    }

    public function sismember($key, $value): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember($key, $value);
    }

    public function slaveof($host = null, $port = 6379): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slaveof($host, $port);
    }

    public function replicaof($host = null, $port = 6379): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->replicaof($host, $port);
    }

    public function touch($key_or_array, ...$more_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->touch($key_or_array, ...$more_keys);
    }

    public function slowlog($operation, $length = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog($operation, $length);
    }

    public function sort($key, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort($key, $options);
    }

    public function sort_ro($key, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro($key, $options);
    }

    public function sortAsc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortAsc($key, $pattern, $get, $offset, $count, $store);
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortAscAlpha($key, $pattern, $get, $offset, $count, $store);
    }

    public function sortDesc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortDesc($key, $pattern, $get, $offset, $count, $store);
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortDescAlpha($key, $pattern, $get, $offset, $count, $store);
    }

    public function srem($key, $value, ...$other_values): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem($key, $value, ...$other_values);
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($key, $iterator, $pattern, $count);
    }

    public function ssubscribe($channels, $cb): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ssubscribe($channels, $cb);
    }

    public function strlen($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen($key);
    }

    public function subscribe($channels, $cb): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->subscribe($channels, $cb);
    }

    public function sunsubscribe($channels): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunsubscribe($channels);
    }

    public function swapdb($src, $dst): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->swapdb($src, $dst);
    }

    public function time(): \Redis|array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time();
    }

    public function ttl($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl($key);
    }

    public function type($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type($key);
    }

    public function unlink($key, ...$other_keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink($key, ...$other_keys);
    }

    public function unsubscribe($channels): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe($channels);
    }

    public function unwatch(): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch();
    }

    public function watch($key, ...$other_keys): \Redis|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch($key, ...$other_keys);
    }

    public function wait($numreplicas, $timeout): false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->wait($numreplicas, $timeout);
    }

    public function xack($key, $group, $ids): false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack($key, $group, $ids);
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Redis|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xadd($key, $id, $values, $maxlen, $approx, $nomkstream);
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xautoclaim($key, $group, $consumer, $min_idle, $start, $count, $justid);
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim($key, $group, $consumer, $min_idle, $ids, $options);
    }

    public function xdel($key, $ids): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel($key, $ids);
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup($operation, $key, $group, $id_or_consumer, $mkstream, $entries_read);
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo($operation, $arg1, $arg2, $count);
    }

    public function xlen($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen($key);
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending($key, $group, $start, $end, $count, $consumer);
    }

    public function xrange($key, $start, $end, $count = -1): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange($key, $start, $end, $count);
    }

    public function xread($streams, $count = -1, $block = -1): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread($streams, $count, $block);
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup($group, $consumer, $streams, $count, $block);
    }

    public function xrevrange($key, $end, $start, $count = -1): \Redis|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange($key, $end, $start, $count);
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim($key, $threshold, $approx, $minid, $limit);
    }

    public function zAdd($key, $score_or_options, ...$more_scores_and_mems): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zAdd($key, $score_or_options, ...$more_scores_and_mems);
    }

    public function zCard($key): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zCard($key);
    }

    public function zCount($key, $start, $end): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zCount($key, $start, $end);
    }

    public function zIncrBy($key, $value, $member): \Redis|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zIncrBy($key, $value, $member);
    }

    public function zLexCount($key, $min, $max): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zLexCount($key, $min, $max);
    }

    public function zMscore($key, $member, ...$other_members): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zMscore($key, $member, ...$other_members);
    }

    public function zPopMax($key, $count = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zPopMax($key, $count);
    }

    public function zPopMin($key, $count = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zPopMin($key, $count);
    }

    public function zRange($key, $start, $end, $options = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRange($key, $start, $end, $options);
    }

    public function zRangeByLex($key, $min, $max, $offset = -1, $count = -1): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRangeByLex($key, $min, $max, $offset, $count);
    }

    public function zRangeByScore($key, $start, $end, $options = []): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRangeByScore($key, $start, $end, $options);
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangestore($dstkey, $srckey, $start, $end, $options);
    }

    public function zRandMember($key, $options = null): \Redis|array|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRandMember($key, $options);
    }

    public function zRank($key, $member): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRank($key, $member);
    }

    public function zRem($key, $member, ...$other_members): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRem($key, $member, ...$other_members);
    }

    public function zRemRangeByLex($key, $min, $max): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByLex($key, $min, $max);
    }

    public function zRemRangeByRank($key, $start, $end): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByRank($key, $start, $end);
    }

    public function zRemRangeByScore($key, $start, $end): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByScore($key, $start, $end);
    }

    public function zRevRange($key, $start, $end, $scores = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRange($key, $start, $end, $scores);
    }

    public function zRevRangeByLex($key, $max, $min, $offset = -1, $count = -1): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRangeByLex($key, $max, $min, $offset, $count);
    }

    public function zRevRangeByScore($key, $max, $min, $options = []): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRangeByScore($key, $max, $min, $options);
    }

    public function zRevRank($key, $member): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRank($key, $member);
    }

    public function zScore($key, $member): \Redis|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zScore($key, $member);
    }

    public function zdiff($keys, $options = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiff($keys, $options);
    }

    public function zdiffstore($dst, $keys): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiffstore($dst, $keys);
    }

    public function zinter($keys, $weights = null, $options = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinter($keys, $weights, $options);
    }

    public function zintercard($keys, $limit = -1): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zintercard($keys, $limit);
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore($dst, $keys, $weights, $aggregate);
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($key, $iterator, $pattern, $count);
    }

    public function zunion($keys, $weights = null, $options = null): \Redis|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunion($keys, $weights, $options);
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore($dst, $keys, $weights, $aggregate);
    }
}
