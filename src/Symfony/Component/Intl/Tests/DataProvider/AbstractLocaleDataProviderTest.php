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
    public function testGetDisplayNames($displayLocale)
    {
        $locales = array_keys($this->dataProvider->getDisplayNames($displayLocale));

        sort($locales);

        $this->assertEquals($this->getLocales(), $locales);
    }

    /**
     * @dataProvider provideLocaleAliases
     * @group locale-alias-based
     */
    public function testGetDisplayNamesSupportsAliases($alias, $ofLocale)
    {
        $this->assertEquals(
            $this->dataProvider->getDisplayNames($ofLocale),
            $this->dataProvider->getDisplayNames($alias)
        );
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetDisplayName($displayLocale)
    {
        $names = $this->dataProvider->getDisplayNames($displayLocale);

        foreach ($names as $locale => $name) {
            $this->assertSame($name, $this->dataProvider->getDisplayName($locale, $displayLocale));
        }
    }
}
