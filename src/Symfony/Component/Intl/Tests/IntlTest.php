<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Intl;

class IntlTest extends TestCase
{
    private string $defaultLocale;

    protected function setUp(): void
    {
        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);
    }

    #[RequiresPhpExtension('intl')]
    public function testIsExtensionLoadedChecksIfIntlExtensionIsLoaded(): void
    {
        $this->assertTrue(Intl::isExtensionLoaded());
    }

    public function testGetIcuVersionReadsTheVersionOfInstalledIcuLibrary(): void
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuVersion());
    }

    public function testGetIcuDataVersionReadsTheVersionOfInstalledIcuData(): void
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuDataVersion());
    }

    public function testGetIcuStubVersionReadsTheVersionOfBundledStubs(): void
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuStubVersion());
    }

    public function testGetDataDirectoryReturnsThePathToIcuData(): void
    {
        $this->assertDirectoryExists(Intl::getDataDirectory());
    }
}
