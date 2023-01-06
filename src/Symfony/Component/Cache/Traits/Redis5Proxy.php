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
        return $this->lazyObjectReal->__construct(...\func_get_args());
    }

    public function _prefix($key)
    {
        return $this->lazyObjectReal->_prefix(...\func_get_args());
    }

    public function _serialize($value)
    {
        return $this->lazyObjectReal->_serialize(...\func_get_args());
    }

    public function _unserialize($value)
    {
        return $this->lazyObjectReal->_unserialize(...\func_get_args());
    }

    public function _pack($value)
    {
        return $this->lazyObjectReal->_pack(...\func_get_args());
    }

    public function _unpack($value)
    {
        return $this->lazyObjectReal->_unpack(...\func_get_args());
    }

    public function _compress($value)
    {
        return $this->lazyObjectReal->_compress(...\func_get_args());
    }

    public function _uncompress($value)
    {
        return $this->lazyObjectReal->_uncompress(...\func_get_args());
    }

    public function acl($subcmd, ...$args)
    {
        return $this->lazyObjectReal->acl(...\func_get_args());
    }

    public function append($key, $value)
    {
        return $this->lazyObjectReal->append(...\func_get_args());
    }

    public function auth($auth)
    {
        return $this->lazyObjectReal->auth(...\func_get_args());
    }

    public function bgSave()
    {
        return $this->lazyObjectReal->bgSave(...\func_get_args());
    }

    public function bgrewriteaof()
    {
        return $this->lazyObjectReal->bgrewriteaof(...\func_get_args());
    }

    public function bitcount($key)
    {
        return $this->lazyObjectReal->bitcount(...\func_get_args());
    }

    public function bitop($operation, $ret_key, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null)
    {
        return $this->lazyObjectReal->bitpos(...\func_get_args());
    }

    public function blPop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->blPop(...\func_get_args());
    }

    public function brPop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->brPop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return $this->lazyObjectReal->brpoplpush(...\func_get_args());
    }

    public function bzPopMax($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzPopMax(...\func_get_args());
    }

    public function bzPopMin($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzPopMin(...\func_get_args());
    }

    public function clearLastError()
    {
        return $this->lazyObjectReal->clearLastError(...\func_get_args());
    }

    public function client($cmd, ...$args)
    {
        return $this->lazyObjectReal->client(...\func_get_args());
    }

    public function close()
    {
        return $this->lazyObjectReal->close(...\func_get_args());
    }

    public function command(...$args)
    {
        return $this->lazyObjectReal->command(...\func_get_args());
    }

    public function config($cmd, $key, $value = null)
    {
        return $this->lazyObjectReal->config(...\func_get_args());
    }

    public function connect($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return $this->lazyObjectReal->connect(...\func_get_args());
    }

    public function dbSize()
    {
        return $this->lazyObjectReal->dbSize(...\func_get_args());
    }

    public function debug($key)
    {
        return $this->lazyObjectReal->debug(...\func_get_args());
    }

    public function decr($key)
    {
        return $this->lazyObjectReal->decr(...\func_get_args());
    }

    public function decrBy($key, $value)
    {
        return $this->lazyObjectReal->decrBy(...\func_get_args());
    }

    public function del($key, ...$other_keys)
    {
        return $this->lazyObjectReal->del(...\func_get_args());
    }

    public function discard()
    {
        return $this->lazyObjectReal->discard(...\func_get_args());
    }

    public function dump($key)
    {
        return $this->lazyObjectReal->dump(...\func_get_args());
    }

    public function echo($msg)
    {
        return $this->lazyObjectReal->echo(...\func_get_args());
    }

    public function eval($script, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->eval(...\func_get_args());
    }

    public function evalsha($script_sha, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->evalsha(...\func_get_args());
    }

    public function exec()
    {
        return $this->lazyObjectReal->exec(...\func_get_args());
    }

    public function exists($key, ...$other_keys)
    {
        return $this->lazyObjectReal->exists(...\func_get_args());
    }

    public function expire($key, $timeout)
    {
        return $this->lazyObjectReal->expire(...\func_get_args());
    }

    public function expireAt($key, $timestamp)
    {
        return $this->lazyObjectReal->expireAt(...\func_get_args());
    }

    public function flushAll($async = null)
    {
        return $this->lazyObjectReal->flushAll(...\func_get_args());
    }

    public function flushDB($async = null)
    {
        return $this->lazyObjectReal->flushDB(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples)
    {
        return $this->lazyObjectReal->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dst, $unit = null)
    {
        return $this->lazyObjectReal->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->geohash(...\func_get_args());
    }

    public function geopos($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->geopos(...\func_get_args());
    }

    public function georadius($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadius(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lan, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadius_ro(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $opts = null)
    {
        return $this->lazyObjectReal->georadiusbymember_ro(...\func_get_args());
    }

    public function get($key)
    {
        return $this->lazyObjectReal->get(...\func_get_args());
    }

    public function getAuth()
    {
        return $this->lazyObjectReal->getAuth(...\func_get_args());
    }

    public function getBit($key, $offset)
    {
        return $this->lazyObjectReal->getBit(...\func_get_args());
    }

    public function getDBNum()
    {
        return $this->lazyObjectReal->getDBNum(...\func_get_args());
    }

    public function getHost()
    {
        return $this->lazyObjectReal->getHost(...\func_get_args());
    }

    public function getLastError()
    {
        return $this->lazyObjectReal->getLastError(...\func_get_args());
    }

    public function getMode()
    {
        return $this->lazyObjectReal->getMode(...\func_get_args());
    }

    public function getOption($option)
    {
        return $this->lazyObjectReal->getOption(...\func_get_args());
    }

    public function getPersistentID()
    {
        return $this->lazyObjectReal->getPersistentID(...\func_get_args());
    }

    public function getPort()
    {
        return $this->lazyObjectReal->getPort(...\func_get_args());
    }

    public function getRange($key, $start, $end)
    {
        return $this->lazyObjectReal->getRange(...\func_get_args());
    }

    public function getReadTimeout()
    {
        return $this->lazyObjectReal->getReadTimeout(...\func_get_args());
    }

    public function getSet($key, $value)
    {
        return $this->lazyObjectReal->getSet(...\func_get_args());
    }

    public function getTimeout()
    {
        return $this->lazyObjectReal->getTimeout(...\func_get_args());
    }

    public function hDel($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->hDel(...\func_get_args());
    }

    public function hExists($key, $member)
    {
        return $this->lazyObjectReal->hExists(...\func_get_args());
    }

    public function hGet($key, $member)
    {
        return $this->lazyObjectReal->hGet(...\func_get_args());
    }

    public function hGetAll($key)
    {
        return $this->lazyObjectReal->hGetAll(...\func_get_args());
    }

    public function hIncrBy($key, $member, $value)
    {
        return $this->lazyObjectReal->hIncrBy(...\func_get_args());
    }

    public function hIncrByFloat($key, $member, $value)
    {
        return $this->lazyObjectReal->hIncrByFloat(...\func_get_args());
    }

    public function hKeys($key)
    {
        return $this->lazyObjectReal->hKeys(...\func_get_args());
    }

    public function hLen($key)
    {
        return $this->lazyObjectReal->hLen(...\func_get_args());
    }

    public function hMget($key, $keys)
    {
        return $this->lazyObjectReal->hMget(...\func_get_args());
    }

    public function hMset($key, $pairs)
    {
        return $this->lazyObjectReal->hMset(...\func_get_args());
    }

    public function hSet($key, $member, $value)
    {
        return $this->lazyObjectReal->hSet(...\func_get_args());
    }

    public function hSetNx($key, $member, $value)
    {
        return $this->lazyObjectReal->hSetNx(...\func_get_args());
    }

    public function hStrLen($key, $member)
    {
        return $this->lazyObjectReal->hStrLen(...\func_get_args());
    }

    public function hVals($key)
    {
        return $this->lazyObjectReal->hVals(...\func_get_args());
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->hscan(...\func_get_args());
    }

    public function incr($key)
    {
        return $this->lazyObjectReal->incr(...\func_get_args());
    }

    public function incrBy($key, $value)
    {
        return $this->lazyObjectReal->incrBy(...\func_get_args());
    }

    public function incrByFloat($key, $value)
    {
        return $this->lazyObjectReal->incrByFloat(...\func_get_args());
    }

    public function info($option = null)
    {
        return $this->lazyObjectReal->info(...\func_get_args());
    }

    public function isConnected()
    {
        return $this->lazyObjectReal->isConnected(...\func_get_args());
    }

    public function keys($pattern)
    {
        return $this->lazyObjectReal->keys(...\func_get_args());
    }

    public function lInsert($key, $position, $pivot, $value)
    {
        return $this->lazyObjectReal->lInsert(...\func_get_args());
    }

    public function lLen($key)
    {
        return $this->lazyObjectReal->lLen(...\func_get_args());
    }

    public function lPop($key)
    {
        return $this->lazyObjectReal->lPop(...\func_get_args());
    }

    public function lPush($key, $value)
    {
        return $this->lazyObjectReal->lPush(...\func_get_args());
    }

    public function lPushx($key, $value)
    {
        return $this->lazyObjectReal->lPushx(...\func_get_args());
    }

    public function lSet($key, $index, $value)
    {
        return $this->lazyObjectReal->lSet(...\func_get_args());
    }

    public function lastSave()
    {
        return $this->lazyObjectReal->lastSave(...\func_get_args());
    }

    public function lindex($key, $index)
    {
        return $this->lazyObjectReal->lindex(...\func_get_args());
    }

    public function lrange($key, $start, $end)
    {
        return $this->lazyObjectReal->lrange(...\func_get_args());
    }

    public function lrem($key, $value, $count)
    {
        return $this->lazyObjectReal->lrem(...\func_get_args());
    }

    public function ltrim($key, $start, $stop)
    {
        return $this->lazyObjectReal->ltrim(...\func_get_args());
    }

    public function mget($keys)
    {
        return $this->lazyObjectReal->mget(...\func_get_args());
    }

    public function migrate($host, $port, $key, $db, $timeout, $copy = null, $replace = null)
    {
        return $this->lazyObjectReal->migrate(...\func_get_args());
    }

    public function move($key, $dbindex)
    {
        return $this->lazyObjectReal->move(...\func_get_args());
    }

    public function mset($pairs)
    {
        return $this->lazyObjectReal->mset(...\func_get_args());
    }

    public function msetnx($pairs)
    {
        return $this->lazyObjectReal->msetnx(...\func_get_args());
    }

    public function multi($mode = null)
    {
        return $this->lazyObjectReal->multi(...\func_get_args());
    }

    public function object($field, $key)
    {
        return $this->lazyObjectReal->object(...\func_get_args());
    }

    public function pconnect($host, $port = null, $timeout = null)
    {
        return $this->lazyObjectReal->pconnect(...\func_get_args());
    }

    public function persist($key)
    {
        return $this->lazyObjectReal->persist(...\func_get_args());
    }

    public function pexpire($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpire(...\func_get_args());
    }

    public function pexpireAt($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpireAt(...\func_get_args());
    }

    public function pfadd($key, $elements)
    {
        return $this->lazyObjectReal->pfadd(...\func_get_args());
    }

    public function pfcount($key)
    {
        return $this->lazyObjectReal->pfcount(...\func_get_args());
    }

    public function pfmerge($dstkey, $keys)
    {
        return $this->lazyObjectReal->pfmerge(...\func_get_args());
    }

    public function ping()
    {
        return $this->lazyObjectReal->ping(...\func_get_args());
    }

    public function pipeline()
    {
        return $this->lazyObjectReal->pipeline(...\func_get_args());
    }

    public function psetex($key, $expire, $value)
    {
        return $this->lazyObjectReal->psetex(...\func_get_args());
    }

    public function psubscribe($patterns, $callback)
    {
        return $this->lazyObjectReal->psubscribe(...\func_get_args());
    }

    public function pttl($key)
    {
        return $this->lazyObjectReal->pttl(...\func_get_args());
    }

    public function publish($channel, $message)
    {
        return $this->lazyObjectReal->publish(...\func_get_args());
    }

    public function pubsub($cmd, ...$args)
    {
        return $this->lazyObjectReal->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return $this->lazyObjectReal->punsubscribe(...\func_get_args());
    }

    public function rPop($key)
    {
        return $this->lazyObjectReal->rPop(...\func_get_args());
    }

    public function rPush($key, $value)
    {
        return $this->lazyObjectReal->rPush(...\func_get_args());
    }

    public function rPushx($key, $value)
    {
        return $this->lazyObjectReal->rPushx(...\func_get_args());
    }

    public function randomKey()
    {
        return $this->lazyObjectReal->randomKey(...\func_get_args());
    }

    public function rawcommand($cmd, ...$args)
    {
        return $this->lazyObjectReal->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey)
    {
        return $this->lazyObjectReal->rename(...\func_get_args());
    }

    public function renameNx($key, $newkey)
    {
        return $this->lazyObjectReal->renameNx(...\func_get_args());
    }

    public function restore($ttl, $key, $value)
    {
        return $this->lazyObjectReal->restore(...\func_get_args());
    }

    public function role()
    {
        return $this->lazyObjectReal->role(...\func_get_args());
    }

    public function rpoplpush($src, $dst)
    {
        return $this->lazyObjectReal->rpoplpush(...\func_get_args());
    }

    public function sAdd($key, $value)
    {
        return $this->lazyObjectReal->sAdd(...\func_get_args());
    }

    public function sAddArray($key, $options)
    {
        return $this->lazyObjectReal->sAddArray(...\func_get_args());
    }

    public function sDiff($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sDiff(...\func_get_args());
    }

    public function sDiffStore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sDiffStore(...\func_get_args());
    }

    public function sInter($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sInter(...\func_get_args());
    }

    public function sInterStore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sInterStore(...\func_get_args());
    }

    public function sMembers($key)
    {
        return $this->lazyObjectReal->sMembers(...\func_get_args());
    }

    public function sMisMember($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->sMisMember(...\func_get_args());
    }

    public function sMove($src, $dst, $value)
    {
        return $this->lazyObjectReal->sMove(...\func_get_args());
    }

    public function sPop($key)
    {
        return $this->lazyObjectReal->sPop(...\func_get_args());
    }

    public function sRandMember($key, $count = null)
    {
        return $this->lazyObjectReal->sRandMember(...\func_get_args());
    }

    public function sUnion($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sUnion(...\func_get_args());
    }

    public function sUnionStore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sUnionStore(...\func_get_args());
    }

    public function save()
    {
        return $this->lazyObjectReal->save(...\func_get_args());
    }

    public function scan(&$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->scan(...\func_get_args());
    }

    public function scard($key)
    {
        return $this->lazyObjectReal->scard(...\func_get_args());
    }

    public function script($cmd, ...$args)
    {
        return $this->lazyObjectReal->script(...\func_get_args());
    }

    public function select($dbindex)
    {
        return $this->lazyObjectReal->select(...\func_get_args());
    }

    public function set($key, $value, $opts = null)
    {
        return $this->lazyObjectReal->set(...\func_get_args());
    }

    public function setBit($key, $offset, $value)
    {
        return $this->lazyObjectReal->setBit(...\func_get_args());
    }

    public function setOption($option, $value)
    {
        return $this->lazyObjectReal->setOption(...\func_get_args());
    }

    public function setRange($key, $offset, $value)
    {
        return $this->lazyObjectReal->setRange(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return $this->lazyObjectReal->setex(...\func_get_args());
    }

    public function setnx($key, $value)
    {
        return $this->lazyObjectReal->setnx(...\func_get_args());
    }

    public function sismember($key, $value)
    {
        return $this->lazyObjectReal->sismember(...\func_get_args());
    }

    public function slaveof($host = null, $port = null)
    {
        return $this->lazyObjectReal->slaveof(...\func_get_args());
    }

    public function slowlog($arg, $option = null)
    {
        return $this->lazyObjectReal->slowlog(...\func_get_args());
    }

    public function sort($key, $options = null)
    {
        return $this->lazyObjectReal->sort(...\func_get_args());
    }

    public function sortAsc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortAsc(...\func_get_args());
    }

    public function sortAscAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortAscAlpha(...\func_get_args());
    }

    public function sortDesc($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortDesc(...\func_get_args());
    }

    public function sortDescAlpha($key, $pattern = null, $get = null, $start = null, $end = null, $getList = null)
    {
        return $this->lazyObjectReal->sortDescAlpha(...\func_get_args());
    }

    public function srem($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->srem(...\func_get_args());
    }

    public function sscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->sscan(...\func_get_args());
    }

    public function strlen($key)
    {
        return $this->lazyObjectReal->strlen(...\func_get_args());
    }

    public function subscribe($channels, $callback)
    {
        return $this->lazyObjectReal->subscribe(...\func_get_args());
    }

    public function swapdb($srcdb, $dstdb)
    {
        return $this->lazyObjectReal->swapdb(...\func_get_args());
    }

    public function time()
    {
        return $this->lazyObjectReal->time(...\func_get_args());
    }

    public function ttl($key)
    {
        return $this->lazyObjectReal->ttl(...\func_get_args());
    }

    public function type($key)
    {
        return $this->lazyObjectReal->type(...\func_get_args());
    }

    public function unlink($key, ...$other_keys)
    {
        return $this->lazyObjectReal->unlink(...\func_get_args());
    }

    public function unsubscribe($channel, ...$other_channels)
    {
        return $this->lazyObjectReal->unsubscribe(...\func_get_args());
    }

    public function unwatch()
    {
        return $this->lazyObjectReal->unwatch(...\func_get_args());
    }

    public function wait($numslaves, $timeout)
    {
        return $this->lazyObjectReal->wait(...\func_get_args());
    }

    public function watch($key, ...$other_keys)
    {
        return $this->lazyObjectReal->watch(...\func_get_args());
    }

    public function xack($str_key, $str_group, $arr_ids)
    {
        return $this->lazyObjectReal->xack(...\func_get_args());
    }

    public function xadd($str_key, $str_id, $arr_fields, $i_maxlen = null, $boo_approximate = null)
    {
        return $this->lazyObjectReal->xadd(...\func_get_args());
    }

    public function xclaim($str_key, $str_group, $str_consumer, $i_min_idle, $arr_ids, $arr_opts = null)
    {
        return $this->lazyObjectReal->xclaim(...\func_get_args());
    }

    public function xdel($str_key, $arr_ids)
    {
        return $this->lazyObjectReal->xdel(...\func_get_args());
    }

    public function xgroup($str_operation, $str_key = null, $str_arg1 = null, $str_arg2 = null, $str_arg3 = null)
    {
        return $this->lazyObjectReal->xgroup(...\func_get_args());
    }

    public function xinfo($str_cmd, $str_key = null, $str_group = null)
    {
        return $this->lazyObjectReal->xinfo(...\func_get_args());
    }

    public function xlen($key)
    {
        return $this->lazyObjectReal->xlen(...\func_get_args());
    }

    public function xpending($str_key, $str_group, $str_start = null, $str_end = null, $i_count = null, $str_consumer = null)
    {
        return $this->lazyObjectReal->xpending(...\func_get_args());
    }

    public function xrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return $this->lazyObjectReal->xrange(...\func_get_args());
    }

    public function xread($arr_streams, $i_count = null, $i_block = null)
    {
        return $this->lazyObjectReal->xread(...\func_get_args());
    }

    public function xreadgroup($str_group, $str_consumer, $arr_streams, $i_count = null, $i_block = null)
    {
        return $this->lazyObjectReal->xreadgroup(...\func_get_args());
    }

    public function xrevrange($str_key, $str_start, $str_end, $i_count = null)
    {
        return $this->lazyObjectReal->xrevrange(...\func_get_args());
    }

    public function xtrim($str_key, $i_maxlen, $boo_approximate = null)
    {
        return $this->lazyObjectReal->xtrim(...\func_get_args());
    }

    public function zAdd($key, $score, $value, ...$extra_args)
    {
        return $this->lazyObjectReal->zAdd(...\func_get_args());
    }

    public function zCard($key)
    {
        return $this->lazyObjectReal->zCard(...\func_get_args());
    }

    public function zCount($key, $min, $max)
    {
        return $this->lazyObjectReal->zCount(...\func_get_args());
    }

    public function zIncrBy($key, $value, $member)
    {
        return $this->lazyObjectReal->zIncrBy(...\func_get_args());
    }

    public function zLexCount($key, $min, $max)
    {
        return $this->lazyObjectReal->zLexCount(...\func_get_args());
    }

    public function zPopMax($key)
    {
        return $this->lazyObjectReal->zPopMax(...\func_get_args());
    }

    public function zPopMin($key)
    {
        return $this->lazyObjectReal->zPopMin(...\func_get_args());
    }

    public function zRange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zRange(...\func_get_args());
    }

    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zRangeByLex(...\func_get_args());
    }

    public function zRangeByScore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zRangeByScore(...\func_get_args());
    }

    public function zRank($key, $member)
    {
        return $this->lazyObjectReal->zRank(...\func_get_args());
    }

    public function zRem($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zRem(...\func_get_args());
    }

    public function zRemRangeByLex($key, $min, $max)
    {
        return $this->lazyObjectReal->zRemRangeByLex(...\func_get_args());
    }

    public function zRemRangeByRank($key, $start, $end)
    {
        return $this->lazyObjectReal->zRemRangeByRank(...\func_get_args());
    }

    public function zRemRangeByScore($key, $min, $max)
    {
        return $this->lazyObjectReal->zRemRangeByScore(...\func_get_args());
    }

    public function zRevRange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zRevRange(...\func_get_args());
    }

    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zRevRangeByLex(...\func_get_args());
    }

    public function zRevRangeByScore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zRevRangeByScore(...\func_get_args());
    }

    public function zRevRank($key, $member)
    {
        return $this->lazyObjectReal->zRevRank(...\func_get_args());
    }

    public function zScore($key, $member)
    {
        return $this->lazyObjectReal->zScore(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zinterstore(...\func_get_args());
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->zscan(...\func_get_args());
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zunionstore(...\func_get_args());
    }

    public function delete($key, ...$other_keys)
    {
        return $this->lazyObjectReal->delete(...\func_get_args());
    }

    public function evaluate($script, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->evaluate(...\func_get_args());
    }

    public function evaluateSha($script_sha, $args = null, $num_keys = null)
    {
        return $this->lazyObjectReal->evaluateSha(...\func_get_args());
    }

    public function getKeys($pattern)
    {
        return $this->lazyObjectReal->getKeys(...\func_get_args());
    }

    public function getMultiple($keys)
    {
        return $this->lazyObjectReal->getMultiple(...\func_get_args());
    }

    public function lGet($key, $index)
    {
        return $this->lazyObjectReal->lGet(...\func_get_args());
    }

    public function lGetRange($key, $start, $end)
    {
        return $this->lazyObjectReal->lGetRange(...\func_get_args());
    }

    public function lRemove($key, $value, $count)
    {
        return $this->lazyObjectReal->lRemove(...\func_get_args());
    }

    public function lSize($key)
    {
        return $this->lazyObjectReal->lSize(...\func_get_args());
    }

    public function listTrim($key, $start, $stop)
    {
        return $this->lazyObjectReal->listTrim(...\func_get_args());
    }

    public function open($host, $port = null, $timeout = null, $retry_interval = null)
    {
        return $this->lazyObjectReal->open(...\func_get_args());
    }

    public function popen($host, $port = null, $timeout = null)
    {
        return $this->lazyObjectReal->popen(...\func_get_args());
    }

    public function renameKey($key, $newkey)
    {
        return $this->lazyObjectReal->renameKey(...\func_get_args());
    }

    public function sContains($key, $value)
    {
        return $this->lazyObjectReal->sContains(...\func_get_args());
    }

    public function sGetMembers($key)
    {
        return $this->lazyObjectReal->sGetMembers(...\func_get_args());
    }

    public function sRemove($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->sRemove(...\func_get_args());
    }

    public function sSize($key)
    {
        return $this->lazyObjectReal->sSize(...\func_get_args());
    }

    public function sendEcho($msg)
    {
        return $this->lazyObjectReal->sendEcho(...\func_get_args());
    }

    public function setTimeout($key, $timeout)
    {
        return $this->lazyObjectReal->setTimeout(...\func_get_args());
    }

    public function substr($key, $start, $end)
    {
        return $this->lazyObjectReal->substr(...\func_get_args());
    }

    public function zDelete($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zDelete(...\func_get_args());
    }

    public function zDeleteRangeByRank($key, $min, $max)
    {
        return $this->lazyObjectReal->zDeleteRangeByRank(...\func_get_args());
    }

    public function zDeleteRangeByScore($key, $min, $max)
    {
        return $this->lazyObjectReal->zDeleteRangeByScore(...\func_get_args());
    }

    public function zInter($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zInter(...\func_get_args());
    }

    public function zRemove($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zRemove(...\func_get_args());
    }

    public function zRemoveRangeByScore($key, $min, $max)
    {
        return $this->lazyObjectReal->zRemoveRangeByScore(...\func_get_args());
    }

    public function zReverseRange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zReverseRange(...\func_get_args());
    }

    public function zSize($key)
    {
        return $this->lazyObjectReal->zSize(...\func_get_args());
    }

    public function zUnion($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zUnion(...\func_get_args());
    }
}
