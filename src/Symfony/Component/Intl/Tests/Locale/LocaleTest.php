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
        self::expectException(MethodNotImplementedException::class);
        $this->call('acceptFromHttp', 'pt-br,en-us;q=0.7,en;q=0.5');
    }

    public function testCanonicalize()
    {
        self::assertSame('en', $this->call('canonicalize', ''));
        self::assertSame('en', $this->call('canonicalize', '.utf8'));
        self::assertSame('fr_FR', $this->call('canonicalize', 'FR-fr'));
        self::assertSame('fr_FR', $this->call('canonicalize', 'FR-fr.utf8'));
        self::assertSame('uz_Latn', $this->call('canonicalize', 'UZ-lATN'));
        self::assertSame('uz_Cyrl_UZ', $this->call('canonicalize', 'UZ-cYRL-uz'));
        self::assertSame('123', $this->call('canonicalize', 123));
    }

    public function testComposeLocale()
    {
        self::expectException(MethodNotImplementedException::class);
        $subtags = [
            'language' => 'pt',
            'script' => 'Latn',
            'region' => 'BR',
        ];
        $this->call('composeLocale', $subtags);
    }

    public function testFilterMatches()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('filterMatches', 'pt-BR', 'pt-BR');
    }

    public function testGetAllVariants()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getAllVariants', 'pt_BR_Latn');
    }

    public function testGetDisplayLanguage()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getDisplayLanguage', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayName()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getDisplayName', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayRegion()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getDisplayRegion', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayScript()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getDisplayScript', 'pt-Latn-BR', 'en');
    }

    public function testGetDisplayVariant()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getDisplayVariant', 'pt-Latn-BR', 'en');
    }

    public function testGetKeywords()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getKeywords', 'pt-BR@currency=BRL');
    }

    public function testGetPrimaryLanguage()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getPrimaryLanguage', 'pt-Latn-BR');
    }

    public function testGetRegion()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getRegion', 'pt-Latn-BR');
    }

    public function testGetScript()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('getScript', 'pt-Latn-BR');
    }

    public function testLookup()
    {
        self::expectException(MethodNotImplementedException::class);
        $langtag = [
            'pt-Latn-BR',
            'pt-BR',
        ];
        $this->call('lookup', $langtag, 'pt-BR-x-priv1');
    }

    public function testParseLocale()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('parseLocale', 'pt-Latn-BR');
    }

    public function testSetDefault()
    {
        self::expectException(MethodNotImplementedException::class);
        $this->call('setDefault', 'pt_BR');
    }

    public function testSetDefaultAcceptsEn()
    {
        $this->call('setDefault', 'en');

        self::assertSame('en', $this->call('getDefault'));
    }

    protected function call(string $methodName, ...$args)
    {
        return Locale::{$methodName}(...$args);
    }
}
