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

use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;

/**
 * @group intl-data
 */
class CountriesTest extends ResourceBundleTestCase
{
    // The below arrays document the state of the ICU data bundled with this package.

    private static $countries = [
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
        'BV',
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
        'DJ',
        'DK',
        'DM',
        'DO',
        'DZ',
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
        'HM',
        'HN',
        'HR',
        'HT',
        'HU',
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
        'YE',
        'YT',
        'ZA',
        'ZM',
        'ZW',
    ];

    private static $alpha2ToAlpha3 = [
        'AW' => 'ABW',
        'AF' => 'AFG',
        'AO' => 'AGO',
        'AI' => 'AIA',
        'AX' => 'ALA',
        'AL' => 'ALB',
        'AD' => 'AND',
        'AE' => 'ARE',
        'AR' => 'ARG',
        'AM' => 'ARM',
        'AS' => 'ASM',
        'AQ' => 'ATA',
        'TF' => 'ATF',
        'AG' => 'ATG',
        'AU' => 'AUS',
        'AT' => 'AUT',
        'AZ' => 'AZE',
        'BI' => 'BDI',
        'BE' => 'BEL',
        'BJ' => 'BEN',
        'BQ' => 'BES',
        'BF' => 'BFA',
        'BD' => 'BGD',
        'BG' => 'BGR',
        'BH' => 'BHR',
        'BS' => 'BHS',
        'BA' => 'BIH',
        'BL' => 'BLM',
        'BY' => 'BLR',
        'BZ' => 'BLZ',
        'BM' => 'BMU',
        'BO' => 'BOL',
        'BR' => 'BRA',
        'BB' => 'BRB',
        'BN' => 'BRN',
        'BT' => 'BTN',
        'BV' => 'BVT',
        'BW' => 'BWA',
        'CF' => 'CAF',
        'CA' => 'CAN',
        'CC' => 'CCK',
        'CH' => 'CHE',
        'CL' => 'CHL',
        'CN' => 'CHN',
        'CI' => 'CIV',
        'CM' => 'CMR',
        'CD' => 'COD',
        'CG' => 'COG',
        'CK' => 'COK',
        'CO' => 'COL',
        'KM' => 'COM',
        'CV' => 'CPV',
        'CR' => 'CRI',
        'CU' => 'CUB',
        'CW' => 'CUW',
        'CX' => 'CXR',
        'KY' => 'CYM',
        'CY' => 'CYP',
        'CZ' => 'CZE',
        'DE' => 'DEU',
        'DJ' => 'DJI',
        'DM' => 'DMA',
        'DK' => 'DNK',
        'DO' => 'DOM',
        'DZ' => 'DZA',
        'EC' => 'ECU',
        'EG' => 'EGY',
        'ER' => 'ERI',
        'EH' => 'ESH',
        'ES' => 'ESP',
        'EE' => 'EST',
        'ET' => 'ETH',
        'FI' => 'FIN',
        'FJ' => 'FJI',
        'FK' => 'FLK',
        'FR' => 'FRA',
        'FO' => 'FRO',
        'FM' => 'FSM',
        'GA' => 'GAB',
        'GB' => 'GBR',
        'GE' => 'GEO',
        'GG' => 'GGY',
        'GH' => 'GHA',
        'GI' => 'GIB',
        'GN' => 'GIN',
        'GP' => 'GLP',
        'GM' => 'GMB',
        'GW' => 'GNB',
        'GQ' => 'GNQ',
        'GR' => 'GRC',
        'GD' => 'GRD',
        'GL' => 'GRL',
        'GT' => 'GTM',
        'GF' => 'GUF',
        'GU' => 'GUM',
        'GY' => 'GUY',
        'HK' => 'HKG',
        'HM' => 'HMD',
        'HN' => 'HND',
        'HR' => 'HRV',
        'HT' => 'HTI',
        'HU' => 'HUN',
        'ID' => 'IDN',
        'IM' => 'IMN',
        'IN' => 'IND',
        'IO' => 'IOT',
        'IE' => 'IRL',
        'IR' => 'IRN',
        'IQ' => 'IRQ',
        'IS' => 'ISL',
        'IL' => 'ISR',
        'IT' => 'ITA',
        'JM' => 'JAM',
        'JE' => 'JEY',
        'JO' => 'JOR',
        'JP' => 'JPN',
        'KZ' => 'KAZ',
        'KE' => 'KEN',
        'KG' => 'KGZ',
        'KH' => 'KHM',
        'KI' => 'KIR',
        'KN' => 'KNA',
        'KR' => 'KOR',
        'KW' => 'KWT',
        'LA' => 'LAO',
        'LB' => 'LBN',
        'LR' => 'LBR',
        'LY' => 'LBY',
        'LC' => 'LCA',
        'LI' => 'LIE',
        'LK' => 'LKA',
        'LS' => 'LSO',
        'LT' => 'LTU',
        'LU' => 'LUX',
        'LV' => 'LVA',
        'MO' => 'MAC',
        'MF' => 'MAF',
        'MA' => 'MAR',
        'MC' => 'MCO',
        'MD' => 'MDA',
        'MG' => 'MDG',
        'MV' => 'MDV',
        'MX' => 'MEX',
        'MH' => 'MHL',
        'MK' => 'MKD',
        'ML' => 'MLI',
        'MT' => 'MLT',
        'MM' => 'MMR',
        'ME' => 'MNE',
        'MN' => 'MNG',
        'MP' => 'MNP',
        'MZ' => 'MOZ',
        'MR' => 'MRT',
        'MS' => 'MSR',
        'MQ' => 'MTQ',
        'MU' => 'MUS',
        'MW' => 'MWI',
        'MY' => 'MYS',
        'YT' => 'MYT',
        'NA' => 'NAM',
        'NC' => 'NCL',
        'NE' => 'NER',
        'NF' => 'NFK',
        'NG' => 'NGA',
        'NI' => 'NIC',
        'NU' => 'NIU',
        'NL' => 'NLD',
        'NO' => 'NOR',
        'NP' => 'NPL',
        'NR' => 'NRU',
        'NZ' => 'NZL',
        'OM' => 'OMN',
        'PK' => 'PAK',
        'PA' => 'PAN',
        'PN' => 'PCN',
        'PE' => 'PER',
        'PH' => 'PHL',
        'PW' => 'PLW',
        'PG' => 'PNG',
        'PL' => 'POL',
        'PR' => 'PRI',
        'KP' => 'PRK',
        'PT' => 'PRT',
        'PY' => 'PRY',
        'PS' => 'PSE',
        'PF' => 'PYF',
        'QA' => 'QAT',
        'RE' => 'REU',
        'RO' => 'ROU',
        'RU' => 'RUS',
        'RW' => 'RWA',
        'SA' => 'SAU',
        'SD' => 'SDN',
        'SN' => 'SEN',
        'SG' => 'SGP',
        'GS' => 'SGS',
        'SH' => 'SHN',
        'SJ' => 'SJM',
        'SB' => 'SLB',
        'SL' => 'SLE',
        'SV' => 'SLV',
        'SM' => 'SMR',
        'SO' => 'SOM',
        'PM' => 'SPM',
        'RS' => 'SRB',
        'SS' => 'SSD',
        'ST' => 'STP',
        'SR' => 'SUR',
        'SK' => 'SVK',
        'SI' => 'SVN',
        'SE' => 'SWE',
        'SZ' => 'SWZ',
        'SX' => 'SXM',
        'SC' => 'SYC',
        'SY' => 'SYR',
        'TC' => 'TCA',
        'TD' => 'TCD',
        'TG' => 'TGO',
        'TH' => 'THA',
        'TJ' => 'TJK',
        'TK' => 'TKL',
        'TM' => 'TKM',
        'TL' => 'TLS',
        'TO' => 'TON',
        'TT' => 'TTO',
        'TN' => 'TUN',
        'TR' => 'TUR',
        'TV' => 'TUV',
        'TW' => 'TWN',
        'TZ' => 'TZA',
        'UG' => 'UGA',
        'UA' => 'UKR',
        'UM' => 'UMI',
        'UY' => 'URY',
        'US' => 'USA',
        'UZ' => 'UZB',
        'VA' => 'VAT',
        'VC' => 'VCT',
        'VE' => 'VEN',
        'VG' => 'VGB',
        'VI' => 'VIR',
        'VN' => 'VNM',
        'VU' => 'VUT',
        'WF' => 'WLF',
        'WS' => 'WSM',
        'YE' => 'YEM',
        'ZA' => 'ZAF',
        'ZM' => 'ZMB',
        'ZW' => 'ZWE',
    ];

    public function testGetCountryCodes()
    {
        $this->assertSame(self::$countries, Countries::getCountryCodes());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $countries = array_keys(Countries::getNames($displayLocale));

        sort($countries);

        $this->assertSame(self::$countries, $countries);
    }

    public function testGetNamesDefaultLocale()
    {
        \Locale::setDefault('de_AT');

        $this->assertSame(Countries::getNames('de_AT'), Countries::getNames());
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        // Can't use assertSame(), because some aliases contain scripts with
        // different collation (=order of output) than their aliased locale
        // e.g. sr_Latn_ME => sr_ME
        $this->assertEquals(Countries::getNames($ofLocale), Countries::getNames($alias));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        $names = Countries::getNames($displayLocale);

        foreach ($names as $country => $name) {
            $this->assertSame($name, Countries::getName($country, $displayLocale));
        }
    }

    /**
     * @requires extension intl
     */
    public function testLocaleAliasesAreLoaded()
    {
        \Locale::setDefault('zh_TW');
        $countryNameZhTw = Countries::getName('AD');

        \Locale::setDefault('zh_Hant_TW');
        $countryNameHantZhTw = Countries::getName('AD');

        \Locale::setDefault('zh');
        $countryNameZh = Countries::getName('AD');

        $this->assertSame($countryNameZhTw, $countryNameHantZhTw, 'zh_TW is an alias to zh_Hant_TW');
        $this->assertNotSame($countryNameZh, $countryNameZhTw, 'zh_TW does not fall back to zh');
    }

    public function testGetNameWithInvalidCountryCode()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MissingResourceException');
        Countries::getName('foo');
    }

    public function testExists()
    {
        $this->assertTrue(Countries::exists('NL'));
        $this->assertFalse(Countries::exists('ZZ'));
    }

    public function testGetAlpha3Codes()
    {
        $this->assertSame(self::$alpha2ToAlpha3, Countries::getAlpha3Codes());
    }

    public function testGetAlpha3Code()
    {
        foreach (self::$countries as $country) {
            $this->assertSame(self::$alpha2ToAlpha3[$country], Countries::getAlpha3Code($country));
        }
    }

    public function testGetAlpha2Code()
    {
        foreach (self::$countries as $alpha2Code) {
            $alpha3Code = self::$alpha2ToAlpha3[$alpha2Code];
            $this->assertSame($alpha2Code, Countries::getAlpha2Code($alpha3Code));
        }
    }

    public function testAlpha3CodeExists()
    {
        $this->assertTrue(Countries::alpha3CodeExists('NOR'));
        $this->assertTrue(Countries::alpha3CodeExists('NLD'));
        $this->assertFalse(Countries::alpha3CodeExists('NL'));
        $this->assertFalse(Countries::alpha3CodeExists('NIO'));
        $this->assertFalse(Countries::alpha3CodeExists('ZZZ'));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetAlpha3Name($displayLocale)
    {
        $names = Countries::getNames($displayLocale);

        foreach ($names as $alpha2 => $name) {
            $alpha3 = self::$alpha2ToAlpha3[$alpha2];
            $this->assertSame($name, Countries::getAlpha3Name($alpha3, $displayLocale));
        }
    }

    public function testGetAlpha3NameWithInvalidCountryCode()
    {
        $this->expectException(MissingResourceException::class);

        Countries::getAlpha3Name('ZZZ');
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetAlpha3Names($displayLocale)
    {
        $names = Countries::getAlpha3Names($displayLocale);

        $alpha3Codes = array_keys($names);
        sort($alpha3Codes);
        $this->assertSame(array_values(self::$alpha2ToAlpha3), $alpha3Codes);

        $alpha2Names = Countries::getNames($displayLocale);
        $this->assertSame(array_values($alpha2Names), array_values($names));
    }
}
