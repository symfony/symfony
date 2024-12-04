<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\CacheWarmer\AssetMapperCacheWarmer;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Event\PreAssetsCompileEvent;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\Path\PublicAssetsFilesystemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AssetMapperCacheWarmerTest extends TestCase
{
    public function testWarmUpCleansAndWrites()
    {
        $cacheDir = sys_get_temp_dir();

        $importMapGenerator = $this->createMock(ImportMapGenerator::class);
        $importMapGenerator->expects($this->once())
            ->method('getEntrypointNames')
            ->willReturn(['entrypoint1', 'entrypoint2']);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PreAssetsCompileEvent::class));

        $warmer = new AssetMapperCacheWarmer(
            new CompiledAssetMapperConfigReader($cacheDir),
            $this->createMock(AssetMapperInterface::class),
            $importMapGenerator,
            $this->createMock(PublicAssetsFilesystemInterface::class),
            $eventDispatcher,
        );

        $this->assertFileDoesNotExist($cacheDir.'/'.AssetMapper::MANIFEST_FILE_NAME);
        $this->assertFileDoesNotExist($cacheDir.'/'.ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);
        $this->assertFileDoesNotExist($cacheDir.'/entrypoint.entrypoint1.json');
        $this->assertFileDoesNotExist($cacheDir.'/entrypoint.entrypoint2.json');

        try {
            $this->assertEmpty($warmer->warmUp($cacheDir));

            $this->assertFileExists($cacheDir.'/'.AssetMapper::MANIFEST_FILE_NAME);
            $this->assertFileExists($cacheDir.'/'.ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);
            $this->assertFileExists($cacheDir.'/entrypoint.entrypoint1.json');
            $this->assertFileExists($cacheDir.'/entrypoint.entrypoint2.json');
        } finally {
            @unlink($cacheDir.'/'.AssetMapper::MANIFEST_FILE_NAME);
            @unlink($cacheDir.'/'.ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);
            @unlink($cacheDir.'/entrypoint.entrypoint1.json');
            @unlink($cacheDir.'/entrypoint.entrypoint2.json');
        }
    }
}
