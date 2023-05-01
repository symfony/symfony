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
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\MappedAsset;

class AssetMapperTest extends TestCase
{
    public function testGetPublicPrefix()
    {
        $assetMapper = new AssetMapper(
            $this->createMock(AssetMapperRepository::class),
            $this->createMock(AssetMapperCompiler::class),
            '/projectRootDir/',
            '/publicPrefix/',
            'publicDirName',
        );
        $this->assertSame('/publicPrefix/', $assetMapper->getPublicPrefix());

        $assetMapper = new AssetMapper(
            $this->createMock(AssetMapperRepository::class),
            $this->createMock(AssetMapperCompiler::class),
            '/projectRootDir/',
            '/publicPrefix',
            'publicDirName',
        );
        // The trailing slash should be added automatically
        $this->assertSame('/publicPrefix/', $assetMapper->getPublicPrefix());
    }

    public function testGetPublicAssetsFilesystemPath()
    {
        $assetMapper = new AssetMapper(
            $this->createMock(AssetMapperRepository::class),
            $this->createMock(AssetMapperCompiler::class),
            '/projectRootDir/',
            '/publicPrefix/',
            'publicDirName',
        );
        $this->assertSame('/projectRootDir/publicDirName/publicPrefix', $assetMapper->getPublicAssetsFilesystemPath());
    }

    public function testGetAsset()
    {
        $assetMapper = $this->createAssetMapper();
        $this->assertNull($assetMapper->getAsset('non-existent.js'));

        $asset = $assetMapper->getAsset('file2.js');
        $this->assertSame('file2.js', $asset->logicalPath);
        $this->assertMatchesRegularExpression('/^\/final-assets\/file2-[a-zA-Z0-9]{7,128}\.js$/', $asset->getPublicPath());
    }

    public function testGetAssetRespectsPreDigestedPaths()
    {
        $assetMapper = $this->createAssetMapper();
        $asset = $assetMapper->getAsset('already-abcdefVWXYZ0123456789.digested.css');
        $this->assertSame('already-abcdefVWXYZ0123456789.digested.css', $asset->logicalPath);
        $this->assertSame('/final-assets/already-abcdefVWXYZ0123456789.digested.css', $asset->getPublicPath());
    }

    public function testGetAssetUsesManifestIfAvailable()
    {
        $assetMapper = $this->createAssetMapper();
        $asset = $assetMapper->getAsset('file4.js');
        $this->assertSame('/final-assets/file4.checksumfrommanifest.js', $asset->getPublicPath());
    }

    public function testGetPublicPath()
    {
        $assetMapper = $this->createAssetMapper();
        $this->assertSame('/final-assets/file1-b3445cb7a86a0795a7af7f2004498aef.css', $assetMapper->getPublicPath('file1.css'));

        // check the manifest is used
        $this->assertSame('/final-assets/file4.checksumfrommanifest.js', $assetMapper->getPublicPath('file4.js'));
    }

    public function testAllAssets()
    {
        $assetMapper = $this->createAssetMapper();
        $assets = $assetMapper->allAssets();
        $this->assertCount(8, $assets);
        $this->assertInstanceOf(MappedAsset::class, $assets[0]);
    }

    public function testGetAssetFromFilesystemPath()
    {
        $assetMapper = $this->createAssetMapper();
        $asset = $assetMapper->getAssetFromSourcePath(__DIR__.'/fixtures/dir1/file1.css');
        $this->assertSame('file1.css', $asset->logicalPath);
    }

    public function testGetAssetWithContentBasic()
    {
        $assetMapper = $this->createAssetMapper();
        $expected = <<<EOF
        /* file1.css */
        body {}

        EOF;

        $asset = $assetMapper->getAsset('file1.css');
        $this->assertSame($expected, $asset->getContent());

        // verify internal caching doesn't cause issues
        $asset = $assetMapper->getAsset('file1.css');
        $this->assertSame($expected, $asset->getContent());
    }

    public function testGetAssetWithContentUsesCompilers()
    {
        $assetMapper = $this->createAssetMapper();
        $expected = <<<EOF
        import '../file4.js';
        console.log('file5.js');

        EOF;

        $asset = $assetMapper->getAsset('subdir/file5.js');
        $this->assertSame($expected, $asset->getContent());
    }

    public function testGetAssetWithContentErrorsOnCircularReferences()
    {
        $assetMapper = $this->createAssetMapper('circular_dir');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular reference detected while creating asset for "circular1.css": "circular1.css -> circular2.css -> circular1.css".');
        $assetMapper->getAsset('circular1.css');
    }

    public function testGetAssetWithDigest()
    {
        $file6Compiler = new class() implements AssetCompilerInterface {
            public function supports(MappedAsset $asset): bool
            {
                return true;
            }

            public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
            {
                if ('subdir/file6.js' === $asset->logicalPath) {
                    return $content.'/* compiled */';
                }

                return $content;
            }
        };

        $assetMapper = $this->createAssetMapper();
        $asset = $assetMapper->getAsset('subdir/file6.js');
        $this->assertSame('7f983f4053a57f07551fed6099c0da4e', $asset->getDigest());
        $this->assertFalse($asset->isPredigested());

        // trigger the compiler, which will change file5.js
        // since file6.js imports file5.js, the digest for file6 should change,
        // because, internally, the file path in file6.js to file5.js will need to change
        $assetMapper = $this->createAssetMapper(null, $file6Compiler);
        $asset = $assetMapper->getAsset('subdir/file6.js');
        $this->assertSame('7e4f24ebddd4ab2a3bcf0d89270b9f30', $asset->getDigest());
    }

    public function testGetAssetWithPredigested()
    {
        $assetMapper = $this->createAssetMapper();
        $asset = $assetMapper->getAsset('already-abcdefVWXYZ0123456789.digested.css');
        $this->assertSame('abcdefVWXYZ0123456789.digested', $asset->getDigest());
        $this->assertTrue($asset->isPredigested());
    }

    public function testGetAssetWithMimeType()
    {
        $assetMapper = $this->createAssetMapper();
        $file1Asset = $assetMapper->getAsset('file1.css');
        $this->assertSame('text/css', $file1Asset->getMimeType());
        $file2Asset = $assetMapper->getAsset('file2.js');
        $this->assertSame('text/javascript', $file2Asset->getMimeType());
        // an extension not in the known extensions
        $testAsset = $assetMapper->getAsset('test.gif.foo');
        $this->assertSame('image/gif', $testAsset->getMimeType());
    }

    private function createAssetMapper(string $extraDir = null, AssetCompilerInterface $extraCompiler = null): AssetMapper
    {
        $dirs = ['dir1' => '', 'dir2' => '', 'dir3' => ''];
        if ($extraDir) {
            $dirs[$extraDir] = '';
        }
        $repository = new AssetMapperRepository($dirs, __DIR__.'/fixtures');

        $compilers = [
            new JavaScriptImportPathCompiler(),
            new CssAssetUrlCompiler(),
        ];
        if ($extraCompiler) {
            $compilers[] = $extraCompiler;
        }
        $compiler = new AssetMapperCompiler($compilers);
        $extensions = [
            'foo' => 'image/gif',
        ];

        return new AssetMapper(
            $repository,
            $compiler,
            __DIR__.'/fixtures',
            '/final-assets/',
            'test_public',
            $extensions,
        );
    }
}
