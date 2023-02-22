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

    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

    public function __construct($name, $seeds = null, $timeout = null, $read_timeout = null, $persistent = null, $auth = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct($name, $seeds, $timeout, $read_timeout, $persistent, $auth);
    }

    public function _masters()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_masters();
    }

    public function _prefix($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix($key);
    }

    public function _redir()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_redir();
    }

    public function _serialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize($value);
    }

    public function _unserialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize($value);
    }

    public function _compress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress($value);
    }

    public function _uncompress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress($value);
    }

    public function _pack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack($value);
    }

    public function _unpack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack($value);
    }

    public function acl($key_or_address, $subcmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl($key_or_address, $subcmd, ...$args);
    }

    public function append($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append($key, $value);
    }

    public function bgrewriteaof($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof($key_or_address);
    }

    public function bgsave($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgsave($key_or_address);
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

    public function blpop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpop($key, $timeout_or_key, ...$extra_args);
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush($src, $dst, $timeout);
    }

    public function clearlasterror()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearlasterror();
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmax($key, $timeout_or_key, ...$extra_args);
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmin($key, $timeout_or_key, ...$extra_args);
    }

    public function client($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client($key_or_address, $arg, ...$other_args);
    }

    public function close()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close();
    }

    public function cluster($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->cluster($key_or_address, $arg, ...$other_args);
    }

    public function command(...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...$args);
    }

    public function config($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config($key_or_address, $arg, ...$other_args);
    }

    public function dbsize($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbsize($key_or_address);
    }

    public function decr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr($key);
    }

    public function decrby($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrby($key, $value);
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

    public function exists($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists($key);
    }

    public function expire($key, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire($key, $timeout);
    }

    public function expireat($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat($key, $timestamp);
    }

    public function flushall($key_or_address, $async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushall($key_or_address, $async);
    }

    public function flushdb($key_or_address, $async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushdb($key_or_address, $async);
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

    public function getbit($key, $offset)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getbit($key, $offset);
    }

    public function getlasterror()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getlasterror();
    }

    public function getmode()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getmode();
    }

    public function getoption($option)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getoption($option);
    }

    public function getrange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange($key, $start, $end);
    }

    public function getset($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset($key, $value);
    }

    public function hdel($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hdel($key, $member, ...$other_members);
    }

    public function hexists($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists($key, $member);
    }

    public function hget($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget($key, $member);
    }

    public function hgetall($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall($key);
    }

    public function hincrby($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrby($key, $member, $value);
    }

    public function hincrbyfloat($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrbyfloat($key, $member, $value);
    }

    public function hkeys($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys($key);
    }

    public function hlen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hlen($key);
    }

    public function hmget($key, $keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget($key, $keys);
    }

    public function hmset($key, $pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset($key, $pairs);
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function hset($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hset($key, $member, $value);
    }

    public function hsetnx($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx($key, $member, $value);
    }

    public function hstrlen($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen($key, $member);
    }

    public function hvals($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals($key);
    }

    public function incr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr($key);
    }

    public function incrby($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrby($key, $value);
    }

    public function incrbyfloat($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrbyfloat($key, $value);
    }

    public function info($key_or_address, $option = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info($key_or_address, $option);
    }

    public function keys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys($pattern);
    }

    public function lastsave($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastsave($key_or_address);
    }

    public function lget($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lget($key, $index);
    }

    public function lindex($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex($key, $index);
    }

    public function linsert($key, $position, $pivot, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->linsert($key, $position, $pivot, $value);
    }

    public function llen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->llen($key);
    }

    public function lpop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop($key);
    }

    public function lpush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpush($key, $value);
    }

    public function lpushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpushx($key, $value);
    }

    public function lrange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange($key, $start, $end);
    }

    public function lrem($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem($key, $value);
    }

    public function lset($key, $index, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lset($key, $index, $value);
    }

    public function ltrim($key, $start, $stop)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim($key, $start, $stop);
    }

    public function mget($keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget($keys);
    }

    public function mset($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset($pairs);
    }

    public function msetnx($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx($pairs);
    }

    public function multi()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi();
    }

    public function object($field, $key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object($field, $key);
    }

    public function persist($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist($key);
    }

    public function pexpire($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire($key, $timestamp);
    }

    public function pexpireat($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat($key, $timestamp);
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

    public function ping($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping($key_or_address);
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

    public function pubsub($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub($key_or_address, $arg, ...$other_args);
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe($pattern, ...$other_patterns);
    }

    public function randomkey($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomkey($key_or_address);
    }

    public function rawcommand($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand($cmd, ...$args);
    }

    public function rename($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename($key, $newkey);
    }

    public function renamenx($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renamenx($key, $newkey);
    }

    public function restore($ttl, $key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore($ttl, $key, $value);
    }

    public function role()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role();
    }

    public function rpop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop($key);
    }

    public function rpoplpush($src, $dst)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush($src, $dst);
    }

    public function rpush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpush($key, $value);
    }

    public function rpushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpushx($key, $value);
    }

    public function sadd($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sadd($key, $value);
    }

    public function saddarray($key, $options)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->saddarray($key, $options);
    }

    public function save($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save($key_or_address);
    }

    public function scan(&$i_iterator, $str_node, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($i_iterator, $str_node, $str_pattern, $i_count);
    }

    public function scard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard($key);
    }

    public function script($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script($key_or_address, $arg, ...$other_args);
    }

    public function sdiff($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiff($key, ...$other_keys);
    }

    public function sdiffstore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffstore($dst, $key, ...$other_keys);
    }

    public function set($key, $value, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set($key, $value, $opts);
    }

    public function setbit($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setbit($key, $offset, $value);
    }

    public function setex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex($key, $expire, $value);
    }

    public function setnx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx($key, $value);
    }

    public function setoption($option, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setoption($option, $value);
    }

    public function setrange($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setrange($key, $offset, $value);
    }

    public function sinter($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinter($key, ...$other_keys);
    }

    public function sinterstore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinterstore($dst, $key, ...$other_keys);
    }

    public function sismember($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember($key, $value);
    }

    public function slowlog($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog($key_or_address, $arg, ...$other_args);
    }

    public function smembers($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smembers($key);
    }

    public function smove($src, $dst, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smove($src, $dst, $value);
    }

    public function sort($key, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort($key, $options);
    }

    public function spop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop($key);
    }

    public function srandmember($key, $count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember($key, $count);
    }

    public function srem($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem($key, $value);
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

    public function sunion($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunion($key, ...$other_keys);
    }

    public function sunionstore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunionstore($dst, $key, ...$other_keys);
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

    public function unsubscribe($channel, ...$other_channels)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe($channel, ...$other_channels);
    }

    public function unlink($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink($key, ...$other_keys);
    }

    public function unwatch()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch();
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

    public function zadd($key, $score, $value, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zadd($key, $score, $value, ...$extra_args);
    }

    public function zcard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcard($key);
    }

    public function zcount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcount($key, $min, $max);
    }

    public function zincrby($key, $value, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zincrby($key, $value, $member);
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore($key, $keys, $weights, $aggregate);
    }

    public function zlexcount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zlexcount($key, $min, $max);
    }

    public function zpopmax($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax($key);
    }

    public function zpopmin($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin($key);
    }

    public function zrange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrange($key, $start, $end, $scores);
    }

    public function zrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebylex($key, $min, $max, $offset, $limit);
    }

    public function zrangebyscore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebyscore($key, $start, $end, $options);
    }

    public function zrank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrank($key, $member);
    }

    public function zrem($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrem($key, $member, ...$other_members);
    }

    public function zremrangebylex($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebylex($key, $min, $max);
    }

    public function zremrangebyrank($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyrank($key, $min, $max);
    }

    public function zremrangebyscore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyscore($key, $min, $max);
    }

    public function zrevrange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrange($key, $start, $end, $scores);
    }

    public function zrevrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex($key, $min, $max, $offset, $limit);
    }

    public function zrevrangebyscore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebyscore($key, $start, $end, $options);
    }

    public function zrevrank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrank($key, $member);
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($str_key, $i_iterator, $str_pattern, $i_count);
    }

    public function zscore($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscore($key, $member);
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore($key, $keys, $weights, $aggregate);
    }
}
