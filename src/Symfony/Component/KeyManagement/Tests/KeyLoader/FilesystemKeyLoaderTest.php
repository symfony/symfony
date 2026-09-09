<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\KeyLoader;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\KeyLoader\FilesystemKeyLoader;

class FilesystemKeyLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/symfony-kms-fs-loader-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    public function testReadsKeyFromFile()
    {
        file_put_contents($this->dir.'/app', str_repeat("\xAA", 32));

        $this->assertSame(str_repeat("\xAA", 32), (new FilesystemKeyLoader($this->dir))->load('app'));
    }

    public function testReadsKeyWithExtension()
    {
        file_put_contents($this->dir.'/app.key', str_repeat("\xBB", 32));

        $this->assertSame(str_repeat("\xBB", 32), (new FilesystemKeyLoader($this->dir, '.key'))->load('app'));
    }

    public function testSubdirectoriesAreAllowed()
    {
        mkdir($this->dir.'/tenant-a');
        file_put_contents($this->dir.'/tenant-a/master', str_repeat("\xCC", 32));

        $this->assertSame(str_repeat("\xCC", 32), (new FilesystemKeyLoader($this->dir))->load('tenant-a/master'));
    }

    public function testMissingFileThrowsKeyNotFound()
    {
        $this->expectException(KeyNotFoundException::class);
        (new FilesystemKeyLoader($this->dir))->load('absent');
    }

    public static function provideTraversalKeyIds(): iterable
    {
        yield 'parent traversal' => ['../etc/passwd'];
        yield 'embedded parent traversal' => ['tenant-a/../../etc/passwd'];
        yield 'null byte' => ["app\x00.key"];
    }

    #[DataProvider('provideTraversalKeyIds')]
    public function testRejectsTraversalAttempts(string $keyId)
    {
        $this->expectException(InvalidArgumentException::class);
        (new FilesystemKeyLoader($this->dir))->load($keyId);
    }

    public function testRedundantSegmentsAreNormalized()
    {
        file_put_contents($this->dir.'/app', str_repeat("\xDD", 32));
        $loader = new FilesystemKeyLoader($this->dir);

        $this->assertSame(str_repeat("\xDD", 32), $loader->load('./app'));
    }

    public function testFileContentIsReturnedVerbatim()
    {
        $key = str_repeat("\xEE", 32);
        file_put_contents($this->dir.'/lf', $key."\n");
        file_put_contents($this->dir.'/crlf', $key."\r\n");
        file_put_contents($this->dir.'/exact', $key);

        $loader = new FilesystemKeyLoader($this->dir);

        $this->assertSame($key."\n", $loader->load('lf'));
        $this->assertSame($key."\r\n", $loader->load('crlf'));
        $this->assertSame($key, $loader->load('exact'));
    }
}
