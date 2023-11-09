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

class JavaScriptImportTest extends TestCase
{
    public function testBasicConstruction()
    {
        $import = new JavaScriptImport('the-import', 'the-asset', '/path/to/the-asset', true, true);

        $this->assertSame('the-import', $import->importName);
        $this->assertTrue($import->isLazy);
        $this->assertSame('the-asset', $import->assetLogicalPath);
        $this->assertSame('/path/to/the-asset', $import->assetSourcePath);
        $this->assertTrue($import->addImplicitlyToImportMap);
    }
}
