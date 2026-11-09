<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\SelfCheckingResourceChecker;
use Symfony\Component\Config\ResourceCheckerConfigCache;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Config\Tests\Fixtures\ResourceFailingOnUnserialize;
use Symfony\Component\Config\Tests\Fixtures\ResourceWithVeryVeryVeryVeryVeryVeryVeryVeryLongName;
use Symfony\Component\Config\Tests\Resource\ResourceStub;

class ResourceCheckerConfigCacheTest extends TestCase
{
    private string $cacheFile;
    private string $metaFile;

    protected function setUp(): void
    {
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'config_');
        $this->metaFile = tempnam(sys_get_temp_dir(), 'config_');
    }

    protected function tearDown(): void
    {
        $files = [$this->cacheFile, $this->cacheFile.'.meta', $this->cacheFile.'.meta.json', $this->metaFile, $this->metaFile.'.json'];

        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    public function testGetPath()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile);

        $this->assertSame($this->cacheFile, $cache->getPath());
    }

    public function testCacheIsNotFreshIfEmpty()
    {
        $checker = $this->createMock(ResourceCheckerInterface::class)
            ->expects($this->never())->method('supports');

        /* If there is nothing in the cache, it needs to be filled (and thus it's not fresh).
            It does not matter if you provide checkers or not. */

        unlink($this->cacheFile); // remove tempnam() side effect
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);

        $this->assertFalse($cache->isFresh());
    }

    public function testCacheIsFreshIfNoCheckerProvided()
    {
        /* For example in prod mode, you may choose not to run any checkers
           at all. In that case, the cache should always be considered fresh. */
        $cache = new ResourceCheckerConfigCache($this->cacheFile);
        $this->assertTrue($cache->isFresh());
    }

    public function testCacheIsFreshIfEmptyCheckerIteratorProvided()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile, new \ArrayIterator([]));
        $this->assertTrue($cache->isFresh());
    }

    public function testResourcesWithoutcheckersAreIgnoredAndConsideredFresh()
    {
        /* As in the previous test, but this time we have a resource. */
        $cache = new ResourceCheckerConfigCache($this->cacheFile);
        $cache->write('', [new ResourceStub()]);

        $this->assertTrue($cache->isFresh()); // no (matching) ResourceChecker passed
    }

    public function testIsFreshWithchecker()
    {
        $checker = $this->createMock(ResourceCheckerInterface::class);

        $checker->expects($this->once())
                  ->method('supports')
                  ->willReturn(true);

        $checker->expects($this->once())
                  ->method('isFresh')
                  ->willReturn(true);

        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('', [new ResourceStub()]);

        $this->assertTrue($cache->isFresh());
    }

    public function testIsNotFreshWithchecker()
    {
        $checker = $this->createMock(ResourceCheckerInterface::class);

        $checker->expects($this->once())
                  ->method('supports')
                  ->willReturn(true);

        $checker->expects($this->once())
                  ->method('isFresh')
                  ->willReturn(false);

        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('', [new ResourceStub()]);

        $this->assertFalse($cache->isFresh());
    }

    public function testCheckersReceiveTheMetadataTimestamp()
    {
        $timestamp = time() - 100;
        $checker = $this->createMock(ResourceCheckerInterface::class);
        $checker->method('supports')->willReturn(true);
        $checker->expects($this->once())->method('isFresh')
            ->with($this->isInstanceOf(ResourceStub::class), $timestamp)
            ->willReturn(true);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker], $this->metaFile);
        $cache->write('cached', [new ResourceStub()]);
        touch($this->metaFile, $timestamp);
        clearstatcache();

        $this->assertTrue($cache->isFresh());
    }

    #[DataProvider('provideWholeSecondBuildClocks')]
    #[Group('time-sensitive')]
    public function testBuildDurationBackdatesOnlyMetadata(int $clockOffset, bool $checkBeforeBuild, bool $customMetadata)
    {
        ClockMock::withClockMock(time() + $clockOffset);
        $metadata = $customMetadata ? $this->metaFile : $this->cacheFile.'.meta';
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$this->createStub(ResourceCheckerInterface::class)], $metadata);
        if ($checkBeforeBuild) {
            unlink($this->cacheFile);
            sleep(20);
            $this->assertFalse($cache->isFresh());
        }
        sleep(10);
        $cache->write('cached', []);
        clearstatcache();

        $restoredWriteTime = filemtime($metadata) + 10;
        $this->assertGreaterThanOrEqual(filemtime($this->cacheFile), $restoredWriteTime);
        $this->assertLessThanOrEqual(filemtime($metadata.'.json'), $restoredWriteTime);
        $this->assertSame('cached', file_get_contents($this->cacheFile));
    }

    /**
     * @return iterable<array{int, bool, bool}>
     */
    public static function provideWholeSecondBuildClocks(): iterable
    {
        foreach ([-30, 0, 30] as $clockOffset) {
            foreach ([false, true] as $checkBeforeBuild) {
                foreach ([false, true] as $customMetadata) {
                    yield [$clockOffset, $checkBeforeBuild, $customMetadata];
                }
            }
        }
    }

    #[Group('time-sensitive')]
    public function testAFreshCheckRestartsTheBuildBoundary()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$this->createStub(ResourceCheckerInterface::class)]);
        $cache->write('first', []);
        sleep(20);
        $this->assertTrue($cache->isFresh());
        sleep(10);
        $cache->write('second', []);
        clearstatcache();

        $restoredWriteTime = filemtime($this->cacheFile.'.meta') + 10;
        $this->assertGreaterThanOrEqual(filemtime($this->cacheFile), $restoredWriteTime);
        $this->assertLessThanOrEqual(filemtime($this->cacheFile.'.meta.json'), $restoredWriteTime);
    }

    #[Group('time-sensitive')]
    public function testWritingWithoutMetadataDoesNotBackdateExistingMetadata()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$this->createStub(ResourceCheckerInterface::class)]);
        $cache->write('first', []);
        $timestamp = filemtime($this->cacheFile.'.meta');
        sleep(10);
        $cache->write('second');
        clearstatcache();

        $this->assertSame($timestamp, filemtime($this->cacheFile.'.meta'));
    }

    public function testAnUnchangedGeneratedFileNeedsOnlyOneCorrectiveBuild()
    {
        $resourceFile = tempnam(sys_get_temp_dir(), 'config_generated_');
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [new SelfCheckingResourceChecker()]);
        sleep(1);
        file_put_contents($resourceFile, 'generated');
        clearstatcache();

        try {
            $cache->write('first', [new FileResource($resourceFile)]);
            clearstatcache();
            $this->assertLessThan(filemtime($resourceFile), filemtime($this->cacheFile.'.meta'));
            $this->assertFalse($cache->isFresh());

            $cache->write('second', [new FileResource($resourceFile)]);
            clearstatcache();
            $this->assertTrue($cache->isFresh());
        } finally {
            unlink($resourceFile);
        }
    }

    public function testIsNotFreshWhenResourceIsModifiedInSameSecondAfterBeingLoaded()
    {
        $resourceFile = tempnam(sys_get_temp_dir(), 'config_');
        touch($resourceFile, time() - 10);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [new SelfCheckingResourceChecker()]);
        $cache->write('', [new FileResource($resourceFile)]);

        try {
            touch($resourceFile, $time = filemtime($this->cacheFile));
            $this->assertFalse($cache->isFresh());

            touch($this->cacheFile, $time + 1);
            $this->assertFalse($cache->isFresh());

            touch($this->cacheFile.'.meta', $time + 1);
            $this->assertTrue($cache->isFresh());
        } finally {
            unlink($resourceFile);
        }
    }

    public function testIsFreshWhenResourceIsWrittenInSameSecondBeforeBeingLoaded()
    {
        $resourceFile = tempnam(sys_get_temp_dir(), 'config_');
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [new SelfCheckingResourceChecker()]);
        $cache->write('', [new FileResource($resourceFile)]);

        try {
            touch($this->cacheFile, filemtime($resourceFile));
            $this->assertTrue($cache->isFresh());
        } finally {
            unlink($resourceFile);
        }
    }

    public function testCacheIsNotFreshWhenUnserializeFails()
    {
        $checker = $this->createStub(ResourceCheckerInterface::class);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('foo', [new FileResource(__FILE__)]);

        $metaFile = "{$this->cacheFile}.meta";
        file_put_contents($metaFile, str_replace('FileResource', 'ClassNotHere', file_get_contents($metaFile)));

        $this->assertFalse($cache->isFresh());
    }

    public function testCacheIsNotFreshWhenMetaFileIsCorrupted()
    {
        $checker = $this->createStub(ResourceCheckerInterface::class);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('foo', [new FileResource(__FILE__)]);

        file_put_contents("{$this->cacheFile}.meta", 'a:1:{i:0;O:4');

        $errors = [];
        set_error_handler(static function (int $type, string $message) use (&$errors) {
            $errors[] = $message;

            return true;
        });

        try {
            $this->assertFalse($cache->isFresh());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $errors);
    }

    public function testExceptionThrownWhileUnserializingMetaFileIsNotSwallowed()
    {
        $checker = $this->createStub(ResourceCheckerInterface::class);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('foo', [new ResourceFailingOnUnserialize()]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot unserialize.');

        $cache->isFresh();
    }

    public function testCacheKeepsContent()
    {
        $cache = new ResourceCheckerConfigCache($this->cacheFile);
        $cache->write('FOOBAR');

        $this->assertSame('FOOBAR', file_get_contents($cache->getPath()));
    }

    public function testCacheIsNotFreshIfNotExistsMetaFile()
    {
        $checker = $this->createStub(ResourceCheckerInterface::class);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('foo', [new FileResource(__FILE__)]);

        $metaFile = "{$this->cacheFile}.meta";
        unlink($metaFile);

        $this->assertFalse($cache->isFresh());
    }

    public function testMetaFilesAreNotWrittenWithoutCheckers()
    {
        touch($this->cacheFile.'.meta');
        touch($this->cacheFile.'.meta.json');

        $cache = new ResourceCheckerConfigCache($this->cacheFile, new \ArrayIterator([]));
        $cache->write('foo', [new FileResource(__FILE__)]);

        $this->assertFileDoesNotExist($this->cacheFile.'.meta');
        $this->assertFileDoesNotExist($this->cacheFile.'.meta.json');
        $this->assertTrue($cache->isFresh());
    }

    public function testCacheWithCustomMetaFile()
    {
        $this->assertStringEqualsFile($this->metaFile, '');

        $checker = $this->createStub(ResourceCheckerInterface::class);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker], $this->metaFile);
        $cache->write('foo', [new FileResource(__FILE__)]);

        $this->assertStringNotEqualsFile($this->metaFile, '');

        $this->assertStringEqualsFile($this->metaFile.'.json', json_encode([
            'resources' => [
                [
                    '@type' => FileResource::class,
                    'resource' => __FILE__,
                ],
            ],
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    public function testCacheWithResourceWithLongPropertyId()
    {
        $checker = $this->createStub(ResourceCheckerInterface::class);
        $cache = new ResourceCheckerConfigCache($this->cacheFile, [$checker]);
        $cache->write('foo', [new ResourceWithVeryVeryVeryVeryVeryVeryVeryVeryLongName(__FILE__)]);

        $this->assertStringNotEqualsFile($this->cacheFile.'.meta', '');

        $this->assertStringEqualsFile($this->cacheFile.'.meta.json', json_encode([
            'resources' => [
                [
                    '@type' => ResourceWithVeryVeryVeryVeryVeryVeryVeryVeryLongName::class,
                    'resource' => __FILE__,
                ],
            ],
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }
}
