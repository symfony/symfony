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

use Relay\Relay;
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
class RelayProxy extends Relay implements ResetInterface, LazyObjectInterface
{
    use LazyProxyTrait {
        resetLazyObject as reset;
    }

    private const LAZY_OBJECT_PROPERTY_SCOPES = [
        'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
        "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
    ];

    public function __construct($host = null, $port = 6379, $connect_timeout = 0.0, $command_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0)
    {
        return $this->lazyObjectReal->__construct(...\func_get_args());
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
    {
        return $this->lazyObjectReal->connect(...\func_get_args());
    }

    public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
    {
        return $this->lazyObjectReal->pconnect(...\func_get_args());
    }

    public function close(): bool
    {
        return $this->lazyObjectReal->close(...\func_get_args());
    }

    public function pclose(): bool
    {
        return $this->lazyObjectReal->pclose(...\func_get_args());
    }

    public function listen($callback): bool
    {
        return $this->lazyObjectReal->listen(...\func_get_args());
    }

    public function onFlushed($callback): bool
    {
        return $this->lazyObjectReal->onFlushed(...\func_get_args());
    }

    public function onInvalidated($callback, $pattern = null): bool
    {
        return $this->lazyObjectReal->onInvalidated(...\func_get_args());
    }

    public function dispatchEvents(): false|int
    {
        return $this->lazyObjectReal->dispatchEvents(...\func_get_args());
    }

    public function getOption($option): mixed
    {
        return $this->lazyObjectReal->getOption(...\func_get_args());
    }

    public function option($option, $value = null): mixed
    {
        return $this->lazyObjectReal->option(...\func_get_args());
    }

    public function setOption($option, $value): bool
    {
        return $this->lazyObjectReal->setOption(...\func_get_args());
    }

    public function getTimeout(): false|float
    {
        return $this->lazyObjectReal->getTimeout(...\func_get_args());
    }

    public function timeout(): false|float
    {
        return $this->lazyObjectReal->timeout(...\func_get_args());
    }

    public function getReadTimeout(): false|float
    {
        return $this->lazyObjectReal->getReadTimeout(...\func_get_args());
    }

    public function readTimeout(): false|float
    {
        return $this->lazyObjectReal->readTimeout(...\func_get_args());
    }

    public function getBytes(): array
    {
        return $this->lazyObjectReal->getBytes(...\func_get_args());
    }

    public function bytes(): array
    {
        return $this->lazyObjectReal->bytes(...\func_get_args());
    }

    public function getHost(): false|string
    {
        return $this->lazyObjectReal->getHost(...\func_get_args());
    }

    public function isConnected(): bool
    {
        return $this->lazyObjectReal->isConnected(...\func_get_args());
    }

    public function getPort(): false|int
    {
        return $this->lazyObjectReal->getPort(...\func_get_args());
    }

    public function getAuth(): mixed
    {
        return $this->lazyObjectReal->getAuth(...\func_get_args());
    }

    public function getDbNum(): mixed
    {
        return $this->lazyObjectReal->getDbNum(...\func_get_args());
    }

    public function _serialize($value): mixed
    {
        return $this->lazyObjectReal->_serialize(...\func_get_args());
    }

    public function _unserialize($value): mixed
    {
        return $this->lazyObjectReal->_unserialize(...\func_get_args());
    }

    public function _compress($value): string
    {
        return $this->lazyObjectReal->_compress(...\func_get_args());
    }

    public function _uncompress($value): string
    {
        return $this->lazyObjectReal->_uncompress(...\func_get_args());
    }

    public function _pack($value): string
    {
        return $this->lazyObjectReal->_pack(...\func_get_args());
    }

    public function _unpack($value): mixed
    {
        return $this->lazyObjectReal->_unpack(...\func_get_args());
    }

    public function _prefix($value): string
    {
        return $this->lazyObjectReal->_prefix(...\func_get_args());
    }

    public function getLastError(): ?string
    {
        return $this->lazyObjectReal->getLastError(...\func_get_args());
    }

    public function clearLastError(): bool
    {
        return $this->lazyObjectReal->clearLastError(...\func_get_args());
    }

    public function endpointId(): false|string
    {
        return $this->lazyObjectReal->endpointId(...\func_get_args());
    }

    public function getPersistentID(): false|string
    {
        return $this->lazyObjectReal->getPersistentID(...\func_get_args());
    }

    public function socketId(): false|string
    {
        return $this->lazyObjectReal->socketId(...\func_get_args());
    }

    public function rawCommand($cmd, ...$args): mixed
    {
        return $this->lazyObjectReal->rawCommand(...\func_get_args());
    }

    public function select($db): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->select(...\func_get_args());
    }

    public function auth(#[\SensitiveParameter] $auth): bool
    {
        return $this->lazyObjectReal->auth(...\func_get_args());
    }

    public function info(...$sections): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->info(...\func_get_args());
    }

    public function flushdb($async = false): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->flushdb(...\func_get_args());
    }

    public function flushall($async = false): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->flushall(...\func_get_args());
    }

    public function fcall($name, $argv = [], $keys = [], $handler = null): mixed
    {
        return $this->lazyObjectReal->fcall(...\func_get_args());
    }

    public function fcall_ro($name, $argv = [], $keys = [], $handler = null): mixed
    {
        return $this->lazyObjectReal->fcall_ro(...\func_get_args());
    }

    public function function($op, ...$args): mixed
    {
        return $this->lazyObjectReal->function(...\func_get_args());
    }

    public function dbsize(): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->dbsize(...\func_get_args());
    }

    public function dump($key): \Relay\Relay|false|string
    {
        return $this->lazyObjectReal->dump(...\func_get_args());
    }

    public function replicaof($host = null, $port = 0): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->replicaof(...\func_get_args());
    }

    public function restore($key, $ttl, $value, $options = null): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->restore(...\func_get_args());
    }

    public function migrate($host, $port, $key, $dstdb, $timeout, $copy = false, $replace = false, #[\SensitiveParameter] $credentials = null): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->migrate(...\func_get_args());
    }

    public function copy($src, $dst, $options = null): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->copy(...\func_get_args());
    }

    public function echo($arg): \Relay\Relay|bool|string
    {
        return $this->lazyObjectReal->echo(...\func_get_args());
    }

    public function ping($arg = null): \Relay\Relay|bool|string
    {
        return $this->lazyObjectReal->ping(...\func_get_args());
    }

    public function idleTime(): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->idleTime(...\func_get_args());
    }

    public function randomkey(): \Relay\Relay|bool|null|string
    {
        return $this->lazyObjectReal->randomkey(...\func_get_args());
    }

    public function time(): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->time(...\func_get_args());
    }

    public function bgrewriteaof(): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->bgrewriteaof(...\func_get_args());
    }

    public function lastsave(): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->lastsave(...\func_get_args());
    }

    public function bgsave(): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->bgsave(...\func_get_args());
    }

    public function save(): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->save(...\func_get_args());
    }

    public function role(): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->role(...\func_get_args());
    }

    public function ttl($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->ttl(...\func_get_args());
    }

    public function pttl($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->pttl(...\func_get_args());
    }

    public function exists(...$keys): \Relay\Relay|bool|int
    {
        return $this->lazyObjectReal->exists(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval(...\func_get_args());
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->eval_ro(...\func_get_args());
    }

    public function evalsha($sha, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha(...\func_get_args());
    }

    public function evalsha_ro($sha, $args = [], $num_keys = 0): mixed
    {
        return $this->lazyObjectReal->evalsha_ro(...\func_get_args());
    }

    public function client($operation, ...$args): mixed
    {
        return $this->lazyObjectReal->client(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->geoadd(...\func_get_args());
    }

    public function geodist($key, $src, $dst, $unit = null): \Relay\Relay|false|float
    {
        return $this->lazyObjectReal->geodist(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->geohash(...\func_get_args());
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadius(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadiusbymember_ro(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return $this->lazyObjectReal->georadius_ro(...\func_get_args());
    }

    public function geosearch($key, $position, $shape, $unit, $options = []): \Relay\Relay|array
    {
        return $this->lazyObjectReal->geosearch(...\func_get_args());
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->geosearchstore(...\func_get_args());
    }

    public function get($key): mixed
    {
        return $this->lazyObjectReal->get(...\func_get_args());
    }

    public function getset($key, $value): mixed
    {
        return $this->lazyObjectReal->getset(...\func_get_args());
    }

    public function getrange($key, $start, $end): \Relay\Relay|false|string
    {
        return $this->lazyObjectReal->getrange(...\func_get_args());
    }

    public function setrange($key, $start, $value): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->setrange(...\func_get_args());
    }

    public function getbit($key, $pos): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->getbit(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1, $by_bit = false): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->bitcount(...\func_get_args());
    }

    public function config($operation, $key = null, $value = null): \Relay\Relay|array|bool
    {
        return $this->lazyObjectReal->config(...\func_get_args());
    }

    public function command(...$args): \Relay\Relay|array|false|int
    {
        return $this->lazyObjectReal->command(...\func_get_args());
    }

    public function bitop($operation, $dstkey, $srckey, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null, $bybit = false): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->bitpos(...\func_get_args());
    }

    public function setbit($key, $pos, $val): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->setbit(...\func_get_args());
    }

    public function acl($cmd, ...$args): mixed
    {
        return $this->lazyObjectReal->acl(...\func_get_args());
    }

    public function append($key, $value): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->append(...\func_get_args());
    }

    public function set($key, $value, $options = null): mixed
    {
        return $this->lazyObjectReal->set(...\func_get_args());
    }

    public function getex($key, $options = null): mixed
    {
        return $this->lazyObjectReal->getex(...\func_get_args());
    }

    public function getdel($key): mixed
    {
        return $this->lazyObjectReal->getdel(...\func_get_args());
    }

    public function setex($key, $seconds, $value): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->setex(...\func_get_args());
    }

    public function pfadd($key, $elements): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->pfadd(...\func_get_args());
    }

    public function pfcount($key): \Relay\Relay|int
    {
        return $this->lazyObjectReal->pfcount(...\func_get_args());
    }

    public function pfmerge($dst, $srckeys): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->pfmerge(...\func_get_args());
    }

    public function psetex($key, $milliseconds, $value): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->psetex(...\func_get_args());
    }

    public function publish($channel, $message): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->publish(...\func_get_args());
    }

    public function setnx($key, $value): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->setnx(...\func_get_args());
    }

    public function mget($keys): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->mget(...\func_get_args());
    }

    public function move($key, $db): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->move(...\func_get_args());
    }

    public function mset($kvals): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->mset(...\func_get_args());
    }

    public function msetnx($kvals): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->msetnx(...\func_get_args());
    }

    public function rename($key, $newkey): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->renamenx(...\func_get_args());
    }

    public function del(...$keys): \Relay\Relay|bool|int
    {
        return $this->lazyObjectReal->del(...\func_get_args());
    }

    public function unlink(...$keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->unlink(...\func_get_args());
    }

    public function expire($key, $seconds, $mode = null): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->expire(...\func_get_args());
    }

    public function pexpire($key, $milliseconds): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->pexpire(...\func_get_args());
    }

    public function expireat($key, $timestamp): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->expireat(...\func_get_args());
    }

    public function expiretime($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->expiretime(...\func_get_args());
    }

    public function pexpireat($key, $timestamp_ms): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->pexpireat(...\func_get_args());
    }

    public function pexpiretime($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->pexpiretime(...\func_get_args());
    }

    public function persist($key): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->persist(...\func_get_args());
    }

    public function type($key): \Relay\Relay|bool|int|string
    {
        return $this->lazyObjectReal->type(...\func_get_args());
    }

    public function lmove($srckey, $dstkey, $srcpos, $dstpos): \Relay\Relay|false|null|string
    {
        return $this->lazyObjectReal->lmove(...\func_get_args());
    }

    public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): \Relay\Relay|false|null|string
    {
        return $this->lazyObjectReal->blmove(...\func_get_args());
    }

    public function lrange($key, $start, $stop): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->lrange(...\func_get_args());
    }

    public function lpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->lpush(...\func_get_args());
    }

    public function rpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->rpush(...\func_get_args());
    }

    public function lpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->lpushx(...\func_get_args());
    }

    public function rpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->rpushx(...\func_get_args());
    }

    public function lset($key, $index, $mem): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->lset(...\func_get_args());
    }

    public function lpop($key, $count = 1): mixed
    {
        return $this->lazyObjectReal->lpop(...\func_get_args());
    }

    public function lpos($key, $value, $options = null): \Relay\Relay|array|false|int|null
    {
        return $this->lazyObjectReal->lpos(...\func_get_args());
    }

    public function rpop($key, $count = 1): mixed
    {
        return $this->lazyObjectReal->rpop(...\func_get_args());
    }

    public function rpoplpush($source, $dest): mixed
    {
        return $this->lazyObjectReal->rpoplpush(...\func_get_args());
    }

    public function brpoplpush($source, $dest, $timeout): mixed
    {
        return $this->lazyObjectReal->brpoplpush(...\func_get_args());
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->blpop(...\func_get_args());
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->blmpop(...\func_get_args());
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->bzmpop(...\func_get_args());
    }

    public function lmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->lmpop(...\func_get_args());
    }

    public function zmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->zmpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->brpop(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return $this->lazyObjectReal->bzpopmin(...\func_get_args());
    }

    public function object($op, $key): mixed
    {
        return $this->lazyObjectReal->object(...\func_get_args());
    }

    public function geopos($key, ...$members): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->geopos(...\func_get_args());
    }

    public function lrem($key, $mem, $count = 0): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->lrem(...\func_get_args());
    }

    public function lindex($key, $index): mixed
    {
        return $this->lazyObjectReal->lindex(...\func_get_args());
    }

    public function linsert($key, $op, $pivot, $element): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->linsert(...\func_get_args());
    }

    public function ltrim($key, $start, $end): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->ltrim(...\func_get_args());
    }

    public function hget($hash, $member): mixed
    {
        return $this->lazyObjectReal->hget(...\func_get_args());
    }

    public function hstrlen($hash, $member): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->hstrlen(...\func_get_args());
    }

    public function hgetall($hash): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->hgetall(...\func_get_args());
    }

    public function hkeys($hash): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->hkeys(...\func_get_args());
    }

    public function hvals($hash): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->hvals(...\func_get_args());
    }

    public function hmget($hash, $members): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->hmget(...\func_get_args());
    }

    public function hrandfield($hash, $options = null): \Relay\Relay|array|false|string
    {
        return $this->lazyObjectReal->hrandfield(...\func_get_args());
    }

    public function hmset($hash, $members): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->hmset(...\func_get_args());
    }

    public function hexists($hash, $member): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->hexists(...\func_get_args());
    }

    public function hsetnx($hash, $member, $value): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->hsetnx(...\func_get_args());
    }

    public function hset($key, $mem, $val, ...$kvals): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->hset(...\func_get_args());
    }

    public function hdel($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->hdel(...\func_get_args());
    }

    public function hincrby($key, $mem, $value): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $mem, $value): \Relay\Relay|bool|float
    {
        return $this->lazyObjectReal->hincrbyfloat(...\func_get_args());
    }

    public function incr($key, $by = 1): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->incr(...\func_get_args());
    }

    public function decr($key, $by = 1): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->decr(...\func_get_args());
    }

    public function incrby($key, $value): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->incrby(...\func_get_args());
    }

    public function decrby($key, $value): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->decrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value): \Relay\Relay|false|float
    {
        return $this->lazyObjectReal->incrbyfloat(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->sdiff(...\func_get_args());
    }

    public function sdiffstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->sdiffstore(...\func_get_args());
    }

    public function sinter($key, ...$other_keys): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->sinter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->sintercard(...\func_get_args());
    }

    public function sinterstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->sinterstore(...\func_get_args());
    }

    public function sunion($key, ...$other_keys): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->sunion(...\func_get_args());
    }

    public function sunionstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->sunionstore(...\func_get_args());
    }

    public function touch($key_or_array, ...$more_keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->touch(...\func_get_args());
    }

    public function pipeline(): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->pipeline(...\func_get_args());
    }

    public function multi($mode = 0): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->multi(...\func_get_args());
    }

    public function exec(): \Relay\Relay|array|bool
    {
        return $this->lazyObjectReal->exec(...\func_get_args());
    }

    public function wait($replicas, $timeout): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->wait(...\func_get_args());
    }

    public function watch($key, ...$other_keys): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->watch(...\func_get_args());
    }

    public function unwatch(): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->unwatch(...\func_get_args());
    }

    public function discard(): bool
    {
        return $this->lazyObjectReal->discard(...\func_get_args());
    }

    public function getMode($masked = false): int
    {
        return $this->lazyObjectReal->getMode(...\func_get_args());
    }

    public function clearBytes(): void
    {
        $this->lazyObjectReal->clearBytes(...\func_get_args());
    }

    public function scan(&$iterator, $match = null, $count = 0, $type = null): array|false
    {
        return $this->lazyObjectReal->scan(...\func_get_args());
    }

    public function hscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->lazyObjectReal->hscan(...\func_get_args());
    }

    public function sscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->lazyObjectReal->sscan(...\func_get_args());
    }

    public function zscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return $this->lazyObjectReal->zscan(...\func_get_args());
    }

    public function keys($pattern): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->keys(...\func_get_args());
    }

    public function slowlog($operation, ...$extra_args): \Relay\Relay|array|bool|int
    {
        return $this->lazyObjectReal->slowlog(...\func_get_args());
    }

    public function smembers($set): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->smembers(...\func_get_args());
    }

    public function sismember($set, $member): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->sismember(...\func_get_args());
    }

    public function smismember($set, ...$members): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->smismember(...\func_get_args());
    }

    public function srem($set, $member, ...$members): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->srem(...\func_get_args());
    }

    public function sadd($set, $member, ...$members): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->sadd(...\func_get_args());
    }

    public function sort($key, $options = []): \Relay\Relay|array|false|int
    {
        return $this->lazyObjectReal->sort(...\func_get_args());
    }

    public function sort_ro($key, $options = []): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->sort_ro(...\func_get_args());
    }

    public function smove($srcset, $dstset, $member): \Relay\Relay|bool
    {
        return $this->lazyObjectReal->smove(...\func_get_args());
    }

    public function spop($set, $count = 1): mixed
    {
        return $this->lazyObjectReal->spop(...\func_get_args());
    }

    public function srandmember($set, $count = 1): mixed
    {
        return $this->lazyObjectReal->srandmember(...\func_get_args());
    }

    public function scard($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->scard(...\func_get_args());
    }

    public function script($command, ...$args): mixed
    {
        return $this->lazyObjectReal->script(...\func_get_args());
    }

    public function strlen($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->strlen(...\func_get_args());
    }

    public function hlen($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->hlen(...\func_get_args());
    }

    public function llen($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->llen(...\func_get_args());
    }

    public function xack($key, $group, $ids): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->xack(...\func_get_args());
    }

    public function xadd($key, $id, $values, $maxlen = 0, $approx = false, $nomkstream = false): \Relay\Relay|false|string
    {
        return $this->lazyObjectReal->xadd(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Relay\Relay|array|bool
    {
        return $this->lazyObjectReal->xclaim(...\func_get_args());
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Relay\Relay|array|bool
    {
        return $this->lazyObjectReal->xautoclaim(...\func_get_args());
    }

    public function xlen($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->xlen(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return $this->lazyObjectReal->xgroup(...\func_get_args());
    }

    public function xdel($key, $ids): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->xdel(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return $this->lazyObjectReal->xinfo(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null, $idle = 0): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->xrange(...\func_get_args());
    }

    public function xrevrange($key, $end, $start, $count = -1): \Relay\Relay|array|bool
    {
        return $this->lazyObjectReal->xrevrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): \Relay\Relay|array|bool|null
    {
        return $this->lazyObjectReal->xread(...\func_get_args());
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Relay\Relay|array|bool|null
    {
        return $this->lazyObjectReal->xreadgroup(...\func_get_args());
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->xtrim(...\func_get_args());
    }

    public function zadd($key, ...$args): mixed
    {
        return $this->lazyObjectReal->zadd(...\func_get_args());
    }

    public function zrandmember($key, $options = null): mixed
    {
        return $this->lazyObjectReal->zrandmember(...\func_get_args());
    }

    public function zrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zrange(...\func_get_args());
    }

    public function zrevrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zrevrange(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zrangebyscore(...\func_get_args());
    }

    public function zrevrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zrevrangebyscore(...\func_get_args());
    }

    public function zrangestore($dst, $src, $start, $end, $options = null): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zrangestore(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zrangebylex(...\func_get_args());
    }

    public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zrevrangebylex(...\func_get_args());
    }

    public function zrank($key, $rank): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zrank(...\func_get_args());
    }

    public function zrevrank($key, $rank): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zrevrank(...\func_get_args());
    }

    public function zrem($key, ...$args): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $start, $end): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zremrangebyscore(...\func_get_args());
    }

    public function zcard($key): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zcard(...\func_get_args());
    }

    public function zcount($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zcount(...\func_get_args());
    }

    public function zdiff($keys, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zdiff(...\func_get_args());
    }

    public function zdiffstore($dst, $keys): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zdiffstore(...\func_get_args());
    }

    public function zincrby($key, $score, $mem): \Relay\Relay|false|float
    {
        return $this->lazyObjectReal->zincrby(...\func_get_args());
    }

    public function zlexcount($key, $min, $max): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zlexcount(...\func_get_args());
    }

    public function zmscore($key, ...$mems): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zmscore(...\func_get_args());
    }

    public function zscore($key, $member): \Relay\Relay|false|float
    {
        return $this->lazyObjectReal->zscore(...\func_get_args());
    }

    public function zinter($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zinter(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zintercard(...\func_get_args());
    }

    public function zinterstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zinterstore(...\func_get_args());
    }

    public function zunion($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zunion(...\func_get_args());
    }

    public function zunionstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return $this->lazyObjectReal->zunionstore(...\func_get_args());
    }

    public function zpopmin($key, $count = 1): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zpopmin(...\func_get_args());
    }

    public function zpopmax($key, $count = 1): \Relay\Relay|array|false
    {
        return $this->lazyObjectReal->zpopmax(...\func_get_args());
    }

    public function _getKeys()
    {
        return $this->lazyObjectReal->_getKeys(...\func_get_args());
    }
}
