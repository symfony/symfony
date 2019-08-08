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
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * @group time-sensitive
 */
class PhpFilesAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
        'testDefaultLifeTime' => 'PhpFilesAdapter does not allow configuring a default lifetime.',
    ];

    public function createCachePool()
    {
        return new PhpFilesAdapter('sf-cache');
    }

    public static function tearDownAfterClass(): void
    {
        FilesystemAdapterTest::rmdir(sys_get_temp_dir().'/symfony-cache');
    }

    protected function isPruned(CacheItemPoolInterface $cache, $name)
    {
        $getFileMethod = (new \ReflectionObject($cache))->getMethod('getFile');
        $getFileMethod->setAccessible(true);

        return !file_exists($getFileMethod->invoke($cache, $name));
    }
}
