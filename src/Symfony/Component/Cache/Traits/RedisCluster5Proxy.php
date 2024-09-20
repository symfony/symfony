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
class RedisCluster5Proxy extends \RedisCluster implements ResetInterface, LazyObjectInterface
{
    use RedisProxyTrait {
        resetLazyObject as reset;
    }

    public function __construct($name, $seeds = null, $timeout = null, $read_timeout = null, $persistent = null, #[\SensitiveParameter] $auth = null)
    {
        $this->initializeLazyObject()->__construct(...\func_get_args());
    }

    public function _masters()
    {
        return $this->initializeLazyObject()->_masters(...\func_get_args());
    }

    public function _prefix($key)
    {
        return $this->initializeLazyObject()->_prefix(...\func_get_args());
    }

    public function _redir()
    {
        return $this->initializeLazyObject()->_redir(...\func_get_args());
    }

    public function _serialize($value)
    {
        return $this->initializeLazyObject()->_serialize(...\func_get_args());
    }

    public function _unserialize($value)
    {
        return $this->initializeLazyObject()->_unserialize(...\func_get_args());
    }

    public function _compress($value)
    {
        return $this->initializeLazyObject()->_compress(...\func_get_args());
    }

    public function _uncompress($value)
    {
        return $this->initializeLazyObject()->_uncompress(...\func_get_args());
    }

    public function _pack($value)
    {
        return $this->initializeLazyObject()->_pack(...\func_get_args());
    }

    public function _unpack($value)
    {
        return $this->initializeLazyObject()->_unpack(...\func_get_args());
    }

    public function acl($key_or_address, $subcmd, ...$args)
    {
        return $this->initializeLazyObject()->acl(...\func_get_args());
    }

    public function append($key, $value)
    {
        return $this->initializeLazyObject()->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address)
    {
        return $this->initializeLazyObject()->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address)
    {
        return $this->initializeLazyObject()->bgsave(...\func_get_args());
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

    public function blpop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->brpop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return $this->initializeLazyObject()->brpoplpush(...\func_get_args());
    }

    public function clearlasterror()
    {
        return $this->initializeLazyObject()->clearlasterror(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args)
    {
        return $this->initializeLazyObject()->bzpopmin(...\func_get_args());
    }

    public function client($key_or_address, $arg = null, ...$other_args)
    {
        return $this->initializeLazyObject()->client(...\func_get_args());
    }

    public function close()
    {
        return $this->initializeLazyObject()->close(...\func_get_args());
    }

    public function cluster($key_or_address, $arg = null, ...$other_args)
    {
        return $this->initializeLazyObject()->cluster(...\func_get_args());
    }

    public function command(...$args)
    {
        return $this->initializeLazyObject()->command(...\func_get_args());
    }

    public function config($key_or_address, $arg = null, ...$other_args)
    {
        return $this->initializeLazyObject()->config(...\func_get_args());
    }

    public function dbsize($key_or_address)
    {
        return $this->initializeLazyObject()->dbsize(...\func_get_args());
    }

    public function decr($key)
    {
        return $this->initializeLazyObject()->decr(...\func_get_args());
    }

    public function decrby($key, $value)
    {
        return $this->initializeLazyObject()->decrby(...\func_get_args());
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

    public function exists($key)
    {
        return $this->initializeLazyObject()->exists(...\func_get_args());
    }

    public function expire($key, $timeout)
    {
        return $this->initializeLazyObject()->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp)
    {
        return $this->initializeLazyObject()->expireat(...\func_get_args());
    }

    public function flushall($key_or_address, $async = null)
    {
        return $this->initializeLazyObject()->flushall(...\func_get_args());
    }

    public function flushdb($key_or_address, $async = null)
    {
        return $this->initializeLazyObject()->flushdb(...\func_get_args());
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

    public function getbit($key, $offset)
    {
        return $this->initializeLazyObject()->getbit(...\func_get_args());
    }

    public function getlasterror()
    {
        return $this->initializeLazyObject()->getlasterror(...\func_get_args());
    }

    public function getmode()
    {
        return $this->initializeLazyObject()->getmode(...\func_get_args());
    }

    public function getoption($option)
    {
        return $this->initializeLazyObject()->getoption(...\func_get_args());
    }

    public function getrange($key, $start, $end)
    {
        return $this->initializeLazyObject()->getrange(...\func_get_args());
    }

    public function getset($key, $value)
    {
        return $this->initializeLazyObject()->getset(...\func_get_args());
    }

    public function hdel($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->hdel(...\func_get_args());
    }

    public function hexists($key, $member)
    {
        return $this->initializeLazyObject()->hexists(...\func_get_args());
    }

    public function hget($key, $member)
    {
        return $this->initializeLazyObject()->hget(...\func_get_args());
    }

    public function hgetall($key)
    {
        return $this->initializeLazyObject()->hgetall(...\func_get_args());
    }

    public function hincrby($key, $member, $value)
    {
        return $this->initializeLazyObject()->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value)
    {
        return $this->initializeLazyObject()->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key)
    {
        return $this->initializeLazyObject()->hkeys(...\func_get_args());
    }

    public function hlen($key)
    {
        return $this->initializeLazyObject()->hlen(...\func_get_args());
    }

    public function hmget($key, $keys)
    {
        return $this->initializeLazyObject()->hmget(...\func_get_args());
    }

    public function hmset($key, $pairs)
    {
        return $this->initializeLazyObject()->hmset(...\func_get_args());
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->hscan($str_key, $i_iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function hset($key, $member, $value)
    {
        return $this->initializeLazyObject()->hset(...\func_get_args());
    }

    public function hsetnx($key, $member, $value)
    {
        return $this->initializeLazyObject()->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $member)
    {
        return $this->initializeLazyObject()->hstrlen(...\func_get_args());
    }

    public function hvals($key)
    {
        return $this->initializeLazyObject()->hvals(...\func_get_args());
    }

    public function incr($key)
    {
        return $this->initializeLazyObject()->incr(...\func_get_args());
    }

    public function incrby($key, $value)
    {
        return $this->initializeLazyObject()->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value)
    {
        return $this->initializeLazyObject()->incrbyfloat(...\func_get_args());
    }

    public function info($key_or_address, $option = null)
    {
        return $this->initializeLazyObject()->info(...\func_get_args());
    }

    public function keys($pattern)
    {
        return $this->initializeLazyObject()->keys(...\func_get_args());
    }

    public function lastsave($key_or_address)
    {
        return $this->initializeLazyObject()->lastsave(...\func_get_args());
    }

    public function lget($key, $index)
    {
        return $this->initializeLazyObject()->lget(...\func_get_args());
    }

    public function lindex($key, $index)
    {
        return $this->initializeLazyObject()->lindex(...\func_get_args());
    }

    public function linsert($key, $position, $pivot, $value)
    {
        return $this->initializeLazyObject()->linsert(...\func_get_args());
    }

    public function llen($key)
    {
        return $this->initializeLazyObject()->llen(...\func_get_args());
    }

    public function lpop($key)
    {
        return $this->initializeLazyObject()->lpop(...\func_get_args());
    }

    public function lpush($key, $value)
    {
        return $this->initializeLazyObject()->lpush(...\func_get_args());
    }

    public function lpushx($key, $value)
    {
        return $this->initializeLazyObject()->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $end)
    {
        return $this->initializeLazyObject()->lrange(...\func_get_args());
    }

    public function lrem($key, $value)
    {
        return $this->initializeLazyObject()->lrem(...\func_get_args());
    }

    public function lset($key, $index, $value)
    {
        return $this->initializeLazyObject()->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $stop)
    {
        return $this->initializeLazyObject()->ltrim(...\func_get_args());
    }

    public function mget($keys)
    {
        return $this->initializeLazyObject()->mget(...\func_get_args());
    }

    public function mset($pairs)
    {
        return $this->initializeLazyObject()->mset(...\func_get_args());
    }

    public function msetnx($pairs)
    {
        return $this->initializeLazyObject()->msetnx(...\func_get_args());
    }

    public function multi()
    {
        return $this->initializeLazyObject()->multi(...\func_get_args());
    }

    public function object($field, $key)
    {
        return $this->initializeLazyObject()->object(...\func_get_args());
    }

    public function persist($key)
    {
        return $this->initializeLazyObject()->persist(...\func_get_args());
    }

    public function pexpire($key, $timestamp)
    {
        return $this->initializeLazyObject()->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp)
    {
        return $this->initializeLazyObject()->pexpireat(...\func_get_args());
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

    public function ping($key_or_address)
    {
        return $this->initializeLazyObject()->ping(...\func_get_args());
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

    public function pubsub($key_or_address, $arg = null, ...$other_args)
    {
        return $this->initializeLazyObject()->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return $this->initializeLazyObject()->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address)
    {
        return $this->initializeLazyObject()->randomkey(...\func_get_args());
    }

    public function rawcommand($cmd, ...$args)
    {
        return $this->initializeLazyObject()->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey)
    {
        return $this->initializeLazyObject()->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey)
    {
        return $this->initializeLazyObject()->renamenx(...\func_get_args());
    }

    public function restore($ttl, $key, $value)
    {
        return $this->initializeLazyObject()->restore(...\func_get_args());
    }

    public function role()
    {
        return $this->initializeLazyObject()->role(...\func_get_args());
    }

    public function rpop($key)
    {
        return $this->initializeLazyObject()->rpop(...\func_get_args());
    }

    public function rpoplpush($src, $dst)
    {
        return $this->initializeLazyObject()->rpoplpush(...\func_get_args());
    }

    public function rpush($key, $value)
    {
        return $this->initializeLazyObject()->rpush(...\func_get_args());
    }

    public function rpushx($key, $value)
    {
        return $this->initializeLazyObject()->rpushx(...\func_get_args());
    }

    public function sadd($key, $value)
    {
        return $this->initializeLazyObject()->sadd(...\func_get_args());
    }

    public function saddarray($key, $options)
    {
        return $this->initializeLazyObject()->saddarray(...\func_get_args());
    }

    public function save($key_or_address)
    {
        return $this->initializeLazyObject()->save(...\func_get_args());
    }

    public function scan(&$i_iterator, $str_node, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->scan($i_iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function scard($key)
    {
        return $this->initializeLazyObject()->scard(...\func_get_args());
    }

    public function script($key_or_address, $arg = null, ...$other_args)
    {
        return $this->initializeLazyObject()->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sdiff(...\func_get_args());
    }

    public function sdiffstore($dst, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sdiffstore(...\func_get_args());
    }

    public function set($key, $value, $opts = null)
    {
        return $this->initializeLazyObject()->set(...\func_get_args());
    }

    public function setbit($key, $offset, $value)
    {
        return $this->initializeLazyObject()->setbit(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return $this->initializeLazyObject()->setex(...\func_get_args());
    }

    public function setnx($key, $value)
    {
        return $this->initializeLazyObject()->setnx(...\func_get_args());
    }

    public function setoption($option, $value)
    {
        return $this->initializeLazyObject()->setoption(...\func_get_args());
    }

    public function setrange($key, $offset, $value)
    {
        return $this->initializeLazyObject()->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sinter(...\func_get_args());
    }

    public function sinterstore($dst, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sinterstore(...\func_get_args());
    }

    public function sismember($key, $value)
    {
        return $this->initializeLazyObject()->sismember(...\func_get_args());
    }

    public function slowlog($key_or_address, $arg = null, ...$other_args)
    {
        return $this->initializeLazyObject()->slowlog(...\func_get_args());
    }

    public function smembers($key)
    {
        return $this->initializeLazyObject()->smembers(...\func_get_args());
    }

    public function smove($src, $dst, $value)
    {
        return $this->initializeLazyObject()->smove(...\func_get_args());
    }

    public function sort($key, $options = null)
    {
        return $this->initializeLazyObject()->sort(...\func_get_args());
    }

    public function spop($key)
    {
        return $this->initializeLazyObject()->spop(...\func_get_args());
    }

    public function srandmember($key, $count = null)
    {
        return $this->initializeLazyObject()->srandmember(...\func_get_args());
    }

    public function srem($key, $value)
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

    public function sunion($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sunion(...\func_get_args());
    }

    public function sunionstore($dst, $key, ...$other_keys)
    {
        return $this->initializeLazyObject()->sunionstore(...\func_get_args());
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

    public function unsubscribe($channel, ...$other_channels)
    {
        return $this->initializeLazyObject()->unsubscribe(...\func_get_args());
    }

    public function unlink($key, ...$other_keys)
    {
        return $this->initializeLazyObject()->unlink(...\func_get_args());
    }

    public function unwatch()
    {
        return $this->initializeLazyObject()->unwatch(...\func_get_args());
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

    public function zadd($key, $score, $value, ...$extra_args)
    {
        return $this->initializeLazyObject()->zadd(...\func_get_args());
    }

    public function zcard($key)
    {
        return $this->initializeLazyObject()->zcard(...\func_get_args());
    }

    public function zcount($key, $min, $max)
    {
        return $this->initializeLazyObject()->zcount(...\func_get_args());
    }

    public function zincrby($key, $value, $member)
    {
        return $this->initializeLazyObject()->zincrby(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->initializeLazyObject()->zinterstore(...\func_get_args());
    }

    public function zlexcount($key, $min, $max)
    {
        return $this->initializeLazyObject()->zlexcount(...\func_get_args());
    }

    public function zpopmax($key)
    {
        return $this->initializeLazyObject()->zpopmax(...\func_get_args());
    }

    public function zpopmin($key)
    {
        return $this->initializeLazyObject()->zpopmin(...\func_get_args());
    }

    public function zrange($key, $start, $end, $scores = null)
    {
        return $this->initializeLazyObject()->zrange(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->initializeLazyObject()->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null)
    {
        return $this->initializeLazyObject()->zrangebyscore(...\func_get_args());
    }

    public function zrank($key, $member)
    {
        return $this->initializeLazyObject()->zrank(...\func_get_args());
    }

    public function zrem($key, $member, ...$other_members)
    {
        return $this->initializeLazyObject()->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max)
    {
        return $this->initializeLazyObject()->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $min, $max)
    {
        return $this->initializeLazyObject()->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max)
    {
        return $this->initializeLazyObject()->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $start, $end, $scores = null)
    {
        return $this->initializeLazyObject()->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->initializeLazyObject()->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $start, $end, $options = null)
    {
        return $this->initializeLazyObject()->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $member)
    {
        return $this->initializeLazyObject()->zrevrank(...\func_get_args());
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->initializeLazyObject()->zscan($str_key, $i_iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zscore($key, $member)
    {
        return $this->initializeLazyObject()->zscore(...\func_get_args());
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->initializeLazyObject()->zunionstore(...\func_get_args());
    }
}
