<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetDependency;
use Symfony\Component\AssetMapper\Factory\CachedMappedAssetFactory;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactoryInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Filesystem\Filesystem;

class CachedMappedAssetFactoryTest extends TestCase
{
    private Filesystem $filesystem;
    private string $cacheDir = __DIR__.'/../fixtures/var/cache_for_mapped_asset_factory_test';

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

        $mappedAsset = new MappedAsset('file1.css');
        $mappedAsset->setSourcePath(__DIR__.'/../fixtures/dir1/file1.css');

        $factory->expects($this->once())
            ->method('createMappedAsset')
            ->with('file1.css', '/anything/file1.css')
            ->willReturn($mappedAsset);

        $this->assertSame($mappedAsset, $cachedFactory->createMappedAsset('file1.css', '/anything/file1.css'));

        // check that calling again does not trigger the inner call
        // and, the objects will be equal, but not identical
        $secondActualAsset = $cachedFactory->createMappedAsset('file1.css', '/anything/file1.css');
        $this->assertNotSame($mappedAsset, $secondActualAsset);
        $this->assertSame('file1.css', $secondActualAsset->getLogicalPath());
        $this->assertSame(__DIR__.'/../fixtures/dir1/file1.css', $secondActualAsset->getSourcePath());
    }

    public function testAssetIsNotBuiltWhenCached()
    {
        $mappedAsset = new MappedAsset('file1.css');
        $sourcePath = __DIR__.'/../fixtures/dir1/file1.css';
        $mappedAsset->setSourcePath($sourcePath);
        $mappedAsset->setContent('cached content');
        $this->saveConfigCache($mappedAsset);

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $cachedFactory = new CachedMappedAssetFactory(
            $factory,
            $this->cacheDir,
            true
        );

        $factory->expects($this->never())
            ->method('createMappedAsset');

        $actualAsset = $cachedFactory->createMappedAsset('file1.css', $sourcePath);
        $this->assertSame($mappedAsset->getLogicalPath(), $actualAsset->getLogicalPath());
        $this->assertSame($mappedAsset->getContent(), $actualAsset->getContent());
    }

    public function testAssetConfigCacheResourceContainsDependencies()
    {
        $mappedAsset = new MappedAsset('file1.css');
        $sourcePath = realpath(__DIR__.'/../fixtures/dir1/file1.css');
        $mappedAsset->setSourcePath($sourcePath);
        $mappedAsset->setContent('cached content');

        $dependentOnContentAsset = new MappedAsset('file3.css');
        $dependentOnContentAsset->setSourcePath(realpath(__DIR__.'/../fixtures/dir2/file3.css'));

        $deeplyNestedAsset = new MappedAsset('file4.js');
        $deeplyNestedAsset->setSourcePath(realpath(__DIR__.'/../fixtures/dir2/file4.js'));

        $dependentOnContentAsset->addDependency(new AssetDependency($deeplyNestedAsset, isContentDependency: true));
        $mappedAsset->addDependency(new AssetDependency($dependentOnContentAsset, isContentDependency: true));

        $notDependentOnContentAsset = new MappedAsset('already-abcdefVWXYZ0123456789.digested.css');
        $notDependentOnContentAsset->setSourcePath(__DIR__.'/../fixtures/dir2/already-abcdefVWXYZ0123456789.digested.css');
        $mappedAsset->addDependency(new AssetDependency($notDependentOnContentAsset, isContentDependency: false));

        $factory = $this->createMock(MappedAssetFactoryInterface::class);
        $factory->expects($this->once())
            ->method('createMappedAsset')
            ->willReturn($mappedAsset);

        $cachedFactory = new CachedMappedAssetFactory(
            $factory,
            $this->cacheDir,
            true
        );
        $cachedFactory->createMappedAsset('file1.css', $sourcePath);

        $configCacheMetadata = $this->loadConfigCacheMetadataFor($mappedAsset);
        $this->assertCount(3, $configCacheMetadata);
        $this->assertInstanceOf(FileResource::class, $configCacheMetadata[0]);
        $this->assertInstanceOf(FileResource::class, $configCacheMetadata[1]);
        $this->assertSame($mappedAsset->getSourcePath(), $configCacheMetadata[0]->getResource());
        $this->assertSame($dependentOnContentAsset->getSourcePath(), $configCacheMetadata[1]->getResource());
        $this->assertSame($deeplyNestedAsset->getSourcePath(), $configCacheMetadata[2]->getResource());
    }

    private function loadConfigCacheMetadataFor(MappedAsset $mappedAsset): array
    {
        $cachedPath = $this->getConfigCachePath($mappedAsset).'.meta';

        return unserialize(file_get_contents($cachedPath));
    }

    private function saveConfigCache(MappedAsset $mappedAsset): void
    {
        $configCache = new ConfigCache($this->getConfigCachePath($mappedAsset), true);
        $configCache->write(serialize($mappedAsset), [new FileResource($mappedAsset->getSourcePath())]);
    }

    private function getConfigCachePath(MappedAsset $mappedAsset): string
    {
        return $this->cacheDir.'/'.hash('xxh128', $mappedAsset->getLogicalPath().':'.$mappedAsset->getSourcePath()).'.php';
    }
}
