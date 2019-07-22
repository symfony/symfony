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

use Symfony\Component\Intl\Scripts;

/**
 * @group intl-data
 */
class ScriptsTest extends ResourceBundleTestCase
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
        'Elym',
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
        'Hmnp',
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
        'Nand',
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
        'Qaag',
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
        'Wcho',
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
    ];

    public function testGetScriptCodes()
    {
        $this->assertSame(self::$scripts, Scripts::getScriptCodes());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $scripts = array_keys(Scripts::getNames($displayLocale));

        sort($scripts);

        // We can't assert on exact list of scripts, as there's too many variations between locales.
        // The best we can do is to make sure getNames() returns a subset of what getScripts() returns.
        $this->assertNotEmpty($scripts);
        $this->assertEmpty(array_diff($scripts, self::$scripts));
    }

    public function testGetNamesDefaultLocale()
    {
        \Locale::setDefault('de_AT');

        $this->assertSame(Scripts::getNames('de_AT'), Scripts::getNames());
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        // Can't use assertSame(), because some aliases contain scripts with
        // different collation (=order of output) than their aliased locale
        // e.g. sr_Latn_ME => sr_ME
        $this->assertEquals(Scripts::getNames($ofLocale), Scripts::getNames($alias));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        $names = Scripts::getNames($displayLocale);

        foreach ($names as $script => $name) {
            $this->assertSame($name, Scripts::getName($script, $displayLocale));
        }
    }

    public function testGetNameDefaultLocale()
    {
        \Locale::setDefault('de_AT');

        $names = Scripts::getNames('de_AT');

        foreach ($names as $script => $name) {
            $this->assertSame($name, Scripts::getName($script));
        }
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testGetNameWithInvalidScriptCode()
    {
        Scripts::getName('foo');
    }

    public function testExists()
    {
        $this->assertTrue(Scripts::exists('Hans'));
        $this->assertFalse(Scripts::exists('Zzzz'));
    }
}
