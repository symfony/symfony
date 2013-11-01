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
abstract class AbstractLocaleDataProviderTest extends AbstractDataProviderTest
{
    /**
     * @var \Symfony\Component\Icu\LocaleDataProvider
     */
    protected $dataProvider;

    protected function setUp()
    {
        $this->dataProvider = $this->createDataProvider();
    }

    abstract protected function createDataProvider();

    public function testGetLocales()
    {
        $this->assertEquals($this->getLocales(), $this->dataProvider->getLocales());
    }

    public function testGetLocaleAliases()
    {
        $this->assertEquals($this->getLocaleAliases(), $this->dataProvider->getAliases());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $locales = array_keys($this->dataProvider->getNames($displayLocale));

        sort($locales);

        $this->assertEquals($this->getLocales(), $locales);
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

        foreach ($names as $locale => $name) {
            $this->assertSame($name, $this->dataProvider->getName($locale, $displayLocale));
        }
    }
}
