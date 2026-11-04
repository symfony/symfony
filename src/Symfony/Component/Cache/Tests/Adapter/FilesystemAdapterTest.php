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
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Filesystem\Filesystem;

#[Group('time-sensitive')]
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

    #[RequiresPhpExtension('phpser')]
    public function testPhpSerSignatures()
    {
        $directory = sys_get_temp_dir().'/symfony-cache/'.__FUNCTION__;
        $cache = new FilesystemAdapter('', 0, $directory, new DefaultMarshaller(false));
        $this->assertTrue($cache->save($cache->getItem('foo')->set('unsigned')));

        $cache = new FilesystemAdapter('', 0, $directory, new DefaultMarshaller(null, false, str_repeat('k', 32)));
        $this->assertFalse($cache->getItem('foo')->isHit());
        $this->assertTrue($cache->save($cache->getItem('foo')->set('signed')));
        $this->assertSame('signed', $cache->getItem('foo')->get());

        $cache = new FilesystemAdapter('', 0, $directory, new DefaultMarshaller(null, false, str_repeat('x', 32)));
        $this->assertFalse($cache->getItem('foo')->isHit());
    }

    public function testConstructorCreatesTheDirectory()
    {
        $directory = sys_get_temp_dir().'/symfony-cache/'.__FUNCTION__;
        (new Filesystem())->remove($directory);

        new FilesystemAdapter('ns', 0, $directory);

        $this->assertDirectoryExists($directory.'/ns');
    }

    public function testSaveWhenTheDirectoryWasRemoved()
    {
        $directory = sys_get_temp_dir().'/symfony-cache/'.__FUNCTION__;
        $cache = new FilesystemAdapter('ns', 0, $directory);
        (new Filesystem())->remove($directory);

        $this->assertTrue($cache->save($cache->getItem('foo')->set('bar')));
        $this->assertSame('bar', (new FilesystemAdapter('ns', 0, $directory))->getItem('foo')->get());
    }

    public function testDeleteItemsWhenUnlinkFails()
    {
        $cache = $this->createCachePool();
        $cache->save($cache->getItem('foo')->set('foo'));
        $cache->save($cache->getItem('bar')->set('bar'));

        $file = (new \ReflectionMethod($cache, 'getFile'))->invoke($cache, (new \ReflectionMethod($cache, 'getId'))->invoke($cache, 'foo'));

        // Windows doesn't delete read-only files, other systems don't delete files in read-only directories
        chmod($file, 0o444);
        chmod(\dirname($file), 0o555);

        try {
            $this->assertFalse($cache->deleteItems(['foo', 'bar']));
            $this->assertTrue($cache->hasItem('foo'));
            $this->assertFalse($cache->hasItem('bar'));
        } finally {
            chmod(\dirname($file), 0o777);
            chmod($file, 0o666);
        }
    }

    public function testDeleteItemsWhenFileIsNotReadable()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Files cannot be made unreadable on Windows.');
        }

        $cache = $this->createCachePool();
        $cache->save($cache->getItem('foo')->set('foo'));

        $file = (new \ReflectionMethod($cache, 'getFile'))->invoke($cache, (new \ReflectionMethod($cache, 'getId'))->invoke($cache, 'foo'));
        chmod($file, 0o000);

        $this->assertTrue($cache->deleteItems(['foo']));
        $this->assertFalse($cache->hasItem('foo'));
    }

    protected function isPruned(CacheItemPoolInterface $cache, string $name): bool
    {
        $getFileMethod = (new \ReflectionObject($cache))->getMethod('getFile');

        return !file_exists($getFileMethod->invoke($cache, $name));
    }
}
