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

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locale;
use Symfony\Component\Intl\Test\ConsistencyTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class RegionBundleConsistencyTestCase extends ConsistencyTestCase
{
    protected static $localesWithoutTranslationForAnyCountry = array();
    protected static $localesWithoutTranslationForCountry = array();

    public function provideLocalesWithCountries()
    {
        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyCountry);
        $aliasesWithoutCountries = array_intersect_assoc($this->getLocaleAliases(), static::$localesWithoutTranslationForAnyCountry);

        // remove locales that have no "Countries" block or are an alias to a locale
        // without "Countries" block
        $locales = array_filter($this->getLocales(), function ($locale) use ($localesWithoutScript, $aliasesWithoutCountries) {
            while (null !== $locale) {
                if (isset($localesWithoutScript[$locale]) || isset($aliasesWithoutCountries[$locale])) {
                    return false;
                }

                $locale = Locale::getFallback($locale);
            }

            return true;
        });

        return array_map(
            function ($locale) { return array($locale); },
            $locales
        );
    }

    public function provideLocaleAliasesWithCountries()
    {
        $localesWithoutScript = array_flip(static::$localesWithoutTranslationForAnyCountry);

        // Remove aliases that point to a locale without "Countries" block
        $aliases = array_filter($this->getLocaleAliases(), function ($targetLocale) use ($localesWithoutScript) {
            while (null !== $targetLocale) {
                if (isset($localesWithoutScript[$targetLocale])) {
                    return false;
                }

                $targetLocale = Locale::getFallback($targetLocale);
            }

            return true;
        });

        return array_map(
            function ($alias, $ofLocale) { return array($alias, $ofLocale); },
            array_keys($aliases),
            $aliases
        );
    }

    public function testGetCountryNames()
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getRegionBundle()->getCountryNames($displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (MissingResourceException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForAnyCountry, $untranslatedLocales);
    }

    public function provideTestedCountries()
    {
        return array_map(
            function ($country) { return array($country); },
            array_keys(static::$localesWithoutTranslationForCountry)
        );
    }

    /**
     * @dataProvider provideTestedCountries
     */
    public function testGetCountryName($country)
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getRegionBundle()->getCountryName($country, $displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (MissingResourceException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, static::$localesWithoutTranslationForAnyCountry, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForCountry[$country], $untranslatedLocales);
    }

    /**
     * @dataProvider provideLocaleAliasesWithCountries
     * @group locale-alias-based
     */
    public function testGetCountryNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            Intl::getRegionBundle()->getCountryNames($ofLocale),
            Intl::getRegionBundle()->getCountryNames($alias)
        );
    }

    /**
     * @dataProvider provideLocalesWithCountries
     */
    public function testGetCountryNamesAndGetCountryNameAreConsistent($displayLocale)
    {
        $names = Intl::getRegionBundle()->getCountryNames($displayLocale);

        foreach ($names as $country => $name) {
            $this->assertSame($name, Intl::getRegionBundle()->getCountryName($country, $displayLocale));
        }
    }
}
