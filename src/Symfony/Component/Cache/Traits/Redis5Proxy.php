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
class Redis5Proxy extends \Redis implements ResetInterface, LazyObjectInterface
{
    use LazyProxyTrait {
        resetLazyObject as reset;
    }

    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

    public function __construct()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct();
    }

    public function _prefix($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix($key);
    }

    public function _serialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize($value);
    }

    public function _unserialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize($value);
    }

    public function _pack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack($value);
    }

    public function _unpack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack($value);
    }

    public function _compress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress($value);
    }

    public function _uncompress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress($value);
    }

    public function acl($subcmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl($subcmd, ...$args);
    }

    public function append($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append($key, $value);
    }

    public function auth($auth)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->auth($auth);
    }

    public function bgSave()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgSave();
    }

    public function bgrewriteaof()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof();
    }

    public function bitcount($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount($key);
    }

    public function bitop($operation, $ret_key, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop($operation, $ret_key, $key, ...$other_keys);
    }

    public function bitpos($key, $bit, $start = null, $end = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos($key, $bit, $start, $end);
    }

    public function blPop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blPop($key, $timeout_or_key, ...$extra_args);
    }

    public function brPop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brPop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush($src, $dst, $timeout);
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzPopMax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzPopMin($key, $timeout_or_key, ...$extra_args);
    }

    public function clearLastError()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearLastError();
    }

    public function client($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client($cmd, ...$args);
    }

    public function close()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close();
    }

    public function command(...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...$args);
    }

    public function config($cmd, $key, $value = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config($cmd, $key, $value);
    }

    public function connect($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect($host, $port, $timeout, $retry_interval);
    }

    public function dbSize()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbSize();
    }

    public function debug($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->debug($key);
    }

    public function decr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr($key);
    }

    public function decrBy($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrBy($key, $value);
    }

    public function del($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del($key, ...$other_keys);
    }

    public function discard()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard();
    }

    public function dump($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump($key);
    }

    public function echo($msg)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo($msg);
    }

    public function eval($script, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval($script, $args, $num_keys);
    }

    public function evalsha($script_sha, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha($script_sha, $args, $num_keys);
    }

    public function exec()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec();
    }

    public function exists($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists($key, ...$other_keys);
    }

    public function expire($key, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire($key, $timeout);
    }

    public function expireAt($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireAt($key, $timestamp);
    }

    public function flushAll($async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushAll($async);
    }

    public function flushDB($async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushDB($async);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd($key, $lng, $lat, $member, ...$other_triples);
    }

    public function geodist($key, $src, $dst, $unit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geodist($key, $src, $dst, $unit);
    }

    public function geohash($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash($key, $member, ...$other_members);
    }

    public function geopos($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geopos($key, $member, ...$other_members);
    }

    public function georadius($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius($key, $lng, $lan, $radius, $unit, $opts);
    }

    public function georadius_ro($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius_ro($key, $lng, $lan, $radius, $unit, $opts);
    }

    public function georadiusbymember($key, $member, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember($key, $member, $radius, $unit, $opts);
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember_ro($key, $member, $radius, $unit, $opts);
    }

    public function get($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get($key);
    }

    public function getAuth()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getAuth();
    }

    public function getBit($key, $offset)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getBit($key, $offset);
    }

    public function getDBNum()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getDBNum();
    }

    public function getHost()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getHost();
    }

    public function getLastError()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getLastError();
    }

    public function getMode()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMode();
    }

    public function getOption($option)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getOption($option);
    }

    public function getPersistentID()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPersistentID();
    }

    public function getPort()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPort();
    }

    public function getRange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getRange($key, $start, $end);
    }

    public function getReadTimeout()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getReadTimeout();
    }

    public function getSet($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getSet($key, $value);
    }

    public function getTimeout()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getTimeout();
    }

    public function hDel($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hDel($key, $member, ...$other_members);
    }

    public function hExists($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hExists($key, $member);
    }

    public function hGet($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGet($key, $member);
    }

    public function hGetAll($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGetAll($key);
    }

    public function hIncrBy($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hIncrBy($key, $member, $value);
    }

    public function hIncrByFloat($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hIncrByFloat($key, $member, $value);
    }

    public function hKeys($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hKeys($key);
    }

    public function hLen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hLen($key);
    }

    public function hMget($key, $keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hMget($key, $keys);
    }

    public function hMset($key, $pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hMset($key, $pairs);
    }

    public function hSet($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSet($key, $member, $value);
    }

    public function hSetNx($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSetNx($key, $member, $value);
    }

    public function hStrLen($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hStrLen($key, $member);
    }

    public function hVals($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hVals($key);
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function incr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr($key);
    }

    public function incrBy($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrBy($key, $value);
    }

    public function incrByFloat($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrByFloat($key, $value);
    }

    public function info($option = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info($option);
    }

    public function isConnected()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->isConnected();
    }

    public function keys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys($pattern);
    }

    public function lInsert($key, $position, $pivot, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lInsert($key, $position, $pivot, $value);
    }

    public function lLen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lLen($key);
    }

    public function lPop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPop($key);
    }

    public function lPush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPush($key, $value);
    }

    public function lPushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPushx($key, $value);
    }

    public function lSet($key, $index, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lSet($key, $index, $value);
    }

    public function lastSave()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastSave();
    }

    public function lindex($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex($key, $index);
    }

    public function lrange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange($key, $start, $end);
    }

    public function lrem($key, $value, $count)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem($key, $value, $count);
    }

    public function ltrim($key, $start, $stop)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim($key, $start, $stop);
    }

    public function mget($keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget($keys);
    }

    public function migrate($host, $port, $key, $db, $timeout, $copy = null, $replace = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->migrate($host, $port, $key, $db, $timeout, $copy, $replace);
    }

    public function move($key, $dbindex)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->move($key, $dbindex);
    }

    public function mset($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset($pairs);
    }

    public function msetnx($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx($pairs);
    }

    public function multi($mode = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi($mode);
    }

    public function object($field, $key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object($field, $key);
    }

    public function pconnect($host, $port = null, $timeout = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect($host, $port, $timeout);
    }

    public function persist($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist($key);
    }

    public function pexpire($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire($key, $timestamp);
    }

    public function pexpireAt($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireAt($key, $timestamp);
    }

    public function pfadd($key, $elements)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd($key, $elements);
    }

    public function pfcount($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount($key);
    }

    public function pfmerge($dstkey, $keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge($dstkey, $keys);
    }

    public function ping()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping();
    }

    public function pipeline()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pipeline();
    }

    public function psetex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex($key, $expire, $value);
    }

    public function psubscribe($patterns, $callback)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psubscribe($patterns, $callback);
    }

    public function pttl($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl($key);
    }

    public function publish($channel, $message)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish($channel, $message);
    }

    public function pubsub($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub($cmd, ...$args);
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe($pattern, ...$other_patterns);
    }

    public function rPop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPop($key);
    }

    public function rPush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPush($key, $value);
    }

    public function rPushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPushx($key, $value);
    }

    public function randomKey()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomKey();
    }

    public function rawcommand($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand($cmd, ...$args);
    }

    public function rename($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename($key, $newkey);
    }

    public function renameNx($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renameNx($key, $newkey);
    }

    public function restore($ttl, $key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore($ttl, $key, $value);
    }

    public function role()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role();
    }

    public function rpoplpush($src, $dst)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush($src, $dst);
    }

    public function sAdd($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sAdd($key, $value);
    }

    public function sAddArray($key, $options)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sAddArray($key, $options);
    }

    public function sDiff($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sDiff($key, ...$other_keys);
    }

    public function sDiffStore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sDiffStore($dst, $key, ...$other_keys);
    }

    public function sInter($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sInter($key, ...$other_keys);
    }

    public function sInterStore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sInterStore($dst, $key, ...$other_keys);
    }

    public function sMembers($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMembers($key);
    }

    public function sMisMember($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMisMember($key, $member, ...$other_members);
    }

    public function sMove($src, $dst, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMove($src, $dst, $value);
    }

    public function sPop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sPop($key);
    }

    public function sRandMember($key, $count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRandMember($key, $count);
    }

    public function sUnion($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sUnion($key, ...$other_keys);
    }

    public function sUnionStore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sUnionStore($dst, $key, ...$other_keys);
    }

    public function save()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save();
    }

    public function scan(&$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($i_iterator, $str_pattern, $i_count);
    }

    public function scard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard($key);
    }

    public function script($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script($cmd, ...$args);
    }

    public function select($dbindex)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->select($dbindex);
    }

    public function set($key, $value, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set($key, $value, $opts);
    }

    public function setBit($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setBit($key, $offset, $value);
    }

    public function setOption($option, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setOption($option, $value);
    }

    public function setRange($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setRange($key, $offset, $value);
    }

    public function setex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex($key, $expire, $value);
    }

    public function setnx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx($key, $value);
    }

    public function sismember($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember($key, $value);
    }

    public function slaveof($host = null, $port = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slaveof($host, $port);
    }

    public function slowlog($arg, $option = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog($arg, $option);
    }

    public function sort($key, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort($key, $options);
    }

    public function sortAsc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortAsc($key, $pattern, $get, $start, $end, $getList);
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortAscAlpha($key, $pattern, $get, $start, $end, $getList);
    }

    public function sortDesc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortDesc($key, $pattern, $get, $start, $end, $getList);
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortDescAlpha($key, $pattern, $get, $start, $end, $getList);
    }

    public function srem($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem($key, $member, ...$other_members);
    }

    public function sscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function strlen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen($key);
    }

    public function subscribe($channels, $callback)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->subscribe($channels, $callback);
    }

    public function swapdb($srcdb, $dstdb)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->swapdb($srcdb, $dstdb);
    }

    public function time()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time();
    }

    public function ttl($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl($key);
    }

    public function type($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type($key);
    }

    public function unlink($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink($key, ...$other_keys);
    }

    public function unsubscribe($channel, ...$other_channels)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe($channel, ...$other_channels);
    }

    public function unwatch()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch();
    }

    public function wait($numslaves, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->wait($numslaves, $timeout);
    }

    public function watch($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch($key, ...$other_keys);
    }

    public function xack($str_key, $str_group, $arr_ids)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack($str_key, $str_group, $arr_ids);
    }

    public function xadd($str_key, $str_id, $arr_fields, $i_maxlen = null, $boo_approximate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xadd($str_key, $str_id, $arr_fields, $i_maxlen, $boo_approximate);
    }

    public function xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts);
    }

    public function xdel($str_key, $arr_ids)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel($str_key, $arr_ids);
    }

    public function xgroup($str_operation, $str_key = null, $str_arg1 = null, $str_arg2 = null, $str_arg3 = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup($str_operation, $str_key, $str_arg1, $str_arg2, $str_arg3);
    }

    public function xinfo($str_cmd, $str_key = null, $str_group = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo($str_cmd, $str_key, $str_group);
    }

    public function xlen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen($key);
    }

    public function xpending($str_key, $str_group, $str_start = null, $str_end = null, $i_count = null, $str_consumer = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending($str_key, $str_group, $str_start, $str_end, $i_count, $str_consumer);
    }

    public function xrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange($str_key, $str_start, $str_end, $i_count);
    }

    public function xread($arr_streams, $i_count = null, $i_block = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread($arr_streams, $i_count, $i_block);
    }

    public function xreadgroup($str_group, $str_consumer, $arr_streams, $i_count = null, $i_block = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup($str_group, $str_consumer, $arr_streams, $i_count, $i_block);
    }

    public function xrevrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange($str_key, $str_start, $str_end, $i_count);
    }

    public function xtrim($str_key, $i_maxlen, $boo_approximate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim($str_key, $i_maxlen, $boo_approximate);
    }

    public function zAdd($key, $score, $value, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zAdd($key, $score, $value, ...$extra_args);
    }

    public function zCard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zCard($key);
    }

    public function zCount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zCount($key, $min, $max);
    }

    public function zIncrBy($key, $value, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zIncrBy($key, $value, $member);
    }

    public function zLexCount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zLexCount($key, $min, $max);
    }

    public function zPopMax($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zPopMax($key);
    }

    public function zPopMin($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zPopMin($key);
    }

    public function zRange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRange($key, $start, $end, $scores);
    }

    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRangeByLex($key, $min, $max, $offset, $limit);
    }

    public function zRangeByScore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRangeByScore($key, $start, $end, $options);
    }

    public function zRank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRank($key, $member);
    }

    public function zRem($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRem($key, $member, ...$other_members);
    }

    public function zRemRangeByLex($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByLex($key, $min, $max);
    }

    public function zRemRangeByRank($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByRank($key, $start, $end);
    }

    public function zRemRangeByScore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByScore($key, $min, $max);
    }

    public function zRevRange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRange($key, $start, $end, $scores);
    }

    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRangeByLex($key, $min, $max, $offset, $limit);
    }

    public function zRevRangeByScore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRangeByScore($key, $start, $end, $options);
    }

    public function zRevRank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRank($key, $member);
    }

    public function zScore($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zScore($key, $member);
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore($key, $keys, $weights, $aggregate);
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore($key, $keys, $weights, $aggregate);
    }

    public function delete($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->delete($key, ...$other_keys);
    }

    public function evaluate($script, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evaluate($script, $args, $num_keys);
    }

    public function evaluateSha($script_sha, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evaluateSha($script_sha, $args, $num_keys);
    }

    public function getKeys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getKeys($pattern);
    }

    public function getMultiple($keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMultiple($keys);
    }

    public function lGet($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lGet($key, $index);
    }

    public function lGetRange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lGetRange($key, $start, $end);
    }

    public function lRemove($key, $value, $count)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lRemove($key, $value, $count);
    }

    public function lSize($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lSize($key);
    }

    public function listTrim($key, $start, $stop)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->listTrim($key, $start, $stop);
    }

    public function open($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->open($host, $port, $timeout, $retry_interval);
    }

    public function popen($host, $port = null, $timeout = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->popen($host, $port, $timeout);
    }

    public function renameKey($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renameKey($key, $newkey);
    }

    public function sContains($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sContains($key, $value);
    }

    public function sGetMembers($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sGetMembers($key);
    }

    public function sRemove($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRemove($key, $member, ...$other_members);
    }

    public function sSize($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sSize($key);
    }

    public function sendEcho($msg)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sendEcho($msg);
    }

    public function setTimeout($key, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setTimeout($key, $timeout);
    }

    public function substr($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->substr($key, $start, $end);
    }

    public function zDelete($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zDelete($key, $member, ...$other_members);
    }

    public function zDeleteRangeByRank($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zDeleteRangeByRank($key, $min, $max);
    }

    public function zDeleteRangeByScore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zDeleteRangeByScore($key, $min, $max);
    }

    public function zInter($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zInter($key, $keys, $weights, $aggregate);
    }

    public function zRemove($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemove($key, $member, ...$other_members);
    }

    public function zRemoveRangeByScore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemoveRangeByScore($key, $min, $max);
    }

    public function zReverseRange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zReverseRange($key, $start, $end, $scores);
    }

    public function zSize($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zSize($key);
    }

    public function zUnion($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zUnion($key, $keys, $weights, $aggregate);
    }
}
