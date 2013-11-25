<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Tests\Stub;

use Symfony\Component\Locale\Stub\StubLocale;
use Symfony\Component\Locale\Tests\TestCase as LocaleTestCase;

class StubLocaleTest extends LocaleTestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetDisplayCountriesWithUnsupportedLocale()
    {
        StubLocale::getDisplayCountries('pt_BR');
    }

    public function testGetDisplayCountries()
    {
        $countries = StubLocale::getDisplayCountries('en');
        $this->assertEquals('Brazil', $countries['BR']);
    }

    public function testGetCountries()
    {
        $countries = StubLocale::getCountries();
        $this->assertTrue(in_array('BR', $countries));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetDisplayLanguagesWithUnsupportedLocale()
    {
        StubLocale::getDisplayLanguages('pt_BR');
    }

    public function testGetDisplayLanguages()
    {
        $languages = StubLocale::getDisplayLanguages('en');
        $this->assertEquals('Brazilian Portuguese', $languages['pt_BR']);
    }

    public function testGetLanguages()
    {
        $languages = StubLocale::getLanguages();
        $this->assertTrue(in_array('pt_BR', $languages));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetCurrenciesDataWithUnsupportedLocale()
    {
        StubLocale::getCurrenciesData('pt_BR');
    }

    public function testGetCurrenciesData()
    {
        $symbol = $this->isSameAsIcuVersion('4.8') ? 'BR$' : 'R$';

        $currencies = StubLocale::getCurrenciesData('en');
        $this->assertEquals($symbol, $currencies['BRL']['symbol']);
        $this->assertEquals('Brazilian Real', $currencies['BRL']['name']);
        $this->assertEquals(2, $currencies['BRL']['fractionDigits']);
        $this->assertEquals(0, $currencies['BRL']['roundingIncrement']);
    }

    public function testGetDisplayCurrencies()
    {
        $currencies = StubLocale::getDisplayCurrencies('en');
        $this->assertEquals('Brazilian Real', $currencies['BRL']);

        // Checking that the cache is being used
        $currencies = StubLocale::getDisplayCurrencies('en');
        $this->assertEquals('Argentine Peso', $currencies['ARS']);
    }

    public function testGetCurrencies()
    {
        $currencies = StubLocale::getCurrencies();
        $this->assertTrue(in_array('BRL', $currencies));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetDisplayLocalesWithUnsupportedLocale()
    {
        StubLocale::getDisplayLocales('pt');
    }

    public function testGetDisplayLocales()
    {
        $locales = StubLocale::getDisplayLocales('en');
        $this->assertEquals('Portuguese', $locales['pt']);
    }

    public function testGetLocales()
    {
        $locales = StubLocale::getLocales();
        $this->assertTrue(in_array('pt', $locales));
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testAcceptFromHttp()
    {
        StubLocale::acceptFromHttp('pt-br,en-us;q=0.7,en;q=0.5');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testComposeLocale()
    {
        $subtags = array(
            'language' => 'pt',
            'script'   => 'Latn',
            'region'   => 'BR'
        );
        StubLocale::composeLocale($subtags);
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testFilterMatches()
    {
        StubLocale::filterMatches('pt-BR', 'pt-BR');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetAllVariants()
    {
        StubLocale::getAllVariants('pt_BR_Latn');
    }

    public function testGetDefault()
    {
        $this->assertEquals('en', StubLocale::getDefault());
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetDisplayLanguage()
    {
        StubLocale::getDisplayLanguage('pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetDisplayName()
    {
        StubLocale::getDisplayName('pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetDisplayRegion()
    {
        StubLocale::getDisplayRegion('pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetDisplayScript()
    {
        StubLocale::getDisplayScript('pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetDisplayVariant()
    {
        StubLocale::getDisplayVariant('pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetKeywords()
    {
        StubLocale::getKeywords('pt-BR@currency=BRL');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetPrimaryLanguage()
    {
        StubLocale::getPrimaryLanguage('pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetRegion()
    {
        StubLocale::getRegion('pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetScript()
    {
        StubLocale::getScript('pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testLookup()
    {
        $langtag = array(
            'pt-Latn-BR',
            'pt-BR'
        );
        StubLocale::lookup($langtag, 'pt-BR-x-priv1');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testParseLocale()
    {
        StubLocale::parseLocale('pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testSetDefault()
    {
        StubLocale::setDefault('pt_BR');
    }

    public function testSetDefaultAcceptsEn()
    {
        StubLocale::setDefault('en');
    }
}
