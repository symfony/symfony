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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;

class MapperAwareAssetPackageTest extends TestCase
{
    public function testGetVersion()
    {
        $inner = $this->createMock(PackageInterface::class);
        $inner->expects($this->once())
            ->method('getVersion')
            ->with('foo')
            ->willReturn('2.0');

        $assetMapperPackage = new MapperAwareAssetPackage($inner, $this->createMock(AssetMapperInterface::class));

        $this->assertSame('2.0', $assetMapperPackage->getVersion('foo'));
    }

    /**
     * @dataProvider getUrlTests
     */
    public function testGetUrl(string $path, string $expectedPathSentToInner)
    {
        $inner = $this->createMock(PackageInterface::class);
        $inner->expects($this->once())
            ->method('getUrl')
            ->with($expectedPathSentToInner)
            ->willReturnCallback(function ($path) {
                return '/'.$path;
            });
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->any())
            ->method('getPublicPath')
            ->willReturnCallback(function ($path) {
                switch ($path) {
                    case 'images/foo.png':
                        return '/assets/images/foo.123456.png';
                    case 'more-styles.css':
                        return '/assets/more-styles.abcd123.css';
                    default:
                        return null;
                }
            });

        $assetMapperPackage = new MapperAwareAssetPackage($inner, $assetMapper);
        $this->assertSame('/'.$expectedPathSentToInner, $assetMapperPackage->getUrl($path));
    }

    public static function getUrlTests(): iterable
    {
        yield 'path_is_found_in_asset_mapper' => [
            'path' => 'images/foo.png',
            'expectedPathSentToInner' => 'assets/images/foo.123456.png',
        ];

        yield 'path_not_found_in_asset_mapper' => [
            'path' => 'styles.css',
            'expectedPathSentToInner' => 'styles.css',
        ];
    }
}
