<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactoryInterface;
use Symfony\Component\AssetMapper\MappedAsset;

class AssetMapperTest extends TestCase
{
    private MappedAssetFactoryInterface&MockObject $mappedAssetFactory;

    public function testGetAsset()
    {
        $assetMapper = $this->createAssetMapper();

        $file1Asset = new MappedAsset('file1.css');
        $this->mappedAssetFactory->expects($this->once())
            ->method('createMappedAsset')
            ->with('file1.css', realpath(__DIR__.'/Fixtures/dir1/file1.css'))
            ->willReturn($file1Asset);

        $actualAsset = $assetMapper->getAsset('file1.css');
        $this->assertSame($file1Asset, $actualAsset);

        $this->assertNull($assetMapper->getAsset('non-existent.js'));
    }

    public function testGetPublicPath()
    {
        $assetMapper = $this->createAssetMapper();

        $file1Asset = new MappedAsset('file1.css', publicPath: '/final-assets/file1-the-checksum.css');
        $this->mappedAssetFactory->expects($this->once())
            ->method('createMappedAsset')
            ->willReturn($file1Asset);

        $this->assertSame('/final-assets/file1-the-checksum.css', $assetMapper->getPublicPath('file1.css'));

        // check the manifest is used
        $this->assertSame('/final-assets/file4.checksumfrommanifest.js', $assetMapper->getPublicPath('file4.js'));
    }

    public function testAllAssets()
    {
        $assetMapper = $this->createAssetMapper();

        $this->mappedAssetFactory->expects($this->exactly(8))
            ->method('createMappedAsset')
            ->willReturnCallback(function (string $logicalPath, string $filePath) {
                $asset = new MappedAsset($logicalPath, publicPath: '/final-assets/'.$logicalPath);

                return $asset;
            });

        $assets = $assetMapper->allAssets();
        $this->assertIsIterable($assets);
        $assets = iterator_to_array($assets);
        $this->assertCount(8, $assets);
        $this->assertInstanceOf(MappedAsset::class, $assets[0]);
    }

    public function testGetAssetFromFilesystemPath()
    {
        $assetMapper = $this->createAssetMapper();

        $this->mappedAssetFactory->expects($this->once())
            ->method('createMappedAsset')
            ->with('file1.css', realpath(__DIR__.'/Fixtures/dir1/file1.css'))
            ->willReturn(new MappedAsset('file1.css'));

        $asset = $assetMapper->getAssetFromSourcePath(__DIR__.'/Fixtures/dir1/file1.css');
        $this->assertSame('file1.css', $asset->logicalPath);
    }

    private function createAssetMapper(): AssetMapper
    {
        $dirs = ['dir1' => '', 'dir2' => '', 'dir3' => ''];
        $repository = new AssetMapperRepository($dirs, __DIR__.'/Fixtures');
        $compiledConfigReader = $this->createMock(CompiledAssetMapperConfigReader::class);
        $compiledConfigReader->expects($this->any())
            ->method('configExists')
            ->with(AssetMapper::MANIFEST_FILE_NAME)
            ->willReturn(true);
        $compiledConfigReader->expects($this->any())
            ->method('loadConfig')
            ->willReturn(['file4.js' => '/final-assets/file4.checksumfrommanifest.js']);

        $this->mappedAssetFactory = $this->createMock(MappedAssetFactoryInterface::class);

        return new AssetMapper(
            $repository,
            $this->mappedAssetFactory,
            $compiledConfigReader,
        );
    }
}
