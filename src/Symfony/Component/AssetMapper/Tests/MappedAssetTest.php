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
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;

class MappedAssetTest extends TestCase
{
    public function testGetLogicalPath()
    {
        $asset = new MappedAsset('foo.css');

        $this->assertSame('foo.css', $asset->logicalPath);
    }

    /**
     * @dataProvider getExtensionTests
     */
    public function testGetExtension(string $filename, string $expectedExtension)
    {
        $asset = new MappedAsset('anything', publicPathWithoutDigest: $filename);

        $this->assertSame($expectedExtension, $asset->publicExtension);
    }

    public static function getExtensionTests(): iterable
    {
        yield 'simple' => ['foo.css', 'css'];
        yield 'with_multiple_dot' => ['foo.css.map', 'map'];
        yield 'with_directory' => ['foo/bar.css', 'css'];
    }

    public function testAddDependencies()
    {
        $mainAsset = new MappedAsset('file.js');

        $assetFoo = new MappedAsset('foo.js');
        $mainAsset->addDependency($assetFoo);
        $mainAsset->addFileDependency('/path/to/foo.js');

        $this->assertSame([$assetFoo], $mainAsset->getDependencies());
        $this->assertSame(['/path/to/foo.js'], $mainAsset->getFileDependencies());
    }

    public function testAddJavaScriptImports()
    {
        $mainAsset = new MappedAsset('file.js');

        $javaScriptImport = new JavaScriptImport('/the_import', assetLogicalPath: 'foo.js', assetSourcePath: '/path/to/foo.js', isLazy: true);
        $mainAsset->addJavaScriptImport($javaScriptImport);

        $this->assertSame([$javaScriptImport], $mainAsset->getJavaScriptImports());
    }
}
