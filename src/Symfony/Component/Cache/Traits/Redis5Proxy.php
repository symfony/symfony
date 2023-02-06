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

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
        "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
    ];

    public function __construct()
    {
        return $this->lazyObjectReal->__construct();
    }

    public function _prefix($key)
    {
        return $this->lazyObjectReal->_prefix($key);
    }

    public function _serialize($value)
    {
        return $this->lazyObjectReal->_serialize($value);
    }

    public function _unserialize($value)
    {
        return $this->lazyObjectReal->_unserialize($value);
    }

    public function _pack($value)
    {
        return $this->lazyObjectReal->_pack($value);
    }

    public function _unpack($value)
    {
        return $this->lazyObjectReal->_unpack($value);
    }

    public function _compress($value)
    {
        return $this->lazyObjectReal->_compress($value);
    }

    public function _uncompress($value)
    {
        return $this->lazyObjectReal->_uncompress($value);
    }

    public function acl($subcmd, ...$args)
    {
        return $this->lazyObjectReal->acl($subcmd, ...$args);
    }

    public function append($key, $value)
    {
        return $this->lazyObjectReal->append($key, $value);
    }

    public function auth($auth)
    {
        return $this->lazyObjectReal->auth($auth);
    }

    public function bgSave()
    {
        return $this->lazyObjectReal->bgSave();
    }

    public function bgrewriteaof()
    {
        return $this->lazyObjectReal->bgrewriteaof();
    }

    public function bitcount($key)
    {
        return $this->lazyObjectReal->bitcount($key);
    }

    public function bitop($operation, $ret_key, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->bitop($operation, $ret_key, $key, ...$other_keys);
    }

    public function bitpos($key, $bit, $start = null, $end = null)
    {
        return $this->lazyObjectReal->bitpos($key, $bit, $start, $end);
    }

    public function blPop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->blPop($key, $timeout_or_key, ...$extra_args);
    }

    public function brPop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->brPop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return $this->lazyObjectReal->brpoplpush($src, $dst, $timeout);
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzPopMax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzPopMin($key, $timeout_or_key, ...$extra_args);
    }

    public function clearLastError()
    {
        return $this->lazyObjectReal->clearLastError();
    }

    public function client($cmd, ...$args)
    {
        return $this->lazyObjectReal->client($cmd, ...$args);
    }

    public function close()
    {
        return $this->lazyObjectReal->close();
    }

    public function command(...$args)
    {
        return $this->lazyObjectReal->command(...$args);
    }

    public function config($cmd, $key, $value = null)
    {
        return $this->lazyObjectReal->config($cmd, $key, $value);
    }

    public function connect($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return $this->lazyObjectReal->connect($host, $port, $timeout, $retry_interval);
    }

    public function dbSize()
    {
        return $this->lazyObjectReal->dbSize();
    }

    public function debug($key)
    {
        return $this->lazyObjectReal->debug($key);
    }

    public function decr($key)
    {
        return $this->lazyObjectReal->decr($key);
    }

    public function decrBy($key, $value)
    {
        return $this->lazyObjectReal->decrBy($key, $value);
    }

    public function del($key, ...$other_keys)
    {
        return $this->lazyObjectReal->del($key, ...$other_keys);
    }

    public function discard()
    {
        return $this->lazyObjectReal->discard();
    }

    public function dump($key)
    {
        return $this->lazyObjectReal->dump($key);
    }

    public function echo($msg)
    {
        return $this->lazyObjectReal->echo($msg);
    }

    public function eval($script, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->eval($script, $args, $num_keys);
    }

    public function evalsha($script_sha, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->evalsha($script_sha, $args, $num_keys);
    }

    public function exec()
    {
        return $this->lazyObjectReal->exec();
    }

    public function exists($key, ...$other_keys)
    {
        return $this->lazyObjectReal->exists($key, ...$other_keys);
    }

    public function expire($key, $timeout)
    {
        return $this->lazyObjectReal->expire($key, $timeout);
    }

    public function expireAt($key, $timestamp)
    {
        return $this->lazyObjectReal->expireAt($key, $timestamp);
    }

    public function flushAll($async = null)
    {
        return $this->lazyObjectReal->flushAll($async);
    }

    public function flushDB($async = null)
    {
        return $this->lazyObjectReal->flushDB($async);
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples)
    {
        return $this->lazyObjectReal->geoadd($key, $lng, $lat, $member, ...$other_triples);
    }

    public function geodist($key, $src, $dst, $unit = null)
    {
        return $this->lazyObjectReal->geodist($key, $src, $dst, $unit);
    }

    public function geohash($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->geohash($key, $member, ...$other_members);
    }

    public function geopos($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->geopos($key, $member, ...$other_members);
    }

    public function georadius($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadius($key, $lng, $lan, $radius, $unit, $opts);
    }

    public function georadius_ro($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadius_ro($key, $lng, $lan, $radius, $unit, $opts);
    }

    public function georadiusbymember($key, $member, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadiusbymember($key, $member, $radius, $unit, $opts);
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadiusbymember_ro($key, $member, $radius, $unit, $opts);
    }

    public function get($key)
    {
        return $this->lazyObjectReal->get($key);
    }

    public function getAuth()
    {
        return $this->lazyObjectReal->getAuth();
    }

    public function getBit($key, $offset)
    {
        return $this->lazyObjectReal->getBit($key, $offset);
    }

    public function getDBNum()
    {
        return $this->lazyObjectReal->getDBNum();
    }

    public function getHost()
    {
        return $this->lazyObjectReal->getHost();
    }

    public function getLastError()
    {
        return $this->lazyObjectReal->getLastError();
    }

    public function getMode()
    {
        return $this->lazyObjectReal->getMode();
    }

    public function getOption($option)
    {
        return $this->lazyObjectReal->getOption($option);
    }

    public function getPersistentID()
    {
        return $this->lazyObjectReal->getPersistentID();
    }

    public function getPort()
    {
        return $this->lazyObjectReal->getPort();
    }

    public function getRange($key, $start, $end)
    {
        return $this->lazyObjectReal->getRange($key, $start, $end);
    }

    public function getReadTimeout()
    {
        return $this->lazyObjectReal->getReadTimeout();
    }

    public function getSet($key, $value)
    {
        return $this->lazyObjectReal->getSet($key, $value);
    }

    public function getTimeout()
    {
        return $this->lazyObjectReal->getTimeout();
    }

    public function hDel($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->hDel($key, $member, ...$other_members);
    }

    public function hExists($key, $member)
    {
        return $this->lazyObjectReal->hExists($key, $member);
    }

    public function hGet($key, $member)
    {
        return $this->lazyObjectReal->hGet($key, $member);
    }

    public function hGetAll($key)
    {
        return $this->lazyObjectReal->hGetAll($key);
    }

    public function hIncrBy($key, $member, $value)
    {
        return $this->lazyObjectReal->hIncrBy($key, $member, $value);
    }

    public function hIncrByFloat($key, $member, $value)
    {
        return $this->lazyObjectReal->hIncrByFloat($key, $member, $value);
    }

    public function hKeys($key)
    {
        return $this->lazyObjectReal->hKeys($key);
    }

    public function hLen($key)
    {
        return $this->lazyObjectReal->hLen($key);
    }

    public function hMget($key, $keys)
    {
        return $this->lazyObjectReal->hMget($key, $keys);
    }

    public function hMset($key, $pairs)
    {
        return $this->lazyObjectReal->hMset($key, $pairs);
    }

    public function hSet($key, $member, $value)
    {
        return $this->lazyObjectReal->hSet($key, $member, $value);
    }

    public function hSetNx($key, $member, $value)
    {
        return $this->lazyObjectReal->hSetNx($key, $member, $value);
    }

    public function hStrLen($key, $member)
    {
        return $this->lazyObjectReal->hStrLen($key, $member);
    }

    public function hVals($key)
    {
        return $this->lazyObjectReal->hVals($key);
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->hscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function incr($key)
    {
        return $this->lazyObjectReal->incr($key);
    }

    public function incrBy($key, $value)
    {
        return $this->lazyObjectReal->incrBy($key, $value);
    }

    public function incrByFloat($key, $value)
    {
        return $this->lazyObjectReal->incrByFloat($key, $value);
    }

    public function info($option = null)
    {
        return $this->lazyObjectReal->info($option);
    }

    public function isConnected()
    {
        return $this->lazyObjectReal->isConnected();
    }

    public function keys($pattern)
    {
        return $this->lazyObjectReal->keys($pattern);
    }

    public function lInsert($key, $position, $pivot, $value)
    {
        return $this->lazyObjectReal->lInsert($key, $position, $pivot, $value);
    }

    public function lLen($key)
    {
        return $this->lazyObjectReal->lLen($key);
    }

    public function lPop($key)
    {
        return $this->lazyObjectReal->lPop($key);
    }

    public function lPush($key, $value)
    {
        return $this->lazyObjectReal->lPush($key, $value);
    }

    public function lPushx($key, $value)
    {
        return $this->lazyObjectReal->lPushx($key, $value);
    }

    public function lSet($key, $index, $value)
    {
        return $this->lazyObjectReal->lSet($key, $index, $value);
    }

    public function lastSave()
    {
        return $this->lazyObjectReal->lastSave();
    }

    public function lindex($key, $index)
    {
        return $this->lazyObjectReal->lindex($key, $index);
    }

    public function lrange($key, $start, $end)
    {
        return $this->lazyObjectReal->lrange($key, $start, $end);
    }

    public function lrem($key, $value, $count)
    {
        return $this->lazyObjectReal->lrem($key, $value, $count);
    }

    public function ltrim($key, $start, $stop)
    {
        return $this->lazyObjectReal->ltrim($key, $start, $stop);
    }

    public function mget($keys)
    {
        return $this->lazyObjectReal->mget($keys);
    }

    public function migrate($host, $port, $key, $db, $timeout, $copy = null, $replace = null)
    {
        return $this->lazyObjectReal->migrate($host, $port, $key, $db, $timeout, $copy, $replace);
    }

    public function move($key, $dbindex)
    {
        return $this->lazyObjectReal->move($key, $dbindex);
    }

    public function mset($pairs)
    {
        return $this->lazyObjectReal->mset($pairs);
    }

    public function msetnx($pairs)
    {
        return $this->lazyObjectReal->msetnx($pairs);
    }

    public function multi($mode = null)
    {
        return $this->lazyObjectReal->multi($mode);
    }

    public function object($field, $key)
    {
        return $this->lazyObjectReal->object($field, $key);
    }

    public function pconnect($host, $port = null, $timeout = null)
    {
        return $this->lazyObjectReal->pconnect($host, $port, $timeout);
    }

    public function persist($key)
    {
        return $this->lazyObjectReal->persist($key);
    }

    public function pexpire($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpire($key, $timestamp);
    }

    public function pexpireAt($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpireAt($key, $timestamp);
    }

    public function pfadd($key, $elements)
    {
        return $this->lazyObjectReal->pfadd($key, $elements);
    }

    public function pfcount($key)
    {
        return $this->lazyObjectReal->pfcount($key);
    }

    public function pfmerge($dstkey, $keys)
    {
        return $this->lazyObjectReal->pfmerge($dstkey, $keys);
    }

    public function ping()
    {
        return $this->lazyObjectReal->ping();
    }

    public function pipeline()
    {
        return $this->lazyObjectReal->pipeline();
    }

    public function psetex($key, $expire, $value)
    {
        return $this->lazyObjectReal->psetex($key, $expire, $value);
    }

    public function psubscribe($patterns, $callback)
    {
        return $this->lazyObjectReal->psubscribe($patterns, $callback);
    }

    public function pttl($key)
    {
        return $this->lazyObjectReal->pttl($key);
    }

    public function publish($channel, $message)
    {
        return $this->lazyObjectReal->publish($channel, $message);
    }

    public function pubsub($cmd, ...$args)
    {
        return $this->lazyObjectReal->pubsub($cmd, ...$args);
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return $this->lazyObjectReal->punsubscribe($pattern, ...$other_patterns);
    }

    public function rPop($key)
    {
        return $this->lazyObjectReal->rPop($key);
    }

    public function rPush($key, $value)
    {
        return $this->lazyObjectReal->rPush($key, $value);
    }

    public function rPushx($key, $value)
    {
        return $this->lazyObjectReal->rPushx($key, $value);
    }

    public function randomKey()
    {
        return $this->lazyObjectReal->randomKey();
    }

    public function rawcommand($cmd, ...$args)
    {
        return $this->lazyObjectReal->rawcommand($cmd, ...$args);
    }

    public function rename($key, $newkey)
    {
        return $this->lazyObjectReal->rename($key, $newkey);
    }

    public function renameNx($key, $newkey)
    {
        return $this->lazyObjectReal->renameNx($key, $newkey);
    }

    public function restore($ttl, $key, $value)
    {
        return $this->lazyObjectReal->restore($ttl, $key, $value);
    }

    public function role()
    {
        return $this->lazyObjectReal->role();
    }

    public function rpoplpush($src, $dst)
    {
        return $this->lazyObjectReal->rpoplpush($src, $dst);
    }

    public function sAdd($key, $value)
    {
        return $this->lazyObjectReal->sAdd($key, $value);
    }

    public function sAddArray($key, $options)
    {
        return $this->lazyObjectReal->sAddArray($key, $options);
    }

    public function sDiff($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sDiff($key, ...$other_keys);
    }

    public function sDiffStore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sDiffStore($dst, $key, ...$other_keys);
    }

    public function sInter($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sInter($key, ...$other_keys);
    }

    public function sInterStore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sInterStore($dst, $key, ...$other_keys);
    }

    public function sMembers($key)
    {
        return $this->lazyObjectReal->sMembers($key);
    }

    public function sMisMember($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->sMisMember($key, $member, ...$other_members);
    }

    public function sMove($src, $dst, $value)
    {
        return $this->lazyObjectReal->sMove($src, $dst, $value);
    }

    public function sPop($key)
    {
        return $this->lazyObjectReal->sPop($key);
    }

    public function sRandMember($key, $count = null)
    {
        return $this->lazyObjectReal->sRandMember($key, $count);
    }

    public function sUnion($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sUnion($key, ...$other_keys);
    }

    public function sUnionStore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sUnionStore($dst, $key, ...$other_keys);
    }

    public function save()
    {
        return $this->lazyObjectReal->save();
    }

    public function scan(&$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->scan($i_iterator, $str_pattern, $i_count);
    }

    public function scard($key)
    {
        return $this->lazyObjectReal->scard($key);
    }

    public function script($cmd, ...$args)
    {
        return $this->lazyObjectReal->script($cmd, ...$args);
    }

    public function select($dbindex)
    {
        return $this->lazyObjectReal->select($dbindex);
    }

    public function set($key, $value, $opts = null)
    {
        return $this->lazyObjectReal->set($key, $value, $opts);
    }

    public function setBit($key, $offset, $value)
    {
        return $this->lazyObjectReal->setBit($key, $offset, $value);
    }

    public function setOption($option, $value)
    {
        return $this->lazyObjectReal->setOption($option, $value);
    }

    public function setRange($key, $offset, $value)
    {
        return $this->lazyObjectReal->setRange($key, $offset, $value);
    }

    public function setex($key, $expire, $value)
    {
        return $this->lazyObjectReal->setex($key, $expire, $value);
    }

    public function setnx($key, $value)
    {
        return $this->lazyObjectReal->setnx($key, $value);
    }

    public function sismember($key, $value)
    {
        return $this->lazyObjectReal->sismember($key, $value);
    }

    public function slaveof($host = null, $port = null)
    {
        return $this->lazyObjectReal->slaveof($host, $port);
    }

    public function slowlog($arg, $option = null)
    {
        return $this->lazyObjectReal->slowlog($arg, $option);
    }

    public function sort($key, $options = null)
    {
        return $this->lazyObjectReal->sort($key, $options);
    }

    public function sortAsc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortAsc($key, $pattern, $get, $start, $end, $getList);
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortAscAlpha($key, $pattern, $get, $start, $end, $getList);
    }

    public function sortDesc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortDesc($key, $pattern, $get, $start, $end, $getList);
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortDescAlpha($key, $pattern, $get, $start, $end, $getList);
    }

    public function srem($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->srem($key, $member, ...$other_members);
    }

    public function sscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->sscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function strlen($key)
    {
        return $this->lazyObjectReal->strlen($key);
    }

    public function subscribe($channels, $callback)
    {
        return $this->lazyObjectReal->subscribe($channels, $callback);
    }

    public function swapdb($srcdb, $dstdb)
    {
        return $this->lazyObjectReal->swapdb($srcdb, $dstdb);
    }

    public function time()
    {
        return $this->lazyObjectReal->time();
    }

    public function ttl($key)
    {
        return $this->lazyObjectReal->ttl($key);
    }

    public function type($key)
    {
        return $this->lazyObjectReal->type($key);
    }

    public function unlink($key, ...$other_keys)
    {
        return $this->lazyObjectReal->unlink($key, ...$other_keys);
    }

    public function unsubscribe($channel, ...$other_channels)
    {
        return $this->lazyObjectReal->unsubscribe($channel, ...$other_channels);
    }

    public function unwatch()
    {
        return $this->lazyObjectReal->unwatch();
    }

    public function wait($numslaves, $timeout)
    {
        return $this->lazyObjectReal->wait($numslaves, $timeout);
    }

    public function watch($key, ...$other_keys)
    {
        return $this->lazyObjectReal->watch($key, ...$other_keys);
    }

    public function xack($str_key, $str_group, $arr_ids)
    {
        return $this->lazyObjectReal->xack($str_key, $str_group, $arr_ids);
    }

    public function xadd($str_key, $str_id, $arr_fields, $i_maxlen = null, $boo_approximate = null)
    {
        return $this->lazyObjectReal->xadd($str_key, $str_id, $arr_fields, $i_maxlen, $boo_approximate);
    }

    public function xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts = null)
    {
        return $this->lazyObjectReal->xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts);
    }

    public function xdel($str_key, $arr_ids)
    {
        return $this->lazyObjectReal->xdel($str_key, $arr_ids);
    }

    public function xgroup($str_operation, $str_key = null, $str_arg1 = null, $str_arg2 = null, $str_arg3 = null)
    {
        return $this->lazyObjectReal->xgroup($str_operation, $str_key, $str_arg1, $str_arg2, $str_arg3);
    }

    public function xinfo($str_cmd, $str_key = null, $str_group = null)
    {
        return $this->lazyObjectReal->xinfo($str_cmd, $str_key, $str_group);
    }

    public function xlen($key)
    {
        return $this->lazyObjectReal->xlen($key);
    }

    public function xpending($str_key, $str_group, $str_start = null, $str_end = null, $i_count = null, $str_consumer = null)
    {
        return $this->lazyObjectReal->xpending($str_key, $str_group, $str_start, $str_end, $i_count, $str_consumer);
    }

    public function xrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return $this->lazyObjectReal->xrange($str_key, $str_start, $str_end, $i_count);
    }

    public function xread($arr_streams, $i_count = null, $i_block = null)
    {
        return $this->lazyObjectReal->xread($arr_streams, $i_count, $i_block);
    }

    public function xreadgroup($str_group, $str_consumer, $arr_streams, $i_count = null, $i_block = null)
    {
        return $this->lazyObjectReal->xreadgroup($str_group, $str_consumer, $arr_streams, $i_count, $i_block);
    }

    public function xrevrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return $this->lazyObjectReal->xrevrange($str_key, $str_start, $str_end, $i_count);
    }

    public function xtrim($str_key, $i_maxlen, $boo_approximate = null)
    {
        return $this->lazyObjectReal->xtrim($str_key, $i_maxlen, $boo_approximate);
    }

    public function zAdd($key, $score, $value, ...$extra_args)
    {
        return $this->lazyObjectReal->zAdd($key, $score, $value, ...$extra_args);
    }

    public function zCard($key)
    {
        return $this->lazyObjectReal->zCard($key);
    }

    public function zCount($key, $min, $max)
    {
        return $this->lazyObjectReal->zCount($key, $min, $max);
    }

    public function zIncrBy($key, $value, $member)
    {
        return $this->lazyObjectReal->zIncrBy($key, $value, $member);
    }

    public function zLexCount($key, $min, $max)
    {
        return $this->lazyObjectReal->zLexCount($key, $min, $max);
    }

    public function zPopMax($key)
    {
        return $this->lazyObjectReal->zPopMax($key);
    }

    public function zPopMin($key)
    {
        return $this->lazyObjectReal->zPopMin($key);
    }

    public function zRange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zRange($key, $start, $end, $scores);
    }

    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zRangeByLex($key, $min, $max, $offset, $limit);
    }

    public function zRangeByScore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zRangeByScore($key, $start, $end, $options);
    }

    public function zRank($key, $member)
    {
        return $this->lazyObjectReal->zRank($key, $member);
    }

    public function zRem($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zRem($key, $member, ...$other_members);
    }

    public function zRemRangeByLex($key, $min, $max)
    {
        return $this->lazyObjectReal->zRemRangeByLex($key, $min, $max);
    }

    public function zRemRangeByRank($key, $start, $end)
    {
        return $this->lazyObjectReal->zRemRangeByRank($key, $start, $end);
    }

    public function zRemRangeByScore($key, $min, $max)
    {
        return $this->lazyObjectReal->zRemRangeByScore($key, $min, $max);
    }

    public function zRevRange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zRevRange($key, $start, $end, $scores);
    }

    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zRevRangeByLex($key, $min, $max, $offset, $limit);
    }

    public function zRevRangeByScore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zRevRangeByScore($key, $start, $end, $options);
    }

    public function zRevRank($key, $member)
    {
        return $this->lazyObjectReal->zRevRank($key, $member);
    }

    public function zScore($key, $member)
    {
        return $this->lazyObjectReal->zScore($key, $member);
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zinterstore($key, $keys, $weights, $aggregate);
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->zscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zunionstore($key, $keys, $weights, $aggregate);
    }

    public function delete($key, ...$other_keys)
    {
        return $this->lazyObjectReal->delete($key, ...$other_keys);
    }

    public function evaluate($script, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->evaluate($script, $args, $num_keys);
    }

    public function evaluateSha($script_sha, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->evaluateSha($script_sha, $args, $num_keys);
    }

    public function getKeys($pattern)
    {
        return $this->lazyObjectReal->getKeys($pattern);
    }

    public function getMultiple($keys)
    {
        return $this->lazyObjectReal->getMultiple($keys);
    }

    public function lGet($key, $index)
    {
        return $this->lazyObjectReal->lGet($key, $index);
    }

    public function lGetRange($key, $start, $end)
    {
        return $this->lazyObjectReal->lGetRange($key, $start, $end);
    }

    public function lRemove($key, $value, $count)
    {
        return $this->lazyObjectReal->lRemove($key, $value, $count);
    }

    public function lSize($key)
    {
        return $this->lazyObjectReal->lSize($key);
    }

    public function listTrim($key, $start, $stop)
    {
        return $this->lazyObjectReal->listTrim($key, $start, $stop);
    }

    public function open($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return $this->lazyObjectReal->open($host, $port, $timeout, $retry_interval);
    }

    public function popen($host, $port = null, $timeout = null)
    {
        return $this->lazyObjectReal->popen($host, $port, $timeout);
    }

    public function renameKey($key, $newkey)
    {
        return $this->lazyObjectReal->renameKey($key, $newkey);
    }

    public function sContains($key, $value)
    {
        return $this->lazyObjectReal->sContains($key, $value);
    }

    public function sGetMembers($key)
    {
        return $this->lazyObjectReal->sGetMembers($key);
    }

    public function sRemove($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->sRemove($key, $member, ...$other_members);
    }

    public function sSize($key)
    {
        return $this->lazyObjectReal->sSize($key);
    }

    public function sendEcho($msg)
    {
        return $this->lazyObjectReal->sendEcho($msg);
    }

    public function setTimeout($key, $timeout)
    {
        return $this->lazyObjectReal->setTimeout($key, $timeout);
    }

    public function substr($key, $start, $end)
    {
        return $this->lazyObjectReal->substr($key, $start, $end);
    }

    public function zDelete($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zDelete($key, $member, ...$other_members);
    }

    public function zDeleteRangeByRank($key, $min, $max)
    {
        return $this->lazyObjectReal->zDeleteRangeByRank($key, $min, $max);
    }

    public function zDeleteRangeByScore($key, $min, $max)
    {
        return $this->lazyObjectReal->zDeleteRangeByScore($key, $min, $max);
    }

    public function zInter($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zInter($key, $keys, $weights, $aggregate);
    }

    public function zRemove($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zRemove($key, $member, ...$other_members);
    }

    public function zRemoveRangeByScore($key, $min, $max)
    {
        return $this->lazyObjectReal->zRemoveRangeByScore($key, $min, $max);
    }

    public function zReverseRange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zReverseRange($key, $start, $end, $scores);
    }

    public function zSize($key)
    {
        return $this->lazyObjectReal->zSize($key);
    }

    public function zUnion($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zUnion($key, $keys, $weights, $aggregate);
    }
}
