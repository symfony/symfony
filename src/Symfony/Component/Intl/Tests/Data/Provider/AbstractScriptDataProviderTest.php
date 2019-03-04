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

    protected static $scripts = [
        'Adlm',
        'Afak',
        'Aghb',
        'Ahom',
        'Arab',
        'Armi',
        'Armn',
        'Avst',
        'Bali',
        'Bamu',
        'Bass',
        'Batk',
        'Beng',
        'Bhks',
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
        'Dogr',
        'Dsrt',
        'Dupl',
        'Egyd',
        'Egyh',
        'Egyp',
        'Elba',
        'Ethi',
        'Geok',
        'Geor',
        'Glag',
        'Gong',
        'Gonm',
        'Goth',
        'Gran',
        'Grek',
        'Gujr',
        'Guru',
        'Hanb',
        'Hang',
        'Hani',
        'Hano',
        'Hans',
        'Hant',
        'Hatr',
        'Hebr',
        'Hira',
        'Hluw',
        'Hmng',
        'Hrkt',
        'Hung',
        'Inds',
        'Ital',
        'Jamo',
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
        'Mahj',
        'Maka',
        'Mand',
        'Mani',
        'Marc',
        'Maya',
        'Medf',
        'Mend',
        'Merc',
        'Mero',
        'Mlym',
        'Modi',
        'Mong',
        'Moon',
        'Mroo',
        'Mtei',
        'Mult',
        'Mymr',
        'Narb',
        'Nbat',
        'Newa',
        'Nkgb',
        'Nkoo',
        'Nshu',
        'Ogam',
        'Olck',
        'Orkh',
        'Orya',
        'Osge',
        'Osma',
        'Palm',
        'Pauc',
        'Perm',
        'Phag',
        'Phli',
        'Phlp',
        'Phlv',
        'Phnx',
        'Plrd',
        'Prti',
        'Rjng',
        'Rohg',
        'Roro',
        'Runr',
        'Samr',
        'Sara',
        'Sarb',
        'Saur',
        'Sgnw',
        'Shaw',
        'Shrd',
        'Sidd',
        'Sind',
        'Sinh',
        'Sogd',
        'Sogo',
        'Sora',
        'Soyo',
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
        'Zanb',
        'Zinh',
        'Zmth',
        'Zsye',
        'Zsym',
        'Zxxx',
        'Zyyy',
        'Zzzz',
    ];

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

        // We can't assert on exact list of scripts, as there's too many variations between locales.
        // The best we can do is to make sure getNames() returns a subset of what getScripts() returns.
        $this->assertNotEmpty($scripts);
        $this->assertEmpty(array_diff($scripts, self::$scripts));
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
