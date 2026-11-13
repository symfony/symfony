<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Tui\Css\AssetMapperStylesheetLoader;
use Symfony\Component\Tui\Exception\InvalidArgumentException;

final class AssetMapperStylesheetLoaderTest extends TestCase
{
    public function testLoadsALogicalPath()
    {
        $loader = new AssetMapperStylesheetLoader($this->createAssetMapper());

        $this->assertSame('.card { color: cyan }', $loader->load('styles/card.css'));
    }

    public function testLoadsTheDigestedPublicPathOfAsset()
    {
        $loader = new AssetMapperStylesheetLoader($this->createAssetMapper());

        $this->assertSame('.card { color: cyan }', $loader->load('/assets/styles/card-abc123.css'));
        $this->assertSame('.card { color: cyan }', $loader->load('/assets/styles/card-abc123.css?v=1'), 'The query string is ignored.');
    }

    public function testReadsTheSourceWhenTheContentIsNotCompiled()
    {
        $file = tempnam(sys_get_temp_dir(), 'tui');
        file_put_contents($file, 'p { color: red }');

        try {
            $assetMapper = $this->createStub(AssetMapperInterface::class);
            $assetMapper->method('getAsset')->willReturn(new MappedAsset('raw.css', $file, '/assets/raw-1.css', '/assets/raw.css'));

            $this->assertSame('p { color: red }', (new AssetMapperStylesheetLoader($assetMapper))->load('raw.css'));
        } finally {
            unlink($file);
        }
    }

    public function testThrowsForAnUnmappedStylesheet()
    {
        $loader = new AssetMapperStylesheetLoader($this->createAssetMapper());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The stylesheet "missing.css" is not a mapped asset.');

        $loader->load('missing.css');
    }

    private function createAssetMapper(): AssetMapperInterface
    {
        $asset = new MappedAsset('styles/card.css', '/app/assets/styles/card.css', '/assets/styles/card-abc123.css', '/assets/styles/card.css', '.card { color: cyan }', 'abc123');

        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper->method('getAsset')->willReturnCallback(static fn (string $path) => 'styles/card.css' === $path ? $asset : null);
        $assetMapper->method('allAssets')->willReturn([$asset]);

        return $assetMapper;
    }
}
