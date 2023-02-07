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

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
        "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
    ];

    public function __construct($options = null)
    {
        return $this->lazyObjectReal->__construct($options);
    }

    public function _compress($value): string
    {
        return $this->lazyObjectReal->_compress($value);
    }

    public function _uncompress($value): string
    {
        return $this->lazyObjectReal->_uncompress($value);
    }

    public function _prefix($key): string
    {
        return $this->lazyObjectReal->_prefix($key);
    }

    public function _serialize($value): string
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

    public function acl($subcmd, ...$args): mixed
    {
        return $this->lazyObjectReal->acl($subcmd, ...$args);
    }

    public function append($key, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->append($key, $value);
    }

    public function auth(#[\SensitiveParameter] $credentials): \Redis|bool
    {
        return $this->lazyObjectReal->auth($credentials);
    }

    public function bgSave(): \Redis|bool
    {
        return $this->lazyObjectReal->bgSave();
    }

    public function bgrewriteaof(): \Redis|bool
    {
        return $this->lazyObjectReal->bgrewriteaof();
    }

    public function bitcount($key, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return $this->lazyObjectReal->bitcount($key, $start, $end, $bybit);
    }

    public function bitop($operation, $deskey, $srckey, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->bitop($operation, $deskey, $srckey, ...$other_keys);
    }

    public function bitpos($key, $bit, $start = 0, $end = -1, $bybit = false): \Redis|false|int
    {
        return $this->lazyObjectReal->bitpos($key, $bit, $start, $end, $bybit);
    }

    public function blPop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return $this->lazyObjectReal->blPop($key_or_keys, $timeout_or_key, ...$extra_args);
    }

    public function brPop($key_or_keys, $timeout_or_key, ...$extra_args): \Redis|array|false|null
    {
        return $this->lazyObjectReal->brPop($key_or_keys, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($src, $dst, $timeout): \Redis|false|string
    {
        return $this->lazyObjectReal->brpoplpush($src, $dst, $timeout);
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return $this->lazyObjectReal->bzPopMax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args): \Redis|array|false
    {
        return $this->lazyObjectReal->bzPopMin($key, $timeout_or_key, ...$extra_args);
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->lazyObjectReal->bzmpop($timeout, $keys, $from, $count);
    }

    public function zmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->lazyObjectReal->zmpop($keys, $from, $count);
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->lazyObjectReal->blmpop($timeout, $keys, $from, $count);
    }

    public function lmpop($keys, $from, $count = 1): \Redis|array|false|null
    {
        return $this->lazyObjectReal->lmpop($keys, $from, $count);
    }

    public function clearLastError(): bool
    {
        return $this->lazyObjectReal->clearLastError();
    }

    public function client($opt, ...$args): mixed
    {
        return $this->lazyObjectReal->client($opt, ...$args);
    }

    public function close(): bool
    {
        return $this->lazyObjectReal->close();
    }

    public function command($opt = null, ...$args): mixed
    {
        return $this->lazyObjectReal->command($opt, ...$args);
    }

    public function config($operation, $key_or_settings = null, $value = null): mixed
    {
        return $this->lazyObjectReal->config($operation, $key_or_settings, $value);
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return $this->lazyObjectReal->connect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function copy($src, $dst, $options = null): \Redis|bool
    {
        return $this->lazyObjectReal->copy($src, $dst, $options);
    }

    public function dbSize(): \Redis|false|int
    {
        return $this->lazyObjectReal->dbSize();
    }

    public function debug($key): \Redis|string
    {
        return $this->lazyObjectReal->debug($key);
    }

    public function decr($key, $by = 1): \Redis|false|int
    {
        return $this->lazyObjectReal->decr($key, $by);
    }

    public function decrBy($key, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->decrBy($key, $value);
    }

    public function del($key, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->del($key, ...$other_keys);
    }

    public function delete($key, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->delete($key, ...$other_keys);
    }

    public function discard(): \Redis|bool
    {
        return $this->lazyObjectReal->discard();
    }

    public function dump($key): \Redis|string
    {
        return $this->lazyObjectReal->dump($key);
    }

    public function echo($str): \Redis|false|string
    {
        return $this->lazyObjectReal->echo($str);
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval($script, $args, $num_keys);
    }

    public function eval_ro($script_sha, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval_ro($script_sha, $args, $num_keys);
    }

    public function evalsha($sha1, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha($sha1, $args, $num_keys);
    }

    public function evalsha_ro($sha1, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha_ro($sha1, $args, $num_keys);
    }

    public function exec(): \Redis|array|false
    {
        return $this->lazyObjectReal->exec();
    }

    public function exists($key, ...$other_keys): \Redis|bool|int
    {
        return $this->lazyObjectReal->exists($key, ...$other_keys);
    }

    public function expire($key, $timeout, $mode = null): \Redis|bool
    {
        return $this->lazyObjectReal->expire($key, $timeout, $mode);
    }

    public function expireAt($key, $timestamp, $mode = null): \Redis|bool
    {
        return $this->lazyObjectReal->expireAt($key, $timestamp, $mode);
    }

    public function failover($to = null, $abort = false, $timeout = 0): \Redis|bool
    {
        return $this->lazyObjectReal->failover($to, $abort, $timeout);
    }

    public function expiretime($key): \Redis|false|int
    {
        return $this->lazyObjectReal->expiretime($key);
    }

    public function pexpiretime($key): \Redis|false|int
    {
        return $this->lazyObjectReal->pexpiretime($key);
    }

    public function fcall($fn, $keys = [], $args = []): mixed
    {
        return $this->lazyObjectReal->fcall($fn, $keys, $args);
    }

    public function fcall_ro($fn, $keys = [], $args = []): mixed
    {
        return $this->lazyObjectReal->fcall_ro($fn, $keys, $args);
    }

    public function flushAll($sync = null): \Redis|bool
    {
        return $this->lazyObjectReal->flushAll($sync);
    }

    public function flushDB($sync = null): \Redis|bool
    {
        return $this->lazyObjectReal->flushDB($sync);
    }

    public function function($operation, ...$args): \Redis|array|bool|string
    {
        return $this->lazyObjectReal->function($operation, ...$args);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Redis|false|int
    {
        return $this->lazyObjectReal->geoadd($key, $lng, $lat, $member, ...$other_triples_and_options);
    }

    public function geodist($key, $src, $dst, $unit = null): \Redis|false|float
    {
        return $this->lazyObjectReal->geodist($key, $src, $dst, $unit);
    }

    public function geohash($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->lazyObjectReal->geohash($key, $member, ...$other_members);
    }

    public function geopos($key, $member, ...$other_members): \Redis|array|false
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

    public function geosearch($key, $position, $shape, $unit, $options = []): array
    {
        return $this->lazyObjectReal->geosearch($key, $position, $shape, $unit, $options);
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Redis|array|false|int
    {
        return $this->lazyObjectReal->geosearchstore($dst, $src, $position, $shape, $unit, $options);
    }

    public function get($key): mixed
    {
        return $this->lazyObjectReal->get($key);
    }

    public function getAuth(): mixed
    {
        return $this->lazyObjectReal->getAuth();
    }

    public function getBit($key, $idx): \Redis|false|int
    {
        return $this->lazyObjectReal->getBit($key, $idx);
    }

    public function getEx($key, $options = []): \Redis|bool|string
    {
        return $this->lazyObjectReal->getEx($key, $options);
    }

    public function getDBNum(): int
    {
        return $this->lazyObjectReal->getDBNum();
    }

    public function getDel($key): \Redis|bool|string
    {
        return $this->lazyObjectReal->getDel($key);
    }

    public function getHost(): string
    {
        return $this->lazyObjectReal->getHost();
    }

    public function getLastError(): ?string
    {
        return $this->lazyObjectReal->getLastError();
    }

    public function getMode(): int
    {
        return $this->lazyObjectReal->getMode();
    }

    public function getOption($option): mixed
    {
        return $this->lazyObjectReal->getOption($option);
    }

    public function getPersistentID(): ?string
    {
        return $this->lazyObjectReal->getPersistentID();
    }

    public function getPort(): int
    {
        return $this->lazyObjectReal->getPort();
    }

    public function getRange($key, $start, $end): \Redis|false|string
    {
        return $this->lazyObjectReal->getRange($key, $start, $end);
    }

    public function lcs($key1, $key2, $options = null): \Redis|array|false|int|string
    {
        return $this->lazyObjectReal->lcs($key1, $key2, $options);
    }

    public function getReadTimeout(): float
    {
        return $this->lazyObjectReal->getReadTimeout();
    }

    public function getset($key, $value): \Redis|false|string
    {
        return $this->lazyObjectReal->getset($key, $value);
    }

    public function getTimeout(): false|float
    {
        return $this->lazyObjectReal->getTimeout();
    }

    public function getTransferredBytes(): array
    {
        return $this->lazyObjectReal->getTransferredBytes();
    }

    public function clearTransferredBytes(): void
    {
        $this->lazyObjectReal->clearTransferredBytes();
    }

    public function hDel($key, $field, ...$other_fields): \Redis|false|int
    {
        return $this->lazyObjectReal->hDel($key, $field, ...$other_fields);
    }

    public function hExists($key, $field): \Redis|bool
    {
        return $this->lazyObjectReal->hExists($key, $field);
    }

    public function hGet($key, $member): mixed
    {
        return $this->lazyObjectReal->hGet($key, $member);
    }

    public function hGetAll($key): \Redis|array|false
    {
        return $this->lazyObjectReal->hGetAll($key);
    }

    public function hIncrBy($key, $field, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->hIncrBy($key, $field, $value);
    }

    public function hIncrByFloat($key, $field, $value): \Redis|false|float
    {
        return $this->lazyObjectReal->hIncrByFloat($key, $field, $value);
    }

    public function hKeys($key): \Redis|array|false
    {
        return $this->lazyObjectReal->hKeys($key);
    }

    public function hLen($key): \Redis|false|int
    {
        return $this->lazyObjectReal->hLen($key);
    }

    public function hMget($key, $fields): \Redis|array|false
    {
        return $this->lazyObjectReal->hMget($key, $fields);
    }

    public function hMset($key, $fieldvals): \Redis|bool
    {
        return $this->lazyObjectReal->hMset($key, $fieldvals);
    }

    public function hRandField($key, $options = null): \Redis|array|string
    {
        return $this->lazyObjectReal->hRandField($key, $options);
    }

    public function hSet($key, $member, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->hSet($key, $member, $value);
    }

    public function hSetNx($key, $field, $value): \Redis|bool
    {
        return $this->lazyObjectReal->hSetNx($key, $field, $value);
    }

    public function hStrLen($key, $field): \Redis|false|int
    {
        return $this->lazyObjectReal->hStrLen($key, $field);
    }

    public function hVals($key): \Redis|array|false
    {
        return $this->lazyObjectReal->hVals($key);
    }

    public function hscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|bool
    {
        return $this->lazyObjectReal->hscan($key, $iterator, $pattern, $count);
    }

    public function incr($key, $by = 1): \Redis|false|int
    {
        return $this->lazyObjectReal->incr($key, $by);
    }

    public function incrBy($key, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->incrBy($key, $value);
    }

    public function incrByFloat($key, $value): \Redis|false|float
    {
        return $this->lazyObjectReal->incrByFloat($key, $value);
    }

    public function info(...$sections): \Redis|array|false
    {
        return $this->lazyObjectReal->info(...$sections);
    }

    public function isConnected(): bool
    {
        return $this->lazyObjectReal->isConnected();
    }

    public function keys($pattern)
    {
        return $this->lazyObjectReal->keys($pattern);
    }

    public function lInsert($key, $pos, $pivot, $value)
    {
        return $this->lazyObjectReal->lInsert($key, $pos, $pivot, $value);
    }

    public function lLen($key): \Redis|false|int
    {
        return $this->lazyObjectReal->lLen($key);
    }

    public function lMove($src, $dst, $wherefrom, $whereto): \Redis|false|string
    {
        return $this->lazyObjectReal->lMove($src, $dst, $wherefrom, $whereto);
    }

    public function blmove($src, $dst, $wherefrom, $whereto, $timeout): \Redis|false|string
    {
        return $this->lazyObjectReal->blmove($src, $dst, $wherefrom, $whereto, $timeout);
    }

    public function lPop($key, $count = 0): \Redis|array|bool|string
    {
        return $this->lazyObjectReal->lPop($key, $count);
    }

    public function lPos($key, $value, $options = null): \Redis|array|bool|int|null
    {
        return $this->lazyObjectReal->lPos($key, $value, $options);
    }

    public function lPush($key, ...$elements): \Redis|false|int
    {
        return $this->lazyObjectReal->lPush($key, ...$elements);
    }

    public function rPush($key, ...$elements): \Redis|false|int
    {
        return $this->lazyObjectReal->rPush($key, ...$elements);
    }

    public function lPushx($key, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->lPushx($key, $value);
    }

    public function rPushx($key, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->rPushx($key, $value);
    }

    public function lSet($key, $index, $value): \Redis|bool
    {
        return $this->lazyObjectReal->lSet($key, $index, $value);
    }

    public function lastSave(): int
    {
        return $this->lazyObjectReal->lastSave();
    }

    public function lindex($key, $index): mixed
    {
        return $this->lazyObjectReal->lindex($key, $index);
    }

    public function lrange($key, $start, $end): \Redis|array|false
    {
        return $this->lazyObjectReal->lrange($key, $start, $end);
    }

    public function lrem($key, $value, $count = 0): \Redis|false|int
    {
        return $this->lazyObjectReal->lrem($key, $value, $count);
    }

    public function ltrim($key, $start, $end): \Redis|bool
    {
        return $this->lazyObjectReal->ltrim($key, $start, $end);
    }

    public function mget($keys): \Redis|array
    {
        return $this->lazyObjectReal->mget($keys);
    }

    public function migrate($host, $port, $key, $dstdb, $timeout, $copy = false, $replace = false, #[\SensitiveParameter] $credentials = null): \Redis|bool
    {
        return $this->lazyObjectReal->migrate($host, $port, $key, $dstdb, $timeout, $copy, $replace, $credentials);
    }

    public function move($key, $index): \Redis|bool
    {
        return $this->lazyObjectReal->move($key, $index);
    }

    public function mset($key_values): \Redis|bool
    {
        return $this->lazyObjectReal->mset($key_values);
    }

    public function msetnx($key_values): \Redis|bool
    {
        return $this->lazyObjectReal->msetnx($key_values);
    }

    public function multi($value = \Redis::MULTI): \Redis|bool
    {
        return $this->lazyObjectReal->multi($value);
    }

    public function object($subcommand, $key): \Redis|false|int|string
    {
        return $this->lazyObjectReal->object($subcommand, $key);
    }

    public function open($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return $this->lazyObjectReal->open($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return $this->lazyObjectReal->pconnect($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function persist($key): \Redis|bool
    {
        return $this->lazyObjectReal->persist($key);
    }

    public function pexpire($key, $timeout, $mode = null): bool
    {
        return $this->lazyObjectReal->pexpire($key, $timeout, $mode);
    }

    public function pexpireAt($key, $timestamp, $mode = null): \Redis|bool
    {
        return $this->lazyObjectReal->pexpireAt($key, $timestamp, $mode);
    }

    public function pfadd($key, $elements): \Redis|int
    {
        return $this->lazyObjectReal->pfadd($key, $elements);
    }

    public function pfcount($key_or_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->pfcount($key_or_keys);
    }

    public function pfmerge($dst, $srckeys): \Redis|bool
    {
        return $this->lazyObjectReal->pfmerge($dst, $srckeys);
    }

    public function ping($message = null): \Redis|bool|string
    {
        return $this->lazyObjectReal->ping($message);
    }

    public function pipeline(): \Redis|bool
    {
        return $this->lazyObjectReal->pipeline();
    }

    public function popen($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, $context = null): bool
    {
        return $this->lazyObjectReal->popen($host, $port, $timeout, $persistent_id, $retry_interval, $read_timeout, $context);
    }

    public function psetex($key, $expire, $value): \Redis|bool
    {
        return $this->lazyObjectReal->psetex($key, $expire, $value);
    }

    public function psubscribe($patterns, $cb): bool
    {
        return $this->lazyObjectReal->psubscribe($patterns, $cb);
    }

    public function pttl($key): \Redis|false|int
    {
        return $this->lazyObjectReal->pttl($key);
    }

    public function publish($channel, $message): \Redis|false|int
    {
        return $this->lazyObjectReal->publish($channel, $message);
    }

    public function pubsub($command, $arg = null): mixed
    {
        return $this->lazyObjectReal->pubsub($command, $arg);
    }

    public function punsubscribe($patterns): \Redis|array|bool
    {
        return $this->lazyObjectReal->punsubscribe($patterns);
    }

    public function rPop($key, $count = 0): \Redis|array|bool|string
    {
        return $this->lazyObjectReal->rPop($key, $count);
    }

    public function randomKey(): \Redis|false|string
    {
        return $this->lazyObjectReal->randomKey();
    }

    public function rawcommand($command, ...$args): mixed
    {
        return $this->lazyObjectReal->rawcommand($command, ...$args);
    }

    public function rename($old_name, $new_name): \Redis|bool
    {
        return $this->lazyObjectReal->rename($old_name, $new_name);
    }

    public function renameNx($key_src, $key_dst): \Redis|bool
    {
        return $this->lazyObjectReal->renameNx($key_src, $key_dst);
    }

    public function restore($key, $ttl, $value, $options = null): \Redis|bool
    {
        return $this->lazyObjectReal->restore($key, $ttl, $value, $options);
    }

    public function role(): mixed
    {
        return $this->lazyObjectReal->role();
    }

    public function rpoplpush($srckey, $dstkey): \Redis|false|string
    {
        return $this->lazyObjectReal->rpoplpush($srckey, $dstkey);
    }

    public function sAdd($key, $value, ...$other_values): \Redis|false|int
    {
        return $this->lazyObjectReal->sAdd($key, $value, ...$other_values);
    }

    public function sAddArray($key, $values): int
    {
        return $this->lazyObjectReal->sAddArray($key, $values);
    }

    public function sDiff($key, ...$other_keys): \Redis|array|false
    {
        return $this->lazyObjectReal->sDiff($key, ...$other_keys);
    }

    public function sDiffStore($dst, $key, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->sDiffStore($dst, $key, ...$other_keys);
    }

    public function sInter($key, ...$other_keys): \Redis|array|false
    {
        return $this->lazyObjectReal->sInter($key, ...$other_keys);
    }

    public function sintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->lazyObjectReal->sintercard($keys, $limit);
    }

    public function sInterStore($key, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->sInterStore($key, ...$other_keys);
    }

    public function sMembers($key): \Redis|array|false
    {
        return $this->lazyObjectReal->sMembers($key);
    }

    public function sMisMember($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->lazyObjectReal->sMisMember($key, $member, ...$other_members);
    }

    public function sMove($src, $dst, $value): \Redis|bool
    {
        return $this->lazyObjectReal->sMove($src, $dst, $value);
    }

    public function sPop($key, $count = 0): \Redis|array|false|string
    {
        return $this->lazyObjectReal->sPop($key, $count);
    }

    public function sRandMember($key, $count = 0): \Redis|array|false|string
    {
        return $this->lazyObjectReal->sRandMember($key, $count);
    }

    public function sUnion($key, ...$other_keys): \Redis|array|false
    {
        return $this->lazyObjectReal->sUnion($key, ...$other_keys);
    }

    public function sUnionStore($dst, $key, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->sUnionStore($dst, $key, ...$other_keys);
    }

    public function save(): \Redis|bool
    {
        return $this->lazyObjectReal->save();
    }

    public function scan(&$iterator, $pattern = null, $count = 0, $type = null): array|false
    {
        return $this->lazyObjectReal->scan($iterator, $pattern, $count, $type);
    }

    public function scard($key): \Redis|false|int
    {
        return $this->lazyObjectReal->scard($key);
    }

    public function script($command, ...$args): mixed
    {
        return $this->lazyObjectReal->script($command, ...$args);
    }

    public function select($db): \Redis|bool
    {
        return $this->lazyObjectReal->select($db);
    }

    public function set($key, $value, $options = null): \Redis|bool|string
    {
        return $this->lazyObjectReal->set($key, $value, $options);
    }

    public function setBit($key, $idx, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->setBit($key, $idx, $value);
    }

    public function setRange($key, $index, $value): \Redis|false|int
    {
        return $this->lazyObjectReal->setRange($key, $index, $value);
    }

    public function setOption($option, $value): bool
    {
        return $this->lazyObjectReal->setOption($option, $value);
    }

    public function setex($key, $expire, $value)
    {
        return $this->lazyObjectReal->setex($key, $expire, $value);
    }

    public function setnx($key, $value): \Redis|bool
    {
        return $this->lazyObjectReal->setnx($key, $value);
    }

    public function sismember($key, $value): \Redis|bool
    {
        return $this->lazyObjectReal->sismember($key, $value);
    }

    public function slaveof($host = null, $port = 6379): \Redis|bool
    {
        return $this->lazyObjectReal->slaveof($host, $port);
    }

    public function replicaof($host = null, $port = 6379): \Redis|bool
    {
        return $this->lazyObjectReal->replicaof($host, $port);
    }

    public function touch($key_or_array, ...$more_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->touch($key_or_array, ...$more_keys);
    }

    public function slowlog($operation, $length = 0): mixed
    {
        return $this->lazyObjectReal->slowlog($operation, $length);
    }

    public function sort($key, $options = null): mixed
    {
        return $this->lazyObjectReal->sort($key, $options);
    }

    public function sort_ro($key, $options = null): mixed
    {
        return $this->lazyObjectReal->sort_ro($key, $options);
    }

    public function sortAsc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->lazyObjectReal->sortAsc($key, $pattern, $get, $offset, $count, $store);
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->lazyObjectReal->sortAscAlpha($key, $pattern, $get, $offset, $count, $store);
    }

    public function sortDesc($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->lazyObjectReal->sortDesc($key, $pattern, $get, $offset, $count, $store);
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $offset = -1, $count = -1, $store = null): array
    {
        return $this->lazyObjectReal->sortDescAlpha($key, $pattern, $get, $offset, $count, $store);
    }

    public function srem($key, $value, ...$other_values): \Redis|false|int
    {
        return $this->lazyObjectReal->srem($key, $value, ...$other_values);
    }

    public function sscan($key, &$iterator, $pattern = null, $count = 0): array|false
    {
        return $this->lazyObjectReal->sscan($key, $iterator, $pattern, $count);
    }

    public function ssubscribe($channels, $cb): bool
    {
        return $this->lazyObjectReal->ssubscribe($channels, $cb);
    }

    public function strlen($key): \Redis|false|int
    {
        return $this->lazyObjectReal->strlen($key);
    }

    public function subscribe($channels, $cb): bool
    {
        return $this->lazyObjectReal->subscribe($channels, $cb);
    }

    public function sunsubscribe($channels): \Redis|array|bool
    {
        return $this->lazyObjectReal->sunsubscribe($channels);
    }

    public function swapdb($src, $dst): \Redis|bool
    {
        return $this->lazyObjectReal->swapdb($src, $dst);
    }

    public function time(): \Redis|array
    {
        return $this->lazyObjectReal->time();
    }

    public function ttl($key): \Redis|false|int
    {
        return $this->lazyObjectReal->ttl($key);
    }

    public function type($key): \Redis|false|int
    {
        return $this->lazyObjectReal->type($key);
    }

    public function unlink($key, ...$other_keys): \Redis|false|int
    {
        return $this->lazyObjectReal->unlink($key, ...$other_keys);
    }

    public function unsubscribe($channels): \Redis|array|bool
    {
        return $this->lazyObjectReal->unsubscribe($channels);
    }

    public function unwatch(): \Redis|bool
    {
        return $this->lazyObjectReal->unwatch();
    }

    public function watch($key, ...$other_keys): \Redis|bool
    {
        return $this->lazyObjectReal->watch($key, ...$other_keys);
    }

    public function wait($numreplicas, $timeout): false|int
    {
        return $this->lazyObjectReal->wait($numreplicas, $timeout);
    }

    public function xack($key, $group, $ids): false|int
    {
        return $this->lazyObjectReal->xack($key, $group, $ids);
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Redis|false|string
    {
        return $this->lazyObjectReal->xadd($key, $id, $values, $maxlen, $approx, $nomkstream);
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Redis|array|bool
    {
        return $this->lazyObjectReal->xautoclaim($key, $group, $consumer, $min_idle, $start, $count, $justid);
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Redis|array|bool
    {
        return $this->lazyObjectReal->xclaim($key, $group, $consumer, $min_idle, $ids, $options);
    }

    public function xdel($key, $ids): \Redis|false|int
    {
        return $this->lazyObjectReal->xdel($key, $ids);
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->lazyObjectReal->xgroup($operation, $key, $group, $id_or_consumer, $mkstream, $entries_read);
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->lazyObjectReal->xinfo($operation, $arg1, $arg2, $count);
    }

    public function xlen($key): \Redis|false|int
    {
        return $this->lazyObjectReal->xlen($key);
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null): \Redis|array|false
    {
        return $this->lazyObjectReal->xpending($key, $group, $start, $end, $count, $consumer);
    }

    public function xrange($key, $start, $end, $count = -1): \Redis|array|bool
    {
        return $this->lazyObjectReal->xrange($key, $start, $end, $count);
    }

    public function xread($streams, $count = -1, $block = -1): \Redis|array|bool
    {
        return $this->lazyObjectReal->xread($streams, $count, $block);
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Redis|array|bool
    {
        return $this->lazyObjectReal->xreadgroup($group, $consumer, $streams, $count, $block);
    }

    public function xrevrange($key, $end, $start, $count = -1): \Redis|array|bool
    {
        return $this->lazyObjectReal->xrevrange($key, $end, $start, $count);
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Redis|false|int
    {
        return $this->lazyObjectReal->xtrim($key, $threshold, $approx, $minid, $limit);
    }

    public function zAdd($key, $score_or_options, ...$more_scores_and_mems): \Redis|false|int
    {
        return $this->lazyObjectReal->zAdd($key, $score_or_options, ...$more_scores_and_mems);
    }

    public function zCard($key): \Redis|false|int
    {
        return $this->lazyObjectReal->zCard($key);
    }

    public function zCount($key, $start, $end): \Redis|false|int
    {
        return $this->lazyObjectReal->zCount($key, $start, $end);
    }

    public function zIncrBy($key, $value, $member): \Redis|false|float
    {
        return $this->lazyObjectReal->zIncrBy($key, $value, $member);
    }

    public function zLexCount($key, $min, $max): \Redis|false|int
    {
        return $this->lazyObjectReal->zLexCount($key, $min, $max);
    }

    public function zMscore($key, $member, ...$other_members): \Redis|array|false
    {
        return $this->lazyObjectReal->zMscore($key, $member, ...$other_members);
    }

    public function zPopMax($key, $count = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zPopMax($key, $count);
    }

    public function zPopMin($key, $count = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zPopMin($key, $count);
    }

    public function zRange($key, $start, $end, $options = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zRange($key, $start, $end, $options);
    }

    public function zRangeByLex($key, $min, $max, $offset = -1, $count = -1): \Redis|array|false
    {
        return $this->lazyObjectReal->zRangeByLex($key, $min, $max, $offset, $count);
    }

    public function zRangeByScore($key, $start, $end, $options = []): \Redis|array|false
    {
        return $this->lazyObjectReal->zRangeByScore($key, $start, $end, $options);
    }

    public function zrangestore($dstkey, $srckey, $start, $end, $options = null): \Redis|false|int
    {
        return $this->lazyObjectReal->zrangestore($dstkey, $srckey, $start, $end, $options);
    }

    public function zRandMember($key, $options = null): \Redis|array|string
    {
        return $this->lazyObjectReal->zRandMember($key, $options);
    }

    public function zRank($key, $member): \Redis|false|int
    {
        return $this->lazyObjectReal->zRank($key, $member);
    }

    public function zRem($key, $member, ...$other_members): \Redis|false|int
    {
        return $this->lazyObjectReal->zRem($key, $member, ...$other_members);
    }

    public function zRemRangeByLex($key, $min, $max): \Redis|false|int
    {
        return $this->lazyObjectReal->zRemRangeByLex($key, $min, $max);
    }

    public function zRemRangeByRank($key, $start, $end): \Redis|false|int
    {
        return $this->lazyObjectReal->zRemRangeByRank($key, $start, $end);
    }

    public function zRemRangeByScore($key, $start, $end): \Redis|false|int
    {
        return $this->lazyObjectReal->zRemRangeByScore($key, $start, $end);
    }

    public function zRevRange($key, $start, $end, $scores = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zRevRange($key, $start, $end, $scores);
    }

    public function zRevRangeByLex($key, $max, $min, $offset = -1, $count = -1): \Redis|array|false
    {
        return $this->lazyObjectReal->zRevRangeByLex($key, $max, $min, $offset, $count);
    }

    public function zRevRangeByScore($key, $max, $min, $options = []): \Redis|array|false
    {
        return $this->lazyObjectReal->zRevRangeByScore($key, $max, $min, $options);
    }

    public function zRevRank($key, $member): \Redis|false|int
    {
        return $this->lazyObjectReal->zRevRank($key, $member);
    }

    public function zScore($key, $member): \Redis|false|float
    {
        return $this->lazyObjectReal->zScore($key, $member);
    }

    public function zdiff($keys, $options = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zdiff($keys, $options);
    }

    public function zdiffstore($dst, $keys): \Redis|false|int
    {
        return $this->lazyObjectReal->zdiffstore($dst, $keys);
    }

    public function zinter($keys, $weights = null, $options = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zinter($keys, $weights, $options);
    }

    public function zintercard($keys, $limit = -1): \Redis|false|int
    {
        return $this->lazyObjectReal->zintercard($keys, $limit);
    }

    public function zinterstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return $this->lazyObjectReal->zinterstore($dst, $keys, $weights, $aggregate);
    }

    public function zscan($key, &$iterator, $pattern = null, $count = 0): \Redis|array|false
    {
        return $this->lazyObjectReal->zscan($key, $iterator, $pattern, $count);
    }

    public function zunion($keys, $weights = null, $options = null): \Redis|array|false
    {
        return $this->lazyObjectReal->zunion($keys, $weights, $options);
    }

    public function zunionstore($dst, $keys, $weights = null, $aggregate = null): \Redis|false|int
    {
        return $this->lazyObjectReal->zunionstore($dst, $keys, $weights, $aggregate);
    }
}
