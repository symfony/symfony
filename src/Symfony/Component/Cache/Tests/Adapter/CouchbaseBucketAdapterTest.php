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

use PHPUnit\Framework\SkippedTestSuiteError;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter;

/**
 * @requires extension couchbase <3.0.0
 * @requires extension couchbase >=2.6.0
 *
 * @group integration
 *
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 */
class CouchbaseBucketAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testClearPrefix' => 'Couchbase cannot clear by prefix',
    ];

    /** @var \CouchbaseBucket */
    protected static $client;

    public static function setupBeforeClass(): void
    {
        if (!CouchbaseBucketAdapter::isSupported()) {
            throw new SkippedTestSuiteError('Couchbase >= 2.6.0 < 3.0.0 is required.');
        }

        self::$client = AbstractAdapter::createConnection('couchbase://'.getenv('COUCHBASE_HOST').'/cache',
            ['username' => getenv('COUCHBASE_USER'), 'password' => getenv('COUCHBASE_PASS')]
        );
    }

    public function createCachePool($defaultLifetime = 0): CacheItemPoolInterface
    {
        $client = $defaultLifetime
            ? AbstractAdapter::createConnection('couchbase://'
                .getenv('COUCHBASE_USER')
                .':'.getenv('COUCHBASE_PASS')
                .'@'.getenv('COUCHBASE_HOST')
                .'/cache')
            : self::$client;

        return new CouchbaseBucketAdapter($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }
}
