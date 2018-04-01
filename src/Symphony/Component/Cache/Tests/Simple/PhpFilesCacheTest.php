<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Cache\Tests\Simple;

use Psr\SimpleCache\CacheInterface;
use Symphony\Component\Cache\Simple\PhpFilesCache;

/**
 * @group time-sensitive
 */
class PhpFilesCacheTest extends CacheTestCase
{
    protected $skippedTests = array(
        'testDefaultLifeTime' => 'PhpFilesCache does not allow configuring a default lifetime.',
    );

    public function createSimpleCache()
    {
        if (!PhpFilesCache::isSupported()) {
            $this->markTestSkipped('OPcache extension is not enabled.');
        }

        return new PhpFilesCache('sf-cache');
    }

    protected function isPruned(CacheInterface $cache, $name)
    {
        $getFileMethod = (new \ReflectionObject($cache))->getMethod('getFile');
        $getFileMethod->setAccessible(true);

        return !file_exists($getFileMethod->invoke($cache, $name));
    }
}
