<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\DataProvider;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractLanguageDataProviderTest extends AbstractDataProviderTest
{
    protected static $languages = array();
    protected static $alpha2ToAlpha3 = array();

    /**
     * @var \Symfony\Component\Icu\LanguageDataProvider
     */
    protected $dataProvider;

    protected function setUp()
    {
        $this->dataProvider = $this->createDataProvider();
    }

    abstract protected function createDataProvider();

    public function testGetLanguages()
    {
        $this->assertEquals(static::$languages, $this->dataProvider->getLanguages());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $languages = array_keys($this->dataProvider->getNames($displayLocale));

        sort($languages);

        $this->assertEquals(static::$languages, $languages);
    }

    /**
     * @dataProvider provideLocaleAliases
     * @group locale-alias-based
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            $this->dataProvider->getNames($ofLocale),
            $this->dataProvider->getNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        $names = $this->dataProvider->getNames($displayLocale);

        foreach ($names as $language => $name) {
            $this->assertSame($name, $this->dataProvider->getName($language, $displayLocale));
        }
    }

    public function provideLanguagesWithAlpha3Equivalent()
    {
        return array_map(
            function ($value) { return array($value); },
            array_keys(static::$alpha2ToAlpha3)
        );
    }

    /**
     * @dataProvider provideLanguagesWithAlpha3Equivalent
     */
    public function testGetAlpha3Code($language)
    {
        $this->assertSame(static::$alpha2ToAlpha3[$language], $this->dataProvider->getAlpha3Code($language));
    }

    public function provideLanguagesWithoutAlpha3Equivalent()
    {
        return array_map(
            function ($value) { return array($value); },
            array_diff(static::$languages, array_keys(static::$alpha2ToAlpha3))
        );
    }

    /**
     * @dataProvider provideLanguagesWithoutAlpha3Equivalent
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testGetAlpha3CodeFailsIfNoAlpha3Equivalent($currency)
    {
        $this->dataProvider->getAlpha3Code($currency);
    }
}
