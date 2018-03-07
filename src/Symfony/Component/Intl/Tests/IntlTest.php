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

use Symfony\Component\Intl\Intl;
use PHPUnit\Framework\TestCase;

class IntlTest extends TestCase
{
    /**
     * @requires extension intl
     */
    public function testIsExtensionLoadedChecksIfIntlExtensionIsLoaded()
    {
        $this->assertTrue(Intl::isExtensionLoaded());
    }

    public function testGetCurrencyBundleCreatesTheCurrencyBundle()
    {
        $this->assertInstanceOf('Symfony\Component\Intl\ResourceBundle\CurrencyBundleInterface', Intl::getCurrencyBundle());
    }

    public function testGetLanguageBundleCreatesTheLanguageBundle()
    {
        $this->assertInstanceOf('Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface', Intl::getLanguageBundle());
    }

    public function testGetLocaleBundleCreatesTheLocaleBundle()
    {
        $this->assertInstanceOf('Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface', Intl::getLocaleBundle());
    }

    public function testGetRegionBundleCreatesTheRegionBundle()
    {
        $this->assertInstanceOf('Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface', Intl::getLocaleBundle());
    }

    public function testGetIcuVersionReadsTheVersionOfInstalledIcuLibrary()
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuVersion());
    }

    public function testGetIcuDataVersionReadsTheVersionOfInstalledIcuData()
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuDataVersion());
    }

    public function testGetIcuStubVersionReadsTheVersionOfBundledStubs()
    {
        $this->assertStringMatchesFormat('%d.%d', Intl::getIcuStubVersion());
    }

    public function testGetDataDirectoryReturnsThePathToIcuData()
    {
        $this->assertDirectoryExists(Intl::getDataDirectory());
    }
}
