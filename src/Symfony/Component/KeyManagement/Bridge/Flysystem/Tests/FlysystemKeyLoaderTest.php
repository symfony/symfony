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

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKeyLoader;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

class FlysystemKeyLoaderTest extends TestCase
{
    private const string SECRET = 'S3CR3T-KEY-MATERIAL-0123456789ab';

    public function testReadsKeyFromFlysystem()
    {
        $key = str_repeat("\xAA", 32);
        $reader = $this->buildReader(['app' => $key]);

        $this->assertSame($key, (new FlysystemKeyLoader($reader))->load('app'));
    }

    public function testLoadedKeysAreMemoized()
    {
        $key = str_repeat("\xAA", 32);
        $reader = $this->createMock(FilesystemReader::class);
        $reader->expects($this->once())->method('read')->with('app')->willReturn($key);

        $loader = new FlysystemKeyLoader($reader);

        $this->assertSame($key, $loader->load('app'));
        $this->assertSame($key, $loader->load('app'));
    }

    public function testResetClearsLoadedKeys()
    {
        $firstKey = str_repeat("\xAA", 32);
        $secondKey = str_repeat("\xBB", 32);
        $reader = $this->createMock(FilesystemReader::class);
        $reader->expects($this->exactly(2))->method('read')->with('app')->willReturnOnConsecutiveCalls($firstKey, $secondKey);

        $loader = new FlysystemKeyLoader($reader);

        $this->assertSame($firstKey, $loader->load('app'));
        $loader->reset();
        $this->assertSame($secondKey, $loader->load('app'));
        $this->assertSame($secondKey, $loader->load('app'));
    }

    public function testClonesHaveIndependentCaches()
    {
        $firstKey = str_repeat("\xAA", 32);
        $secondKey = str_repeat("\xBB", 32);
        $reader = $this->createMock(FilesystemReader::class);
        $reader->expects($this->exactly(2))->method('read')->with('app')->willReturnOnConsecutiveCalls($firstKey, $secondKey);

        $loader = new FlysystemKeyLoader($reader);
        $this->assertSame($firstKey, $loader->load('app'));

        $clone = clone $loader;

        $this->assertSame($secondKey, $clone->load('app'));
        $this->assertSame($firstKey, $loader->load('app'));
        $this->assertSame($secondKey, $clone->load('app'));
    }

    public function testFailedReadsAreNotMemoized()
    {
        $key = str_repeat("\xAA", 32);
        $attempt = 0;
        $reader = $this->createMock(FilesystemReader::class);
        $reader->expects($this->exactly(2))->method('read')->with('app')->willReturnCallback(static function (string $location) use (&$attempt, $key): string {
            if (0 === $attempt++) {
                throw UnableToReadFile::fromLocation($location);
            }

            return $key;
        });

        $loader = new FlysystemKeyLoader($reader);

        try {
            $loader->load('app');
            $this->fail('A KeyNotFoundException should have been thrown.');
        } catch (KeyNotFoundException) {
        }

        $this->assertSame($key, $loader->load('app'));
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
        yield 'backslash parent traversal' => ['..\\..\\secret.txt'];
        yield 'backslash parent segment' => ['..\\app.bin'];
        yield 'backslash separator' => ['a\\b'];
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

    public function testCachedKeyMaterialIsNotExposedByPrintingTools()
    {
        $reader = new class extends Filesystem {
            private const string SECRET = 'S3CR3T-KEY-MATERIAL-0123456789ab';

            public function __construct()
            {
            }

            public function read(string $location): string
            {
                return self::SECRET;
            }
        };
        $loader = new FlysystemKeyLoader($reader);
        $this->assertSame(self::SECRET, $loader->load('app'));

        ob_start();
        var_dump($loader);
        print_r($loader);
        var_export($loader);
        $printed = ob_get_clean();

        $this->assertStringNotContainsString(self::SECRET, $printed);
    }

    public function testSerializingAfterCachingKeyMaterialIsRefused()
    {
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willReturn(self::SECRET);
        $loader = new FlysystemKeyLoader($reader);
        $loader->load('app');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize($loader);
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
