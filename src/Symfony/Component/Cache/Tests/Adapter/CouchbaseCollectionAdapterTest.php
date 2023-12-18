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
 * @requires extension couchbase >=3.0.0
 *
 * @group integration
 * @group legacy
 *
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 */
class CouchbaseCollectionAdapterTest extends AdapterTestCase
{
    use ExpectDeprecationTrait;

    protected $skippedTests = [
        'testClearPrefix' => 'Couchbase cannot clear by prefix',
    ];

    public static function setUpBeforeClass(): void
    {
        if (!CouchbaseCollectionAdapter::isSupported()) {
            self::markTestSkipped('Couchbase >= 3.0.0 < 4.0.0 is required.');
        }
    }

    public function createCachePool($defaultLifetime = 0): CacheItemPoolInterface
    {
        $this->expectDeprecation('Since symfony/cache 7.1: The "Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter" class is deprecated, use "Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter" instead.');

        $client = AbstractAdapter::createConnection('couchbase://'.getenv('COUCHBASE_HOST').'/cache',
            ['username' => getenv('COUCHBASE_USER'), 'password' => getenv('COUCHBASE_PASS')]
        );

        return new CouchbaseCollectionAdapter($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }
}
