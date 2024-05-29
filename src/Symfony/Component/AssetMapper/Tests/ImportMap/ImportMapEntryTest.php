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
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;

class ImportMapEntryTest extends TestCase
{
    public function testCreateLocal()
    {
        $entry = ImportMapEntry::createLocal('foo', ImportMapType::JS, 'foo.js', true);
        $this->assertSame('foo', $entry->importName);
        $this->assertSame(ImportMapType::JS, $entry->type);
        $this->assertSame('foo.js', $entry->path);
        $this->assertTrue($entry->isEntrypoint);
        $this->assertFalse($entry->isRemotePackage());
    }

    public function testCreateRemote()
    {
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, 'foo.js', '1.0.0', 'foo/bar', true);
        $this->assertSame('foo', $entry->importName);
        $this->assertSame(ImportMapType::JS, $entry->type);
        $this->assertSame('foo.js', $entry->path);
        $this->assertTrue($entry->isEntrypoint);
        $this->assertTrue($entry->isRemotePackage());
        $this->assertSame('1.0.0', $entry->version);
        $this->assertSame('foo/bar', $entry->packageModuleSpecifier);
    }

    /**
     * @dataProvider getSplitPackageNameTests
     */
    public function testSplitPackageNameAndFilePath(string $packageModuleSpecifier, string $expectedPackage, string $expectedPath)
    {
        [$actualPackage, $actualPath] = ImportMapEntry::splitPackageNameAndFilePath($packageModuleSpecifier);
        $this->assertSame($expectedPackage, $actualPackage);
        $this->assertSame($expectedPath, $actualPath);
    }

    public static function getSplitPackageNameTests()
    {
        yield 'package-name' => [
            'package-name',
            'package-name',
            '',
        ];

        yield 'package-name/path' => [
            'package-name/path',
            'package-name',
            '/path',
        ];

        yield '@scope/package-name' => [
            '@scope/package-name',
            '@scope/package-name',
            '',
        ];

        yield '@scope/package-name/path' => [
            '@scope/package-name/path',
            '@scope/package-name',
            '/path',
        ];
    }

    public function testGetPackageNameAndPackagePath()
    {
        $entry = ImportMapEntry::createRemote('foo', ImportMapType::JS, 'foo.js', '1.0.0', 'foo/bar', true);
        $this->assertSame('foo', $entry->getPackageName());
        $this->assertSame('/bar', $entry->getPackagePathString());
    }
}
