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
use Symfony\Component\Filesystem\Filesystem;

/**
 * @group time-sensitive
 */
class PhpFilesAdapterTest extends AdapterTestCase
{
    protected $skippedTests = [
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

    public function testFileReferencingAMissingClassIsAMiss()
    {
        $pool = new PhpFilesAdapter('sf-cache-stale', 0, null, true);
        $pool->save($pool->getItem('foo')->set(new \ArrayObject()));

        $content = <<<'EOPHP'
            <?php //foo

            return [\PHP_INT_MAX, new class() implements \Symfony\Component\Cache\Traits\CachedValueInterface {
                public function getValue(): mixed { return \Symfony\Component\Cache\Tests\Adapter\MissingClass::hydrate(); }
            }];

            EOPHP;

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(sys_get_temp_dir().'/symfony-cache/sf-cache-stale', \FilesystemIterator::SKIP_DOTS)) as $file) {
            file_put_contents($file, $content);
        }

        $this->assertFalse((new PhpFilesAdapter('sf-cache-stale', 0, null, true))->getItem('foo')->isHit());
    }

    protected function isPruned(CacheItemPoolInterface $cache, string $name): bool
    {
        $getFileMethod = (new \ReflectionObject($cache))->getMethod('getFile');

        return !file_exists($getFileMethod->invoke($cache, $name));
    }
}
