<?php

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;

class JavaScriptImportTest extends TestCase
{
    public function testBasicConstruction()
    {
        $asset = new MappedAsset('the-asset');
        $import = new JavaScriptImport('the-import', true, $asset, true);

        $this->assertSame('the-import', $import->importName);
        $this->assertTrue($import->isLazy);
        $this->assertSame($asset, $import->asset);
        $this->assertTrue($import->addImplicitlyToImportMap);
    }
}
