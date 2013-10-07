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
use Symfony\Component\Intl\Test\ConsistencyTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class RegionBundleConsistencyTestCase extends ConsistencyTestCase
{
    protected static $localesWithoutTranslationForAnyCountry = array('ti');
    protected static $localesWithoutTranslationForUS = array('bem', 'dua', 'dyo', 'gv', 'ig', 'jgo', 'kl', 'kok', 'kw', 'mgo', 'nus', 'pa', 'ps', 'rw', 'uz');
    protected static $localesWithoutTranslationForDE = array('bem', 'dua', 'gv', 'ig', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'nus', 'pa', 'rw', 'uz');
    protected static $localesWithoutTranslationForGB = array('bem', 'dua', 'dyo', 'fo', 'ig', 'jgo', 'kl', 'kok', 'mgh', 'mgo', 'nus', 'pa', 'rw', 'uz');
    protected static $localesWithoutTranslationForFR = array('bem', 'bo', 'dua', 'gv', 'ig', 'kl', 'kok', 'kw', 'mgo', 'nus', 'pa', 'rw', 'uz');
    protected static $localesWithoutTranslationForIT = array('bem', 'dua', 'gv', 'ig', 'kl', 'kok', 'kw', 'mgo', 'nus', 'pa', 'rw', 'uz');
    protected static $localesWithoutTranslationForBR = array('bem', 'bo', 'dua', 'gv', 'haw', 'ig', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'pa', 'ps', 'rw', 'uz');
    protected static $localesWithoutTranslationForRU = array('bem', 'dua', 'dyo', 'gv', 'ig', 'kl', 'kok', 'kw', 'mgh', 'mgo', 'nus', 'pa', 'rw', 'uz');
    protected static $localesWithoutTranslationForCN = array('bem', 'dua', 'gv', 'kl', 'kok', 'kw', 'mgo', 'pa', 'rw', 'uz');

    /**
     * @var \Symfony\Component\Intl\ResourceBundle\RegionBundleInterface
     */
    protected static $regionBundle;

    public static function setUpBeforeClass()
    {
        static::$regionBundle = Intl::getRegionBundle();
    }

    public function provideLocalesWithCountries()
    {
        $parameters = array();

        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyCountry);
        $aliasesWithoutCountries = Intl::getLocaleBundle()->getLocaleAliases();
        $aliasesWithoutCountries = array_intersect_assoc($aliasesWithoutCountries, static::$localesWithoutTranslationForAnyCountry);

        $locales = Intl::getLocaleBundle()->getLocales();

        // remove locales that have no "Countries" block or are an alias to a locale
        // without "Countries" block
        $locales = array_filter($locales, function ($locale) use ($localesWithoutScript, $aliasesWithoutCountries) {
            while (null !== $locale) {
                if (isset($localesWithoutScript[$locale]) || isset($aliasesWithoutCountries[$locale])) {
                    return false;
                }

                $locale = Intl::getFallbackLocale($locale);
            }

            return true;
        });

        foreach ($locales as $locale) {
            $parameters[] = array($locale);
        }

        return $parameters;
    }

    public function provideLocaleAliasesWithCountries()
    {
        $parameters = array();

        $aliases = Intl::getLocaleBundle()->getLocaleAliases();
        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyCountry);

        // Remove aliases that point to a locale without "Countries" block
        $aliases = array_filter($aliases, function ($targetLocale) use ($localesWithoutScript) {
            while (null !== $targetLocale) {
                if (isset($localesWithoutScript[$targetLocale])) {
                    return false;
                }

                $targetLocale = Intl::getFallbackLocale($targetLocale);
            }

            return true;
        });

        foreach ($aliases as $alias => $ofLocale) {
            $parameters[] = array($alias, $ofLocale);
        }

        return $parameters;
    }

    /**
     * @dataProvider provideRootLocales
     */
    public function testGetCountryNames($displayLocale)
    {
        try {
            $countryNames = static::$regionBundle->getCountryNames($displayLocale);

            if (in_array($displayLocale, static::$localesWithoutTranslationForAnyCountry)) {
                $this->fail('Did not expect any country translations for locale '.$displayLocale);
            }
        } catch (NoSuchEntryException $e) {
            if (in_array($displayLocale, static::$localesWithoutTranslationForAnyCountry)) {
                return;
            }

            throw $e;
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForUS)) {
            $this->assertArrayNotHasKey('US', $countryNames);
        } else {
            $this->assertArrayHasKey('US', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForDE)) {
            $this->assertArrayNotHasKey('DE', $countryNames);
        } else {
            $this->assertArrayHasKey('DE', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForGB)) {
            $this->assertArrayNotHasKey('GB', $countryNames);
        } else {
            $this->assertArrayHasKey('GB', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForFR)) {
            $this->assertArrayNotHasKey('FR', $countryNames);
        } else {
            $this->assertArrayHasKey('FR', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForIT)) {
            $this->assertArrayNotHasKey('IT', $countryNames);
        } else {
            $this->assertArrayHasKey('IT', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForBR)) {
            $this->assertArrayNotHasKey('BR', $countryNames);
        } else {
            $this->assertArrayHasKey('BR', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForRU)) {
            $this->assertArrayNotHasKey('RU', $countryNames);
        } else {
            $this->assertArrayHasKey('RU', $countryNames);
        }

        if (in_array($displayLocale, static::$localesWithoutTranslationForCN)) {
            $this->assertArrayNotHasKey('CN', $countryNames);
        } else {
            $this->assertArrayHasKey('CN', $countryNames);
        }
    }

    /**
     * @dataProvider provideLocaleAliasesWithCountries
     * @group locale-alias-based
     */
    public function testGetCountryNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            static::$regionBundle->getCountryNames($ofLocale),
            static::$regionBundle->getCountryNames($alias)
        );
    }

    /**
     * @dataProvider provideLocalesWithCountries
     */
    public function testGetCountryNamesAndGetCountryNameAreConsistent($displayLocale)
    {
        $names = static::$regionBundle->getCountryNames($displayLocale);

        foreach ($names as $country => $name) {
            $this->assertSame($name, static::$regionBundle->getCountryName($country, $displayLocale));
        }
    }
}
