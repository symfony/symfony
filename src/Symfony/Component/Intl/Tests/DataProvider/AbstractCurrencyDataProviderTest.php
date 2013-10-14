<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\DataProvider;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractCurrencyDataProviderTest extends AbstractDataProviderTest
{
    protected static $localesWithoutTranslationForAnyCurrency = array();
    protected static $localesWithoutTranslationForCurrency = array();

    protected static $currencies = array();

    protected static $alpha3ToNumeric = array();

    /**
     * @var \Symfony\Component\Icu\CurrencyDataProvider
     */
    protected $dataProvider;

    protected function setUp()
    {
        $this->dataProvider = $this->createDataProvider();
    }

    abstract protected function createDataProvider();

    public function provideCurrencies()
    {
        return array_map(
            function ($currency) { return array($currency); },
            static::$currencies
        );
    }

    public function testGetAlpha3CurrencyCodes()
    {
        $this->assertEquals(static::$currencies, $this->dataProvider->getCurrencies());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetDisplayNames($displayLocale)
    {
        $names = $this->dataProvider->getDisplayNames($displayLocale);

        $keys = array_keys($names);

        sort($keys);

        $this->assertEquals(static::$currencies, $keys);

        // Names should be sorted
        $sortedNames = $names;
        $collator = new \Collator($displayLocale);
        $collator->asort($names);

        $this->assertSame($sortedNames, $names);
    }

    /**
     * @dataProvider provideLocaleAliases
     * @group locale-alias-based
     */
    public function testGetDisplayNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            $this->dataProvider->getDisplayNames($ofLocale),
            $this->dataProvider->getDisplayNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetDisplayName($displayLocale)
    {
        $names = $this->dataProvider->getDisplayNames($displayLocale);

        foreach ($names as $currency => $name) {
            $this->assertSame($name, $this->dataProvider->getDisplayName($currency, $displayLocale));
        }
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetSymbol($displayLocale)
    {
        $names = $this->dataProvider->getDisplayNames($displayLocale);

        foreach ($names as $currency => $name) {
            $this->assertGreaterThan(0, mb_strlen($this->dataProvider->getSymbol($currency, $displayLocale)));
        }
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetFractionDigits($currency)
    {
        $this->assertTrue(is_numeric($this->dataProvider->getFractionDigits($currency)));
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetRoundingIncrement($currency)
    {
        $this->assertTrue(is_numeric($this->dataProvider->getRoundingIncrement($currency)));
    }

    public function provideCurrenciesWithNumericEquivalent()
    {
        return array_map(
            function ($value) { return array($value); },
            array_keys(static::$alpha3ToNumeric)
        );
    }

    /**
     * @dataProvider provideCurrenciesWithNumericEquivalent
     */
    public function testGetNumericCode($currency)
    {
        $this->assertSame(static::$alpha3ToNumeric[$currency], $this->dataProvider->getNumericCode($currency));
    }

    public function provideCurrenciesWithoutNumericEquivalent()
    {
        return array_map(
            function ($value) { return array($value); },
            array_diff(static::$currencies, array_keys(static::$alpha3ToNumeric))
        );
    }

    /**
     * @dataProvider provideCurrenciesWithoutNumericEquivalent
     */
    public function testGetNumericCodeReturnsZeroIfNoNumericEquivalent($currency)
    {
        $this->assertSame(0, $this->dataProvider->getNumericCode($currency));
    }
}
