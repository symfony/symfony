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
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @group intl-data
 */
class LocalesTest extends ResourceBundleTestCase
{
    public function testGetLocales()
    {
        $this->assertSame(static::getLocales(), Locales::getLocales());
    }

    public function testGetAliases()
    {
        $this->assertSame(static::getLocaleAliases(), Locales::getAliases());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $locales = array_keys(Locales::getNames($displayLocale));

        sort($locales);

        // We can't assert on exact list of locale, as there's too many variations.
        // The best we can do is to make sure getNames() returns a subset of what getLocales() returns.
        $this->assertNotEmpty($locales);
        $this->assertEmpty(array_diff($locales, static::getLocales()));
    }

    public function testGetNamesDefaultLocale()
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $this->assertSame(Locales::getNames('de_AT'), Locales::getNames());
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        if ('en' !== $ofLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        // Can't use assertSame(), because some aliases contain scripts with
        // different collation (=order of output) than their aliased locale
        // e.g. sr_Latn_ME => sr_ME
        $this->assertEquals(Locales::getNames($ofLocale), Locales::getNames($alias));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $names = Locales::getNames($displayLocale);

        foreach ($names as $locale => $name) {
            $this->assertSame($name, Locales::getName($locale, $displayLocale));
        }
    }

    public function testGetNameDefaultLocale()
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $names = Locales::getNames('de_AT');

        foreach ($names as $locale => $name) {
            $this->assertSame($name, Locales::getName($locale));
        }
    }

    public function testGetNameWithInvalidLocale()
    {
        $this->expectException(MissingResourceException::class);
        Locales::getName('foo');
    }

    public function testGetNameWithAliasLocale()
    {
        $this->assertSame(Locales::getName('tl_PH'), Locales::getName('fil_PH'));
    }

    public function testExists()
    {
        $this->assertTrue(Locales::exists('nl_NL'));
        $this->assertTrue(Locales::exists('tl_PH'));
        $this->assertTrue(Locales::exists('fil_PH')); // alias for "tl_PH"
        $this->assertTrue(Locales::exists('es_419'));
        $this->assertFalse(Locales::exists('zxx_ZZ'));
    }
}
