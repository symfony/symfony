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

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Tests\IntlTestCase;
use Symfony\Component\Intl\Util\IcuVersion;
use Symfony\Component\Locale\Locale;

/**
 * Test case for the {@link Locale} class.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleTest extends IntlTestCase
{
    protected function setUp()
    {
        // This class extends from \Ļocale, so we need the extension
        if (!Intl::isExtensionLoaded()) {
            $this->markTestSkipped('The intl extension is not available.');
        }

        Intl::setDataSource(Intl::STUB);

        Locale::setDefault('en');
    }

    public function testGetDisplayCountries()
    {
        $countries = Locale::getDisplayCountries('en');
        $this->assertEquals('Brazil', $countries['BR']);
    }

    public function testGetCountries()
    {
        $countries = Locale::getCountries();
        $this->assertTrue(in_array('BR', $countries));
    }

    public function testGetDisplayLanguages()
    {
        $languages = Locale::getDisplayLanguages('en');
        $this->assertEquals('Brazilian Portuguese', $languages['pt_BR']);
    }

    public function testGetLanguages()
    {
        $languages = Locale::getLanguages();
        $this->assertTrue(in_array('pt_BR', $languages));
    }

    public function testGetDisplayLocales()
    {
        $locales = Locale::getDisplayLocales('en');
        $this->assertEquals('Portuguese', $locales['pt']);
    }

    public function testGetLocales()
    {
        $locales = Locale::getLocales();
        $this->assertTrue(in_array('pt', $locales));
    }
}
