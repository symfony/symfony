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

use Couchbase\Collection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter;

/**
 * @requires extension couchbase <4.0.0
 * @requires extension couchbase >=3.0.0
 *
 * @group integration
 *
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 */
class CouchbaseCollectionAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testClearPrefix' => 'Couchbase cannot clear by prefix',
    ];

    /** @var Collection */
    protected static $client;

    public static function setupBeforeClass(): void
    {
        if (!CouchbaseCollectionAdapter::isSupported()) {
            self::markTestSkipped('Couchbase >= 3.0.0 < 4.0.0 is required.');
        }

        self::$client = AbstractAdapter::createConnection('couchbase://'.getenv('COUCHBASE_HOST').'/cache',
            ['username' => getenv('COUCHBASE_USER'), 'password' => getenv('COUCHBASE_PASS')]
        );
    }

    public function createCachePool($defaultLifetime = 0): CacheItemPoolInterface
    {
        if (!CouchbaseCollectionAdapter::isSupported()) {
            self::markTestSkipped('Couchbase >= 3.0.0 < 4.0.0 is required.');
        }

        $client = $defaultLifetime
            ? AbstractAdapter::createConnection('couchbase://'
                .getenv('COUCHBASE_USER')
                .':'.getenv('COUCHBASE_PASS')
                .'@'.getenv('COUCHBASE_HOST')
                .'/cache')
            : self::$client;

        return new CouchbaseCollectionAdapter($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }
}
