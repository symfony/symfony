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
class Redis5Proxy extends \Redis implements ResetInterface, LazyObjectInterface
{
    use RedisProxyTrait {
        resetLazyObject as reset;
    }

    public function __construct()
    {
        $this->initializeLazyObject()->__construct(...\func_get_args());
    }

    public function _prefix($key)
    {
        return $this->initializeLazyObject()->_prefix(...\func_get_args());
    }

    public function _serialize($value)
    {
        return $this->initializeLazyObject()->_serialize(...\func_get_args());
    }

    public function _unserialize($value)
    {
        return $this->initializeLazyObject()->_unserialize(...\func_get_args());
    }

    public function _pack($value)
    {
        return $this->initializeLazyObject()->_pack(...\func_get_args());
    }

    public function _unpack($value)
    {
        return $this->initializeLazyObject()->_unpack(...\func_get_args());
    }

    public function _compress($value)
    {
        return $this->initializeLazyObject()->_compress(...\func_get_args());
    }

    public function _uncompress($value)
    {
        return $this->initializeLazyObject()->_uncompress(...\func_get_args());
    }

    public function acl($subcmd, ...$args)
    {
        return $this->initializeLazyObject()->acl(...\func_get_args());
    }

    public function append($key, $value)
    {
        return $this->initializeLazyObject()->append(...\func_get_args());
    }

    public function auth(#[\SensitiveParameter] $auth)
    {
        return $this->initializeLazyObject()->auth(...\func_get_args());
    }

    public function bgSave()
    {
        return $this->initializeLazyObject()->bgSave(...\func_get_args());
    }

    public function bgrewriteaof()
    {
        return $this->initializeLazyObject()->bgrewriteaof(...\func_get_args());
    }

    public function bitcount($key)
    {
        return $this->initializeLazyObject()->bitcount(...\func_get_args());
    }

    public function bitop($operation, $ret_key, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null)
    {
        return $this->initializeLazyObject()->bitpos(...\func_get_args());
    }

    public function blPop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->blPop(...\func_get_args());
    }

    public function brPop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->brPop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return $this->initializeLazyObject()->brpoplpush(...\func_get_args());
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->bzPopMax(...\func_get_args());
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->bzPopMin(...\func_get_args());
    }

    public function clearLastError()
    {
        return $this->initializeLazyObject()->clearLastError(...\func_get_args());
    }

    public function client($cmd, ...$args)
    {
        return $this->initializeLazyObject()->client(...\func_get_args());
    }

    public function close()
    {
        return $this->initializeLazyObject()->close(...\func_get_args());
    }

    public function command(...$args)
    {
        return $this->initializeLazyObject()->command(...\func_get_args());
    }

    public function config($cmd, $key, $value = null)
    {
        return $this->initializeLazyObject()->config(...\func_get_args());
    }

    public function connect($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return $this->initializeLazyObject()->connect(...\func_get_args());
    }

    public function dbSize()
    {
        return $this->initializeLazyObject()->dbSize(...\func_get_args());
    }

    public function debug($key)
    {
        return $this->initializeLazyObject()->debug(...\func_get_args());
    }

    public function decr($key)
    {
        return $this->initializeLazyObject()->decr(...\func_get_args());
    }

    public function decrBy($key, $value)
    {
        return $this->initializeLazyObject()->decrBy(...\func_get_args());
    }

    public function del($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->del(...\func_get_args());
    }

    public function discard()
    {
        return $this->initializeLazyObject()->discard(...\func_get_args());
    }

    public function dump($key)
    {
        return $this->initializeLazyObject()->dump(...\func_get_args());
    }

    public function echo($msg)
    {
        return $this->initializeLazyObject()->echo(...\func_get_args());
    }

    public function eval($script, $args = null, $num_keys = null)
    {
        return $this->initializeLazyObject()->eval(...\func_get_args());
    }

    public function evalsha($script_sha, $args = null, $num_keys = null)
    {
        return $this->initializeLazyObject()->evalsha(...\func_get_args());
    }

    public function exec()
    {
        return $this->initializeLazyObject()->exec(...\func_get_args());
    }

    public function exists($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->exists(...\func_get_args());
    }

    public function expire($key, $timeout)
    {
        return $this->initializeLazyObject()->expire(...\func_get_args());
    }

    public function expireAt($key, $timestamp)
    {
        return $this->initializeLazyObject()->expireAt(...\func_get_args());
    }

    public function flushAll($async = null)
    {
        return $this->initializeLazyObject()->flushAll(...\func_get_args());
    }

    public function flushDB($async = null)
    {
        return $this->initializeLazyObject()->flushDB(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples)
    {
        return $this->initializeLazyObject()->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dst, $unit = null)
    {
        return $this->initializeLazyObject()->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->geopos(...\func_get_args());
    }

    public function georadius($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return $this->initializeLazyObject()->georadius(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return $this->initializeLazyObject()->georadius_ro(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $opts = null)
    {
        return $this->initializeLazyObject()->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $opts = null)
    {
        return $this->initializeLazyObject()->georadiusbymember_ro(...\func_get_args());
    }

    public function get($key)
    {
        return $this->initializeLazyObject()->get(...\func_get_args());
    }

    public function getAuth()
    {
        return $this->initializeLazyObject()->getAuth(...\func_get_args());
    }

    public function getBit($key, $offset)
    {
        return $this->initializeLazyObject()->getBit(...\func_get_args());
    }

    public function getDBNum()
    {
        return $this->initializeLazyObject()->getDBNum(...\func_get_args());
    }

    public function getHost()
    {
        return $this->initializeLazyObject()->getHost(...\func_get_args());
    }

    public function getLastError()
    {
        return $this->initializeLazyObject()->getLastError(...\func_get_args());
    }

    public function getMode()
    {
        return $this->initializeLazyObject()->getMode(...\func_get_args());
    }

    public function getOption($option)
    {
        return $this->initializeLazyObject()->getOption(...\func_get_args());
    }

    public function getPersistentID()
    {
        return $this->initializeLazyObject()->getPersistentID(...\func_get_args());
    }

    public function getPort()
    {
        return $this->initializeLazyObject()->getPort(...\func_get_args());
    }

    public function getRange($key, $start, $end)
    {
        return $this->initializeLazyObject()->getRange(...\func_get_args());
    }

    public function getReadTimeout()
    {
        return $this->initializeLazyObject()->getReadTimeout(...\func_get_args());
    }

    public function getSet($key, $value)
    {
        return $this->initializeLazyObject()->getSet(...\func_get_args());
    }

    public function getTimeout()
    {
        return $this->initializeLazyObject()->getTimeout(...\func_get_args());
    }

    public function hDel($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->hDel(...\func_get_args());
    }

    public function hExists($key, $member)
    {
        return $this->initializeLazyObject()->hExists(...\func_get_args());
    }

    public function hGet($key, $member)
    {
        return $this->initializeLazyObject()->hGet(...\func_get_args());
    }

    public function hGetAll($key)
    {
        return $this->initializeLazyObject()->hGetAll(...\func_get_args());
    }

    public function hIncrBy($key, $member, $value)
    {
        return $this->initializeLazyObject()->hIncrBy(...\func_get_args());
    }

    public function hIncrByFloat($key, $member, $value)
    {
        return $this->initializeLazyObject()->hIncrByFloat(...\func_get_args());
    }

    public function hKeys($key)
    {
        return $this->initializeLazyObject()->hKeys(...\func_get_args());
    }

    public function hLen($key)
    {
        return $this->initializeLazyObject()->hLen(...\func_get_args());
    }

    public function hMget($key, $keys)
    {
        return $this->initializeLazyObject()->hMget(...\func_get_args());
    }

    public function hMset($key, $pairs)
    {
        return $this->initializeLazyObject()->hMset(...\func_get_args());
    }

    public function hSet($key, $member, $value)
    {
        return $this->initializeLazyObject()->hSet(...\func_get_args());
    }

    public function hSetNx($key, $member, $value)
    {
        return $this->initializeLazyObject()->hSetNx(...\func_get_args());
    }

    public function hStrLen($key, $member)
    {
        return $this->initializeLazyObject()->hStrLen(...\func_get_args());
    }

    public function hVals($key)
    {
        return $this->initializeLazyObject()->hVals(...\func_get_args());
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->hscan($str_key, $i_iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function incr($key)
    {
        return $this->initializeLazyObject()->incr(...\func_get_args());
    }

    public function incrBy($key, $value)
    {
        return $this->initializeLazyObject()->incrBy(...\func_get_args());
    }

    public function incrByFloat($key, $value)
    {
        return $this->initializeLazyObject()->incrByFloat(...\func_get_args());
    }

    public function info($option = null)
    {
        return $this->initializeLazyObject()->info(...\func_get_args());
    }

    public function isConnected()
    {
        return $this->initializeLazyObject()->isConnected(...\func_get_args());
    }

    public function keys($pattern)
    {
        return $this->initializeLazyObject()->keys(...\func_get_args());
    }

    public function lInsert($key, $position, $pivot, $value)
    {
        return $this->initializeLazyObject()->lInsert(...\func_get_args());
    }

    public function lLen($key)
    {
        return $this->initializeLazyObject()->lLen(...\func_get_args());
    }

    public function lPop($key)
    {
        return $this->initializeLazyObject()->lPop(...\func_get_args());
    }

    public function lPush($key, $value)
    {
        return $this->initializeLazyObject()->lPush(...\func_get_args());
    }

    public function lPushx($key, $value)
    {
        return $this->initializeLazyObject()->lPushx(...\func_get_args());
    }

    public function lSet($key, $index, $value)
    {
        return $this->initializeLazyObject()->lSet(...\func_get_args());
    }

    public function lastSave()
    {
        return $this->initializeLazyObject()->lastSave(...\func_get_args());
    }

    public function lindex($key, $index)
    {
        return $this->initializeLazyObject()->lindex(...\func_get_args());
    }

    public function lrange($key, $start, $end)
    {
        return $this->initializeLazyObject()->lrange(...\func_get_args());
    }

    public function lrem($key, $value, $count)
    {
        return $this->initializeLazyObject()->lrem(...\func_get_args());
    }

    public function ltrim($key, $start, $stop)
    {
        return $this->initializeLazyObject()->ltrim(...\func_get_args());
    }

    public function mget($keys)
    {
        return $this->initializeLazyObject()->mget(...\func_get_args());
    }

    public function migrate($host, $port, $key, $db, $timeout, $copy = null, $replace = null)
    {
        return $this->initializeLazyObject()->migrate(...\func_get_args());
    }

    public function move($key, $dbindex)
    {
        return $this->initializeLazyObject()->move(...\func_get_args());
    }

    public function mset($pairs)
    {
        return $this->initializeLazyObject()->mset(...\func_get_args());
    }

    public function msetnx($pairs)
    {
        return $this->initializeLazyObject()->msetnx(...\func_get_args());
    }

    public function multi($mode = null)
    {
        return $this->initializeLazyObject()->multi(...\func_get_args());
    }

    public function object($field, $key)
    {
        return $this->initializeLazyObject()->object(...\func_get_args());
    }

    public function pconnect($host, $port = null, $timeout = null)
    {
        return $this->initializeLazyObject()->pconnect(...\func_get_args());
    }

    public function persist($key)
    {
        return $this->initializeLazyObject()->persist(...\func_get_args());
    }

    public function pexpire($key, $timestamp)
    {
        return $this->initializeLazyObject()->pexpire(...\func_get_args());
    }

    public function pexpireAt($key, $timestamp)
    {
        return $this->initializeLazyObject()->pexpireAt(...\func_get_args());
    }

    public function pfadd($key, $elements)
    {
        return $this->initializeLazyObject()->pfadd(...\func_get_args());
    }

    public function pfcount($key)
    {
        return $this->initializeLazyObject()->pfcount(...\func_get_args());
    }

    public function pfmerge($dstkey, $keys)
    {
        return $this->initializeLazyObject()->pfmerge(...\func_get_args());
    }

    public function ping()
    {
        return $this->initializeLazyObject()->ping(...\func_get_args());
    }

    public function pipeline()
    {
        return $this->initializeLazyObject()->pipeline(...\func_get_args());
    }

    public function psetex($key, $expire, $value)
    {
        return $this->initializeLazyObject()->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback)
    {
        return $this->initializeLazyObject()->psubscribe(...\func_get_args());
    }

    public function pttl($key)
    {
        return $this->initializeLazyObject()->pttl(...\func_get_args());
    }

    public function publish($channel, $message)
    {
        return $this->initializeLazyObject()->publish(...\func_get_args());
    }

    public function pubsub($cmd, ...$args)
    {
        return $this->initializeLazyObject()->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return $this->initializeLazyObject()->punsubscribe(...\func_get_args());
    }

    public function rPop($key)
    {
        return $this->initializeLazyObject()->rPop(...\func_get_args());
    }

    public function rPush($key, $value)
    {
        return $this->initializeLazyObject()->rPush(...\func_get_args());
    }

    public function rPushx($key, $value)
    {
        return $this->initializeLazyObject()->rPushx(...\func_get_args());
    }

    public function randomKey()
    {
        return $this->initializeLazyObject()->randomKey(...\func_get_args());
    }

    public function rawcommand($cmd, ...$args)
    {
        return $this->initializeLazyObject()->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey)
    {
        return $this->initializeLazyObject()->rename(...\func_get_args());
    }

    public function renameNx($key, $newkey)
    {
        return $this->initializeLazyObject()->renameNx(...\func_get_args());
    }

    public function restore($ttl, $key, $value)
    {
        return $this->initializeLazyObject()->restore(...\func_get_args());
    }

    public function role()
    {
        return $this->initializeLazyObject()->role(...\func_get_args());
    }

    public function rpoplpush($src, $dst)
    {
        return $this->initializeLazyObject()->rpoplpush(...\func_get_args());
    }

    public function sAdd($key, $value)
    {
        return $this->initializeLazyObject()->sAdd(...\func_get_args());
    }

    public function sAddArray($key, $options)
    {
        return $this->initializeLazyObject()->sAddArray(...\func_get_args());
    }

    public function sDiff($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sDiff(...\func_get_args());
    }

    public function sDiffStore($dst, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sDiffStore(...\func_get_args());
    }

    public function sInter($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sInter(...\func_get_args());
    }

    public function sInterStore($dst, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sInterStore(...\func_get_args());
    }

    public function sMembers($key)
    {
        return $this->initializeLazyObject()->sMembers(...\func_get_args());
    }

    public function sMisMember($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->sMisMember(...\func_get_args());
    }

    public function sMove($src, $dst, $value)
    {
        return $this->initializeLazyObject()->sMove(...\func_get_args());
    }

    public function sPop($key)
    {
        return $this->initializeLazyObject()->sPop(...\func_get_args());
    }

    public function sRandMember($key, $count = null)
    {
        return $this->initializeLazyObject()->sRandMember(...\func_get_args());
    }

    public function sUnion($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sUnion(...\func_get_args());
    }

    public function sUnionStore($dst, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sUnionStore(...\func_get_args());
    }

    public function save()
    {
        return $this->initializeLazyObject()->save(...\func_get_args());
    }

    public function scan(&$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->scan($i_iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function scard($key)
    {
        return $this->initializeLazyObject()->scard(...\func_get_args());
    }

    public function script($cmd, ...$args)
    {
        return $this->initializeLazyObject()->script(...\func_get_args());
    }

    public function select($dbindex)
    {
        return $this->initializeLazyObject()->select(...\func_get_args());
    }

    public function set($key, $value, $opts = null)
    {
        return $this->initializeLazyObject()->set(...\func_get_args());
    }

    public function setBit($key, $offset, $value)
    {
        return $this->initializeLazyObject()->setBit(...\func_get_args());
    }

    public function setOption($option, $value)
    {
        return $this->initializeLazyObject()->setOption(...\func_get_args());
    }

    public function setRange($key, $offset, $value)
    {
        return $this->initializeLazyObject()->setRange(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return $this->initializeLazyObject()->setex(...\func_get_args());
    }

    public function setnx($key, $value)
    {
        return $this->initializeLazyObject()->setnx(...\func_get_args());
    }

    public function sismember($key, $value)
    {
        return $this->initializeLazyObject()->sismember(...\func_get_args());
    }

    public function slaveof($host = null, $port = null)
    {
        return $this->initializeLazyObject()->slaveof(...\func_get_args());
    }

    public function slowlog($arg, $option = null)
    {
        return $this->initializeLazyObject()->slowlog(...\func_get_args());
    }

    public function sort($key, $options = null)
    {
        return $this->initializeLazyObject()->sort(...\func_get_args());
    }

    public function sortAsc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->initializeLazyObject()->sortAsc(...\func_get_args());
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->initializeLazyObject()->sortAscAlpha(...\func_get_args());
    }

    public function sortDesc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->initializeLazyObject()->sortDesc(...\func_get_args());
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->initializeLazyObject()->sortDescAlpha(...\func_get_args());
    }

    public function srem($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->srem(...\func_get_args());
    }

    public function sscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->sscan($str_key, $i_iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function strlen($key)
    {
        return $this->initializeLazyObject()->strlen(...\func_get_args());
    }

    public function subscribe($channels, $callback)
    {
        return $this->initializeLazyObject()->subscribe(...\func_get_args());
    }

    public function swapdb($srcdb, $dstdb)
    {
        return $this->initializeLazyObject()->swapdb(...\func_get_args());
    }

    public function time()
    {
        return $this->initializeLazyObject()->time(...\func_get_args());
    }

    public function ttl($key)
    {
        return $this->initializeLazyObject()->ttl(...\func_get_args());
    }

    public function type($key)
    {
        return $this->initializeLazyObject()->type(...\func_get_args());
    }

    public function unlink($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->unlink(...\func_get_args());
    }

    public function unsubscribe($channel, ...$other_channels)
    {
        return $this->initializeLazyObject()->unsubscribe(...\func_get_args());
    }

    public function unwatch()
    {
        return $this->initializeLazyObject()->unwatch(...\func_get_args());
    }

    public function wait($numslaves, $timeout)
    {
        return $this->initializeLazyObject()->wait(...\func_get_args());
    }

    public function watch($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->watch(...\func_get_args());
    }

    public function xack($str_key, $str_group, $arr_ids)
    {
        return $this->initializeLazyObject()->xack(...\func_get_args());
    }

    public function xadd($str_key, $str_id, $arr_fields, $i_maxlen = null, $boo_approximate = null)
    {
        return $this->initializeLazyObject()->xadd(...\func_get_args());
    }

    public function xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts = null)
    {
        return $this->initializeLazyObject()->xclaim(...\func_get_args());
    }

    public function xdel($str_key, $arr_ids)
    {
        return $this->initializeLazyObject()->xdel(...\func_get_args());
    }

    public function xgroup($str_operation, $str_key = null, $str_arg1 = null, $str_arg2 = null, $str_arg3 = null)
    {
        return $this->initializeLazyObject()->xgroup(...\func_get_args());
    }

    public function xinfo($str_cmd, $str_key = null, $str_group = null)
    {
        return $this->initializeLazyObject()->xinfo(...\func_get_args());
    }

    public function xlen($key)
    {
        return $this->initializeLazyObject()->xlen(...\func_get_args());
    }

    public function xpending($str_key, $str_group, $str_start = null, $str_end = null, $i_count = null, $str_consumer = null)
    {
        return $this->initializeLazyObject()->xpending(...\func_get_args());
    }

    public function xrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return $this->initializeLazyObject()->xrange(...\func_get_args());
    }

    public function xread($arr_streams, $i_count = null, $i_block = null)
    {
        return $this->initializeLazyObject()->xread(...\func_get_args());
    }

    public function xreadgroup($str_group, $str_consumer, $arr_streams, $i_count = null, $i_block = null)
    {
        return $this->initializeLazyObject()->xreadgroup(...\func_get_args());
    }

    public function xrevrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return $this->initializeLazyObject()->xrevrange(...\func_get_args());
    }

    public function xtrim($str_key, $i_maxlen, $boo_approximate = null)
    {
        return $this->initializeLazyObject()->xtrim(...\func_get_args());
    }

    public function zAdd($key, $score, $value, ...$extra_args)
    {
        return $this->initializeLazyObject()->zAdd(...\func_get_args());
    }

    public function zCard($key)
    {
        return $this->initializeLazyObject()->zCard(...\func_get_args());
    }

    public function zCount($key, $min, $max)
    {
        return $this->initializeLazyObject()->zCount(...\func_get_args());
    }

    public function zIncrBy($key, $value, $member)
    {
        return $this->initializeLazyObject()->zIncrBy(...\func_get_args());
    }

    public function zLexCount($key, $min, $max)
    {
        return $this->initializeLazyObject()->zLexCount(...\func_get_args());
    }

    public function zPopMax($key)
    {
        return $this->initializeLazyObject()->zPopMax(...\func_get_args());
    }

    public function zPopMin($key)
    {
        return $this->initializeLazyObject()->zPopMin(...\func_get_args());
    }

    public function zRange($key, $start, $end, $scores = null)
    {
        return $this->initializeLazyObject()->zRange(...\func_get_args());
    }

    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->initializeLazyObject()->zRangeByLex(...\func_get_args());
    }

    public function zRangeByScore($key, $start, $end, $options = null)
    {
        return $this->initializeLazyObject()->zRangeByScore(...\func_get_args());
    }

    public function zRank($key, $member)
    {
        return $this->initializeLazyObject()->zRank(...\func_get_args());
    }

    public function zRem($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->zRem(...\func_get_args());
    }

    public function zRemRangeByLex($key, $min, $max)
    {
        return $this->initializeLazyObject()->zRemRangeByLex(...\func_get_args());
    }

    public function zRemRangeByRank($key, $start, $end)
    {
        return $this->initializeLazyObject()->zRemRangeByRank(...\func_get_args());
    }

    public function zRemRangeByScore($key, $min, $max)
    {
        return $this->initializeLazyObject()->zRemRangeByScore(...\func_get_args());
    }

    public function zRevRange($key, $start, $end, $scores = null)
    {
        return $this->initializeLazyObject()->zRevRange(...\func_get_args());
    }

    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->initializeLazyObject()->zRevRangeByLex(...\func_get_args());
    }

    public function zRevRangeByScore($key, $start, $end, $options = null)
    {
        return $this->initializeLazyObject()->zRevRangeByScore(...\func_get_args());
    }

    public function zRevRank($key, $member)
    {
        return $this->initializeLazyObject()->zRevRank(...\func_get_args());
    }

    public function zScore($key, $member)
    {
        return $this->initializeLazyObject()->zScore(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->initializeLazyObject()->zinterstore(...\func_get_args());
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->zscan($str_key, $i_iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->initializeLazyObject()->zunionstore(...\func_get_args());
    }

    public function delete($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->delete(...\func_get_args());
    }

    public function evaluate($script, $args = null, $num_keys = null)
    {
        return $this->initializeLazyObject()->evaluate(...\func_get_args());
    }

    public function evaluateSha($script_sha, $args = null, $num_keys = null)
    {
        return $this->initializeLazyObject()->evaluateSha(...\func_get_args());
    }

    public function getKeys($pattern)
    {
        return $this->initializeLazyObject()->getKeys(...\func_get_args());
    }

    public function getMultiple($keys)
    {
        return $this->initializeLazyObject()->getMultiple(...\func_get_args());
    }

    public function lGet($key, $index)
    {
        return $this->initializeLazyObject()->lGet(...\func_get_args());
    }

    public function lGetRange($key, $start, $end)
    {
        return $this->initializeLazyObject()->lGetRange(...\func_get_args());
    }

    public function lRemove($key, $value, $count)
    {
        return $this->initializeLazyObject()->lRemove(...\func_get_args());
    }

    public function lSize($key)
    {
        return $this->initializeLazyObject()->lSize(...\func_get_args());
    }

    public function listTrim($key, $start, $stop)
    {
        return $this->initializeLazyObject()->listTrim(...\func_get_args());
    }

    public function open($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return $this->initializeLazyObject()->open(...\func_get_args());
    }

    public function popen($host, $port = null, $timeout = null)
    {
        return $this->initializeLazyObject()->popen(...\func_get_args());
    }

    public function renameKey($key, $newkey)
    {
        return $this->initializeLazyObject()->renameKey(...\func_get_args());
    }

    public function sContains($key, $value)
    {
        return $this->initializeLazyObject()->sContains(...\func_get_args());
    }

    public function sGetMembers($key)
    {
        return $this->initializeLazyObject()->sGetMembers(...\func_get_args());
    }

    public function sRemove($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->sRemove(...\func_get_args());
    }

    public function sSize($key)
    {
        return $this->initializeLazyObject()->sSize(...\func_get_args());
    }

    public function sendEcho($msg)
    {
        return $this->initializeLazyObject()->sendEcho(...\func_get_args());
    }

    public function setTimeout($key, $timeout)
    {
        return $this->initializeLazyObject()->setTimeout(...\func_get_args());
    }

    public function substr($key, $start, $end)
    {
        return $this->initializeLazyObject()->substr(...\func_get_args());
    }

    public function zDelete($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->zDelete(...\func_get_args());
    }

    public function zDeleteRangeByRank($key, $min, $max)
    {
        return $this->initializeLazyObject()->zDeleteRangeByRank(...\func_get_args());
    }

    public function zDeleteRangeByScore($key, $min, $max)
    {
        return $this->initializeLazyObject()->zDeleteRangeByScore(...\func_get_args());
    }

    public function zInter($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->initializeLazyObject()->zInter(...\func_get_args());
    }

    public function zRemove($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->zRemove(...\func_get_args());
    }

    public function zRemoveRangeByScore($key, $min, $max)
    {
        return $this->initializeLazyObject()->zRemoveRangeByScore(...\func_get_args());
    }

    public function zReverseRange($key, $start, $end, $scores = null)
    {
        return $this->initializeLazyObject()->zReverseRange(...\func_get_args());
    }

    public function zSize($key)
    {
        return $this->initializeLazyObject()->zSize(...\func_get_args());
    }

    public function zUnion($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->initializeLazyObject()->zUnion(...\func_get_args());
    }
}
