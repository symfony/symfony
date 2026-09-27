<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Factory\CachedMappedAssetFactory;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactoryInterface;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Filesystem\Filesystem;

class CachedMappedAssetFactoryTest extends TestCase
{
    private Filesystem $filesystem;
    private string $cacheDir = __DIR__.'/../Fixtures/var/cache_for_mapped_asset_factory_test';

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->cacheDir);
    }

    public function testCreateMappedAssetCallsInsideWhenNoCache()
    {
        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $cachedFactory = new CachedMappedAssetFactory(
            $factory,
            $this->cacheDir,
            true
        );

        $mappedAsset = new MappedAsset('file1.css', __DIR__.'/../Fixtures/dir1/file1.css');

        $factory->expects($this->once())
            ->method('createMappedAsset')
            ->with('file1.css', '/anything/file1.css')
            ->willReturn($mappedAsset);

        $this->assertSame($mappedAsset, $cachedFactory->createMappedAsset('file1.css', '/anything/file1.css'));

        // check that calling again after a reset does not trigger the inner call
        // and, the objects will be equal, but not identical
        $cachedFactory->reset();
        $secondActualAsset = $cachedFactory->createMappedAsset('file1.css', '/anything/file1.css');
        $this->assertNotSame($mappedAsset, $secondActualAsset);
        $this->assertSame('file1.css', $secondActualAsset->logicalPath);
        $this->assertSame(__DIR__.'/../Fixtures/dir1/file1.css', $secondActualAsset->sourcePath);
    }

    public function testAssetIsNotBuiltWhenCached()
    {
        $sourcePath = __DIR__.'/../Fixtures/dir1/file1.css';
        $mappedAsset = new MappedAsset('file1.css', $sourcePath, content: 'cached content');
        $this->warmUpCache($mappedAsset);

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $cachedFactory = new CachedMappedAssetFactory(
            $factory,
            $this->cacheDir,
            true
        );

        $factory->expects($this->never())
            ->method('createMappedAsset');

        $actualAsset = $cachedFactory->createMappedAsset('file1.css', $sourcePath);
        $this->assertSame($mappedAsset->logicalPath, $actualAsset->logicalPath);
        $this->assertSame($mappedAsset->content, $actualAsset->content);
    }

    public function testCachedAssetIsLoadedOnceUntilReset()
    {
        $sourcePath = __DIR__.'/../Fixtures/dir1/file1.css';
        $this->warmUpCache(new MappedAsset('file1.css', $sourcePath, content: 'cached content'));

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $factory->expects($this->never())
            ->method('createMappedAsset');
        $cachedFactory = new CachedMappedAssetFactory($factory, $this->cacheDir, true);

        $actualAsset = $cachedFactory->createMappedAsset('file1.css', $sourcePath);
        $this->assertSame($actualAsset, $cachedFactory->createMappedAsset('file1.css', $sourcePath));

        $cachedFactory->reset();
        $this->assertNotSame($actualAsset, $cachedFactory->createMappedAsset('file1.css', $sourcePath));
    }

    public function testAssetCacheFreshnessIsNotMemoizedInDebugMode()
    {
        $sourcePath = $this->cacheDir.'/externally_built.css';
        file_put_contents($sourcePath, 'old content');
        touch($sourcePath, time() - 10);

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $factory->expects($this->exactly(2))
            ->method('createMappedAsset')
            ->with('externally_built.css', $sourcePath)
            ->willReturnOnConsecutiveCalls(
                new MappedAsset('externally_built.css', $sourcePath, content: 'cached content'),
                new MappedAsset('externally_built.css', $sourcePath, content: 'rebuilt content'),
            );

        $cachedFactory = new CachedMappedAssetFactory(
            $factory,
            $this->cacheDir,
            true
        );
        $this->assertSame('cached content', $cachedFactory->createMappedAsset('externally_built.css', $sourcePath)->content);

        // Simulate an external build tool updating a dependency while the PHP process keeps running.
        file_put_contents($sourcePath, 'new content');
        touch($sourcePath, time() + 10);

        // AssetMapper should recheck the dependency on the next request instead of reusing the asset it loaded before.
        $cachedFactory->reset();
        $this->assertSame('rebuilt content', $cachedFactory->createMappedAsset('externally_built.css', $sourcePath)->content);
    }

    #[DataProvider('provideResourceChanges')]
    public function testAssetIsRebuiltWhenOneOfItsResourcesChanges(\Closure $change)
    {
        $dir = $this->cacheDir.'/assets';
        foreach (['file1.css', 'file3.css', 'file4.js', 'file6.js', 'built.css', 'dir/file.txt'] as $file) {
            $this->filesystem->dumpFile($dir.'/'.$file, $file);
            touch($dir.'/'.$file, time() - 10);
        }
        touch($dir.'/dir', time() - 10);

        $mappedAsset = new MappedAsset('file1.css', $dir.'/file1.css', content: 'cached content');
        $dependentOnContentAsset = new MappedAsset('file3.css', $dir.'/file3.css');
        $deeplyNestedAsset = new MappedAsset('file4.js', $dir.'/file4.js');
        $deeplyNestedAsset->addJavaScriptImport(new JavaScriptImport('file6', assetLogicalPath: 'file6.js', assetSourcePath: $dir.'/file6.js'));
        $dependentOnContentAsset->addDependency($deeplyNestedAsset);
        $mappedAsset->addDependency($dependentOnContentAsset);
        $mappedAsset->addFileDependency($dir.'/built.css');
        $mappedAsset->addFileDependency($dir.'/dir');
        $this->warmUpCache($mappedAsset);

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $factory->expects($this->once())
            ->method('createMappedAsset')
            ->willReturn($mappedAsset);

        $change($dir);

        (new CachedMappedAssetFactory($factory, $this->cacheDir, true))->createMappedAsset('file1.css', $dir.'/file1.css');
    }

    public static function provideResourceChanges(): iterable
    {
        yield 'source file' => [static fn (string $dir) => touch($dir.'/file1.css', time() + 10)];
        yield 'file dependency' => [static fn (string $dir) => touch($dir.'/built.css', time() + 10)];
        yield 'file added to a directory dependency' => [static fn (string $dir) => touch($dir.'/dir/new.txt', time() + 10)];
        yield 'dependency' => [static fn (string $dir) => touch($dir.'/file3.css', time() + 10)];
        yield 'dependency of a dependency' => [static fn (string $dir) => touch($dir.'/file4.js', time() + 10)];
        yield 'removed JavaScript import' => [static fn (string $dir) => unlink($dir.'/file6.js')];
    }

    public function testAssetIsRebuiltWhenItsSourceChangesInTheSecondTheCacheWasWritten()
    {
        $sourcePath = $this->cacheDir.'/source.css';
        file_put_contents($sourcePath, 'content');
        $mappedAsset = new MappedAsset('source.css', $sourcePath, content: 'cached content');
        $this->warmUpCache($mappedAsset);

        [$cacheFile] = array_values(array_diff(glob($this->cacheDir.'/*'), [$sourcePath]));
        file_put_contents($sourcePath, 'new content');
        touch($sourcePath, filemtime($cacheFile));

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $factory->expects($this->once())
            ->method('createMappedAsset')
            ->willReturn($mappedAsset);

        (new CachedMappedAssetFactory($factory, $this->cacheDir, true))->createMappedAsset('source.css', $sourcePath);
    }

    public function testCachedAssetIsNotCheckedForFreshnessWhenNotInDebugMode()
    {
        $sourcePath = $this->cacheDir.'/source.css';
        file_put_contents($sourcePath, 'content');
        touch($sourcePath, time() - 10);
        $this->warmUpCache(new MappedAsset('source.css', $sourcePath, content: 'cached content'), false);

        touch($sourcePath, time() + 10);

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $factory->expects($this->never())
            ->method('createMappedAsset');

        $actualAsset = (new CachedMappedAssetFactory($factory, $this->cacheDir, false))->createMappedAsset('source.css', $sourcePath);
        $this->assertSame('cached content', $actualAsset->content);
    }

    public function testResetIsForwardedToTheInnerFactory()
    {
        $innerFactory = new class implements MappedAssetFactoryInterface {
            public int $resetCount = 0;

            public function createMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset
            {
                return null;
            }

            public function reset(): void
            {
                ++$this->resetCount;
            }
        };

        (new CachedMappedAssetFactory($innerFactory, $this->cacheDir, true))->reset();

        $this->assertSame(1, $innerFactory->resetCount);
    }

    public function testResetAcceptsAnInnerFactoryWithoutResetMethod()
    {
        $innerFactory = new class implements MappedAssetFactoryInterface {
            public function createMappedAsset(string $logicalPath, string $sourcePath): ?MappedAsset
            {
                return null;
            }
        };

        $this->expectNotToPerformAssertions();

        (new CachedMappedAssetFactory($innerFactory, $this->cacheDir, true))->reset();
    }

    private function warmUpCache(MappedAsset $mappedAsset, bool $debug = true): void
    {
        $factory = $this->createStub(MappedAssetFactoryInterface::class);
        $factory->method('createMappedAsset')->willReturn($mappedAsset);

        (new CachedMappedAssetFactory($factory, $this->cacheDir, $debug))->createMappedAsset($mappedAsset->logicalPath, $mappedAsset->sourcePath);
    }
}
