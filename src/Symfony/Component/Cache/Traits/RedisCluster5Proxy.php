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
        return $this->lazyObjectReal->__construct(...\func_get_args());
    }

    public function _masters()
    {
        return $this->lazyObjectReal->_masters(...\func_get_args());
    }

    public function _prefix($key)
    {
        return $this->lazyObjectReal->_prefix(...\func_get_args());
    }

    public function _redir()
    {
        return $this->lazyObjectReal->_redir(...\func_get_args());
    }

    public function _serialize($value)
    {
        return $this->lazyObjectReal->_serialize(...\func_get_args());
    }

    public function _unserialize($value)
    {
        return $this->lazyObjectReal->_unserialize(...\func_get_args());
    }

    public function _compress($value)
    {
        return $this->lazyObjectReal->_compress(...\func_get_args());
    }

    public function _uncompress($value)
    {
        return $this->lazyObjectReal->_uncompress(...\func_get_args());
    }

    public function _pack($value)
    {
        return $this->lazyObjectReal->_pack(...\func_get_args());
    }

    public function _unpack($value)
    {
        return $this->lazyObjectReal->_unpack(...\func_get_args());
    }

    public function acl($key_or_address, $subcmd, ...$args)
    {
        return $this->lazyObjectReal->acl(...\func_get_args());
    }

    public function append($key, $value)
    {
        return $this->lazyObjectReal->append(...\func_get_args());
    }

    public function bgrewriteaof($key_or_address)
    {
        return $this->lazyObjectReal->bgrewriteaof(...\func_get_args());
    }

    public function bgsave($key_or_address)
    {
        return $this->lazyObjectReal->bgsave(...\func_get_args());
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

    public function blpop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->blpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->brpop(...\func_get_args());
    }

    public function brpoplpush($src, $dst, $timeout)
    {
        return $this->lazyObjectReal->brpoplpush(...\func_get_args());
    }

    public function clearlasterror()
    {
        return $this->lazyObjectReal->clearlasterror(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args)
    {
        return $this->lazyObjectReal->bzpopmin(...\func_get_args());
    }

    public function client($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->client(...\func_get_args());
    }

    public function close()
    {
        return $this->lazyObjectReal->close(...\func_get_args());
    }

    public function cluster($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->cluster(...\func_get_args());
    }

    public function command(...$args)
    {
        return $this->lazyObjectReal->command(...\func_get_args());
    }

    public function config($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->config(...\func_get_args());
    }

    public function dbsize($key_or_address)
    {
        return $this->lazyObjectReal->dbsize(...\func_get_args());
    }

    public function decr($key)
    {
        return $this->lazyObjectReal->decr(...\func_get_args());
    }

    public function decrby($key, $value)
    {
        return $this->lazyObjectReal->decrby(...\func_get_args());
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

    public function exists($key)
    {
        return $this->lazyObjectReal->exists(...\func_get_args());
    }

    public function expire($key, $timeout)
    {
        return $this->lazyObjectReal->expire(...\func_get_args());
    }

    public function expireat($key, $timestamp)
    {
        return $this->lazyObjectReal->expireat(...\func_get_args());
    }

    public function flushall($key_or_address, $async = null)
    {
        return $this->lazyObjectReal->flushall(...\func_get_args());
    }

    public function flushdb($key_or_address, $async = null)
    {
        return $this->lazyObjectReal->flushdb(...\func_get_args());
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

    public function getbit($key, $offset)
    {
        return $this->lazyObjectReal->getbit(...\func_get_args());
    }

    public function getlasterror()
    {
        return $this->lazyObjectReal->getlasterror(...\func_get_args());
    }

    public function getmode()
    {
        return $this->lazyObjectReal->getmode(...\func_get_args());
    }

    public function getoption($option)
    {
        return $this->lazyObjectReal->getoption(...\func_get_args());
    }

    public function getrange($key, $start, $end)
    {
        return $this->lazyObjectReal->getrange(...\func_get_args());
    }

    public function getset($key, $value)
    {
        return $this->lazyObjectReal->getset(...\func_get_args());
    }

    public function hdel($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->hdel(...\func_get_args());
    }

    public function hexists($key, $member)
    {
        return $this->lazyObjectReal->hexists(...\func_get_args());
    }

    public function hget($key, $member)
    {
        return $this->lazyObjectReal->hget(...\func_get_args());
    }

    public function hgetall($key)
    {
        return $this->lazyObjectReal->hgetall(...\func_get_args());
    }

    public function hincrby($key, $member, $value)
    {
        return $this->lazyObjectReal->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $member, $value)
    {
        return $this->lazyObjectReal->hincrbyfloat(...\func_get_args());
    }

    public function hkeys($key)
    {
        return $this->lazyObjectReal->hkeys(...\func_get_args());
    }

    public function hlen($key)
    {
        return $this->lazyObjectReal->hlen(...\func_get_args());
    }

    public function hmget($key, $keys)
    {
        return $this->lazyObjectReal->hmget(...\func_get_args());
    }

    public function hmset($key, $pairs)
    {
        return $this->lazyObjectReal->hmset(...\func_get_args());
    }

    public function hscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->hscan(...\func_get_args());
    }

    public function hset($key, $member, $value)
    {
        return $this->lazyObjectReal->hset(...\func_get_args());
    }

    public function hsetnx($key, $member, $value)
    {
        return $this->lazyObjectReal->hsetnx(...\func_get_args());
    }

    public function hstrlen($key, $member)
    {
        return $this->lazyObjectReal->hstrlen(...\func_get_args());
    }

    public function hvals($key)
    {
        return $this->lazyObjectReal->hvals(...\func_get_args());
    }

    public function incr($key)
    {
        return $this->lazyObjectReal->incr(...\func_get_args());
    }

    public function incrby($key, $value)
    {
        return $this->lazyObjectReal->incrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value)
    {
        return $this->lazyObjectReal->incrbyfloat(...\func_get_args());
    }

    public function info($key_or_address, $option = null)
    {
        return $this->lazyObjectReal->info(...\func_get_args());
    }

    public function keys($pattern)
    {
        return $this->lazyObjectReal->keys(...\func_get_args());
    }

    public function lastsave($key_or_address)
    {
        return $this->lazyObjectReal->lastsave(...\func_get_args());
    }

    public function lget($key, $index)
    {
        return $this->lazyObjectReal->lget(...\func_get_args());
    }

    public function lindex($key, $index)
    {
        return $this->lazyObjectReal->lindex(...\func_get_args());
    }

    public function linsert($key, $position, $pivot, $value)
    {
        return $this->lazyObjectReal->linsert(...\func_get_args());
    }

    public function llen($key)
    {
        return $this->lazyObjectReal->llen(...\func_get_args());
    }

    public function lpop($key)
    {
        return $this->lazyObjectReal->lpop(...\func_get_args());
    }

    public function lpush($key, $value)
    {
        return $this->lazyObjectReal->lpush(...\func_get_args());
    }

    public function lpushx($key, $value)
    {
        return $this->lazyObjectReal->lpushx(...\func_get_args());
    }

    public function lrange($key, $start, $end)
    {
        return $this->lazyObjectReal->lrange(...\func_get_args());
    }

    public function lrem($key, $value)
    {
        return $this->lazyObjectReal->lrem(...\func_get_args());
    }

    public function lset($key, $index, $value)
    {
        return $this->lazyObjectReal->lset(...\func_get_args());
    }

    public function ltrim($key, $start, $stop)
    {
        return $this->lazyObjectReal->ltrim(...\func_get_args());
    }

    public function mget($keys)
    {
        return $this->lazyObjectReal->mget(...\func_get_args());
    }

    public function mset($pairs)
    {
        return $this->lazyObjectReal->mset(...\func_get_args());
    }

    public function msetnx($pairs)
    {
        return $this->lazyObjectReal->msetnx(...\func_get_args());
    }

    public function multi()
    {
        return $this->lazyObjectReal->multi(...\func_get_args());
    }

    public function object($field, $key)
    {
        return $this->lazyObjectReal->object(...\func_get_args());
    }

    public function persist($key)
    {
        return $this->lazyObjectReal->persist(...\func_get_args());
    }

    public function pexpire($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpire(...\func_get_args());
    }

    public function pexpireat($key, $timestamp)
    {
        return $this->lazyObjectReal->pexpireat(...\func_get_args());
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

    public function ping($key_or_address)
    {
        return $this->lazyObjectReal->ping(...\func_get_args());
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

    public function pubsub($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->pubsub(...\func_get_args());
    }

    public function punsubscribe($pattern, ...$other_patterns)
    {
        return $this->lazyObjectReal->punsubscribe(...\func_get_args());
    }

    public function randomkey($key_or_address)
    {
        return $this->lazyObjectReal->randomkey(...\func_get_args());
    }

    public function rawcommand($cmd, ...$args)
    {
        return $this->lazyObjectReal->rawcommand(...\func_get_args());
    }

    public function rename($key, $newkey)
    {
        return $this->lazyObjectReal->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey)
    {
        return $this->lazyObjectReal->renamenx(...\func_get_args());
    }

    public function restore($ttl, $key, $value)
    {
        return $this->lazyObjectReal->restore(...\func_get_args());
    }

    public function role()
    {
        return $this->lazyObjectReal->role(...\func_get_args());
    }

    public function rpop($key)
    {
        return $this->lazyObjectReal->rpop(...\func_get_args());
    }

    public function rpoplpush($src, $dst)
    {
        return $this->lazyObjectReal->rpoplpush(...\func_get_args());
    }

    public function rpush($key, $value)
    {
        return $this->lazyObjectReal->rpush(...\func_get_args());
    }

    public function rpushx($key, $value)
    {
        return $this->lazyObjectReal->rpushx(...\func_get_args());
    }

    public function sadd($key, $value)
    {
        return $this->lazyObjectReal->sadd(...\func_get_args());
    }

    public function saddarray($key, $options)
    {
        return $this->lazyObjectReal->saddarray(...\func_get_args());
    }

    public function save($key_or_address)
    {
        return $this->lazyObjectReal->save(...\func_get_args());
    }

    public function scan(&$i_iterator, $str_node, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->scan(...\func_get_args());
    }

    public function scard($key)
    {
        return $this->lazyObjectReal->scard(...\func_get_args());
    }

    public function script($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->script(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sdiff(...\func_get_args());
    }

    public function sdiffstore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sdiffstore(...\func_get_args());
    }

    public function set($key, $value, $opts = null)
    {
        return $this->lazyObjectReal->set(...\func_get_args());
    }

    public function setbit($key, $offset, $value)
    {
        return $this->lazyObjectReal->setbit(...\func_get_args());
    }

    public function setex($key, $expire, $value)
    {
        return $this->lazyObjectReal->setex(...\func_get_args());
    }

    public function setnx($key, $value)
    {
        return $this->lazyObjectReal->setnx(...\func_get_args());
    }

    public function setoption($option, $value)
    {
        return $this->lazyObjectReal->setoption(...\func_get_args());
    }

    public function setrange($key, $offset, $value)
    {
        return $this->lazyObjectReal->setrange(...\func_get_args());
    }

    public function sinter($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sinter(...\func_get_args());
    }

    public function sinterstore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sinterstore(...\func_get_args());
    }

    public function sismember($key, $value)
    {
        return $this->lazyObjectReal->sismember(...\func_get_args());
    }

    public function slowlog($key_or_address, $arg = null, ...$other_args)
    {
        return $this->lazyObjectReal->slowlog(...\func_get_args());
    }

    public function smembers($key)
    {
        return $this->lazyObjectReal->smembers(...\func_get_args());
    }

    public function smove($src, $dst, $value)
    {
        return $this->lazyObjectReal->smove(...\func_get_args());
    }

    public function sort($key, $options = null)
    {
        return $this->lazyObjectReal->sort(...\func_get_args());
    }

    public function spop($key)
    {
        return $this->lazyObjectReal->spop(...\func_get_args());
    }

    public function srandmember($key, $count = null)
    {
        return $this->lazyObjectReal->srandmember(...\func_get_args());
    }

    public function srem($key, $value)
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

    public function sunion($key, ...$other_keys)
    {
        return $this->lazyObjectReal->sunion(...\func_get_args());
    }

    public function sunionstore($dst, $key, ...$other_keys)
    {
        return $this->lazyObjectReal->sunionstore(...\func_get_args());
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

    public function unsubscribe($channel, ...$other_channels)
    {
        return $this->lazyObjectReal->unsubscribe(...\func_get_args());
    }

    public function unlink($key, ...$other_keys)
    {
        return $this->lazyObjectReal->unlink(...\func_get_args());
    }

    public function unwatch()
    {
        return $this->lazyObjectReal->unwatch(...\func_get_args());
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

    public function zadd($key, $score, $value, ...$extra_args)
    {
        return $this->lazyObjectReal->zadd(...\func_get_args());
    }

    public function zcard($key)
    {
        return $this->lazyObjectReal->zcard(...\func_get_args());
    }

    public function zcount($key, $min, $max)
    {
        return $this->lazyObjectReal->zcount(...\func_get_args());
    }

    public function zincrby($key, $value, $member)
    {
        return $this->lazyObjectReal->zincrby(...\func_get_args());
    }

    public function zinterstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zinterstore(...\func_get_args());
    }

    public function zlexcount($key, $min, $max)
    {
        return $this->lazyObjectReal->zlexcount(...\func_get_args());
    }

    public function zpopmax($key)
    {
        return $this->lazyObjectReal->zpopmax(...\func_get_args());
    }

    public function zpopmin($key)
    {
        return $this->lazyObjectReal->zpopmin(...\func_get_args());
    }

    public function zrange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zrange(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zrangebylex(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zrangebyscore(...\func_get_args());
    }

    public function zrank($key, $member)
    {
        return $this->lazyObjectReal->zrank(...\func_get_args());
    }

    public function zrem($key, $member, ...$other_members)
    {
        return $this->lazyObjectReal->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max)
    {
        return $this->lazyObjectReal->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $min, $max)
    {
        return $this->lazyObjectReal->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max)
    {
        return $this->lazyObjectReal->zremrangebyscore(...\func_get_args());
    }

    public function zrevrange($key, $start, $end, $scores = null)
    {
        return $this->lazyObjectReal->zrevrange(...\func_get_args());
    }

    public function zrevrangebylex($key, $min, $max, $offset = null, $limit = null)
    {
        return $this->lazyObjectReal->zrevrangebylex(...\func_get_args());
    }

    public function zrevrangebyscore($key, $start, $end, $options = null)
    {
        return $this->lazyObjectReal->zrevrangebyscore(...\func_get_args());
    }

    public function zrevrank($key, $member)
    {
        return $this->lazyObjectReal->zrevrank(...\func_get_args());
    }

    public function zscan($str_key, &$i_iterator, $str_pattern = null, $i_count = null)
    {
        return $this->lazyObjectReal->zscan(...\func_get_args());
    }

    public function zscore($key, $member)
    {
        return $this->lazyObjectReal->zscore(...\func_get_args());
    }

    public function zunionstore($key, $keys, $weights = null, $aggregate = null)
    {
        return $this->lazyObjectReal->zunionstore(...\func_get_args());
    }
}
