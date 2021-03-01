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

use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Locale\Locale;

class LocaleTest extends AbstractLocaleTest
{
    public function testAcceptFromHttp()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('acceptFromHttp', 'pt-br,en-us;q=0.7,en;q=0.5');
    }

    public function testCanonicalize()
    {
        $this->assertSame('en', $this->call('canonicalize', ''));
        $this->assertSame('en', $this->call('canonicalize', '.utf8'));
        $this->assertSame('fr_FR', $this->call('canonicalize', 'FR-fr'));
        $this->assertSame('fr_FR', $this->call('canonicalize', 'FR-fr.utf8'));
        $this->assertSame('uz_Latn', $this->call('canonicalize', 'UZ-lATN'));
        $this->assertSame('uz_Cyrl_UZ', $this->call('canonicalize', 'UZ-cYRL-uz'));
        $this->assertSame('123', $this->call('canonicalize', 123));
    }

    public function testComposeLocale()
    {
        $this->expectException(MethodNotImplementedException::class);
        $subtags = [
            'language' => 'pt',
            'script' => 'Latn',
            'region' => 'BR',
        ];
        $this->call('composeLocale', $subtags);
    }

    public function testFilterMatches()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('filterMatches', 'pt-BR', 'pt-BR');
    }

    public function testGetAllVariants()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getAllVariants', 'pt_BR_Latn');
    }

    public function testGetDisplayLanguage()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getDisplayLanguage', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayName()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getDisplayName', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayRegion()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getDisplayRegion', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayScript()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getDisplayScript', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayVariant()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getDisplayVariant', 'pt-Latn-BR', 'en');
    }

    public function testGetKeywords()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getKeywords', 'pt-BR@currency=BRL');
    }

    public function testGetPrimaryLanguage()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getPrimaryLanguage', 'pt-Latn-BR');
    }

    public function testGetRegion()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getRegion', 'pt-Latn-BR');
    }

    public function testGetScript()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('getScript', 'pt-Latn-BR');
    }

    public function testLookup()
    {
        $this->expectException(MethodNotImplementedException::class);
        $langtag = [
            'pt-Latn-BR',
            'pt-BR',
        ];
        $this->call('lookup', $langtag, 'pt-BR-x-priv1');
    }

    public function testParseLocale()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('parseLocale', 'pt-Latn-BR');
    }

    public function testSetDefault()
    {
        $this->expectException(MethodNotImplementedException::class);
        $this->call('setDefault', 'pt_BR');
    }

    public function testSetDefaultAcceptsEn()
    {
        $this->call('setDefault', 'en');

        $this->assertSame('en', $this->call('getDefault'));
    }

    protected function call(string $methodName, ...$args)
    {
        return Locale::{$methodName}(...$args);
    }
}
