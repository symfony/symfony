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

use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Locales;

/**
 * @group intl-data
 */
class LocalesTest extends ResourceBundleTestCase
{
    public function testGetLocales()
    {
        self::assertSame($this->getLocales(), Locales::getLocales());
    }

    public function testGetAliases()
    {
        self::assertSame($this->getLocaleAliases(), Locales::getAliases());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $locales = array_keys(Locales::getNames($displayLocale));

        sort($locales);

        // We can't assert on exact list of locale, as there's too many variations.
        // The best we can do is to make sure getNames() returns a subset of what getLocales() returns.
        self::assertNotEmpty($locales);
        self::assertEmpty(array_diff($locales, $this->getLocales()));
    }

    public function testGetNamesDefaultLocale()
    {
        \Locale::setDefault('de_AT');

        self::assertSame(Locales::getNames('de_AT'), Locales::getNames());
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        // Can't use assertSame(), because some aliases contain scripts with
        // different collation (=order of output) than their aliased locale
        // e.g. sr_Latn_ME => sr_ME
        self::assertEquals(Locales::getNames($ofLocale), Locales::getNames($alias));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        $names = Locales::getNames($displayLocale);

        foreach ($names as $locale => $name) {
            self::assertSame($name, Locales::getName($locale, $displayLocale));
        }
    }

    public function testGetNameDefaultLocale()
    {
        \Locale::setDefault('de_AT');

        $names = Locales::getNames('de_AT');

        foreach ($names as $locale => $name) {
            self::assertSame($name, Locales::getName($locale));
        }
    }

    public function testGetNameWithInvalidLocale()
    {
        self::expectException(MissingResourceException::class);
        Locales::getName('foo');
    }

    public function testGetNameWithAliasLocale()
    {
        self::assertSame(Locales::getName('tl_PH'), Locales::getName('fil_PH'));
    }

    public function testExists()
    {
        self::assertTrue(Locales::exists('nl_NL'));
        self::assertTrue(Locales::exists('tl_PH'));
        self::assertTrue(Locales::exists('fil_PH')); // alias for "tl_PH"
        self::assertTrue(Locales::exists('es_419'));
        self::assertFalse(Locales::exists('zxx_ZZ'));
    }
}
