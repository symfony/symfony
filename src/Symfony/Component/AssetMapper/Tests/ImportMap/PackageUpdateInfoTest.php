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
use Symfony\Component\AssetMapper\ImportMap\PackageUpdateInfo;

class PackageUpdateInfoTest extends TestCase
{
    /**
     * @dataProvider provideValidConstructorArguments
     */
    public function testConstructor($importName, $currentVersion, $latestVersion, $updateType)
    {
        $packageUpdateInfo = new PackageUpdateInfo(
            packageName: $importName,
            currentVersion: $currentVersion,
            latestVersion: $latestVersion,
            updateType: $updateType,
        );

        $this->assertSame($importName, $packageUpdateInfo->packageName);
        $this->assertSame($currentVersion, $packageUpdateInfo->currentVersion);
        $this->assertSame($latestVersion, $packageUpdateInfo->latestVersion);
        $this->assertSame($updateType, $packageUpdateInfo->updateType);
    }

    public static function provideValidConstructorArguments(): iterable
    {
        return [
            ['@hotwired/stimulus', '5.2.1', 'string', 'downgrade'],
            ['@hotwired/stimulus', 'v3.2.1', '3.2.1', 'up-to-date'],
            ['@hotwired/stimulus', '3.0.0-beta', 'v1.0.0', 'major'],
            ['@hotwired/stimulus', 'string', null, null],
        ];
    }

    /**
     * @dataProvider provideHasUpdateArguments
     */
    public function testHasUpdate($updateType, $expectUpdate)
    {
        $packageUpdateInfo = new PackageUpdateInfo(
            packageName: 'packageName',
            currentVersion: '1.0.0',
            updateType: $updateType,
        );
        $this->assertSame($expectUpdate, $packageUpdateInfo->hasUpdate());
    }

    public static function provideHasUpdateArguments(): iterable
    {
        return [
            ['downgrade', false],
            ['up-to-date', false],
            ['major', true],
            ['minor', true],
            ['patch', true],
        ];
    }
}
