<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Recorder\Store;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\Har\HarFile;
use Symfony\Component\HttpClient\Recorder\Store\FilesystemStore;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class FilesystemStoreTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sf_filesystem_store_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    public function testUpdateLocksInTheGivenDirectory()
    {
        $store = new FilesystemStore(null, $this->dir.'/locks');
        $store->update($this->dir.'/records/my.har', static function (HarFile $har) {
            $har->addEntry('GET', 'https://example.com/', null, [], 200, [], 'foo');
        });

        $this->assertFileExists($this->dir.'/records/my.har');
        $this->assertCount(1, glob($this->dir.'/locks/sf_har_*.lock'));
        $this->assertSame([$this->dir.'/records/my.har'], glob($this->dir.'/records/*'), 'no lock file next to the records');
    }

    public function testUpdateUsesTheLockFactory()
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())->method('acquire')->with(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('sf_har_'.hash('xxh128', $this->dir.'/my.har'))
            ->willReturn($lock);

        new FilesystemStore($lockFactory, $this->dir.'/locks')->update($this->dir.'/my.har', static function () {});

        $this->assertFileExists($this->dir.'/my.har');
        $this->assertDirectoryDoesNotExist($this->dir.'/locks');
    }

    public static function provideIsAbsolutePathCases(): array
    {
        return [
            ['', false],
            ['my.har', false],
            ['./my.har', false],
            ['/abs/my.har', true],
            ['\\\\server\\share\\my.har', true],
            ['C:\\records\\my.har', true],
            ['C:/records/my.har', true],
        ];
    }

    #[DataProvider('provideIsAbsolutePathCases')]
    public function testIsAbsolutePath(string $path, bool $expected)
    {
        $this->assertSame($expected, FilesystemStore::isAbsolutePath($path));
    }
}
