<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Data\Provider;

use Symfony\Component\Intl\Data\Provider\ScriptDataProvider;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locale;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @group intl-data
 */
abstract class AbstractScriptDataProviderTest extends AbstractDataProviderTest
{
    // The below arrays document the state of the ICU data bundled with this package.

    protected static $scripts = array(
        'Afak',
        'Arab',
        'Armi',
        'Armn',
        'Avst',
        'Bali',
        'Bamu',
        'Bass',
        'Batk',
        'Beng',
        'Blis',
        'Bopo',
        'Brah',
        'Brai',
        'Bugi',
        'Buhd',
        'Cakm',
        'Cans',
        'Cari',
        'Cham',
        'Cher',
        'Cirt',
        'Copt',
        'Cprt',
        'Cyrl',
        'Cyrs',
        'Deva',
        'Dsrt',
        'Dupl',
        'Egyd',
        'Egyh',
        'Egyp',
        'Ethi',
        'Geok',
        'Geor',
        'Glag',
        'Goth',
        'Gran',
        'Grek',
        'Gujr',
        'Guru',
        'Hang',
        'Hani',
        'Hano',
        'Hans',
        'Hant',
        'Hebr',
        'Hira',
        'Hluw',
        'Hmng',
        'Hrkt',
        'Hung',
        'Inds',
        'Ital',
        'Java',
        'Jpan',
        'Jurc',
        'Kali',
        'Kana',
        'Khar',
        'Khmr',
        'Khoj',
        'Knda',
        'Kore',
        'Kpel',
        'Kthi',
        'Lana',
        'Laoo',
        'Latf',
        'Latg',
        'Latn',
        'Lepc',
        'Limb',
        'Lina',
        'Linb',
        'Lisu',
        'Loma',
        'Lyci',
        'Lydi',
        'Mand',
        'Mani',
        'Maya',
        'Mend',
        'Merc',
        'Mero',
        'Mlym',
        'Mong',
        'Moon',
        'Mroo',
        'Mtei',
        'Mymr',
        'Narb',
        'Nbat',
        'Nkgb',
        'Nkoo',
        'Nshu',
        'Ogam',
        'Olck',
        'Orkh',
        'Orya',
        'Osma',
        'Palm',
        'Perm',
        'Phag',
        'Phli',
        'Phlp',
        'Phlv',
        'Phnx',
        'Plrd',
        'Prti',
        'Rjng',
        'Roro',
        'Runr',
        'Samr',
        'Sara',
        'Sarb',
        'Saur',
        'Sgnw',
        'Shaw',
        'Shrd',
        'Sind',
        'Sinh',
        'Sora',
        'Sund',
        'Sylo',
        'Syrc',
        'Syre',
        'Syrj',
        'Syrn',
        'Tagb',
        'Takr',
        'Tale',
        'Talu',
        'Taml',
        'Tang',
        'Tavt',
        'Telu',
        'Teng',
        'Tfng',
        'Tglg',
        'Thaa',
        'Thai',
        'Tibt',
        'Tirh',
        'Ugar',
        'Vaii',
        'Visp',
        'Wara',
        'Wole',
        'Xpeo',
        'Xsux',
        'Yiii',
        'Zinh',
        'Zmth',
        'Zsym',
        'Zxxx',
        'Zyyy',
        'Zzzz',
    );

    /**
     * @var ScriptDataProvider
     */
    protected $dataProvider;

    protected function setUp()
    {
        parent::setUp();

        $this->dataProvider = new ScriptDataProvider(
            $this->getDataDirectory().'/'.Intl::SCRIPT_DIR,
            $this->createEntryReader()
        );
    }

    abstract protected function getDataDirectory();

    public function testGetScripts()
    {
        $this->assertSame(static::$scripts, $this->dataProvider->getScripts());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $scripts = array_keys($this->dataProvider->getNames($displayLocale));

        sort($scripts);

        $this->assertSame(static::$scripts, $scripts);
    }

    public function testGetNamesDefaultLocale()
    {
        Locale::setDefault('de_AT');

        $this->assertSame(
            $this->dataProvider->getNames('de_AT'),
            $this->dataProvider->getNames()
        );
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        // Can't use assertSame(), because some aliases contain scripts with
        // different collation (=order of output) than their aliased locale
        // e.g. sr_Latn_ME => sr_ME
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

        foreach ($names as $script => $name) {
            $this->assertSame($name, $this->dataProvider->getName($script, $displayLocale));
        }
    }

    public function testGetNameDefaultLocale()
    {
        Locale::setDefault('de_AT');

        $names = $this->dataProvider->getNames('de_AT');

        foreach ($names as $script => $name) {
            $this->assertSame($name, $this->dataProvider->getName($script));
        }
    }
}
