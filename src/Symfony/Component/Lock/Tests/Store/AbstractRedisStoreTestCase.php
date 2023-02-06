<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Relay\Relay;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
abstract class AbstractRedisStoreTestCase extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    protected function getClockDelay()
    {
        return 250000;
    }

    abstract protected function getRedisConnection(): \Redis|Relay|\RedisArray|\RedisCluster|\Predis\ClientInterface;

    public function getStore(): PersistingStoreInterface
    {
        return new RedisStore($this->getRedisConnection());
    }

    public function testBackwardCompatibility()
    {
        $resource = uniqid(__METHOD__, true);
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $oldStore = new Symfony51Store($this->getRedisConnection());
        $newStore = $this->getStore();

        $oldStore->save($key1);
        $this->assertTrue($oldStore->exists($key1));

        $this->expectException(LockConflictedException::class);
        $newStore->save($key2);
    }
}

class Symfony51Store
{
    private $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    public function save(Key $key)
    {
        $script = '
            if redis.call("GET", KEYS[1]) == ARGV[1] then
                return redis.call("PEXPIRE", KEYS[1], ARGV[2])
            elseif redis.call("SET", KEYS[1], ARGV[1], "NX", "PX", ARGV[2]) then
                return 1
            else
                return 0
            end
        ';
        if (!$this->evaluate($script, (string) $key, [$this->getUniqueToken($key), (int) ceil(5 * 1000)])) {
            throw new LockConflictedException();
        }
    }

    public function exists(Key $key)
    {
        return $this->redis->get((string) $key) === $this->getUniqueToken($key);
    }

    private function evaluate(string $script, string $resource, array $args)
    {
        if ($this->redis instanceof \Redis || $this->redis instanceof Relay || $this->redis instanceof \RedisCluster) {
            return $this->redis->eval($script, array_merge([$resource], $args), 1);
        }

        if ($this->redis instanceof \RedisArray) {
            return $this->redis->_instance($this->redis->_target($resource))->eval($script, array_merge([$resource], $args), 1);
        }

        if ($this->redis instanceof \Predis\ClientInterface) {
            return $this->redis->eval(...array_merge([$script, 1, $resource], $args));
        }

        throw new InvalidArgumentException(sprintf('"%s()" expects being initialized with a Redis, Relay, RedisArray, RedisCluster or Predis\ClientInterface, "%s" given.', __METHOD__, get_debug_type($this->redis)));
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}
