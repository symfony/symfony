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

use Cache\IntegrationTests\CachePoolTest;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * @group time-sensitive
 */
class PhpFilesAdapterTest extends CachePoolTest
{
    public function createCachePool()
    {
        if (defined('HHVM_VERSION')) {
            $this->skippedTests['testDeferredSaveWithoutCommit'] = 'Fails on HHVM';
        }
        if (!PhpFilesAdapter::isSupported()) {
            $this->markTestSkipped('OPcache extension is not enabled.');
        }

        return new PhpFilesAdapter('sf-cache');
    }

    public static function tearDownAfterClass()
    {
        FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
    }
}
