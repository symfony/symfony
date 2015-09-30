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
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * Test case for the {@link Locale} class.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @group legacy
 */
class LocaleTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        \Locale::setDefault('en');
    }

    public function testGetDisplayCountries()
    {
        $countries = Locale::getDisplayCountries('en');
        $this->assertEquals('Brazil', $countries['BR']);
    }

    public function testGetDisplayCountriesForSwitzerland()
    {
        IntlTestHelper::requireFullIntl($this);

        $countries = Locale::getDisplayCountries('de_CH');
        $this->assertEquals('Schweiz', $countries['CH']);
    }

    public function testGetCountries()
    {
        $countries = Locale::getCountries();
        $this->assertContains('BR', $countries);
    }

    public function testGetCountriesForSwitzerland()
    {
        $countries = Locale::getCountries();
        $this->assertContains('CH', $countries);
    }

    public function testGetDisplayLanguages()
    {
        $languages = Locale::getDisplayLanguages('en');
        $this->assertEquals('Brazilian Portuguese', $languages['pt_BR']);
    }

    public function testGetLanguages()
    {
        $languages = Locale::getLanguages();
        $this->assertContains('pt_BR', $languages);
    }

    public function testGetDisplayLocales()
    {
        $locales = Locale::getDisplayLocales('en');
        $this->assertEquals('Portuguese', $locales['pt']);
    }

    public function testGetLocales()
    {
        $locales = Locale::getLocales();
        $this->assertContains('pt', $locales);
    }
}
