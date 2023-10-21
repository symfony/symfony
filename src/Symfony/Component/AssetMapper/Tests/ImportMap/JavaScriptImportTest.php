<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
