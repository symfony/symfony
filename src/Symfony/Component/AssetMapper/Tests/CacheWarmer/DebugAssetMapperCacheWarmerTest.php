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
use Symfony\Component\AssetMapper\CacheWarmer\DebugAssetMapperCacheWarmer;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;

class DebugAssetMapperCacheWarmerTest extends TestCase
{
    public function testWarmUpCleansAndDoesntWrite()
    {
        $cacheDir = sys_get_temp_dir();

        $importMapGenerator = $this->createMock(ImportMapGenerator::class);
        $importMapGenerator->expects($this->once())
            ->method('getEntrypointNames')
            ->willReturn(['entrypoint1', 'entrypoint2']);

        $warmer = new DebugAssetMapperCacheWarmer(
            new CompiledAssetMapperConfigReader($cacheDir),
            $importMapGenerator,
        );

        $this->assertFileDoesNotExist($cacheDir.'/'.AssetMapper::MANIFEST_FILE_NAME);
        $this->assertFileDoesNotExist($cacheDir.'/'.ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);
        $this->assertFileDoesNotExist($cacheDir.'/entrypoint.entrypoint1.json');
        $this->assertFileDoesNotExist($cacheDir.'/entrypoint.entrypoint2.json');

        $this->assertEmpty($warmer->warmUp($cacheDir));

        $this->assertFileDoesNotExist($cacheDir.'/'.AssetMapper::MANIFEST_FILE_NAME);
        $this->assertFileDoesNotExist($cacheDir.'/'.ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);
        $this->assertFileDoesNotExist($cacheDir.'/entrypoint.entrypoint1.json');
        $this->assertFileDoesNotExist($cacheDir.'/entrypoint.entrypoint2.json');
    }
}
