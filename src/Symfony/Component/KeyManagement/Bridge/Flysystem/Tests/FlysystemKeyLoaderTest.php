<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Flysystem\Tests;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKeyLoader;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

class FlysystemKeyLoaderTest extends TestCase
{
    public function testReadsKeyFromFlysystem()
    {
        $key = str_repeat("\xAA", 32);
        $reader = $this->buildReader(['app' => $key]);

        $this->assertSame($key, (new FlysystemKeyLoader($reader))->load('app'));
    }

    public function testDirectoryAndExtensionAreCombined()
    {
        $key = str_repeat("\xBB", 32);
        $reader = $this->buildReader(['secrets/app.bin' => $key]);

        $loader = new FlysystemKeyLoader($reader, 'secrets', '.bin');

        $this->assertSame($key, $loader->load('app'));
    }

    public function testMissingKeySurfacesAsKeyNotFound()
    {
        $reader = $this->buildReader([]);

        $this->expectException(KeyNotFoundException::class);
        (new FlysystemKeyLoader($reader))->load('absent');
    }

    public function testGenericFlysystemErrorsBecomeRuntimeException()
    {
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willThrowException(new class extends \RuntimeException implements FilesystemException {});

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read key material for "app"');
        (new FlysystemKeyLoader($reader))->load('app');
    }

    public static function provideMaliciousKeyIds(): iterable
    {
        yield 'parent traversal' => ['../etc/passwd'];
        yield 'embedded parent traversal' => ['tenant-a/../../etc/passwd'];
        yield 'leading dot segment' => ['./app'];
        yield 'empty segment' => ['tenant-a//master'];
        yield 'null byte' => ["app\x00.key"];
    }

    #[DataProvider('provideMaliciousKeyIds')]
    public function testRejectsMaliciousKeyIds(string $keyId)
    {
        $loader = new FlysystemKeyLoader($this->buildReader([]));

        $this->expectException(InvalidArgumentException::class);
        $loader->load($keyId);
    }

    public function testKeyMaterialIsReturnedVerbatim()
    {
        $key = str_repeat("\xCC", 32);
        $reader = $this->buildReader(['raw' => $key, 'lf' => $key."\n", 'crlf' => $key."\r\n"]);

        $loader = new FlysystemKeyLoader($reader);

        $this->assertSame($key, $loader->load('raw'));
        $this->assertSame($key."\n", $loader->load('lf'));
        $this->assertSame($key."\r\n", $loader->load('crlf'));
    }

    /**
     * @param array<string, string> $files
     */
    private function buildReader(array $files): FilesystemReader
    {
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willReturnCallback(static fn (string $location): string => $files[$location] ?? throw UnableToReadFile::fromLocation($location));

        return $reader;
    }
}
