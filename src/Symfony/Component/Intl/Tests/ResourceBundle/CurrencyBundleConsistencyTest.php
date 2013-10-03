<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\ResourceBundle;

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Test\ConsistencyTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CurrencyBundleConsistencyTest extends ConsistencyTest
{
    // The below arrays document the current state of the ICU data.
    // This state is verified in the tests below.
    // You can add arbitrary rules here if you want to document the availability
    // of other currencies.
    private static $localesWithoutTranslationForUSD = array('as', 'bem', 'bo', 'dua', 'dyo', 'eo', 'fo', 'gv', 'ig', 'ii', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'mt', 'nus', 'or', 'pa', 'ps', 'to', 'rw', 'uz', 'yav');
    private static $localesWithoutTranslationForEUR = array('as', 'bem', 'bo', 'dua', 'eo', 'fo', 'gv', 'haw', 'ig', 'ii', 'kok', 'kw', 'mgh', 'mgo', 'nus', 'or', 'pa', 'ps', 'to', 'rw', 'uz');
    private static $localesWithoutTranslationForGBP = array('as', 'bem', 'bo', 'dua', 'dyo', 'eo', 'fo', 'gv', 'haw', 'ig', 'ii', 'jgo', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'mt', 'nus', 'or', 'pa', 'ps', 'so', 'to', 'rw', 'uz');
    private static $localesWithoutTranslationForJPY = array('as', 'bem', 'bo', 'dua', 'eo', 'fo', 'gv', 'haw', 'ig', 'ii', 'jgo', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'mt', 'nus', 'or', 'pa', 'ps', 'so', 'to', 'rw', 'uz');
    private static $localesWithoutTranslationForCNY = array('as', 'bem', 'dua', 'eo', 'fo', 'gv', 'haw', 'ig', 'jgo', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'mt', 'nus', 'or', 'pa', 'ps', 'so', 'to', 'rw', 'uz');

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\CurrencyBundleInterface
     */
    private static $currencyBundle;

    public static function setUpBeforeClass()
    {
        static::$currencyBundle = Intl::getCurrencyBundle();
    }

    public function provideCurrencies()
    {
        $locales = Intl::getLocaleBundle()->getLocales();
        $aliases = Intl::getLocaleBundle()->getLocaleAliases();

        // Filter non-root locales
        $locales = array_filter($locales, function ($locale) use ($aliases) {
            return false === strpos($locale, '_') && !isset($aliases[$locale]);
        });

        $currencies = array();
        $currencyBundle = Intl::getCurrencyBundle();

        // Merge all currency codes that can be found for any locale
        foreach ($locales as $locale) {
            $currencies = array_replace($currencies, $currencyBundle->getCurrencyNames($locale));
        }

        $currencies = array_keys($currencies);
        $parameters = array();

        foreach ($currencies as $currency) {
            $parameters[] = array($currency);
        }

        return $parameters;
    }

    /**
     * @dataProvider provideRootLocales
     */
    public function testGetCurrencyNames($displayLocale)
    {
        $currencyNames = static::$currencyBundle->getCurrencyNames($displayLocale);

        if (in_array($displayLocale, static::$localesWithoutTranslationForUSD)) {
            $this->assertArrayNotHasKey('USD', $currencyNames);
        } else {
            $this->assertArrayHasKey('USD', $currencyNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForEUR)) {
            $this->assertArrayNotHasKey('EUR', $currencyNames);
        } else {
            $this->assertArrayHasKey('EUR', $currencyNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForGBP)) {
            $this->assertArrayNotHasKey('GBP', $currencyNames);
        } else {
            $this->assertArrayHasKey('GBP', $currencyNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForJPY)) {
            $this->assertArrayNotHasKey('JPY', $currencyNames);
        } else {
            $this->assertArrayHasKey('JPY', $currencyNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForCNY)) {
            $this->assertArrayNotHasKey('CNY', $currencyNames);
        } else {
            $this->assertArrayHasKey('CNY', $currencyNames);
        }
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetCurrencyNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            static::$currencyBundle->getCurrencyNames($ofLocale),
            static::$currencyBundle->getCurrencyNames($alias)
        );
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetFractionDigits($currency)
    {
        $this->assertTrue(is_numeric(static::$currencyBundle->getFractionDigits($currency)));
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetRoundingIncrement($currency)
    {
        $this->assertTrue(is_numeric(static::$currencyBundle->getRoundingIncrement($currency)));
    }

    /**
     * @dataProvider provideLocales
     * @group icu-consistency
     */
    public function testGetCurrencyNamesAndGetCurrencyNameAreConsistent($displayLocale)
    {
        $names = static::$currencyBundle->getCurrencyNames($displayLocale);

        foreach ($names as $currency => $name) {
            $this->assertSame($name, static::$currencyBundle->getCurrencyName($currency, $displayLocale));
        }
    }

    /**
     * @dataProvider provideLocales
     * @group icu-consistency
     */
    public function testGetCurrencyNamesAndGetCurrencySymbolAreConsistent($displayLocale)
    {
        $names = static::$currencyBundle->getCurrencyNames($displayLocale);

        foreach ($names as $currency => $name) {
            $this->assertGreaterThan(0, mb_strlen(static::$currencyBundle->getCurrencySymbol($currency, $displayLocale)));
        }
    }
}
