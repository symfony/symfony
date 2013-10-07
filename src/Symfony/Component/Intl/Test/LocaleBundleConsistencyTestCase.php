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
abstract class LocaleBundleConsistencyTestCase extends ConsistencyTestCase
{
    protected static $localesWithoutTranslationForAnyLocale = array();
    protected static $localesWithoutTranslationForLocale = array();

    public function testGetLocales()
    {
        $this->assertEquals($this->getLocales(), Intl::getLocaleBundle()->getLocales());
    }

    public function testGetLocaleAliases()
    {
        $this->assertEquals($this->getLocaleAliases(), Intl::getLocaleBundle()->getLocaleAliases());
    }

    public function testGetLocaleNames()
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getLocaleBundle()->getLocaleNames($displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (NoSuchEntryException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForAnyLocale, $untranslatedLocales);
    }

    public function provideTestedLocales()
    {
        return array_map(
            function ($locale) { return array($locale); },
            array_keys(static::$localesWithoutTranslationForLocale)
        );
    }

    /**
     * @dataProvider provideTestedLocales
     */
    public function testGetLocaleName($locale)
    {
        $translatedLocales = array();
        $rootLocales = $this->getRootLocales();

        foreach ($rootLocales as $displayLocale) {
            try {
                Intl::getLocaleBundle()->getLocaleName($locale ?: $displayLocale, $displayLocale);
                $translatedLocales[] = $displayLocale;
            } catch (NoSuchEntryException $e) {
            }
        }

        $untranslatedLocales = array_diff($rootLocales, static::$localesWithoutTranslationForAnyLocale, $translatedLocales);

        sort($untranslatedLocales);

        $this->assertEquals(static::$localesWithoutTranslationForLocale[$locale], $untranslatedLocales);
    }

    /**
     * @dataProvider provideLocaleAliases
     * @group locale-alias-based
     */
    public function testGetLocaleNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            Intl::getLocaleBundle()->getLocaleNames($ofLocale),
            Intl::getLocaleBundle()->getLocaleNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetLocaleNamesAndGetLocaleNameAreConsistent($displayLocale)
    {
        $names = Intl::getLocaleBundle()->getLocaleNames($displayLocale);

        foreach ($names as $locale => $name) {
            $this->assertSame($name, Intl::getLocaleBundle()->getLocaleName($locale, $displayLocale));
        }
    }
}
