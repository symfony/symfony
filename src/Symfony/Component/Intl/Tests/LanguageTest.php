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

use Symfony\Component\Intl\Language;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LanguageTest extends \PHPUnit_Framework_TestCase
{
    public function existsProvider()
    {
        return array(
            array(true, 'de'),
            array(true, 'de_AT'),
            // scripts are not supported
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
        $this->assertSame($exists, Language::exists($language));
    }

    public function canonicalizationProvider()
    {
        return array(
            array('EN-GB', 'en_GB'),
            array('De_at', 'de_AT'),
            // Scripts in languages are not supported and are interpreted
            // as custom additional subtags
            array('IT_Latn_IT', 'it_LATN_IT'),
            // Aliases are converted
            array('DEU', 'de'),
            array('deu-CH', 'de_CH'),
            // Aliases with individual translations are not converted
            array('mo', 'mo'),
            // Country aliases are converted
            // TODO uncomment once the Region class is implemented
            //array('de_AUT', 'de_AT'),
        );
    }

    /**
     * @dataProvider canonicalizationProvider
     */
    public function testCanonicalize($language, $canonicalized)
    {
        $this->assertSame($canonicalized, Language::canonicalize($language));
    }

    public function testGetName()
    {
        $this->assertSame('German', Language::getName('de', 'en'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNameFailsOnInvalidLanguage()
    {
        Language::getName('FOO');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNameFailsOnInvalidDisplayLocale()
    {
        Language::getName('de', 'foo');
    }

    public function testGetNames()
    {
        $names = Language::getNames('en');

        $this->assertArrayHasKey('de', $names);
        $this->assertSame('German', $names['de']);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNamesFailsOnInvalidDisplayLocale()
    {
        Language::getNames('foo');
    }
}
