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
use Symfony\Component\Intl\Regions;

/**
 * @group intl-data
 */
class RegionsTest extends ResourceBundleTestCase
{
    // The below arrays document the state of the ICU data bundled with this package.

    private static $territories = [
        'AC',
        'AD',
        'AE',
        'AF',
        'AG',
        'AI',
        'AL',
        'AM',
        'AO',
        'AQ',
        'AR',
        'AS',
        'AT',
        'AU',
        'AW',
        'AX',
        'AZ',
        'BA',
        'BB',
        'BD',
        'BE',
        'BF',
        'BG',
        'BH',
        'BI',
        'BJ',
        'BL',
        'BM',
        'BN',
        'BO',
        'BQ',
        'BR',
        'BS',
        'BT',
        'BW',
        'BY',
        'BZ',
        'CA',
        'CC',
        'CD',
        'CF',
        'CG',
        'CH',
        'CI',
        'CK',
        'CL',
        'CM',
        'CN',
        'CO',
        'CR',
        'CU',
        'CV',
        'CW',
        'CX',
        'CY',
        'CZ',
        'DE',
        'DG',
        'DJ',
        'DK',
        'DM',
        'DO',
        'DZ',
        'EA',
        'EC',
        'EE',
        'EG',
        'EH',
        'ER',
        'ES',
        'ET',
        'FI',
        'FJ',
        'FK',
        'FM',
        'FO',
        'FR',
        'GA',
        'GB',
        'GD',
        'GE',
        'GF',
        'GG',
        'GH',
        'GI',
        'GL',
        'GM',
        'GN',
        'GP',
        'GQ',
        'GR',
        'GS',
        'GT',
        'GU',
        'GW',
        'GY',
        'HK',
        'HN',
        'HR',
        'HT',
        'HU',
        'IC',
        'ID',
        'IE',
        'IL',
        'IM',
        'IN',
        'IO',
        'IQ',
        'IR',
        'IS',
        'IT',
        'JE',
        'JM',
        'JO',
        'JP',
        'KE',
        'KG',
        'KH',
        'KI',
        'KM',
        'KN',
        'KP',
        'KR',
        'KW',
        'KY',
        'KZ',
        'LA',
        'LB',
        'LC',
        'LI',
        'LK',
        'LR',
        'LS',
        'LT',
        'LU',
        'LV',
        'LY',
        'MA',
        'MC',
        'MD',
        'ME',
        'MF',
        'MG',
        'MH',
        'MK',
        'ML',
        'MM',
        'MN',
        'MO',
        'MP',
        'MQ',
        'MR',
        'MS',
        'MT',
        'MU',
        'MV',
        'MW',
        'MX',
        'MY',
        'MZ',
        'NA',
        'NC',
        'NE',
        'NF',
        'NG',
        'NI',
        'NL',
        'NO',
        'NP',
        'NR',
        'NU',
        'NZ',
        'OM',
        'PA',
        'PE',
        'PF',
        'PG',
        'PH',
        'PK',
        'PL',
        'PM',
        'PN',
        'PR',
        'PS',
        'PT',
        'PW',
        'PY',
        'QA',
        'RE',
        'RO',
        'RS',
        'RU',
        'RW',
        'SA',
        'SB',
        'SC',
        'SD',
        'SE',
        'SG',
        'SH',
        'SI',
        'SJ',
        'SK',
        'SL',
        'SM',
        'SN',
        'SO',
        'SR',
        'SS',
        'ST',
        'SV',
        'SX',
        'SY',
        'SZ',
        'TA',
        'TC',
        'TD',
        'TF',
        'TG',
        'TH',
        'TJ',
        'TK',
        'TL',
        'TM',
        'TN',
        'TO',
        'TR',
        'TT',
        'TV',
        'TW',
        'TZ',
        'UA',
        'UG',
        'UM',
        'US',
        'UY',
        'UZ',
        'VA',
        'VC',
        'VE',
        'VG',
        'VI',
        'VN',
        'VU',
        'WF',
        'WS',
        'XA',
        'XB',
        'XK',
        'YE',
        'YT',
        'ZA',
        'ZM',
        'ZW',
    ];

    public function testGetRegions()
    {
        $this->assertSame(self::$territories, Regions::getRegionCodes());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $countries = array_keys(Regions::getNames($displayLocale));

        sort($countries);

        $this->assertSame(self::$territories, $countries);
    }

    public function testGetNamesDefaultLocale()
    {
        Locale::setDefault('de_AT');

        $this->assertSame(Regions::getNames('de_AT'), Regions::getNames());
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        // Can't use assertSame(), because some aliases contain scripts with
        // different collation (=order of output) than their aliased locale
        // e.g. sr_Latn_ME => sr_ME
        $this->assertEquals(Regions::getNames($ofLocale), Regions::getNames($alias));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        $names = Regions::getNames($displayLocale);

        foreach ($names as $country => $name) {
            $this->assertSame($name, Regions::getName($country, $displayLocale));
        }
    }

    /**
     * @requires extension intl
     */
    public function testLocaleAliasesAreLoaded()
    {
        \Locale::setDefault('zh_TW');
        $countryNameZhTw = Regions::getName('AD');

        \Locale::setDefault('zh_Hant_TW');
        $countryNameHantZhTw = Regions::getName('AD');

        \Locale::setDefault('zh');
        $countryNameZh = Regions::getName('AD');

        $this->assertSame($countryNameZhTw, $countryNameHantZhTw, 'zh_TW is an alias to zh_Hant_TW');
        $this->assertNotSame($countryNameZh, $countryNameZhTw, 'zh_TW does not fall back to zh');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testGetNameWithInvalidRegionCode()
    {
        Regions::getName('foo');
    }

    public function testExists()
    {
        $this->assertTrue(Regions::exists('NL'));
        $this->assertFalse(Regions::exists('ZZ'));
    }
}
