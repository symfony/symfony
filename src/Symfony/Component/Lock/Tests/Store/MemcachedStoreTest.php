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

use PHPUnit\Framework\SkippedTestSuiteError;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MemcachedStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension memcached
 *
 * @group integration
 */
class MemcachedStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    public static function setUpBeforeClass(): void
    {
        if (version_compare(phpversion('memcached'), '3.1.6', '<')) {
            throw new SkippedTestSuiteError('Extension memcached > 3.1.5 required.');
        }

        $memcached = new \Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST'), 11211);
        $memcached->get('foo');
        $code = $memcached->getResultCode();

        if (\Memcached::RES_SUCCESS !== $code && \Memcached::RES_NOTFOUND !== $code) {
            throw new SkippedTestSuiteError('Unable to connect to the memcache host');
        }
    }

    protected function getClockDelay()
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

    public function testInvalidTtl()
    {
        $this->expectException(InvalidTtlException::class);
        $store = $this->getStore();
        $store->putOffExpiration(new Key('toto'), 0.1);
    }
}
