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

use Symfony\Component\Intl\Locale;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LocaleTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Locale::setDefault('en');
    }

    public function existsProvider()
    {
        return array(
            array(true, 'de'),
            array(true, 'de_AT'),
            // scripts are supported in some cases
            array(true, 'zh_Hant_TW'),
            // but not in others
            array(false, 'de_Latn_AT'),
            // different casing is not supported
            array(false, 'De_AT'),
            // hyphens are not supported
            array(false, 'de-AT'),
            // aliases with individual translations are supported
            array(true, 'mo'),
            // ISO 936-2 is not supported if an equivalent exists in ISO 936-1
            array(false, 'deu'),
            array(false, 'deu_AT'),
            // country aliases are not supported
            array(false, 'de_AUT'),
        );
    }

    /**
     * @dataProvider existsProvider
     */
    public function testExists($exists, $language)
    {
        $this->assertSame($exists, Locale::exists($language));
    }

    public function testGetName()
    {
        $this->assertSame('English', Locale::getName('en', 'en'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNameFailsOnInvalidLocale()
    {
        Locale::getName('foo');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNameFailsOnInvalidDisplayLocale()
    {
        Locale::getName('en', 'foo');
    }

    public function testGetNames()
    {
        $names = Locale::getNames('en');

        $this->assertArrayHasKey('en', $names);
        $this->assertSame('English', $names['en']);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNamesFailsOnInvalidDisplayLocale()
    {
        Locale::getNames('foo');
    }

    public function testGetFallback()
    {
        $this->assertSame('fr', Locale::getFallback('fr_FR'));
    }

    public function testGetFallbackForTopLevelLocale()
    {
        $this->assertSame('root', Locale::getFallback('en'));
    }

    public function testGetFallbackForRoot()
    {
        $this->assertNull(Locale::getFallback('root'));
    }
}
