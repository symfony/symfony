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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Filesystem\Filesystem;

#[Group('time-sensitive')]
class PhpFilesAdapterTest extends AdapterTestCase
{
    protected array $skippedTests = [
        'testDefaultLifeTime' => 'PhpFilesAdapter does not allow configuring a default lifetime.',
    ];

    public function createCachePool(): CacheItemPoolInterface
    {
        return new PhpFilesAdapter('sf-cache');
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    public function testHitWhenOpcacheDoesNotHoldTheFile()
    {
        if (!PhpFilesAdapter::isSupported()) {
            $this->markTestSkipped('OPcache is not enabled.');
        }

        $pool = $this->createCachePool();
        $file = (new \ReflectionMethod($pool, 'getFile'))->invoke($pool, 'foo');

        $pool->save($pool->getItem('foo')->set('bar'));
        opcache_invalidate($file, true);
        $this->assertFalse(opcache_is_script_cached($file));
        $this->assertTrue($this->createCachePool()->hasItem('foo'));

        $pool->save($pool->getItem('foo')->set('baz'));
        opcache_invalidate($file, true);
        $this->assertFalse(opcache_is_script_cached($file));
        $this->assertSame('baz', $this->createCachePool()->getItem('foo')->get());
    }

    protected function isPruned(CacheItemPoolInterface $cache, string $name): bool
    {
        $getFileMethod = (new \ReflectionObject($cache))->getMethod('getFile');

        return !file_exists($getFileMethod->invoke($cache, $name));
    }
}
