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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
#[RequiresPhpExtension('memcached')]
#[Group('integration')]
class MemcachedStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    public static function setUpBeforeClass(): void
    {
        if (version_compare(phpversion('memcached'), '3.1.6', '<')) {
            self::markTestSkipped('Extension memcached > 3.1.5 required.');
        }

        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);
        $memcached->get('foo');
        $code = $memcached->getResultCode();

        if (\Memcached::RES_SUCCESS !== $code && \Memcached::RES_NOTFOUND !== $code) {
            self::markTestSkipped('Unable to connect to the memcache host');
        }
    }

    protected function getClockDelay(): int
    {
        return 1000000;
    }

    public function getStore(): PersistingStoreInterface
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);

        return new MemcachedStore($memcached);
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Memcached expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    public function testTtlLongerThanThirtyDays()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);

        $store = new MemcachedStore($memcached, 31 * 86400);
        $key = new Key(uniqid(__METHOD__, true));

        $store->save($key);
        $this->assertTrue($store->exists($key));

        $store->putOffExpiration($key, 45 * 86400);
        $this->assertTrue($store->exists($key));

        $memcached->delete((string) $key);
        $store->putOffExpiration($key, 45 * 86400);
        $this->assertTrue($store->exists($key));

        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testTtlEndingAfter2038()
    {
        $memcached = $this->createMock(\Memcached::class);
        $memcached->expects($this->once())
            ->method('add')
            ->with($this->anything(), $this->anything(), 2147483647)
            ->willReturnCallback(static function (string $key, string $value) use (&$token) {
                $token = $value;

                return true;
            });
        $memcached->method('get')->willReturnCallback(static function () use (&$token) {
            return ['value' => $token, 'cas' => 1];
        });
        $memcached->method('getResultCode')->willReturn(\Memcached::RES_SUCCESS);
        $memcached->expects($this->once())
            ->method('cas')
            ->with(1, $this->anything(), $this->anything(), 2147483647)
            ->willReturn(true);

        $store = new MemcachedStore($memcached, 100 * 365 * 86400);
        $key = new Key(__METHOD__);

        $store->save($key);
        $store->putOffExpiration($key, 100 * 365 * 86400);
    }

    public function testInvalidTtl()
    {
        $this->expectException(InvalidTtlException::class);
        $store = $this->getStore();
        $store->putOffExpiration(new Key('toto'), 0.1);
    }

    public function testKeysRejectedByMemcachedAreHashed()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);
        $memcached->setOption(\Memcached::OPT_PREFIX_KEY, 'lock_');
        $store = new MemcachedStore($memcached);

        foreach (['', 'a b', "a\nb", str_repeat('a', 246)] as $resource) {
            $key = new Key($resource);

            $store->save($key);
            $this->assertTrue($store->exists($key));
            $this->assertSame($key->getState(MemcachedStore::class), $memcached->get(hash('sha256', $resource)));

            $store->putOffExpiration($key, 300);
            $this->assertTrue($store->exists($key));

            $store->delete($key);
            $this->assertFalse($store->exists($key));
        }
    }

    public function testKeysAcceptedByMemcachedAreNotHashed()
    {
        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);
        $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
        $memcached->setOption(\Memcached::OPT_PREFIX_KEY, 'lock_');
        $store = new MemcachedStore($memcached);

        foreach (['a b', str_repeat('a', 245)] as $resource) {
            $key = new Key($resource);

            $store->save($key);
            $this->assertSame($key->getState(MemcachedStore::class), $memcached->get($resource));

            $store->delete($key);
        }
    }
}
