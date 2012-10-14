<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Tests;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Tests\TestCase as LocaleTestCase;

class LocaleTest extends LocaleTestCase
{
    public function testGetDisplayCountriesReturnsFullListForSubLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        Locale::setDefault('de_CH');

        $countriesDe = Locale::getDisplayCountries('de');
        $countriesDeCh = Locale::getDisplayCountries('de_CH');

        $this->assertEquals(count($countriesDe), count($countriesDeCh));
        $this->assertEquals($countriesDe['BD'], 'Bangladesch');
        $this->assertEquals($countriesDeCh['BD'], 'Bangladesh');
    }

    public function testGetDisplayLanguagesReturnsFullListForSubLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        Locale::setDefault('de_CH');

        $languagesDe = Locale::getDisplayLanguages('de');
        $languagesDeCh = Locale::getDisplayLanguages('de_CH');

        $this->assertEquals(count($languagesDe), count($languagesDeCh));
        $this->assertEquals($languagesDe['be'], 'Weißrussisch');
        $this->assertEquals($languagesDeCh['be'], 'Weissrussisch');
    }

    public function testGetDisplayLocalesReturnsFullListForSubLocale()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        Locale::setDefault('de_CH');

        $localesDe = Locale::getDisplayLocales('de');
        $localesDeCh = Locale::getDisplayLocales('de_CH');

        $this->assertEquals(count($localesDe), count($localesDeCh));
        $this->assertEquals($localesDe['be'], 'Weißrussisch');
        $this->assertEquals($localesDeCh['be'], 'Weissrussisch');
    }
}
