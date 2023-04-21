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
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct(...\func_get_args());
    }

    public function _masters()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_masters(...\func_get_args());
    }

    public function _prefix($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix(...\func_get_args());
    }

    public function _redir()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_redir(...\func_get_args());
    }

    public function _serialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize(...\func_get_args());
    }

    public function _unserialize($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize(...\func_get_args());
    }

    public function _compress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress(...\func_get_args());
    }

    public function _uncompress($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress(...\func_get_args());
    }

    public function _pack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack(...\func_get_args());
    }

    public function _unpack($value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack(...\func_get_args());
    }

    public function acl($key_or_address, $subcmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl(...\func_get_args());
    }

    public function append($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgsave(...\func_get_args());
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

    public function blpop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush(...\func_get_args());
    }

    public function clearlasterror()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearlasterror(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmin(...\func_get_args());
    }

    public function client($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client(...\func_get_args());
    }

    public function close()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close(...\func_get_args());
    }

    public function cluster($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->cluster(...\func_get_args());
    }

    public function command(...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...\func_get_args());
    }

    public function config($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config(...\func_get_args());
    }

    public function dbsize($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbsize(...\func_get_args());
    }

    public function decr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr(...\func_get_args());
    }

    public function decrby($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrby(...\func_get_args());
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

    public function exists($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists(...\func_get_args());
    }

    public function expire($key, $timeout)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat(...\func_get_args());
    }

    public function flushall($key_or_address, $async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushall(...\func_get_args());
    }

    public function flushdb($key_or_address, $async = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushdb(...\func_get_args());
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

    public function getbit($key, $offset)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getbit(...\func_get_args());
    }

    public function getlasterror()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getlasterror(...\func_get_args());
    }

    public function getmode()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getmode(...\func_get_args());
    }

    public function getoption($option)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getoption(...\func_get_args());
    }

    public function getrange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange(...\func_get_args());
    }

    public function getset($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset(...\func_get_args());
    }

    public function hdel($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hdel(...\func_get_args());
    }

    public function hexists($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists(...\func_get_args());
    }

    public function hget($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget(...\func_get_args());
    }

    public function hgetall($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall(...\func_get_args());
    }

    public function hincrby($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys(...\func_get_args());
    }

    public function hlen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hlen(...\func_get_args());
    }

    public function hmget($key, $keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget(...\func_get_args());
    }

    public function hmset($key, $pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset(...\func_get_args());
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($str_key, $i_iterator, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 4));
    }

    public function hset($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hset(...\func_get_args());
    }

    public function hsetnx($key, $member, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen(...\func_get_args());
    }

    public function hvals($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals(...\func_get_args());
    }

    public function incr($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr(...\func_get_args());
    }

    public function incrby($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrbyfloat(...\func_get_args());
    }

    public function info($key_or_address, $option = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info(...\func_get_args());
    }

    public function keys($pattern)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys(...\func_get_args());
    }

    public function lastsave($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastsave(...\func_get_args());
    }

    public function lget($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lget(...\func_get_args());
    }

    public function lindex($key, $index)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex(...\func_get_args());
    }

    public function linsert($key, $position, $pivot, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->linsert(...\func_get_args());
    }

    public function llen($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->llen(...\func_get_args());
    }

    public function lpop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop(...\func_get_args());
    }

    public function lpush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpush(...\func_get_args());
    }

    public function lpushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $end)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange(...\func_get_args());
    }

    public function lrem($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem(...\func_get_args());
    }

    public function lset($key, $index, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $stop)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim(...\func_get_args());
    }

    public function mget($keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
    }

    public function mset($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset(...\func_get_args());
    }

    public function msetnx($pairs)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx(...\func_get_args());
    }

    public function multi()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi(...\func_get_args());
    }

    public function object($field, $key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object(...\func_get_args());
    }

    public function persist($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist(...\func_get_args());
    }

    public function pexpire($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat(...\func_get_args());
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

    public function ping($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping(...\func_get_args());
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

    public function pubsub($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomkey(...\func_get_args());
    }

    public function rawcommand($cmd, ...$args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renamenx(...\func_get_args());
    }

    public function restore($ttl, $key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore(...\func_get_args());
    }

    public function role()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role(...\func_get_args());
    }

    public function rpop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop(...\func_get_args());
    }

    public function rpoplpush($src, $dst)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush(...\func_get_args());
    }

    public function rpush($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpush(...\func_get_args());
    }

    public function rpushx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpushx(...\func_get_args());
    }

    public function sadd($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sadd(...\func_get_args());
    }

    public function saddarray($key, $options)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->saddarray(...\func_get_args());
    }

    public function save($key_or_address)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save(...\func_get_args());
    }

    public function scan(&$i_iterator, $str_node, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($i_iterator, $str_node, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 4));
    }

    public function scard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard(...\func_get_args());
    }

    public function script($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiff(...\func_get_args());
    }

    public function sdiffstore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffstore(...\func_get_args());
    }

    public function set($key, $value, $opts = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set(...\func_get_args());
    }

    public function setbit($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setbit(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex(...\func_get_args());
    }

    public function setnx($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx(...\func_get_args());
    }

    public function setoption($option, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setoption(...\func_get_args());
    }

    public function setrange($key, $offset, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinter(...\func_get_args());
    }

    public function sinterstore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinterstore(...\func_get_args());
    }

    public function sismember($key, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember(...\func_get_args());
    }

    public function slowlog($key_or_address, $arg = null, ...$other_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog(...\func_get_args());
    }

    public function smembers($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smembers(...\func_get_args());
    }

    public function smove($src, $dst, $value)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smove(...\func_get_args());
    }

    public function sort($key, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort(...\func_get_args());
    }

    public function spop($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop(...\func_get_args());
    }

    public function srandmember($key, $count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember(...\func_get_args());
    }

    public function srem($key, $value)
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

    public function sunion($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunion(...\func_get_args());
    }

    public function sunionstore($dst, $key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunionstore(...\func_get_args());
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

    public function unsubscribe($channel, ...$other_channels)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe(...\func_get_args());
    }

    public function unlink($key, ...$other_keys)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink(...\func_get_args());
    }

    public function unwatch()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch(...\func_get_args());
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

    public function zadd($key, $score, $value, ...$extra_args)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zadd(...\func_get_args());
    }

    public function zcard($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcard(...\func_get_args());
    }

    public function zcount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcount(...\func_get_args());
    }

    public function zincrby($key, $value, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zincrby(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore(...\func_get_args());
    }

    public function zlexcount($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zlexcount(...\func_get_args());
    }

    public function zpopmax($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax(...\func_get_args());
    }

    public function zpopmin($key)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin(...\func_get_args());
    }

    public function zrange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrange(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebyscore(...\func_get_args());
    }

    public function zrank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrank(...\func_get_args());
    }

    public function zrem($key, $member, ...$other_members)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $start, $end, $scores = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $start, $end, $options = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrank(...\func_get_args());
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($str_key, $i_iterator, $str_pattern, $i_count, ...\array_slice(\func_get_args(), 4));
    }

    public function zscore($key, $member)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscore(...\func_get_args());
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore(...\func_get_args());
    }
}
