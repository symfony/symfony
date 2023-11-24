<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter;

/**
 * @requires extension couchbase <4.0.0
 * @requires extension couchbase >=3.0.5
 *
 * @group legacy integration
 *
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 */
class CouchbaseCollectionAdapterTest extends AdapterTestCase
{
    use ExpectDeprecationTrait;

    protected $skippedTests = [
        'testClearPrefix' => 'Couchbase cannot clear by prefix',
    ];

    public function createCachePool($defaultLifetime = 0): CacheItemPoolInterface
    {
        $this->expectDeprecation('Since symfony/cache 7.1: The "Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter" class is deprecated, use "Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter" instead.');

        $client = AbstractAdapter::createConnection('couchbase://'.getenv('COUCHBASE_HOST').'/cache',
            ['username' => getenv('COUCHBASE_USER'), 'password' => getenv('COUCHBASE_PASS')]
        );

        return new CouchbaseCollectionAdapter($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }

    /**
     * Couchbase consider expiration time greater than 30 days as an absolute timestamp.
     * This test case overrides parent to avoid this behavior for the "k2" item.
     */
    public function testExpiration()
    {
        $cache = $this->createCachePool();
        $cache->save($cache->getItem('k1')->set('v1')->expiresAfter(2));
        $cache->save($cache->getItem('k2')->set('v2')->expiresAfter(86400));

        sleep(3);
        $item = $cache->getItem('k1');
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get(), "Item's value must be null when isHit() is false.");

        $item = $cache->getItem('k2');
        $this->assertTrue($item->isHit());
        $this->assertSame('v2', $item->get());
    }
}
