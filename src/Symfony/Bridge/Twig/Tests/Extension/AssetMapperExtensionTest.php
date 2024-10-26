<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\AssetMapperExtension;
use Symfony\Bridge\Twig\Extension\AssetMapperRuntime;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class AssetMapperExtensionTest extends TestCase
{
    public function testItRendersSourceFromContent()
    {
        $twig = $this->createTwig(
            '{{ asset_mapper_source("asset-source.txt") }}',
            'asset-source.txt',
            new MappedAsset(
                logicalPath: 'asset-source.txt',
                sourcePath: __DIR__.'/Fixtures/asset-source.txt',
                content: 'from content'
            ),
        );

        $this->assertSame('from content', $twig->render('template'));
    }

    public function testItRendersSourceFromSourcePath()
    {
        $twig = $this->createTwig(
            '{{ asset_mapper_source("asset-source.txt") }}',
            'asset-source.txt',
            new MappedAsset(
                logicalPath: 'asset-source.txt',
                sourcePath: $path = __DIR__.'/Fixtures/asset-source.txt',
            ),
        );

        $this->assertSame(file_get_contents($path), $twig->render('template'));
    }

    public function testItFailsIfAssetNotFound()
    {
        $twig = $this->createTwig('{{ asset_mapper_source("invalid") }}', 'invalid', null);

        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('The asset "invalid" was not found.');

        $twig->render('template');
    }

    private function createTwig(string $template, string $logicalPath, ?MappedAsset $asset): Environment
    {
        $twig = new Environment(new ArrayLoader([
            'template' => $template,
        ]), ['debug' => true, 'cache' => false, 'autoescape' => 'html', 'optimizations' => 0]);
        $twig->addExtension(new AssetMapperExtension());
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper->expects($this->once())
            ->method('getAsset')
            ->with($logicalPath)
            ->willReturn($asset);
        $runtime = new AssetMapperRuntime($assetMapper);
        $mockRuntimeLoader = $this->createMock(RuntimeLoaderInterface::class);
        $mockRuntimeLoader
            ->method('load')
            ->willReturnMap([
                [AssetMapperRuntime::class, $runtime],
            ])
        ;
        $twig->addRuntimeLoader($mockRuntimeLoader);

        return $twig;
    }
}
