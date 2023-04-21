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
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct(...\func_get_args());
    }

    public function _prefix($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix(...\func_get_args());
    }

    public function _serialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize(...\func_get_args());
    }

    public function _unserialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize(...\func_get_args());
    }

    public function _pack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack(...\func_get_args());
    }

    public function _unpack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack(...\func_get_args());
    }

    public function _compress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress(...\func_get_args());
    }

    public function _uncompress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress(...\func_get_args());
    }

    public function acl($subcmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl(...\func_get_args());
    }

    public function append($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append(...\func_get_args());
    }

    public function auth($auth)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->auth(...\func_get_args());
    }

    public function bgSave()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgSave(...\func_get_args());
    }

    public function bgrewriteaof()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof(...\func_get_args());
    }

    public function bitcount($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount(...\func_get_args());
    }

    public function bitop($operation, $ret_key, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos(...\func_get_args());
    }

    public function blPop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blPop(...\func_get_args());
    }

    public function brPop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brPop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush(...\func_get_args());
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzPopMax(...\func_get_args());
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzPopMin(...\func_get_args());
    }

    public function clearLastError()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearLastError(...\func_get_args());
    }

    public function client($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client(...\func_get_args());
    }

    public function close()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close(...\func_get_args());
    }

    public function command(...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...\func_get_args());
    }

    public function config($cmd, $key, $value = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config(...\func_get_args());
    }

    public function connect($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect(...\func_get_args());
    }

    public function dbSize()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbSize(...\func_get_args());
    }

    public function debug($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->debug(...\func_get_args());
    }

    public function decr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr(...\func_get_args());
    }

    public function decrBy($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrBy(...\func_get_args());
    }

    public function del($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del(...\func_get_args());
    }

    public function discard()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard(...\func_get_args());
    }

    public function dump($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dump(...\func_get_args());
    }

    public function echo($msg)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo(...\func_get_args());
    }

    public function eval($script, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval(...\func_get_args());
    }

    public function evalsha($script_sha, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha(...\func_get_args());
    }

    public function exec()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec(...\func_get_args());
    }

    public function exists($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists(...\func_get_args());
    }

    public function expire($key, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire(...\func_get_args());
    }

    public function expireAt($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireAt(...\func_get_args());
    }

    public function flushAll($async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushAll(...\func_get_args());
    }

    public function flushDB($async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushDB(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dst, $unit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geopos(...\func_get_args());
    }

    public function georadius($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius_ro(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember_ro(...\func_get_args());
    }

    public function get($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get(...\func_get_args());
    }

    public function getAuth()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getAuth(...\func_get_args());
    }

    public function getBit($key, $offset)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getBit(...\func_get_args());
    }

    public function getDBNum()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getDBNum(...\func_get_args());
    }

    public function getHost()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getHost(...\func_get_args());
    }

    public function getLastError()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getLastError(...\func_get_args());
    }

    public function getMode()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMode(...\func_get_args());
    }

    public function getOption($option)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getOption(...\func_get_args());
    }

    public function getPersistentID()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPersistentID(...\func_get_args());
    }

    public function getPort()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPort(...\func_get_args());
    }

    public function getRange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getRange(...\func_get_args());
    }

    public function getReadTimeout()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getReadTimeout(...\func_get_args());
    }

    public function getSet($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getSet(...\func_get_args());
    }

    public function getTimeout()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getTimeout(...\func_get_args());
    }

    public function hDel($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hDel(...\func_get_args());
    }

    public function hExists($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hExists(...\func_get_args());
    }

    public function hGet($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGet(...\func_get_args());
    }

    public function hGetAll($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hGetAll(...\func_get_args());
    }

    public function hIncrBy($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hIncrBy(...\func_get_args());
    }

    public function hIncrByFloat($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hIncrByFloat(...\func_get_args());
    }

    public function hKeys($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hKeys(...\func_get_args());
    }

    public function hLen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hLen(...\func_get_args());
    }

    public function hMget($key, $keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hMget(...\func_get_args());
    }

    public function hMset($key, $pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hMset(...\func_get_args());
    }

    public function hSet($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSet(...\func_get_args());
    }

    public function hSetNx($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hSetNx(...\func_get_args());
    }

    public function hStrLen($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hStrLen(...\func_get_args());
    }

    public function hVals($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hVals(...\func_get_args());
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($str_key, $i_iterator, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 4));
    }

    public function incr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr(...\func_get_args());
    }

    public function incrBy($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrBy(...\func_get_args());
    }

    public function incrByFloat($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrByFloat(...\func_get_args());
    }

    public function info($option = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info(...\func_get_args());
    }

    public function isConnected()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->isConnected(...\func_get_args());
    }

    public function keys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys(...\func_get_args());
    }

    public function lInsert($key, $position, $pivot, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lInsert(...\func_get_args());
    }

    public function lLen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lLen(...\func_get_args());
    }

    public function lPop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPop(...\func_get_args());
    }

    public function lPush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPush(...\func_get_args());
    }

    public function lPushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lPushx(...\func_get_args());
    }

    public function lSet($key, $index, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lSet(...\func_get_args());
    }

    public function lastSave()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastSave(...\func_get_args());
    }

    public function lindex($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex(...\func_get_args());
    }

    public function lrange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange(...\func_get_args());
    }

    public function lrem($key, $value, $count)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem(...\func_get_args());
    }

    public function ltrim($key, $start, $stop)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim(...\func_get_args());
    }

    public function mget($keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
    }

    public function migrate($host, $port, $key, $db, $timeout, $copy = null, $replace = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->migrate(...\func_get_args());
    }

    public function move($key, $dbindex)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->move(...\func_get_args());
    }

    public function mset($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset(...\func_get_args());
    }

    public function msetnx($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx(...\func_get_args());
    }

    public function multi($mode = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi(...\func_get_args());
    }

    public function object($field, $key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object(...\func_get_args());
    }

    public function pconnect($host, $port = null, $timeout = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect(...\func_get_args());
    }

    public function persist($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist(...\func_get_args());
    }

    public function pexpire($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire(...\func_get_args());
    }

    public function pexpireAt($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireAt(...\func_get_args());
    }

    public function pfadd($key, $elements)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd(...\func_get_args());
    }

    public function pfcount($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfcount(...\func_get_args());
    }

    public function pfmerge($dstkey, $keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge(...\func_get_args());
    }

    public function ping()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping(...\func_get_args());
    }

    public function pipeline()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pipeline(...\func_get_args());
    }

    public function psetex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psubscribe(...\func_get_args());
    }

    public function pttl($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl(...\func_get_args());
    }

    public function publish($channel, $message)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish(...\func_get_args());
    }

    public function pubsub($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe(...\func_get_args());
    }

    public function rPop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPop(...\func_get_args());
    }

    public function rPush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPush(...\func_get_args());
    }

    public function rPushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rPushx(...\func_get_args());
    }

    public function randomKey()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomKey(...\func_get_args());
    }

    public function rawcommand($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename(...\func_get_args());
    }

    public function renameNx($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renameNx(...\func_get_args());
    }

    public function restore($ttl, $key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore(...\func_get_args());
    }

    public function role()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role(...\func_get_args());
    }

    public function rpoplpush($src, $dst)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush(...\func_get_args());
    }

    public function sAdd($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sAdd(...\func_get_args());
    }

    public function sAddArray($key, $options)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sAddArray(...\func_get_args());
    }

    public function sDiff($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sDiff(...\func_get_args());
    }

    public function sDiffStore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sDiffStore(...\func_get_args());
    }

    public function sInter($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sInter(...\func_get_args());
    }

    public function sInterStore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sInterStore(...\func_get_args());
    }

    public function sMembers($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMembers(...\func_get_args());
    }

    public function sMisMember($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMisMember(...\func_get_args());
    }

    public function sMove($src, $dst, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sMove(...\func_get_args());
    }

    public function sPop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sPop(...\func_get_args());
    }

    public function sRandMember($key, $count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRandMember(...\func_get_args());
    }

    public function sUnion($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sUnion(...\func_get_args());
    }

    public function sUnionStore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sUnionStore(...\func_get_args());
    }

    public function save()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save(...\func_get_args());
    }

    public function scan(&$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($i_iterator, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 3));
    }

    public function scard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard(...\func_get_args());
    }

    public function script($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script(...\func_get_args());
    }

    public function select($dbindex)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->select(...\func_get_args());
    }

    public function set($key, $value, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set(...\func_get_args());
    }

    public function setBit($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setBit(...\func_get_args());
    }

    public function setOption($option, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setOption(...\func_get_args());
    }

    public function setRange($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setRange(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex(...\func_get_args());
    }

    public function setnx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx(...\func_get_args());
    }

    public function sismember($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember(...\func_get_args());
    }

    public function slaveof($host = null, $port = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slaveof(...\func_get_args());
    }

    public function slowlog($arg, $option = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog(...\func_get_args());
    }

    public function sort($key, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort(...\func_get_args());
    }

    public function sortAsc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortAsc(...\func_get_args());
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortAscAlpha(...\func_get_args());
    }

    public function sortDesc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortDesc(...\func_get_args());
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sortDescAlpha(...\func_get_args());
    }

    public function srem($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem(...\func_get_args());
    }

    public function sscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($str_key, $i_iterator, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 4));
    }

    public function strlen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen(...\func_get_args());
    }

    public function subscribe($channels, $callback)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->subscribe(...\func_get_args());
    }

    public function swapdb($srcdb, $dstdb)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->swapdb(...\func_get_args());
    }

    public function time()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time(...\func_get_args());
    }

    public function ttl($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl(...\func_get_args());
    }

    public function type($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type(...\func_get_args());
    }

    public function unlink($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink(...\func_get_args());
    }

    public function unsubscribe($channel, ...$other_channels)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe(...\func_get_args());
    }

    public function unwatch()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch(...\func_get_args());
    }

    public function wait($numslaves, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->wait(...\func_get_args());
    }

    public function watch($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch(...\func_get_args());
    }

    public function xack($str_key, $str_group, $arr_ids)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack(...\func_get_args());
    }

    public function xadd($str_key, $str_id, $arr_fields, $i_maxlen = null, $boo_approximate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xadd(...\func_get_args());
    }

    public function xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim(...\func_get_args());
    }

    public function xdel($str_key, $arr_ids)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel(...\func_get_args());
    }

    public function xgroup($str_operation, $str_key = null, $str_arg1 = null, $str_arg2 = null, $str_arg3 = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup(...\func_get_args());
    }

    public function xinfo($str_cmd, $str_key = null, $str_group = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo(...\func_get_args());
    }

    public function xlen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen(...\func_get_args());
    }

    public function xpending($str_key, $str_group, $str_start = null, $str_end = null, $i_count = null, $str_consumer = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending(...\func_get_args());
    }

    public function xrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange(...\func_get_args());
    }

    public function xread($arr_streams, $i_count = null, $i_block = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread(...\func_get_args());
    }

    public function xreadgroup($str_group, $str_consumer, $arr_streams, $i_count = null, $i_block = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup(...\func_get_args());
    }

    public function xrevrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange(...\func_get_args());
    }

    public function xtrim($str_key, $i_maxlen, $boo_approximate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim(...\func_get_args());
    }

    public function zAdd($key, $score, $value, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zAdd(...\func_get_args());
    }

    public function zCard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zCard(...\func_get_args());
    }

    public function zCount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zCount(...\func_get_args());
    }

    public function zIncrBy($key, $value, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zIncrBy(...\func_get_args());
    }

    public function zLexCount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zLexCount(...\func_get_args());
    }

    public function zPopMax($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zPopMax(...\func_get_args());
    }

    public function zPopMin($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zPopMin(...\func_get_args());
    }

    public function zRange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRange(...\func_get_args());
    }

    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRangeByLex(...\func_get_args());
    }

    public function zRangeByScore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRangeByScore(...\func_get_args());
    }

    public function zRank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRank(...\func_get_args());
    }

    public function zRem($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRem(...\func_get_args());
    }

    public function zRemRangeByLex($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByLex(...\func_get_args());
    }

    public function zRemRangeByRank($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByRank(...\func_get_args());
    }

    public function zRemRangeByScore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemRangeByScore(...\func_get_args());
    }

    public function zRevRange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRange(...\func_get_args());
    }

    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRangeByLex(...\func_get_args());
    }

    public function zRevRangeByScore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRangeByScore(...\func_get_args());
    }

    public function zRevRank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRevRank(...\func_get_args());
    }

    public function zScore($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zScore(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore(...\func_get_args());
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($str_key, $i_iterator, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 4));
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore(...\func_get_args());
    }

    public function delete($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->delete(...\func_get_args());
    }

    public function evaluate($script, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evaluate(...\func_get_args());
    }

    public function evaluateSha($script_sha, $args = null, $num_keys = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evaluateSha(...\func_get_args());
    }

    public function getKeys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getKeys(...\func_get_args());
    }

    public function getMultiple($keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMultiple(...\func_get_args());
    }

    public function lGet($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lGet(...\func_get_args());
    }

    public function lGetRange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lGetRange(...\func_get_args());
    }

    public function lRemove($key, $value, $count)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lRemove(...\func_get_args());
    }

    public function lSize($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lSize(...\func_get_args());
    }

    public function listTrim($key, $start, $stop)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->listTrim(...\func_get_args());
    }

    public function open($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->open(...\func_get_args());
    }

    public function popen($host, $port = null, $timeout = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->popen(...\func_get_args());
    }

    public function renameKey($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renameKey(...\func_get_args());
    }

    public function sContains($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sContains(...\func_get_args());
    }

    public function sGetMembers($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sGetMembers(...\func_get_args());
    }

    public function sRemove($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sRemove(...\func_get_args());
    }

    public function sSize($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sSize(...\func_get_args());
    }

    public function sendEcho($msg)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sendEcho(...\func_get_args());
    }

    public function setTimeout($key, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setTimeout(...\func_get_args());
    }

    public function substr($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->substr(...\func_get_args());
    }

    public function zDelete($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zDelete(...\func_get_args());
    }

    public function zDeleteRangeByRank($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zDeleteRangeByRank(...\func_get_args());
    }

    public function zDeleteRangeByScore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zDeleteRangeByScore(...\func_get_args());
    }

    public function zInter($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zInter(...\func_get_args());
    }

    public function zRemove($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemove(...\func_get_args());
    }

    public function zRemoveRangeByScore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zRemoveRangeByScore(...\func_get_args());
    }

    public function zReverseRange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zReverseRange(...\func_get_args());
    }

    public function zSize($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zSize(...\func_get_args());
    }

    public function zUnion($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zUnion(...\func_get_args());
    }
}
