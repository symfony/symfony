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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\Intl;

class IntlTest extends TestCase
{
    private $defaultLocale;

    protected function setUp(): void
    {
        self::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        self::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    /**
     * @requires extension intl
     */
    public function testIsExtensionLoadedChecksIfIntlExtensionIsLoaded()
    {
        self::assertTrue(Intl::isExtensionLoaded());
    }

    public function testGetIcuVersionReadsTheVersionOfInstalledIcuLibrary()
    {
        self::assertStringMatchesFormat('%d.%d', Intl::getIcuVersion());
    }

    public function testGetIcuDataVersionReadsTheVersionOfInstalledIcuData()
    {
        self::assertStringMatchesFormat('%d.%d', Intl::getIcuDataVersion());
    }

    public function testGetIcuStubVersionReadsTheVersionOfBundledStubs()
    {
        self::assertStringMatchesFormat('%d.%d', Intl::getIcuStubVersion());
    }

    public function testGetDataDirectoryReturnsThePathToIcuData()
    {
        self::assertDirectoryExists(Intl::getDataDirectory());
    }
}
