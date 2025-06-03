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
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @group intl-data
 */
class CountriesTest extends ResourceBundleTestCase
{
    // The below arrays document the state of the ICU data bundled with this package.

    private const COUNTRIES = [
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

    private const ALPHA2_TO_ALPHA3 = [
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

    private const ALPHA2_TO_NUMERIC = [
        'AA' => '958',
        'AD' => '020',
        'AE' => '784',
        'AF' => '004',
        'AG' => '028',
        'AI' => '660',
        'AL' => '008',
        'AM' => '051',
        'AO' => '024',
        'AQ' => '010',
        'AR' => '032',
        'AS' => '016',
        'AT' => '040',
        'AU' => '036',
        'AW' => '533',
        'AX' => '248',
        'AZ' => '031',
        'BA' => '070',
        'BB' => '052',
        'BD' => '050',
        'BE' => '056',
        'BF' => '854',
        'BG' => '100',
        'BH' => '048',
        'BI' => '108',
        'BJ' => '204',
        'BL' => '652',
        'BM' => '060',
        'BN' => '096',
        'BO' => '068',
        'BQ' => '535',
        'BR' => '076',
        'BS' => '044',
        'BT' => '064',
        'BV' => '074',
        'BW' => '072',
        'BY' => '112',
        'BZ' => '084',
        'CA' => '124',
        'CC' => '166',
        'CD' => '180',
        'CF' => '140',
        'CG' => '178',
        'CH' => '756',
        'CI' => '384',
        'CK' => '184',
        'CL' => '152',
        'CM' => '120',
        'CN' => '156',
        'CO' => '170',
        'CR' => '188',
        'CU' => '192',
        'CV' => '132',
        'CW' => '531',
        'CX' => '162',
        'CY' => '196',
        'CZ' => '203',
        'DE' => '276',
        'DJ' => '262',
        'DK' => '208',
        'DM' => '212',
        'DO' => '214',
        'DZ' => '012',
        'EC' => '218',
        'EE' => '233',
        'EG' => '818',
        'EH' => '732',
        'ER' => '232',
        'ES' => '724',
        'ET' => '231',
        'FI' => '246',
        'FJ' => '242',
        'FK' => '238',
        'FM' => '583',
        'FO' => '234',
        'FR' => '250',
        'GA' => '266',
        'GB' => '826',
        'GD' => '308',
        'GE' => '268',
        'GF' => '254',
        'GG' => '831',
        'GH' => '288',
        'GI' => '292',
        'GL' => '304',
        'GM' => '270',
        'GN' => '324',
        'GP' => '312',
        'GQ' => '226',
        'GR' => '300',
        'GS' => '239',
        'GT' => '320',
        'GU' => '316',
        'GW' => '624',
        'GY' => '328',
        'HK' => '344',
        'HM' => '334',
        'HN' => '340',
        'HR' => '191',
        'HT' => '332',
        'HU' => '348',
        'ID' => '360',
        'IE' => '372',
        'IL' => '376',
        'IM' => '833',
        'IN' => '356',
        'IO' => '086',
        'IQ' => '368',
        'IR' => '364',
        'IS' => '352',
        'IT' => '380',
        'JE' => '832',
        'JM' => '388',
        'JO' => '400',
        'JP' => '392',
        'KE' => '404',
        'KG' => '417',
        'KH' => '116',
        'KI' => '296',
        'KM' => '174',
        'KN' => '659',
        'KP' => '408',
        'KR' => '410',
        'KW' => '414',
        'KY' => '136',
        'KZ' => '398',
        'LA' => '418',
        'LB' => '422',
        'LC' => '662',
        'LI' => '438',
        'LK' => '144',
        'LR' => '430',
        'LS' => '426',
        'LT' => '440',
        'LU' => '442',
        'LV' => '428',
        'LY' => '434',
        'MA' => '504',
        'MC' => '492',
        'MD' => '498',
        'ME' => '499',
        'MF' => '663',
        'MG' => '450',
        'MH' => '584',
        'MK' => '807',
        'ML' => '466',
        'MM' => '104',
        'MN' => '496',
        'MO' => '446',
        'MP' => '580',
        'MQ' => '474',
        'MR' => '478',
        'MS' => '500',
        'MT' => '470',
        'MU' => '480',
        'MV' => '462',
        'MW' => '454',
        'MX' => '484',
        'MY' => '458',
        'MZ' => '508',
        'NA' => '516',
        'NC' => '540',
        'NE' => '562',
        'NF' => '574',
        'NG' => '566',
        'NI' => '558',
        'NL' => '528',
        'NO' => '578',
        'NP' => '524',
        'NR' => '520',
        'NU' => '570',
        'NZ' => '554',
        'OM' => '512',
        'PA' => '591',
        'PE' => '604',
        'PF' => '258',
        'PG' => '598',
        'PH' => '608',
        'PK' => '586',
        'PL' => '616',
        'PM' => '666',
        'PN' => '612',
        'PR' => '630',
        'PS' => '275',
        'PT' => '620',
        'PW' => '585',
        'PY' => '600',
        'QA' => '634',
        'QM' => '959',
        'QN' => '960',
        'QP' => '962',
        'QQ' => '963',
        'QR' => '964',
        'QS' => '965',
        'QT' => '966',
        'QV' => '968',
        'QW' => '969',
        'QX' => '970',
        'QY' => '971',
        'QZ' => '972',
        'RE' => '638',
        'RO' => '642',
        'RS' => '688',
        'RU' => '643',
        'RW' => '646',
        'SA' => '682',
        'SB' => '090',
        'SC' => '690',
        'SD' => '729',
        'SE' => '752',
        'SG' => '702',
        'SH' => '654',
        'SI' => '705',
        'SJ' => '744',
        'SK' => '703',
        'SL' => '694',
        'SM' => '674',
        'SN' => '686',
        'SO' => '706',
        'SR' => '740',
        'SS' => '728',
        'ST' => '678',
        'SV' => '222',
        'SX' => '534',
        'SY' => '760',
        'SZ' => '748',
        'TC' => '796',
        'TD' => '148',
        'TF' => '260',
        'TG' => '768',
        'TH' => '764',
        'TJ' => '762',
        'TK' => '772',
        'TL' => '626',
        'TM' => '795',
        'TN' => '788',
        'TO' => '776',
        'TR' => '792',
        'TT' => '780',
        'TV' => '798',
        'TW' => '158',
        'TZ' => '834',
        'UA' => '804',
        'UG' => '800',
        'UM' => '581',
        'US' => '840',
        'UY' => '858',
        'UZ' => '860',
        'VA' => '336',
        'VC' => '670',
        'VE' => '862',
        'VG' => '092',
        'VI' => '850',
        'VN' => '704',
        'VU' => '548',
        'WF' => '876',
        'WS' => '882',
        'XC' => '975',
        'XD' => '976',
        'XE' => '977',
        'XF' => '978',
        'XG' => '979',
        'XH' => '980',
        'XI' => '981',
        'XJ' => '982',
        'XL' => '984',
        'XM' => '985',
        'XN' => '986',
        'XO' => '987',
        'XP' => '988',
        'XQ' => '989',
        'XR' => '990',
        'XS' => '991',
        'XT' => '992',
        'XU' => '993',
        'XV' => '994',
        'XW' => '995',
        'XX' => '996',
        'XY' => '997',
        'XZ' => '998',
        'YE' => '887',
        'YT' => '175',
        'ZA' => '710',
        'ZM' => '894',
        'ZW' => '716',
    ];

    public function testGetCountryCodes()
    {
        $this->assertSame(self::COUNTRIES, Countries::getCountryCodes());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $countries = array_keys(Countries::getNames($displayLocale));

        sort($countries);

        $this->assertSame(self::COUNTRIES, $countries);
    }

    public function testGetNamesDefaultLocale()
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $this->assertSame(Countries::getNames('de_AT'), Countries::getNames());
    }

    /**
     * @dataProvider provideLocaleAliases
     */
    public function testGetNamesSupportsAliases($alias, $ofLocale)
    {
        if ('en' !== $ofLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

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
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

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
        $this->expectException(MissingResourceException::class);
        Countries::getName('foo');
    }

    public function testExists()
    {
        $this->assertTrue(Countries::exists('NL'));
        $this->assertFalse(Countries::exists('ZZ'));
    }

    public function testGetAlpha3Codes()
    {
        $this->assertSame(self::ALPHA2_TO_ALPHA3, Countries::getAlpha3Codes());
    }

    public function testGetAlpha3Code()
    {
        foreach (self::COUNTRIES as $country) {
            $this->assertSame(self::ALPHA2_TO_ALPHA3[$country], Countries::getAlpha3Code($country));
        }
    }

    public function testGetAlpha2Code()
    {
        foreach (self::COUNTRIES as $alpha2Code) {
            $alpha3Code = self::ALPHA2_TO_ALPHA3[$alpha2Code];
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
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $names = Countries::getNames($displayLocale);

        foreach ($names as $alpha2 => $name) {
            $alpha3 = self::ALPHA2_TO_ALPHA3[$alpha2];
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
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $names = Countries::getAlpha3Names($displayLocale);

        $alpha3Codes = array_keys($names);
        sort($alpha3Codes);
        $this->assertSame(array_values(self::ALPHA2_TO_ALPHA3), $alpha3Codes);

        $alpha2Names = Countries::getNames($displayLocale);
        $this->assertSame(array_values($alpha2Names), array_values($names));
    }

    public function testGetNumericCodes()
    {
        $this->assertSame(self::ALPHA2_TO_NUMERIC, Countries::getNumericCodes());
    }

    public function testGetNumericCode()
    {
        foreach (self::COUNTRIES as $country) {
            $this->assertSame(self::ALPHA2_TO_NUMERIC[$country], Countries::getNumericCode($country));
        }
    }

    public function testNumericCodeExists()
    {
        $this->assertTrue(Countries::numericCodeExists('250'));
        $this->assertTrue(Countries::numericCodeExists('982'));
        $this->assertTrue(Countries::numericCodeExists('716'));
        $this->assertTrue(Countries::numericCodeExists('036'));
        $this->assertFalse(Countries::numericCodeExists('667'));
    }

    public function testGetAlpha2FromNumeric()
    {
        $alpha2Lookup = array_flip(self::ALPHA2_TO_NUMERIC);

        foreach (self::ALPHA2_TO_NUMERIC as $numeric) {
            $this->assertSame($alpha2Lookup[$numeric], Countries::getAlpha2FromNumeric($numeric));
        }
    }

    public function testNumericCodesDoNotContainDenyListItems()
    {
        $numericCodes = Countries::getNumericCodes();

        $this->assertArrayNotHasKey('EZ', $numericCodes);
        $this->assertArrayNotHasKey('XA', $numericCodes);
        $this->assertArrayNotHasKey('ZZ', $numericCodes);
    }
}
