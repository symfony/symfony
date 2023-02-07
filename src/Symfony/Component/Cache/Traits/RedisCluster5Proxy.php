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
class RedisCluster5Proxy extends \RedisCluster implements ResetInterface, LazyObjectInterface
{
    use LazyProxyTrait {
        resetLazyObject as reset;
    }

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
        "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
    ];

    public function __construct($name, $seeds = null, $timeout = null, $read_timeout = null, $persistent = null, $auth = null)
    {
        return $this->lazyObjectReal->__construct($name, $seeds, $timeout, $read_timeout, $persistent, $auth);
    }

    public function _masters()
    {
        return $this->lazyObjectReal->_masters();
    }

    public function _prefix($key)
    {
        return $this->lazyObjectReal->_prefix($key);
    }

    public function _redir()
    {
        return $this->lazyObjectReal->_redir();
    }

    public function _serialize($value)
    {
        return $this->lazyObjectReal->_serialize($value);
    }

    public function _unserialize($value)
    {
        return $this->lazyObjectReal->_unserialize($value);
    }

    public function _compress($value)
    {
        return $this->lazyObjectReal->_compress($value);
    }

    public function _uncompress($value)
    {
        return $this->lazyObjectReal->_uncompress($value);
    }

    public function _pack($value)
    {
        return $this->lazyObjectReal->_pack($value);
    }

    public function _unpack($value)
    {
        return $this->lazyObjectReal->_unpack($value);
    }

    public function acl($key_or_address, $subcmd, ...$args)
    {
        return $this->lazyObjectReal->acl($key_or_address, $subcmd, ...$args);
    }

    public function append($key, $value)
    {
        return $this->lazyObjectReal->append($key, $value);
    }

    public function bgrewriteaof($key_or_address)
    {
        return $this->lazyObjectReal->bgrewriteaof($key_or_address);
    }

    public function bgsave($key_or_address)
    {
        return $this->lazyObjectReal->bgsave($key_or_address);
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

    public function blpop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->blpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->brpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return $this->lazyObjectReal->brpoplpush($src, $dst, $timeout);
    }

    public function clearlasterror()
    {
        return $this->lazyObjectReal->clearlasterror();
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzpopmax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzpopmin($key, $timeout_or_key, ...$extra_args);
    }

    public function client($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->client($key_or_address, $arg, ...$other_args);
    }

    public function close()
    {
        return $this->lazyObjectReal->close();
    }

    public function cluster($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->cluster($key_or_address, $arg, ...$other_args);
    }

    public function command(...$args)
    {
        return $this->lazyObjectReal->command(...$args);
    }

    public function config($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->config($key_or_address, $arg, ...$other_args);
    }

    public function dbsize($key_or_address)
    {
        return $this->lazyObjectReal->dbsize($key_or_address);
    }

    public function decr($key)
    {
        return $this->lazyObjectReal->decr($key);
    }

    public function decrby($key, $value)
    {
        return $this->lazyObjectReal->decrby($key, $value);
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

    public function exists($key)
    {
        return $this->lazyObjectReal->exists($key);
    }

    public function expire($key, $timeout)
    {
        return $this->lazyObjectReal->expire($key, $timeout);
    }

    public function expireat($key, $timestamp)
    {
        return $this->lazyObjectReal->expireat($key, $timestamp);
    }

    public function flushall($key_or_address, $async = null)
    {
        return $this->lazyObjectReal->flushall($key_or_address, $async);
    }

    public function flushdb($key_or_address, $async = null)
    {
        return $this->lazyObjectReal->flushdb($key_or_address, $async);
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

    public function getbit($key, $offset)
    {
        return $this->lazyObjectReal->getbit($key, $offset);
    }

    public function getlasterror()
    {
        return $this->lazyObjectReal->getlasterror();
    }

    public function getmode()
    {
        return $this->lazyObjectReal->getmode();
    }

    public function getoption($option)
    {
        return $this->lazyObjectReal->getoption($option);
    }

    public function getrange($key, $start, $end)
    {
        return $this->lazyObjectReal->getrange($key, $start, $end);
    }

    public function getset($key, $value)
    {
        return $this->lazyObjectReal->getset($key, $value);
    }

    public function hdel($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->hdel($key, $member, ...$other_members);
    }

    public function hexists($key, $member)
    {
        return $this->lazyObjectReal->hexists($key, $member);
    }

    public function hget($key, $member)
    {
        return $this->lazyObjectReal->hget($key, $member);
    }

    public function hgetall($key)
    {
        return $this->lazyObjectReal->hgetall($key);
    }

    public function hincrby($key, $member, $value)
    {
        return $this->lazyObjectReal->hincrby($key, $member, $value);
    }

    public function hincrbyfloat($key, $member, $value)
    {
        return $this->lazyObjectReal->hincrbyfloat($key, $member, $value);
    }

    public function hkeys($key)
    {
        return $this->lazyObjectReal->hkeys($key);
    }

    public function hlen($key)
    {
        return $this->lazyObjectReal->hlen($key);
    }

    public function hmget($key, $keys)
    {
        return $this->lazyObjectReal->hmget($key, $keys);
    }

    public function hmset($key, $pairs)
    {
        return $this->lazyObjectReal->hmset($key, $pairs);
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->hscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function hset($key, $member, $value)
    {
        return $this->lazyObjectReal->hset($key, $member, $value);
    }

    public function hsetnx($key, $member, $value)
    {
        return $this->lazyObjectReal->hsetnx($key, $member, $value);
    }

    public function hstrlen($key, $member)
    {
        return $this->lazyObjectReal->hstrlen($key, $member);
    }

    public function hvals($key)
    {
        return $this->lazyObjectReal->hvals($key);
    }

    public function incr($key)
    {
        return $this->lazyObjectReal->incr($key);
    }

    public function incrby($key, $value)
    {
        return $this->lazyObjectReal->incrby($key, $value);
    }

    public function incrbyfloat($key, $value)
    {
        return $this->lazyObjectReal->incrbyfloat($key, $value);
    }

    public function info($key_or_address, $option = null)
    {
        return $this->lazyObjectReal->info($key_or_address, $option);
    }

    public function keys($pattern)
    {
        return $this->lazyObjectReal->keys($pattern);
    }

    public function lastsave($key_or_address)
    {
        return $this->lazyObjectReal->lastsave($key_or_address);
    }

    public function lget($key, $index)
    {
        return $this->lazyObjectReal->lget($key, $index);
    }

    public function lindex($key, $index)
    {
        return $this->lazyObjectReal->lindex($key, $index);
    }

    public function linsert($key, $position, $pivot, $value)
    {
        return $this->lazyObjectReal->linsert($key, $position, $pivot, $value);
    }

    public function llen($key)
    {
        return $this->lazyObjectReal->llen($key);
    }

    public function lpop($key)
    {
        return $this->lazyObjectReal->lpop($key);
    }

    public function lpush($key, $value)
    {
        return $this->lazyObjectReal->lpush($key, $value);
    }

    public function lpushx($key, $value)
    {
        return $this->lazyObjectReal->lpushx($key, $value);
    }

    public function lrange($key, $start, $end)
    {
        return $this->lazyObjectReal->lrange($key, $start, $end);
    }

    public function lrem($key, $value)
    {
        return $this->lazyObjectReal->lrem($key, $value);
    }

    public function lset($key, $index, $value)
    {
        return $this->lazyObjectReal->lset($key, $index, $value);
    }

    public function ltrim($key, $start, $stop)
    {
        return $this->lazyObjectReal->ltrim($key, $start, $stop);
    }

    public function mget($keys)
    {
        return $this->lazyObjectReal->mget($keys);
    }

    public function mset($pairs)
    {
        return $this->lazyObjectReal->mset($pairs);
    }

    public function msetnx($pairs)
    {
        return $this->lazyObjectReal->msetnx($pairs);
    }

    public function multi()
    {
        return $this->lazyObjectReal->multi();
    }

    public function object($field, $key)
    {
        return $this->lazyObjectReal->object($field, $key);
    }

    public function persist($key)
    {
        return $this->lazyObjectReal->persist($key);
    }

    public function pexpire($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpire($key, $timestamp);
    }

    public function pexpireat($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpireat($key, $timestamp);
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

    public function ping($key_or_address)
    {
        return $this->lazyObjectReal->ping($key_or_address);
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

    public function pubsub($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->pubsub($key_or_address, $arg, ...$other_args);
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return $this->lazyObjectReal->punsubscribe($pattern, ...$other_patterns);
    }

    public function randomkey($key_or_address)
    {
        return $this->lazyObjectReal->randomkey($key_or_address);
    }

    public function rawcommand($cmd, ...$args)
    {
        return $this->lazyObjectReal->rawcommand($cmd, ...$args);
    }

    public function rename($key, $newkey)
    {
        return $this->lazyObjectReal->rename($key, $newkey);
    }

    public function renamenx($key, $newkey)
    {
        return $this->lazyObjectReal->renamenx($key, $newkey);
    }

    public function restore($ttl, $key, $value)
    {
        return $this->lazyObjectReal->restore($ttl, $key, $value);
    }

    public function role()
    {
        return $this->lazyObjectReal->role();
    }

    public function rpop($key)
    {
        return $this->lazyObjectReal->rpop($key);
    }

    public function rpoplpush($src, $dst)
    {
        return $this->lazyObjectReal->rpoplpush($src, $dst);
    }

    public function rpush($key, $value)
    {
        return $this->lazyObjectReal->rpush($key, $value);
    }

    public function rpushx($key, $value)
    {
        return $this->lazyObjectReal->rpushx($key, $value);
    }

    public function sadd($key, $value)
    {
        return $this->lazyObjectReal->sadd($key, $value);
    }

    public function saddarray($key, $options)
    {
        return $this->lazyObjectReal->saddarray($key, $options);
    }

    public function save($key_or_address)
    {
        return $this->lazyObjectReal->save($key_or_address);
    }

    public function scan(&$i_iterator, $str_node, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->scan($i_iterator, $str_node, $str_pattern, $i_count);
    }

    public function scard($key)
    {
        return $this->lazyObjectReal->scard($key);
    }

    public function script($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->script($key_or_address, $arg, ...$other_args);
    }

    public function sdiff($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sdiff($key, ...$other_keys);
    }

    public function sdiffstore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sdiffstore($dst, $key, ...$other_keys);
    }

    public function set($key, $value, $opts = null)
    {
        return $this->lazyObjectReal->set($key, $value, $opts);
    }

    public function setbit($key, $offset, $value)
    {
        return $this->lazyObjectReal->setbit($key, $offset, $value);
    }

    public function setex($key, $expire, $value)
    {
        return $this->lazyObjectReal->setex($key, $expire, $value);
    }

    public function setnx($key, $value)
    {
        return $this->lazyObjectReal->setnx($key, $value);
    }

    public function setoption($option, $value)
    {
        return $this->lazyObjectReal->setoption($option, $value);
    }

    public function setrange($key, $offset, $value)
    {
        return $this->lazyObjectReal->setrange($key, $offset, $value);
    }

    public function sinter($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sinter($key, ...$other_keys);
    }

    public function sinterstore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sinterstore($dst, $key, ...$other_keys);
    }

    public function sismember($key, $value)
    {
        return $this->lazyObjectReal->sismember($key, $value);
    }

    public function slowlog($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->slowlog($key_or_address, $arg, ...$other_args);
    }

    public function smembers($key)
    {
        return $this->lazyObjectReal->smembers($key);
    }

    public function smove($src, $dst, $value)
    {
        return $this->lazyObjectReal->smove($src, $dst, $value);
    }

    public function sort($key, $options = null)
    {
        return $this->lazyObjectReal->sort($key, $options);
    }

    public function spop($key)
    {
        return $this->lazyObjectReal->spop($key);
    }

    public function srandmember($key, $count = null)
    {
        return $this->lazyObjectReal->srandmember($key, $count);
    }

    public function srem($key, $value)
    {
        return $this->lazyObjectReal->srem($key, $value);
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

    public function sunion($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sunion($key, ...$other_keys);
    }

    public function sunionstore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sunionstore($dst, $key, ...$other_keys);
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

    public function unsubscribe($channel, ...$other_channels)
    {
        return $this->lazyObjectReal->unsubscribe($channel, ...$other_channels);
    }

    public function unlink($key, ...$other_keys)
    {
        return $this->lazyObjectReal->unlink($key, ...$other_keys);
    }

    public function unwatch()
    {
        return $this->lazyObjectReal->unwatch();
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

    public function zadd($key, $score, $value, ...$extra_args)
    {
        return $this->lazyObjectReal->zadd($key, $score, $value, ...$extra_args);
    }

    public function zcard($key)
    {
        return $this->lazyObjectReal->zcard($key);
    }

    public function zcount($key, $min, $max)
    {
        return $this->lazyObjectReal->zcount($key, $min, $max);
    }

    public function zincrby($key, $value, $member)
    {
        return $this->lazyObjectReal->zincrby($key, $value, $member);
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zinterstore($key, $keys, $weights, $aggregate);
    }

    public function zlexcount($key, $min, $max)
    {
        return $this->lazyObjectReal->zlexcount($key, $min, $max);
    }

    public function zpopmax($key)
    {
        return $this->lazyObjectReal->zpopmax($key);
    }

    public function zpopmin($key)
    {
        return $this->lazyObjectReal->zpopmin($key);
    }

    public function zrange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zrange($key, $start, $end, $scores);
    }

    public function zrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zrangebylex($key, $min, $max, $offset, $limit);
    }

    public function zrangebyscore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zrangebyscore($key, $start, $end, $options);
    }

    public function zrank($key, $member)
    {
        return $this->lazyObjectReal->zrank($key, $member);
    }

    public function zrem($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zrem($key, $member, ...$other_members);
    }

    public function zremrangebylex($key, $min, $max)
    {
        return $this->lazyObjectReal->zremrangebylex($key, $min, $max);
    }

    public function zremrangebyrank($key, $min, $max)
    {
        return $this->lazyObjectReal->zremrangebyrank($key, $min, $max);
    }

    public function zremrangebyscore($key, $min, $max)
    {
        return $this->lazyObjectReal->zremrangebyscore($key, $min, $max);
    }

    public function zrevrange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zrevrange($key, $start, $end, $scores);
    }

    public function zrevrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zrevrangebylex($key, $min, $max, $offset, $limit);
    }

    public function zrevrangebyscore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zrevrangebyscore($key, $start, $end, $options);
    }

    public function zrevrank($key, $member)
    {
        return $this->lazyObjectReal->zrevrank($key, $member);
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->zscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function zscore($key, $member)
    {
        return $this->lazyObjectReal->zscore($key, $member);
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zunionstore($key, $keys, $weights, $aggregate);
    }
}
