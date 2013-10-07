<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Test;

use Symfony\Component\Intl\Exception\NoSuchEntryException;
use Symfony\Component\Intl\Intl;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class CurrencyBundleConsistencyTestCase extends ConsistencyTestCase
{
    protected static $localesWithoutTranslationForAnyCurrency = array();
    protected static $localesWithoutTranslationForCurrency = array();

    protected static $currencies = array();

    public function provideCurrencies()
    {
        $aliases = $this->getLocaleAliases();

        // Filter non-root and alias locales
        $locales = array_filter($this->getLocales(), function ($locale) use ($aliases) {
            return false === strpos($locale, '_') && !isset($aliases[$locale]);
        });

        return array_map(
            function ($currency) { return array($currency); },
            static::$currencies
        );
    }

    public function testGetCurrencyNames()
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getCurrencyBundle()->getCurrencyNames($displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (NoSuchEntryException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForAnyCurrency, $untranslatedLocales);
    }

    public function provideTestedCurrencies()
    {
        return array_map(
            function ($currency) { return array($currency); },
            array_keys(static::$localesWithoutTranslationForCurrency)
        );
    }

    /**
     * @dataProvider provideTestedCurrencies
     */
    public function testGetCurrencyName($currency)
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getCurrencyBundle()->getCurrencyName($currency, $displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (NoSuchEntryException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, static::$localesWithoutTranslationForAnyCurrency, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForCurrency[$currency], $untranslatedLocales);
    }

    /**
     * @dataProvider provideLocaleAliases
     * @group locale-alias-based
     */
    public function testGetCurrencyNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            Intl::getCurrencyBundle()->getCurrencyNames($ofLocale),
            Intl::getCurrencyBundle()->getCurrencyNames($alias)
        );
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetFractionDigits($currency)
    {
        $this->assertTrue(is_numeric(Intl::getCurrencyBundle()->getFractionDigits($currency)));
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetRoundingIncrement($currency)
    {
        $this->assertTrue(is_numeric(Intl::getCurrencyBundle()->getRoundingIncrement($currency)));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetCurrencyNamesAndGetCurrencyNameAreConsistent($displayLocale)
    {
        $names = Intl::getCurrencyBundle()->getCurrencyNames($displayLocale);

        foreach ($names as $currency => $name) {
            $this->assertSame($name, Intl::getCurrencyBundle()->getCurrencyName($currency, $displayLocale));
        }
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetCurrencyNamesAndGetCurrencySymbolAreConsistent($displayLocale)
    {
        $names = Intl::getCurrencyBundle()->getCurrencyNames($displayLocale);

        foreach ($names as $currency => $name) {
            $this->assertGreaterThan(0, mb_strlen(Intl::getCurrencyBundle()->getCurrencySymbol($currency, $displayLocale)));
        }
    }
}
