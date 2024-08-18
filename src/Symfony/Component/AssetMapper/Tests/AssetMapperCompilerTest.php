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
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;

class AssetMapperCompilerTest extends TestCase
{
    public function testCompile()
    {
        $compiler1 = new class implements AssetCompilerInterface {
            public function supports(MappedAsset $asset): bool
            {
                return 'css' === $asset->publicExtension;
            }

            public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
            {
                return 'should_not_be_called';
            }
        };

        $compiler2 = new class implements AssetCompilerInterface {
            public function supports(MappedAsset $asset): bool
            {
                return 'js' === $asset->publicExtension;
            }

            public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
            {
                return $content.' compiler2 called';
            }
        };

        $compiler3 = new class implements AssetCompilerInterface {
            public function supports(MappedAsset $asset): bool
            {
                return 'js' === $asset->publicExtension;
            }

            public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
            {
                return $content.' compiler3 called';
            }
        };

        $compiler = new AssetMapperCompiler(
            [$compiler1, $compiler2, $compiler3],
            fn () => $this->createMock(AssetMapperInterface::class),
        );
        $asset = new MappedAsset('foo.js', publicPathWithoutDigest: '/assets/foo.js');
        $actualContents = $compiler->compile('starting contents', $asset, $this->createMock(AssetMapperInterface::class));
        $this->assertSame('starting contents compiler2 called compiler3 called', $actualContents);
    }
}
