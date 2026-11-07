<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Resource;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Tests\Fixtures\LegacyResource;
use Symfony\Component\Filesystem\Filesystem;

class DirectoryResourceTest extends TestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'symfonyDirectoryIterator';
        if (!file_exists($this->directory)) {
            mkdir($this->directory);
        }
        touch($this->directory.'/tmp.xml');
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $this->removeDirectory($this->directory);
    }

    protected function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $path) {
            if (preg_match('#[/\\\\]\.\.?$#', $path->__toString())) {
                continue;
            }
            if ($path->isDir()) {
                rmdir($path->__toString());
            } else {
                unlink($path->__toString());
            }
        }
        rmdir($directory);
    }

    public function testGetResource()
    {
        $resource = new DirectoryResource($this->directory);
        $this->assertSame(realpath($this->directory), $resource->getResource(), '->getResource() returns the path to the resource');
    }

    public function testGetPattern()
    {
        $resource = new DirectoryResource($this->directory, 'bar');
        $this->assertEquals('bar', $resource->getPattern());
    }

    public function testResourceDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/The directory ".*" does not exist./');
        new DirectoryResource('/____foo/foobar'.random_int(1, 999999));
    }

    public function testIsFresh()
    {
        $resource = new DirectoryResource($this->directory);
        $this->assertTrue($resource->isFresh(time() + 10), '->isFresh() returns true if the resource has not changed');
        $this->assertFalse($resource->isFresh(time() - 86400), '->isFresh() returns false if the resource has been updated');
    }

    public function testIsFreshForDeletedResources()
    {
        $resource = new DirectoryResource($this->directory);
        $this->removeDirectory($this->directory);

        $this->assertFalse($resource->isFresh(time()), '->isFresh() returns false if the resource does not exist');
    }

    public function testIsFreshUpdateFile()
    {
        $resource = new DirectoryResource($this->directory);
        touch($this->directory.'/tmp.xml', time() + 20);
        $this->assertFalse($resource->isFresh(time() + 10), '->isFresh() returns false if an existing file is modified');
    }

    public function testIsFreshUpdateFileInSameSecond()
    {
        $resource = new DirectoryResource($this->directory);
        touch($this->directory.'/tmp.xml', $time = time() + 20);
        $this->assertFalse($resource->isFresh($time), '->isFresh() returns false if an existing file is modified in the same second');
        $this->assertFalse($resource->isFresh($time + 1), 'A later cache write does not replace the captured input.');
    }

    public function testIsFreshModifyDirectoryInSameSecond()
    {
        $resource = new DirectoryResource($this->directory);
        touch($this->directory, $time = time() + 20);
        // touch() clears the stat cache only as of PHP 8.4.5
        clearstatcache();
        $this->assertFalse($resource->isFresh($time), '->isFresh() returns false if the directory is modified in the same second');
        $this->assertFalse($resource->isFresh($time + 1), 'A later cache write does not replace the captured input.');
    }

    public function testIsFreshNewFile()
    {
        $resource = new DirectoryResource($this->directory);
        touch($this->directory.'/new.xml', time() + 20);
        $this->assertFalse($resource->isFresh(time() + 10), '->isFresh() returns false if a new file is added');
    }

    public function testIsFreshNewFileWithDifferentPattern()
    {
        $resource = new DirectoryResource($this->directory, '/.xml$/');
        touch($this->directory.'/new.yaml', time() + 20);
        $this->assertTrue($resource->isFresh(time() + 10), '->isFresh() returns true if a new file with a non-matching pattern is added');
    }

    public function testIsFreshDeleteFile()
    {
        $resource = new DirectoryResource($this->directory);
        $time = time();
        usleep(1500000);
        unlink($this->directory.'/tmp.xml');
        $this->assertFalse($resource->isFresh($time), '->isFresh() returns false if an existing file is removed');
    }

    public function testIsFreshDeleteDirectory()
    {
        $resource = new DirectoryResource($this->directory);
        $this->removeDirectory($this->directory);
        $this->assertFalse($resource->isFresh(time()), '->isFresh() returns false if the whole resource is removed');
    }

    public function testIsFreshIgnoresTheParentDirectory()
    {
        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);
        touch($subdirectory.'/file.xml');
        touch($this->directory, time() + 20);

        $resource = new DirectoryResource($subdirectory);
        $this->assertTrue($resource->isFresh(time() + 10), '->isFresh() returns true if only the parent directory is modified');
    }

    public function testIsFreshCreateFileInSubdirectory()
    {
        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);

        $resource = new DirectoryResource($this->directory);
        $this->assertTrue($resource->isFresh(time() + 10), '->isFresh() returns true if an unmodified subdirectory exists');

        touch($subdirectory.'/newfile.xml', time() + 20);
        $this->assertFalse($resource->isFresh(time() + 10), '->isFresh() returns false if a new file in a subdirectory is added');
    }

    public function testIsFreshModifySubdirectory()
    {
        $resource = new DirectoryResource($this->directory);

        $subdirectory = $this->directory.'/subdirectory';
        mkdir($subdirectory);
        touch($subdirectory, time() + 20);

        $this->assertFalse($resource->isFresh(time() + 10), '->isFresh() returns false if a subdirectory is modified (e.g. a file gets deleted)');
    }

    public function testFilterRegexListNoMatch()
    {
        $resource = new DirectoryResource($this->directory, '/\.(foo|xml)$/');

        touch($this->directory.'/new.bar', time() + 20);
        $this->assertTrue($resource->isFresh(time() + 10), '->isFresh() returns true if a new file not matching the filter regex is created');
    }

    public function testFilterRegexListMatch()
    {
        $resource = new DirectoryResource($this->directory, '/\.(foo|xml)$/');

        touch($this->directory.'/new.xml', time() + 20);
        $this->assertFalse($resource->isFresh(time() + 10), '->isFresh() returns false if an new file matching the filter regex is created ');
    }

    public function testSerializeUnserialize()
    {
        $resource = new DirectoryResource($this->directory, '/\.(foo|xml)$/');

        unserialize(serialize($resource));

        $this->assertSame(realpath($this->directory), $resource->getResource());
        $this->assertSame('/\.(foo|xml)$/', $resource->getPattern());
    }

    public function testResourcesWithDifferentPatternsAreDifferent()
    {
        $resourceA = new DirectoryResource($this->directory, '/.xml$/');
        $resourceB = new DirectoryResource($this->directory, '/.yaml$/');

        $this->assertCount(2, array_unique([$resourceA, $resourceB]));
    }

    #[DataProvider('provideDirectoryChanges')]
    public function testDirectoryRemembersRecentEntries(string $change, bool $fresh)
    {
        $now = time();
        mkdir($this->directory.'/nested');
        file_put_contents($this->directory.'/nested/a.yml', 'v1');
        touch($this->directory.'/nested/a.yml', $now);
        touch($this->directory.'/nested', $now);
        touch($this->directory, $now);
        clearstatcache();
        $resource = new DirectoryResource($this->directory, '/\.yml$/');
        $serialized = serialize($resource);

        match ($change) {
            'rewrite' => file_put_contents($this->directory.'/nested/a.yml', 'v2'),
            'add' => file_put_contents($this->directory.'/nested/b.yml', 'v2'),
            'remove' => unlink($this->directory.'/nested/a.yml'),
            'ignored' => file_put_contents($this->directory.'/nested/a.txt', 'v2'),
            'type' => (new Filesystem())->remove($this->directory.'/nested/a.yml'),
            default => null,
        };
        if ('type' === $change) {
            mkdir($this->directory.'/nested/a.yml');
        }
        if (is_file($this->directory.'/nested/a.yml')) {
            touch($this->directory.'/nested/a.yml', $now);
        }
        touch($this->directory.'/nested', $now);
        touch($this->directory, $now);
        clearstatcache();

        self::assertSame($fresh, $resource->isFresh($now + 10));
        self::assertSame($fresh, unserialize($serialized)->isFresh($now + 10));
        if ($fresh) {
            self::assertTrue($resource->isFresh($now));
        }
        self::assertSame($serialized, serialize($resource));
    }

    public static function provideDirectoryChanges(): iterable
    {
        yield 'same-second byte rewrite' => ['rewrite', false];
        yield 'same-second addition' => ['add', false];
        yield 'same-second removal' => ['remove', false];
        yield 'file becomes directory' => ['type', false];
        yield 'ignored file' => ['ignored', true];
        yield 'unchanged including equal cache time' => ['unchanged', true];
    }

    public function testDirectoryOldInputEditedBeforeLaterCacheWrite()
    {
        $now = time();
        file_put_contents($path = $this->directory.'/a', 'v1');
        touch($path, $now - 100);
        touch($this->directory, $now - 100);
        clearstatcache();
        $resource = new DirectoryResource($this->directory);
        file_put_contents($path, 'v2');
        touch($path, $now);
        clearstatcache();

        self::assertFalse($resource->isFresh($now + 10));
        self::assertTrue((new DirectoryResource($this->directory))->isFresh($now + 10));
    }

    #[RequiresFunction('symlink')]
    #[DataProvider('provideSymlinkChanges')]
    public function testRecentSymlinkTargets(string $change, bool $fresh)
    {
        $now = time();
        $target = tempnam(sys_get_temp_dir(), 'sf-directory-link-');
        file_put_contents($target, 'v1');
        touch($target, $now);
        symlink($target, $this->directory.'/link.yml');
        if (str_starts_with($change, 'broken')) {
            unlink($target);
        }
        touch($this->directory, $now);
        clearstatcache();
        try {
            $resource = new DirectoryResource($this->directory, '/\.yml$/');
            $serialized = serialize($resource);
            if ('rewrite' === $change || 'broken repaired' === $change) {
                file_put_contents($target, 'v2');
                touch($target, $now);
            } elseif ('remove' === $change) {
                unlink($target);
            }
            clearstatcache();

            self::assertSame($fresh, $resource->isFresh($now + 10));
            self::assertSame($fresh, unserialize($serialized)->isFresh($now + 10));
        } finally {
            unlink($this->directory.'/link.yml');
            (new Filesystem())->remove($target);
        }
    }

    public static function provideSymlinkChanges(): iterable
    {
        yield 'unchanged target' => ['unchanged', true];
        yield 'same-second target rewrite' => ['rewrite', false];
        yield 'target disappears' => ['remove', false];
        yield 'unchanged broken link' => ['broken', true];
        yield 'broken link acquires a target' => ['broken repaired', false];
    }

    public function testRecentEntryMetadataHasDeterministicTraversalOrder()
    {
        $now = time();
        foreach (['b.yml', 'a.yml'] as $name) {
            file_put_contents($this->directory.'/'.$name, 'v1');
            touch($this->directory.'/'.$name, $now);
        }
        touch($this->directory, $now);
        clearstatcache();
        $first = (new DirectoryResource($this->directory, '/\.yml$/'))->__serialize()['recent'];
        foreach (['a.yml', 'b.yml'] as $name) {
            unlink($this->directory.'/'.$name);
            file_put_contents($this->directory.'/'.$name, 'v1');
            touch($this->directory.'/'.$name, $now);
        }
        touch($this->directory, $now);
        clearstatcache();
        $second = (new DirectoryResource($this->directory, '/\.yml$/'))->__serialize()['recent'];

        self::assertSame(['', 'a.yml', 'b.yml'], array_keys($first));
        self::assertSame($first, $second);
    }

    public function testInvalidPatternDoesNotWarnDuringConstruction()
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $resource = new DirectoryResource($this->directory, 'invalid');
            self::assertSame('invalid', $resource->getPattern());
        } finally {
            restore_error_handler();
        }
    }

    public function testIncompleteDirectoryMetadataCannotCertifyFreshness()
    {
        $properties = ['resource' => $this->directory, 'pattern' => null, 'loadedAt' => time(), 'recent' => null];
        self::assertFalse(LegacyResource::create(DirectoryResource::class, $properties)->isFresh(time() + 10));
        $properties['loadedAt'] = null;
        $properties['recent'] = ['' => 'not a fingerprint'];
        self::assertFalse(LegacyResource::create(DirectoryResource::class, $properties)->isFresh(time() + 10));
    }

    public function testFailedDirectoryCaptureDoesNotWarnOrTrustTwoFailedReads()
    {
        file_put_contents($path = $this->directory.'/unreadable', 'v1');
        chmod($path, 0);
        clearstatcache();
        if (is_readable($path)) {
            chmod($path, 0o600);
            self::markTestSkipped('This process can read files without read permission.');
        }
        $calls = 0;
        set_error_handler(static function (int $severity, string $message) use (&$calls): never {
            ++$calls;
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $resource = new DirectoryResource($this->directory);
            self::assertSame(0, $calls);
            self::assertFalse($resource->isFresh(time() + 10));
            self::assertFalse(unserialize(serialize($resource))->isFresh(time() + 10));
            self::assertSame(0, $calls);
            try {
                trigger_error('The original handler is restored.', \E_USER_WARNING);
            } catch (\ErrorException $e) {
                self::assertSame('The original handler is restored.', $e->getMessage());
            }
            self::assertSame(1, $calls);
        } finally {
            restore_error_handler();
            chmod($path, 0o600);
        }
    }

    public function testLegacyMetadataRemainsReadable()
    {
        $resource = LegacyResource::create(DirectoryResource::class, ['resource' => $this->directory, 'pattern' => null]);
        self::assertTrue($resource->isFresh(time() + 10));
    }

    public function testSerializedFormIsReproducibleWithSourceDateEpoch()
    {
        $server = $_SERVER;
        $_SERVER['SOURCE_DATE_EPOCH'] = '1700000000';
        try {
            $before = serialize(new DirectoryResource($this->directory));
            touch($this->directory, time() - 100);
            touch($this->directory.'/tmp.xml', time() - 100);
            clearstatcache();
            self::assertSame($before, serialize(new DirectoryResource($this->directory)));
        } finally {
            $_SERVER = $server;
        }
    }

    #[RequiresFunction('posix_mkfifo')]
    public function testConstructingAResourceDoesNotReadNamedPipes()
    {
        self::assertTrue(posix_mkfifo($this->directory.'/pipe', 0o600));
        $result = $this->checkDirectoryInChild();

        self::assertTrue($result['fresh']);
        self::assertTrue($result['roundtrip']);
    }

    public function testPartialDirectoryScanCannotCertifyFreshness()
    {
        $path = $this->directory.'/unreadable';
        mkdir($path);
        file_put_contents($path.'/file', 'v1');
        touch($path.'/file', time() - 100);
        touch($path, time() - 100);
        touch($this->directory, time() - 100);
        chmod($path, 0);
        clearstatcache();
        if (is_readable($path)) {
            chmod($path, 0o700);
            self::markTestSkipped('This process can scan directories without read permission.');
        }
        try {
            $resource = new DirectoryResource($this->directory);
            self::assertFalse($resource->isFresh(time() + 10));
            chmod($path, 0o700);
            clearstatcache();
            self::assertFalse(unserialize(serialize($resource))->isFresh(time() + 10));
            self::assertTrue((new DirectoryResource($this->directory))->isFresh(time() + 10));
        } finally {
            chmod($path, 0o700);
        }
    }

    /**
     * Bounds construction in a child process so opening a named pipe cannot hang the test suite.
     *
     * @return array{fresh: bool, roundtrip: bool}
     */
    private function checkDirectoryInChild(): array
    {
        foreach (ClassLoader::getRegisteredLoaders() as $vendor => $loader) {
            if ($loader->findFile(DirectoryResource::class)) {
                $autoload = $vendor.'/autoload.php';
                break;
            }
        }
        $code = 'require $argv[1]; $resource = new Symfony\Component\Config\Resource\DirectoryResource($argv[2]); echo json_encode(["fresh" => $resource->isFresh(time() + 10), "roundtrip" => unserialize(serialize($resource))->isFresh(time() + 10)]);';
        $process = proc_open([\PHP_BINARY, '-r', $code, $autoload, $this->directory], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $deadline = hrtime(true) + 5_000_000_000;
        $status = proc_get_status($process);
        while ($status['running'] && hrtime(true) < $deadline) {
            usleep(10_000);
            $status = proc_get_status($process);
        }
        if ($status['running']) {
            proc_terminate($process);
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        self::assertFalse($status['running'], 'Directory resource construction tried to read a named pipe.');
        self::assertSame(0, 0 <= $status['exitcode'] ? $status['exitcode'] : $exitCode, $error);

        return json_decode($output, true, flags: \JSON_THROW_ON_ERROR);
    }
}
