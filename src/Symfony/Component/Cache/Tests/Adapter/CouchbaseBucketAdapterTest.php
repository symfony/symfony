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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter;

/**
 * @author Antonio Jose Cerezo Aranda <aj.cerezo@gmail.com>
 */
#[Group('integration')]
#[Group('legacy')]
#[IgnoreDeprecations]
#[RequiresPhpExtension('couchbase', '<3.0.0')]
#[RequiresPhpExtension('couchbase', '>=2.6.0')]
class CouchbaseBucketAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testClearPrefix' => 'Couchbase cannot clear by prefix',
    ];

    protected \CouchbaseBucket $client;

    protected function setUp(): void
    {
        $this->expectUserDeprecationMessage('Since symfony/cache 7.1: The "Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter" class is deprecated, use "Symfony\Component\Cache\Adapter\CouchbaseCollectionAdapter" instead.');

        $this->client = AbstractAdapter::createConnection('couchbase://'.getenv('COUCHBASE_HOST').'/cache',
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
            : $this->client;

        return new CouchbaseBucketAdapter($client, str_replace('\\', '.', __CLASS__), $defaultLifetime);
    }
}
