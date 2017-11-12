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
    public function testAcceptFromHttp(): void
    {
        $this->call('acceptFromHttp', 'pt-br,en-us;q=0.7,en;q=0.5');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testComposeLocale(): void
    {
        $subtags = array(
            'language' => 'pt',
            'script' => 'Latn',
            'region' => 'BR',
        );
        $this->call('composeLocale', $subtags);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testFilterMatches(): void
    {
        $this->call('filterMatches', 'pt-BR', 'pt-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetAllVariants(): void
    {
        $this->call('getAllVariants', 'pt_BR_Latn');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayLanguage(): void
    {
        $this->call('getDisplayLanguage', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayName(): void
    {
        $this->call('getDisplayName', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayRegion(): void
    {
        $this->call('getDisplayRegion', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayScript(): void
    {
        $this->call('getDisplayScript', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetDisplayVariant(): void
    {
        $this->call('getDisplayVariant', 'pt-Latn-BR', 'en');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetKeywords(): void
    {
        $this->call('getKeywords', 'pt-BR@currency=BRL');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetPrimaryLanguage(): void
    {
        $this->call('getPrimaryLanguage', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetRegion(): void
    {
        $this->call('getRegion', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetScript(): void
    {
        $this->call('getScript', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testLookup(): void
    {
        $langtag = array(
            'pt-Latn-BR',
            'pt-BR',
        );
        $this->call('lookup', $langtag, 'pt-BR-x-priv1');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testParseLocale(): void
    {
        $this->call('parseLocale', 'pt-Latn-BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetDefault(): void
    {
        $this->call('setDefault', 'pt_BR');
    }

    public function testSetDefaultAcceptsEn(): void
    {
        $this->call('setDefault', 'en');

        $this->assertSame('en', $this->call('getDefault'));
    }

    protected function call($methodName)
    {
        $args = array_slice(func_get_args(), 1);

        return call_user_func_array(array('Symfony\Component\Intl\Locale\Locale', $methodName), $args);
    }
}
