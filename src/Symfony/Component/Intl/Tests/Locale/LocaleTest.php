<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Locale;

class LocaleTest extends AbstractLocaleTest
{
    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testAcceptFromHttp()
    {
        $this->call('acceptFromHttp', 'pt-br,en-us;q=0.7,en;q=0.5');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testComposeLocale()
    {
        $subtags = array(
            'language' => 'pt',
            'script'   => 'Latn',
            'region'   => 'BR'
        );
        $this->call('composeLocale', $subtags);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testFilterMatches()
    {
        $this->call('filterMatches', 'pt-BR', 'pt-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetAllVariants()
    {
        $this->call('getAllVariants', 'pt_BR_Latn');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayLanguage()
    {
        $this->call('getDisplayLanguage', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayName()
    {
        $this->call('getDisplayName', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayRegion()
    {
        $this->call('getDisplayRegion', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayScript()
    {
        $this->call('getDisplayScript', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayVariant()
    {
        $this->call('getDisplayVariant', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetKeywords()
    {
        $this->call('getKeywords', 'pt-BR@currency=BRL');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetPrimaryLanguage()
    {
        $this->call('getPrimaryLanguage', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetRegion()
    {
        $this->call('getRegion', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetScript()
    {
        $this->call('getScript', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testLookup()
    {
        $langtag = array(
            'pt-Latn-BR',
            'pt-BR'
        );
        $this->call('lookup', $langtag, 'pt-BR-x-priv1');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testParseLocale()
    {
        $this->call('parseLocale', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetDefault()
    {
        $this->call('setDefault', 'pt_BR');
    }

    public function testSetDefaultAcceptsEn()
    {
        $this->call('setDefault', 'en');
    }

    protected function call($methodName)
    {
        $args = array_slice(func_get_args(), 1);

        return call_user_func_array(array('Symfony\Component\Intl\Locale\Locale', $methodName), $args);
    }
}
