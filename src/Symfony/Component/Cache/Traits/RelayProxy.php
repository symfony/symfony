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

use Symfony\Component\Cache\Traits\Relay\CopyTrait;
use Symfony\Component\Cache\Traits\Relay\GeosearchTrait;
use Symfony\Component\Cache\Traits\Relay\GetrangeTrait;
use Symfony\Component\Cache\Traits\Relay\HsetTrait;
use Symfony\Component\Cache\Traits\Relay\MoveTrait;
use Symfony\Component\Cache\Traits\Relay\NullableReturnTrait;
use Symfony\Component\Cache\Traits\Relay\PfcountTrait;
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
class RelayProxy extends \Relay\Relay implements ResetInterface, LazyObjectInterface
{
    use CopyTrait;
    use GeosearchTrait;
    use GetrangeTrait;
    use HsetTrait;
    use LazyProxyTrait {
        resetLazyObject as reset;
    }
    use MoveTrait;
    use NullableReturnTrait;
    use PfcountTrait;
    use RelayProxyTrait;

    private const LAZY_OBJECT_PROPERTY_SCOPES = [];

    public function __construct($host = null, $port = 6379, $connect_timeout = 0.0, $command_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0)
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->__construct(...\func_get_args());
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->connect(...\func_get_args());
    }

    public function pconnect($host, $port = 6379, $timeout = 0.0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0.0, #[\SensitiveParameter] $context = [], $database = 0): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pconnect(...\func_get_args());
    }

    public function close(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->close(...\func_get_args());
    }

    public function pclose(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pclose(...\func_get_args());
    }

    public function listen($callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->listen(...\func_get_args());
    }

    public function onFlushed($callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->onFlushed(...\func_get_args());
    }

    public function onInvalidated($callback, $pattern = null): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->onInvalidated(...\func_get_args());
    }

    public function dispatchEvents(): false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dispatchEvents(...\func_get_args());
    }

    public function getOption($option): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getOption(...\func_get_args());
    }

    public function option($option, $value = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->option(...\func_get_args());
    }

    public function setOption($option, $value): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setOption(...\func_get_args());
    }

    public function addIgnorePatterns(...$pattern): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->addIgnorePatterns(...\func_get_args());
    }

    public function addAllowPatterns(...$pattern): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->addAllowPatterns(...\func_get_args());
    }

    public function getTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getTimeout(...\func_get_args());
    }

    public function timeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->timeout(...\func_get_args());
    }

    public function getReadTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getReadTimeout(...\func_get_args());
    }

    public function readTimeout(): false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->readTimeout(...\func_get_args());
    }

    public function getBytes(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getBytes(...\func_get_args());
    }

    public function bytes(): array
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bytes(...\func_get_args());
    }

    public function getHost(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getHost(...\func_get_args());
    }

    public function isConnected(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->isConnected(...\func_get_args());
    }

    public function getPort(): false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPort(...\func_get_args());
    }

    public function getAuth(): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getAuth(...\func_get_args());
    }

    public function getDbNum(): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getDbNum(...\func_get_args());
    }

    public function _serialize($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_serialize(...\func_get_args());
    }

    public function _unserialize($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unserialize(...\func_get_args());
    }

    public function _compress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_compress(...\func_get_args());
    }

    public function _uncompress($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_uncompress(...\func_get_args());
    }

    public function _pack($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_pack(...\func_get_args());
    }

    public function _unpack($value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_unpack(...\func_get_args());
    }

    public function _prefix($value): string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_prefix(...\func_get_args());
    }

    public function getLastError(): ?string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getLastError(...\func_get_args());
    }

    public function clearLastError(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearLastError(...\func_get_args());
    }

    public function endpointId(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->endpointId(...\func_get_args());
    }

    public function getPersistentID(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getPersistentID(...\func_get_args());
    }

    public function socketId(): false|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->socketId(...\func_get_args());
    }

    public function rawCommand($cmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rawCommand(...\func_get_args());
    }

    public function select($db): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->select(...\func_get_args());
    }

    public function auth(#[\SensitiveParameter] $auth): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->auth(...\func_get_args());
    }

    public function info(...$sections): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->info(...\func_get_args());
    }

    public function flushdb($sync = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushdb(...\func_get_args());
    }

    public function flushall($sync = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->flushall(...\func_get_args());
    }

    public function fcall($name, $keys = [], $argv = [], $handler = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->fcall(...\func_get_args());
    }

    public function fcall_ro($name, $keys = [], $argv = [], $handler = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->fcall_ro(...\func_get_args());
    }

    public function function($op, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->function(...\func_get_args());
    }

    public function dbsize(): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->dbsize(...\func_get_args());
    }

    public function replicaof($host = null, $port = 0): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->replicaof(...\func_get_args());
    }

    public function waitaof($numlocal, $numremote, $timeout): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->waitaof(...\func_get_args());
    }

    public function restore($key, $ttl, $value, $options = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->restore(...\func_get_args());
    }

    public function migrate($host, $port, $key, $dstdb, $timeout, $copy = false, $replace = false, #[\SensitiveParameter] $credentials = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->migrate(...\func_get_args());
    }

    public function echo($arg): \Relay\Relay|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->echo(...\func_get_args());
    }

    public function ping($arg = null): \Relay\Relay|bool|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ping(...\func_get_args());
    }

    public function idleTime(): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->idleTime(...\func_get_args());
    }

    public function randomkey(): \Relay\Relay|bool|null|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->randomkey(...\func_get_args());
    }

    public function time(): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->time(...\func_get_args());
    }

    public function bgrewriteaof(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgrewriteaof(...\func_get_args());
    }

    public function lastsave(): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lastsave(...\func_get_args());
    }

    public function lcs($key1, $key2, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lcs(...\func_get_args());
    }

    public function bgsave($schedule = false): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bgsave(...\func_get_args());
    }

    public function save(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->save(...\func_get_args());
    }

    public function role(): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->role(...\func_get_args());
    }

    public function ttl($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ttl(...\func_get_args());
    }

    public function pttl($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pttl(...\func_get_args());
    }

    public function exists(...$keys): \Relay\Relay|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exists(...\func_get_args());
    }

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval(...\func_get_args());
    }

    public function eval_ro($script, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->eval_ro(...\func_get_args());
    }

    public function evalsha($sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha(...\func_get_args());
    }

    public function evalsha_ro($sha, $args = [], $num_keys = 0): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->evalsha_ro(...\func_get_args());
    }

    public function client($operation, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->client(...\func_get_args());
    }

    public function geoadd($key, $lng, $lat, $member, ...$other_triples_and_options): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geoadd(...\func_get_args());
    }

    public function geohash($key, $member, ...$other_members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geohash(...\func_get_args());
    }

    public function georadius($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius(...\func_get_args());
    }

    public function georadiusbymember($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember(...\func_get_args());
    }

    public function georadiusbymember_ro($key, $member, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadiusbymember_ro(...\func_get_args());
    }

    public function georadius_ro($key, $lng, $lat, $radius, $unit, $options = []): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->georadius_ro(...\func_get_args());
    }

    public function geosearchstore($dst, $src, $position, $shape, $unit, $options = []): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geosearchstore(...\func_get_args());
    }

    public function get($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->get(...\func_get_args());
    }

    public function getset($key, $value): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getset(...\func_get_args());
    }

    public function setrange($key, $start, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setrange(...\func_get_args());
    }

    public function getbit($key, $pos): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getbit(...\func_get_args());
    }

    public function bitcount($key, $start = 0, $end = -1, $by_bit = false): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitcount(...\func_get_args());
    }

    public function bitfield($key, ...$args): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitfield(...\func_get_args());
    }

    public function config($operation, $key = null, $value = null): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->config(...\func_get_args());
    }

    public function command(...$args): \Relay\Relay|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->command(...\func_get_args());
    }

    public function bitop($operation, $dstkey, $srckey, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitop(...\func_get_args());
    }

    public function bitpos($key, $bit, $start = null, $end = null, $bybit = false): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bitpos(...\func_get_args());
    }

    public function setbit($key, $pos, $val): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setbit(...\func_get_args());
    }

    public function acl($cmd, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->acl(...\func_get_args());
    }

    public function append($key, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->append(...\func_get_args());
    }

    public function set($key, $value, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->set(...\func_get_args());
    }

    public function getex($key, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getex(...\func_get_args());
    }

    public function getdel($key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getdel(...\func_get_args());
    }

    public function setex($key, $seconds, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setex(...\func_get_args());
    }

    public function pfadd($key, $elements): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfadd(...\func_get_args());
    }

    public function pfmerge($dst, $srckeys): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pfmerge(...\func_get_args());
    }

    public function psetex($key, $milliseconds, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psetex(...\func_get_args());
    }

    public function publish($channel, $message): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->publish(...\func_get_args());
    }

    public function pubsub($operation, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pubsub(...\func_get_args());
    }

    public function spublish($channel, $message): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spublish(...\func_get_args());
    }

    public function setnx($key, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->setnx(...\func_get_args());
    }

    public function mget($keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mget(...\func_get_args());
    }

    public function move($key, $db): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->move(...\func_get_args());
    }

    public function mset($kvals): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->mset(...\func_get_args());
    }

    public function msetnx($kvals): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->msetnx(...\func_get_args());
    }

    public function rename($key, $newkey): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rename(...\func_get_args());
    }

    public function renamenx($key, $newkey): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->renamenx(...\func_get_args());
    }

    public function del(...$keys): \Relay\Relay|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->del(...\func_get_args());
    }

    public function unlink(...$keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unlink(...\func_get_args());
    }

    public function expire($key, $seconds, $mode = null): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expire(...\func_get_args());
    }

    public function pexpire($key, $milliseconds): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpire(...\func_get_args());
    }

    public function expireat($key, $timestamp): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expireat(...\func_get_args());
    }

    public function expiretime($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->expiretime(...\func_get_args());
    }

    public function pexpireat($key, $timestamp_ms): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpireat(...\func_get_args());
    }

    public function pexpiretime($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pexpiretime(...\func_get_args());
    }

    public function persist($key): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->persist(...\func_get_args());
    }

    public function type($key): \Relay\Relay|bool|int|string
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->type(...\func_get_args());
    }

    public function lrange($key, $start, $stop): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrange(...\func_get_args());
    }

    public function lpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpush(...\func_get_args());
    }

    public function rpush($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpush(...\func_get_args());
    }

    public function lpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpushx(...\func_get_args());
    }

    public function rpushx($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpushx(...\func_get_args());
    }

    public function lset($key, $index, $mem): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lset(...\func_get_args());
    }

    public function lpop($key, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpop(...\func_get_args());
    }

    public function lpos($key, $value, $options = null): \Relay\Relay|array|false|int|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lpos(...\func_get_args());
    }

    public function rpop($key, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpop(...\func_get_args());
    }

    public function rpoplpush($source, $dest): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->rpoplpush(...\func_get_args());
    }

    public function brpoplpush($source, $dest, $timeout): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpoplpush(...\func_get_args());
    }

    public function blpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blpop(...\func_get_args());
    }

    public function blmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmpop(...\func_get_args());
    }

    public function bzmpop($timeout, $keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzmpop(...\func_get_args());
    }

    public function lmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmpop(...\func_get_args());
    }

    public function zmpop($keys, $from, $count = 1): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmpop(...\func_get_args());
    }

    public function brpop($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->brpop(...\func_get_args());
    }

    public function bzpopmax($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmax(...\func_get_args());
    }

    public function bzpopmin($key, $timeout_or_key, ...$extra_args): \Relay\Relay|array|false|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->bzpopmin(...\func_get_args());
    }

    public function object($op, $key): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->object(...\func_get_args());
    }

    public function geopos($key, ...$members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->geopos(...\func_get_args());
    }

    public function lrem($key, $mem, $count = 0): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lrem(...\func_get_args());
    }

    public function lindex($key, $index): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lindex(...\func_get_args());
    }

    public function linsert($key, $op, $pivot, $element): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->linsert(...\func_get_args());
    }

    public function ltrim($key, $start, $end): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ltrim(...\func_get_args());
    }

    public function hget($hash, $member): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hget(...\func_get_args());
    }

    public function hstrlen($hash, $member): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hstrlen(...\func_get_args());
    }

    public function hgetall($hash): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hgetall(...\func_get_args());
    }

    public function hkeys($hash): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hkeys(...\func_get_args());
    }

    public function hvals($hash): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hvals(...\func_get_args());
    }

    public function hmget($hash, $members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmget(...\func_get_args());
    }

    public function hmset($hash, $members): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hmset(...\func_get_args());
    }

    public function hexists($hash, $member): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hexists(...\func_get_args());
    }

    public function hsetnx($hash, $member, $value): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hsetnx(...\func_get_args());
    }

    public function hdel($key, $mem, ...$mems): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hdel(...\func_get_args());
    }

    public function hincrby($key, $mem, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrby(...\func_get_args());
    }

    public function hincrbyfloat($key, $mem, $value): \Relay\Relay|bool|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hincrbyfloat(...\func_get_args());
    }

    public function incr($key, $by = 1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incr(...\func_get_args());
    }

    public function decr($key, $by = 1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decr(...\func_get_args());
    }

    public function incrby($key, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrby(...\func_get_args());
    }

    public function decrby($key, $value): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->decrby(...\func_get_args());
    }

    public function incrbyfloat($key, $value): \Relay\Relay|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->incrbyfloat(...\func_get_args());
    }

    public function sdiff($key, ...$other_keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiff(...\func_get_args());
    }

    public function sdiffstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffstore(...\func_get_args());
    }

    public function sinter($key, ...$other_keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinter(...\func_get_args());
    }

    public function sintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sintercard(...\func_get_args());
    }

    public function sinterstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sinterstore(...\func_get_args());
    }

    public function sunion($key, ...$other_keys): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunion(...\func_get_args());
    }

    public function sunionstore($key, ...$other_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunionstore(...\func_get_args());
    }

    public function subscribe($channels, $callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->subscribe(...\func_get_args());
    }

    public function unsubscribe($channels = []): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unsubscribe(...\func_get_args());
    }

    public function psubscribe($patterns, $callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->psubscribe(...\func_get_args());
    }

    public function punsubscribe($patterns = []): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->punsubscribe(...\func_get_args());
    }

    public function ssubscribe($channels, $callback): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->ssubscribe(...\func_get_args());
    }

    public function sunsubscribe($channels = []): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunsubscribe(...\func_get_args());
    }

    public function touch($key_or_array, ...$more_keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->touch(...\func_get_args());
    }

    public function pipeline(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->pipeline(...\func_get_args());
    }

    public function multi($mode = 0): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->multi(...\func_get_args());
    }

    public function exec(): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->exec(...\func_get_args());
    }

    public function wait($replicas, $timeout): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->wait(...\func_get_args());
    }

    public function watch($key, ...$other_keys): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->watch(...\func_get_args());
    }

    public function unwatch(): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->unwatch(...\func_get_args());
    }

    public function discard(): bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->discard(...\func_get_args());
    }

    public function getMode($masked = false): int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getMode(...\func_get_args());
    }

    public function clearBytes(): void
    {
        ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->clearBytes(...\func_get_args());
    }

    public function scan(&$iterator, $match = null, $count = 0, $type = null): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scan($iterator, ...\array_slice(\func_get_args(), 1));
    }

    public function hscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function sscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function zscan($key, &$iterator, $match = null, $count = 0): array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zscan($key, $iterator, ...\array_slice(\func_get_args(), 2));
    }

    public function keys($pattern): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->keys(...\func_get_args());
    }

    public function slowlog($operation, ...$extra_args): \Relay\Relay|array|bool|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->slowlog(...\func_get_args());
    }

    public function smembers($set): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smembers(...\func_get_args());
    }

    public function sismember($set, $member): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sismember(...\func_get_args());
    }

    public function smismember($set, ...$members): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smismember(...\func_get_args());
    }

    public function srem($set, $member, ...$members): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srem(...\func_get_args());
    }

    public function sadd($set, $member, ...$members): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sadd(...\func_get_args());
    }

    public function sort($key, $options = []): \Relay\Relay|array|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort(...\func_get_args());
    }

    public function sort_ro($key, $options = []): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sort_ro(...\func_get_args());
    }

    public function smove($srcset, $dstset, $member): \Relay\Relay|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->smove(...\func_get_args());
    }

    public function spop($set, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->spop(...\func_get_args());
    }

    public function srandmember($set, $count = 1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->srandmember(...\func_get_args());
    }

    public function scard($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->scard(...\func_get_args());
    }

    public function script($command, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->script(...\func_get_args());
    }

    public function strlen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->strlen(...\func_get_args());
    }

    public function hlen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->hlen(...\func_get_args());
    }

    public function llen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->llen(...\func_get_args());
    }

    public function xack($key, $group, $ids): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xack(...\func_get_args());
    }

    public function xclaim($key, $group, $consumer, $min_idle, $ids, $options): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xclaim(...\func_get_args());
    }

    public function xautoclaim($key, $group, $consumer, $min_idle, $start, $count = -1, $justid = false): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xautoclaim(...\func_get_args());
    }

    public function xlen($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xlen(...\func_get_args());
    }

    public function xgroup($operation, $key = null, $group = null, $id_or_consumer = null, $mkstream = false, $entries_read = -2): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xgroup(...\func_get_args());
    }

    public function xdel($key, $ids): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xdel(...\func_get_args());
    }

    public function xinfo($operation, $arg1 = null, $arg2 = null, $count = -1): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xinfo(...\func_get_args());
    }

    public function xpending($key, $group, $start = null, $end = null, $count = -1, $consumer = null, $idle = 0): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xpending(...\func_get_args());
    }

    public function xrange($key, $start, $end, $count = -1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrange(...\func_get_args());
    }

    public function xrevrange($key, $end, $start, $count = -1): \Relay\Relay|array|bool
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xrevrange(...\func_get_args());
    }

    public function xread($streams, $count = -1, $block = -1): \Relay\Relay|array|bool|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xread(...\func_get_args());
    }

    public function xreadgroup($group, $consumer, $streams, $count = 1, $block = 1): \Relay\Relay|array|bool|null
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xreadgroup(...\func_get_args());
    }

    public function xtrim($key, $threshold, $approx = false, $minid = false, $limit = -1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->xtrim(...\func_get_args());
    }

    public function zadd($key, ...$args): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zadd(...\func_get_args());
    }

    public function zrandmember($key, $options = null): mixed
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrandmember(...\func_get_args());
    }

    public function zrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrange(...\func_get_args());
    }

    public function zrevrange($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrange(...\func_get_args());
    }

    public function zrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebyscore(...\func_get_args());
    }

    public function zrevrangebyscore($key, $start, $end, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebyscore(...\func_get_args());
    }

    public function zrangestore($dst, $src, $start, $end, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangestore(...\func_get_args());
    }

    public function zrangebylex($key, $min, $max, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrangebylex(...\func_get_args());
    }

    public function zrevrangebylex($key, $max, $min, $offset = -1, $count = -1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrevrangebylex(...\func_get_args());
    }

    public function zrem($key, ...$args): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zrem(...\func_get_args());
    }

    public function zremrangebylex($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebylex(...\func_get_args());
    }

    public function zremrangebyrank($key, $start, $end): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyrank(...\func_get_args());
    }

    public function zremrangebyscore($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zremrangebyscore(...\func_get_args());
    }

    public function zcard($key): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcard(...\func_get_args());
    }

    public function zcount($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zcount(...\func_get_args());
    }

    public function zdiff($keys, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiff(...\func_get_args());
    }

    public function zdiffstore($dst, $keys): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zdiffstore(...\func_get_args());
    }

    public function zincrby($key, $score, $mem): \Relay\Relay|false|float
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zincrby(...\func_get_args());
    }

    public function zlexcount($key, $min, $max): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zlexcount(...\func_get_args());
    }

    public function zmscore($key, ...$mems): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zmscore(...\func_get_args());
    }

    public function zinter($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinter(...\func_get_args());
    }

    public function zintercard($keys, $limit = -1): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zintercard(...\func_get_args());
    }

    public function zinterstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zinterstore(...\func_get_args());
    }

    public function zunion($keys, $weights = null, $options = null): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunion(...\func_get_args());
    }

    public function zunionstore($dst, $keys, $weights = null, $options = null): \Relay\Relay|false|int
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zunionstore(...\func_get_args());
    }

    public function zpopmin($key, $count = 1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmin(...\func_get_args());
    }

    public function zpopmax($key, $count = 1): \Relay\Relay|array|false
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->zpopmax(...\func_get_args());
    }

    public function _getKeys()
    {
        return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->_getKeys(...\func_get_args());
    }
}
