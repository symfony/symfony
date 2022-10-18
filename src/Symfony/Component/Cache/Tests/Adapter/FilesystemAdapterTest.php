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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @group time-sensitive
 */
class FilesystemAdapterTest extends AdapterTestCase
{
    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new FilesystemAdapter('', $defaultLifetime);
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    protected function isPruned(CacheItemPoolInterface $cache, string $name): bool
    {
        $getFileMethod = (new \ReflectionObject($cache))->getMethod('getFile');

        return !file_exists($getFileMethod->invoke($cache, $name));
    }
}
