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

use Relay\Relay;
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
class RelayProxy extends Relay implements ResetInterface, LazyObjectInterface
{
    use LazyProxyTrait {
        resetLazyObject as reset;
    }

    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

    public function __construct($host = null, $port = 6379, $connect_timeout = 0.0, $command_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct($host, $port, $connect_timeout, $command_timeout, $context, $database);
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context, $database);
    }

    public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context, $database);
    }

    public function close(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close();
    }

    public function pclose(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pclose();
    }

    public function listen($callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->listen($callback);
    }

    public function onFlushed($callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->onFlushed($callback);
    }

    public function onInvalidated($callback, $pattern = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->onInvalidated($callback, $pattern);
    }

    public function dispatchEvents(): false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dispatchEvents();
    }

    public function getOption($option): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getOption($option);
    }

    public function option($option, $value = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->option($option, $value);
    }

    public function setOption($option, $value): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setOption($option, $value);
    }

    public function addIgnorePatterns(...$pattern): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->addIgnorePatterns(...$pattern);
    }

    public function addAllowPatterns(...$pattern): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->addAllowPatterns(...$pattern);
    }

    public function getTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getTimeout();
    }

    public function timeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->timeout();
    }

    public function getReadTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getReadTimeout();
    }

    public function readTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->readTimeout();
    }

    public function getBytes(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getBytes();
    }

    public function bytes(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bytes();
    }

    public function getHost(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getHost();
    }

    public function isConnected(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->isConnected();
    }

    public function getPort(): false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPort();
    }

    public function getAuth(): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getAuth();
    }

    public function getDbNum(): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getDbNum();
    }

    public function _serialize($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize($value);
    }

    public function _unserialize($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize($value);
    }

    public function _compress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress($value);
    }

    public function _uncompress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress($value);
    }

    public function _pack($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack($value);
    }

    public function _unpack($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack($value);
    }

    public function _prefix($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix($value);
    }

    public function getLastError(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getLastError();
    }

    public function clearLastError(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearLastError();
    }

    public function endpointId(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->endpointId();
    }

    public function getPersistentID(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPersistentID();
    }

    public function socketId(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->socketId();
    }

    public function rawCommand($cmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawCommand($cmd, ...$args);
    }

    public function select($db): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->select($db);
    }

    public function auth(#[\SensitiveParameter] $auth): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->auth($auth);
    }

    public function info(...$sections): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info(...$sections);
    }

    public function flushdb($async = false): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushdb($async);
    }

    public function flushall($async = false): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushall($async);
    }

    public function fcall($name, $keys = [], $argv = [], $handler = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->fcall($name, $keys, $argv, $handler);
    }

    public function fcall_ro($name, $keys = [], $argv = [], $handler = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->fcall_ro($name, $keys, $argv, $handler);
    }

    public function function($op, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->function($op, ...$args);
    }

    public function dbsize(): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbsize();
    }

    public function dump($key): \Relay\Relay|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump($key);
    }

    public function replicaof($host = null, $port = 0): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->replicaof($host, $port);
    }

    public function restore($key, $ttl, $value, $options = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore($key, $ttl, $value, $options);
    }

    public function migrate($host, $port, $key, $dstdb, $timeout, $copy = false, $replace = false, #[\SensitiveParameter] $credentials = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->migrate($host, $port, $key, $dstdb, $timeout, $copy, $replace, $credentials);
    }

    public function copy($src, $dst, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->copy($src, $dst, $options);
    }

    public function echo($arg): \Relay\Relay|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo($arg);
    }

    public function ping($arg = null): \Relay\Relay|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping($arg);
    }

    public function idleTime(): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->idleTime();
    }

    public function randomkey(): \Relay\Relay|bool|null|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomkey();
    }

    public function time(): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time();
    }

    public function bgrewriteaof(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof();
    }

    public function lastsave(): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastsave();
    }

    public function bgsave($schedule = false): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgsave($schedule);
    }

    public function save(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save();
    }

    public function role(): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role();
    }

    public function ttl($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl($key);
    }

    public function pttl($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl($key);
    }

    public function exists(...$keys): \Relay\Relay|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists(...$keys);
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval($script, $args, $num_keys);
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval_ro($script, $args, $num_keys);
    }

    public function evalsha($sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha($sha, $args, $num_keys);
    }

    public function evalsha_ro($sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha_ro($sha, $args, $num_keys);
    }

    public function client($operation, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client($operation, ...$args);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd($key, $lng, $lat, $member, ...$other_triples_and_options);
    }

    public function geodist($key, $src, $dst, $unit = null): \Relay\Relay|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geodist($key, $src, $dst, $unit);
    }

    public function geohash($key, $member, ...$other_members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash($key, $member, ...$other_members);
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius($key, $lng, $lat, $radius, $unit, $options);
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember($key, $member, $radius, $unit, $options);
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember_ro($key, $member, $radius, $unit, $options);
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius_ro($key, $lng, $lat, $radius, $unit, $options);
    }

    public function geosearch($key, $position, $shape, $unit, $options = []): \Relay\Relay|array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearch($key, $position, $shape, $unit, $options);
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearchstore($dst, $src, $position, $shape, $unit, $options);
    }

    public function get($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get($key);
    }

    public function getset($key, $value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset($key, $value);
    }

    public function getrange($key, $start, $end): \Relay\Relay|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange($key, $start, $end);
    }

    public function setrange($key, $start, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setrange($key, $start, $value);
    }

    public function getbit($key, $pos): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getbit($key, $pos);
    }

    public function bitcount($key, $start = 0, $end = -1, $by_bit = false): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount($key, $start, $end, $by_bit);
    }

    public function config($operation, $key = null, $value = null): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config($operation, $key, $value);
    }

    public function command(...$args): \Relay\Relay|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...$args);
    }

    public function bitop($operation, $dstkey, $srckey, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop($operation, $dstkey, $srckey, ...$other_keys);
    }

    public function bitpos($key, $bit, $start = null, $end = null, $bybit = false): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos($key, $bit, $start, $end, $bybit);
    }

    public function setbit($key, $pos, $val): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setbit($key, $pos, $val);
    }

    public function acl($cmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl($cmd, ...$args);
    }

    public function append($key, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append($key, $value);
    }

    public function set($key, $value, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set($key, $value, $options);
    }

    public function getex($key, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getex($key, $options);
    }

    public function getdel($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getdel($key);
    }

    public function setex($key, $seconds, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex($key, $seconds, $value);
    }

    public function pfadd($key, $elements): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd($key, $elements);
    }

    public function pfcount($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount($key);
    }

    public function pfmerge($dst, $srckeys): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge($dst, $srckeys);
    }

    public function psetex($key, $milliseconds, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex($key, $milliseconds, $value);
    }

    public function publish($channel, $message): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish($channel, $message);
    }

    public function setnx($key, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx($key, $value);
    }

    public function mget($keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget($keys);
    }

    public function move($key, $db): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->move($key, $db);
    }

    public function mset($kvals): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset($kvals);
    }

    public function msetnx($kvals): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx($kvals);
    }

    public function rename($key, $newkey): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename($key, $newkey);
    }

    public function renamenx($key, $newkey): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renamenx($key, $newkey);
    }

    public function del(...$keys): \Relay\Relay|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del(...$keys);
    }

    public function unlink(...$keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink(...$keys);
    }

    public function expire($key, $seconds, $mode = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire($key, $seconds, $mode);
    }

    public function pexpire($key, $milliseconds): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire($key, $milliseconds);
    }

    public function expireat($key, $timestamp): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat($key, $timestamp);
    }

    public function expiretime($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expiretime($key);
    }

    public function pexpireat($key, $timestamp_ms): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat($key, $timestamp_ms);
    }

    public function pexpiretime($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpiretime($key);
    }

    public function persist($key): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist($key);
    }

    public function type($key): \Relay\Relay|bool|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type($key);
    }

    public function lmove($srckey, $dstkey, $srcpos, $dstpos): \Relay\Relay|false|null|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove($srckey, $dstkey, $srcpos, $dstpos);
    }

    public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): \Relay\Relay|false|null|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout);
    }

    public function lrange($key, $start, $stop): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange($key, $start, $stop);
    }

    public function lpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpush($key, $mem, ...$mems);
    }

    public function rpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpush($key, $mem, ...$mems);
    }

    public function lpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpushx($key, $mem, ...$mems);
    }

    public function rpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpushx($key, $mem, ...$mems);
    }

    public function lset($key, $index, $mem): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lset($key, $index, $mem);
    }

    public function lpop($key, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop($key, $count);
    }

    public function lpos($key, $value, $options = null): \Relay\Relay|array|false|int|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpos($key, $value, $options);
    }

    public function rpop($key, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop($key, $count);
    }

    public function rpoplpush($source, $dest): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush($source, $dest);
    }

    public function brpoplpush($source, $dest, $timeout): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush($source, $dest, $timeout);
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blpop($key, $timeout_or_key, ...$extra_args);
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmpop($timeout, $keys, $from, $count);
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzmpop($timeout, $keys, $from, $count);
    }

    public function lmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmpop($keys, $from, $count);
    }

    public function zmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmpop($keys, $from, $count);
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpop($key, $timeout_or_key, ...$extra_args);
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmin($key, $timeout_or_key, ...$extra_args);
    }

    public function object($op, $key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object($op, $key);
    }

    public function geopos($key, ...$members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geopos($key, ...$members);
    }

    public function lrem($key, $mem, $count = 0): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem($key, $mem, $count);
    }

    public function lindex($key, $index): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex($key, $index);
    }

    public function linsert($key, $op, $pivot, $element): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->linsert($key, $op, $pivot, $element);
    }

    public function ltrim($key, $start, $end): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim($key, $start, $end);
    }

    public function hget($hash, $member): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget($hash, $member);
    }

    public function hstrlen($hash, $member): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen($hash, $member);
    }

    public function hgetall($hash): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall($hash);
    }

    public function hkeys($hash): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys($hash);
    }

    public function hvals($hash): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals($hash);
    }

    public function hmget($hash, $members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget($hash, $members);
    }

    public function hrandfield($hash, $options = null): \Relay\Relay|array|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hrandfield($hash, $options);
    }

    public function hmset($hash, $members): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset($hash, $members);
    }

    public function hexists($hash, $member): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists($hash, $member);
    }

    public function hsetnx($hash, $member, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx($hash, $member, $value);
    }

    public function hset($key, $mem, $val, ...$kvals): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hset($key, $mem, $val, ...$kvals);
    }

    public function hdel($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hdel($key, $mem, ...$mems);
    }

    public function hincrby($key, $mem, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrby($key, $mem, $value);
    }

    public function hincrbyfloat($key, $mem, $value): \Relay\Relay|bool|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrbyfloat($key, $mem, $value);
    }

    public function incr($key, $by = 1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr($key, $by);
    }

    public function decr($key, $by = 1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr($key, $by);
    }

    public function incrby($key, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrby($key, $value);
    }

    public function decrby($key, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrby($key, $value);
    }

    public function incrbyfloat($key, $value): \Relay\Relay|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrbyfloat($key, $value);
    }

    public function sdiff($key, ...$other_keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiff($key, ...$other_keys);
    }

    public function sdiffstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffstore($key, ...$other_keys);
    }

    public function sinter($key, ...$other_keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinter($key, ...$other_keys);
    }

    public function sintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sintercard($keys, $limit);
    }

    public function sinterstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinterstore($key, ...$other_keys);
    }

    public function sunion($key, ...$other_keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunion($key, ...$other_keys);
    }

    public function sunionstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunionstore($key, ...$other_keys);
    }

    public function touch($key_or_array, ...$more_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->touch($key_or_array, ...$more_keys);
    }

    public function pipeline(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pipeline();
    }

    public function multi($mode = 0): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi($mode);
    }

    public function exec(): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec();
    }

    public function wait($replicas, $timeout): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->wait($replicas, $timeout);
    }

    public function watch($key, ...$other_keys): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch($key, ...$other_keys);
    }

    public function unwatch(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch();
    }

    public function discard(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard();
    }

    public function getMode($masked = false): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMode($masked);
    }

    public function clearBytes(): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearBytes();
    }

    public function scan(&$iterator, $match = null, $count = 0, $type = null): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($iterator, $match, $count, $type);
    }

    public function hscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($key, $iterator, $match, $count);
    }

    public function sscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($key, $iterator, $match, $count);
    }

    public function zscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($key, $iterator, $match, $count);
    }

    public function keys($pattern): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys($pattern);
    }

    public function slowlog($operation, ...$extra_args): \Relay\Relay|array|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog($operation, ...$extra_args);
    }

    public function smembers($set): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smembers($set);
    }

    public function sismember($set, $member): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember($set, $member);
    }

    public function smismember($set, ...$members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smismember($set, ...$members);
    }

    public function srem($set, $member, ...$members): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem($set, $member, ...$members);
    }

    public function sadd($set, $member, ...$members): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sadd($set, $member, ...$members);
    }

    public function sort($key, $options = []): \Relay\Relay|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort($key, $options);
    }

    public function sort_ro($key, $options = []): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro($key, $options);
    }

    public function smove($srcset, $dstset, $member): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smove($srcset, $dstset, $member);
    }

    public function spop($set, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop($set, $count);
    }

    public function srandmember($set, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember($set, $count);
    }

    public function scard($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard($key);
    }

    public function script($command, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script($command, ...$args);
    }

    public function strlen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen($key);
    }

    public function hlen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hlen($key);
    }

    public function llen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->llen($key);
    }

    public function xack($key, $group, $ids): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack($key, $group, $ids);
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Relay|false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xadd($key, $id, $values, $maxlen, $approx, $nomkstream);
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim($key, $group, $consumer, $min_idle, $ids, $options);
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xautoclaim($key, $group, $consumer, $min_idle, $start, $count, $justid);
    }

    public function xlen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen($key);
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup($operation, $key, $group, $id_or_consumer, $mkstream, $entries_read);
    }

    public function xdel($key, $ids): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel($key, $ids);
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo($operation, $arg1, $arg2, $count);
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null, $idle = 0): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending($key, $group, $start, $end, $count, $consumer, $idle);
    }

    public function xrange($key, $start, $end, $count = -1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange($key, $start, $end, $count);
    }

    public function xrevrange($key, $end, $start, $count = -1): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange($key, $end, $start, $count);
    }

    public function xread($streams, $count = -1, $block = -1): \Relay\Relay|array|bool|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread($streams, $count, $block);
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Relay\Relay|array|bool|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup($group, $consumer, $streams, $count, $block);
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim($key, $threshold, $approx, $minid, $limit);
    }

    public function zadd($key, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zadd($key, ...$args);
    }

    public function zrandmember($key, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrandmember($key, $options);
    }

    public function zrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrange($key, $start, $end, $options);
    }

    public function zrevrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrange($key, $start, $end, $options);
    }

    public function zrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebyscore($key, $start, $end, $options);
    }

    public function zrevrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebyscore($key, $start, $end, $options);
    }

    public function zrangestore($dst, $src, $start, $end, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangestore($dst, $src, $start, $end, $options);
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebylex($key, $min, $max, $offset, $count);
    }

    public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex($key, $max, $min, $offset, $count);
    }

    public function zrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrank($key, $rank, $withscore);
    }

    public function zrevrank($key, $rank, $withscore = false): \Relay\Relay|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrank($key, $rank, $withscore);
    }

    public function zrem($key, ...$args): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrem($key, ...$args);
    }

    public function zremrangebylex($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebylex($key, $min, $max);
    }

    public function zremrangebyrank($key, $start, $end): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyrank($key, $start, $end);
    }

    public function zremrangebyscore($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyscore($key, $min, $max);
    }

    public function zcard($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcard($key);
    }

    public function zcount($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcount($key, $min, $max);
    }

    public function zdiff($keys, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiff($keys, $options);
    }

    public function zdiffstore($dst, $keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiffstore($dst, $keys);
    }

    public function zincrby($key, $score, $mem): \Relay\Relay|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zincrby($key, $score, $mem);
    }

    public function zlexcount($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zlexcount($key, $min, $max);
    }

    public function zmscore($key, ...$mems): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmscore($key, ...$mems);
    }

    public function zscore($key, $member): \Relay\Relay|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscore($key, $member);
    }

    public function zinter($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinter($keys, $weights, $options);
    }

    public function zintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zintercard($keys, $limit);
    }

    public function zinterstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore($dst, $keys, $weights, $options);
    }

    public function zunion($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunion($keys, $weights, $options);
    }

    public function zunionstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore($dst, $keys, $weights, $options);
    }

    public function zpopmin($key, $count = 1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin($key, $count);
    }

    public function zpopmax($key, $count = 1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax($key, $count);
    }

    public function _getKeys()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_getKeys();
    }
}
