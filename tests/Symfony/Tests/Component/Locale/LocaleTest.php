<?php

namespace Symfony\Tests\Component\Locale;

use Symfony\Component\Locale\Locale;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class LocaleTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The intl extension is not available.');
        }
    }

    public function testAcceptFromHttp()
    {
        $this->assertEquals('pt_BR', Locale::acceptFromHttp('pt-br,en-us;q=0.7,en;q=0.5'));
    }

    public function testComposeLocale()
    {
        $subtags = array(
            'language' => 'pt',
            'script'   => 'Latn',
            'region'   => 'BR'
        );
        $this->assertEquals('pt_Latn_BR', Locale::composeLocale($subtags));
    }

    public function testFilterMatches()
    {
        $this->assertTrue(Locale::filterMatches('pt-BR', 'pt-BR'));
    }

    public function testGetAllVariants()
    {
        $this->assertEquals(array('LATN'), Locale::getAllVariants('pt_BR_Latn'));
    }

    /**
     * @covers Symfony\Component\Locale\Locale::getDefault
     * @covers Symfony\Component\Locale\Locale::setDefault
     */
    public function testGetDefault()
    {
        Locale::setDefault('en_US');
        $this->assertEquals('en_US', Locale::getDefault());
    }

    public function testGetDisplayLanguage()
    {
        $this->assertEquals('Portuguese', Locale::getDisplayLanguage('pt-Latn-BR', 'en'));
    }

    public function testGetDisplayName()
    {
        $this->assertEquals('Portuguese (Latin, Brazil)', Locale::getDisplayName('pt-Latn-BR', 'en'));
    }

    public function testGetDisplayRegion()
    {
        $this->assertEquals('Brazil', Locale::getDisplayRegion('pt-Latn-BR', 'en'));
    }

    public function testGetDisplayScript()
    {
        $this->assertEquals('Latin', Locale::getDisplayScript('pt-Latn-BR', 'en'));
    }

    public function testGetDisplayVariant()
    {
        $this->assertEmpty(Locale::getDisplayVariant('pt-Latn-BR', 'en'));
    }

    public function testGetKeywords()
    {
        $this->assertEquals(
            array('currency' => 'BRL'),
            Locale::getKeywords('pt-BR@currency=BRL')
        );
    }

    public function testGetPrimaryLanguage()
    {
        $this->assertEquals('pt', Locale::getPrimaryLanguage('pt-Latn-BR'));
    }

    public function testGetRegion()
    {
        $this->assertEquals('BR', Locale::getRegion('pt-Latn-BR'));
    }

    public function testGetScript()
    {
        $this->assertEquals('Latn', Locale::getScript('pt-Latn-BR'));
    }

    public function testLookup()
    {
        $langtag = array(
            'pt-Latn-BR',
            'pt-BR'
        );
        $this->assertEquals('pt-BR', Locale::lookup($langtag, 'pt-BR-x-priv1'));
    }

    public function testParseLocale()
    {
        $expected = array(
            'language' => 'pt',
            'script'   => 'Latn',
            'region'   => 'BR'
        );
        $this->assertEquals($expected, Locale::parseLocale('pt-Latn-BR'));
    }
}
