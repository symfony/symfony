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
use Symfony\Component\Intl\ResourceBundle\CurrencyBundleInterface;
use Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface;
use Symfony\Component\Intl\ResourceBundle\LocaleBundleInterface;
use Symfony\Component\Intl\ResourceBundle\RegionBundleInterface;

class IntlTest extends TestCase
{
    private $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    /**
     * @requires extension intl
     */
    public function testIsExtensionLoadedChecksIfIntlExtensionIsLoaded()
    {
        $this->assertTrue(Intl::isExtensionLoaded());
    }

    /**
     * @group legacy
     */
    public function testGetCurrencyBundleCreatesTheCurrencyBundle()
    {
        $this->assertInstanceOf(CurrencyBundleInterface::class, Intl::getCurrencyBundle());
    }

    /**
     * @group legacy
     */
    public function testGetLanguageBundleCreatesTheLanguageBundle()
    {
        $this->assertInstanceOf(LanguageBundleInterface::class, Intl::getLanguageBundle());
    }

    /**
     * @group legacy
     */
    public function testGetLocaleBundleCreatesTheLocaleBundle()
    {
        $this->assertInstanceOf(LocaleBundleInterface::class, Intl::getLocaleBundle());
    }

    /**
     * @group legacy
     */
    public function testGetRegionBundleCreatesTheRegionBundle()
    {
        $this->assertInstanceOf(RegionBundleInterface::class, Intl::getRegionBundle());
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

    /**
     * @group legacy
     * @requires extension intl
     */
    public function testLocaleAliasesAreLoaded()
    {
        \Locale::setDefault('zh_TW');
        $countryNameZhTw = Intl::getRegionBundle()->getCountryName('AD');

        \Locale::setDefault('zh_Hant_TW');
        $countryNameHantZhTw = Intl::getRegionBundle()->getCountryName('AD');

        \Locale::setDefault('zh');
        $countryNameZh = Intl::getRegionBundle()->getCountryName('AD');

        $this->assertSame($countryNameZhTw, $countryNameHantZhTw, 'zh_TW is an alias to zh_Hant_TW');
        $this->assertNotSame($countryNameZh, $countryNameZhTw, 'zh_TW does not fall back to zh');
    }
}
