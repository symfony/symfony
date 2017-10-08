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

use Symfony\Component\Intl\Data\Provider\CurrencyDataProvider;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Locale;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractCurrencyDataProviderTest extends AbstractDataProviderTest
{
    // The below arrays document the state of the ICU data bundled with this package.

    protected static $currencies = array(
        'ADP',
        'AED',
        'AFA',
        'AFN',
        'ALK',
        'ALL',
        'AMD',
        'ANG',
        'AOA',
        'AOK',
        'AON',
        'AOR',
        'ARA',
        'ARL',
        'ARM',
        'ARP',
        'ARS',
        'ATS',
        'AUD',
        'AWG',
        'AZM',
        'AZN',
        'BAD',
        'BAM',
        'BAN',
        'BBD',
        'BDT',
        'BEC',
        'BEF',
        'BEL',
        'BGL',
        'BGM',
        'BGN',
        'BGO',
        'BHD',
        'BIF',
        'BMD',
        'BND',
        'BOB',
        'BOL',
        'BOP',
        'BOV',
        'BRB',
        'BRC',
        'BRE',
        'BRL',
        'BRN',
        'BRR',
        'BRZ',
        'BSD',
        'BTN',
        'BUK',
        'BWP',
        'BYB',
        'BYR',
        'BZD',
        'CAD',
        'CDF',
        'CHE',
        'CHF',
        'CHW',
        'CLE',
        'CLF',
        'CLP',
        'CNX',
        'CNY',
        'COP',
        'COU',
        'CRC',
        'CSD',
        'CSK',
        'CUC',
        'CUP',
        'CVE',
        'CYP',
        'CZK',
        'DDM',
        'DEM',
        'DJF',
        'DKK',
        'DOP',
        'DZD',
        'ECS',
        'ECV',
        'EEK',
        'EGP',
        'ERN',
        'ESA',
        'ESB',
        'ESP',
        'ETB',
        'EUR',
        'FIM',
        'FJD',
        'FKP',
        'FRF',
        'GBP',
        'GEK',
        'GEL',
        'GHC',
        'GHS',
        'GIP',
        'GMD',
        'GNF',
        'GNS',
        'GQE',
        'GRD',
        'GTQ',
        'GWE',
        'GWP',
        'GYD',
        'HKD',
        'HNL',
        'HRD',
        'HRK',
        'HTG',
        'HUF',
        'IDR',
        'IEP',
        'ILP',
        'ILR',
        'ILS',
        'INR',
        'IQD',
        'IRR',
        'ISJ',
        'ISK',
        'ITL',
        'JMD',
        'JOD',
        'JPY',
        'KES',
        'KGS',
        'KHR',
        'KMF',
        'KPW',
        'KRH',
        'KRO',
        'KRW',
        'KWD',
        'KYD',
        'KZT',
        'LAK',
        'LBP',
        'LKR',
        'LRD',
        'LSL',
        'LTL',
        'LTT',
        'LUC',
        'LUF',
        'LUL',
        'LVL',
        'LVR',
        'LYD',
        'MAD',
        'MAF',
        'MCF',
        'MDC',
        'MDL',
        'MGA',
        'MGF',
        'MKD',
        'MKN',
        'MLF',
        'MMK',
        'MNT',
        'MOP',
        'MRO',
        'MTL',
        'MTP',
        'MUR',
        'MVP',
        'MVR',
        'MWK',
        'MXN',
        'MXP',
        'MXV',
        'MYR',
        'MZE',
        'MZM',
        'MZN',
        'NAD',
        'NGN',
        'NIC',
        'NIO',
        'NLG',
        'NOK',
        'NPR',
        'NZD',
        'OMR',
        'PAB',
        'PEI',
        'PEN',
        'PES',
        'PGK',
        'PHP',
        'PKR',
        'PLN',
        'PLZ',
        'PTE',
        'PYG',
        'QAR',
        'RHD',
        'ROL',
        'RON',
        'RSD',
        'RUB',
        'RUR',
        'RWF',
        'SAR',
        'SBD',
        'SCR',
        'SDD',
        'SDG',
        'SDP',
        'SEK',
        'SGD',
        'SHP',
        'SIT',
        'SKK',
        'SLL',
        'SOS',
        'SRD',
        'SRG',
        'SSP',
        'STD',
        'SUR',
        'SVC',
        'SYP',
        'SZL',
        'THB',
        'TJR',
        'TJS',
        'TMM',
        'TMT',
        'TND',
        'TOP',
        'TPE',
        'TRL',
        'TRY',
        'TTD',
        'TWD',
        'TZS',
        'UAH',
        'UAK',
        'UGS',
        'UGX',
        'USD',
        'USN',
        'USS',
        'UYI',
        'UYP',
        'UYU',
        'UZS',
        'VEB',
        'VEF',
        'VND',
        'VNN',
        'VUV',
        'WST',
        'XAF',
        'XCD',
        'XEU',
        'XFO',
        'XFU',
        'XOF',
        'XPF',
        'XRE',
        'YDD',
        'YER',
        'YUD',
        'YUM',
        'YUN',
        'YUR',
        'ZAL',
        'ZAR',
        'ZMK',
        'ZMW',
        'ZRN',
        'ZRZ',
        'ZWD',
        'ZWL',
        'ZWR',
    );

    protected static $alpha3ToNumeric = array(
        'ADP' => 20,
        'AED' => 784,
        'AFA' => 4,
        'AFN' => 971,
        'ALL' => 8,
        'AMD' => 51,
        'ANG' => 532,
        'AOA' => 973,
        'AON' => 24,
        'AOR' => 982,
        'ARA' => 32,
        'ARP' => 32,
        'ARS' => 32,
        'ATS' => 40,
        'AUD' => 36,
        'AWG' => 533,
        'AZM' => 31,
        'AZN' => 944,
        'BAD' => 70,
        'BAM' => 977,
        'BBD' => 52,
        'BDT' => 50,
        'BEC' => 993,
        'BEF' => 56,
        'BEL' => 992,
        'BGL' => 100,
        'BGN' => 975,
        'BHD' => 48,
        'BIF' => 108,
        'BMD' => 60,
        'BND' => 96,
        'BOB' => 68,
        'BOV' => 984,
        'BRC' => 76,
        'BRE' => 76,
        'BRL' => 986,
        'BRN' => 76,
        'BRR' => 987,
        'BSD' => 44,
        'BTN' => 64,
        'BWP' => 72,
        'BYB' => 112,
        'BYR' => 974,
        'BZD' => 84,
        'CAD' => 124,
        'CDF' => 976,
        'CHE' => 947,
        'CHF' => 756,
        'CHW' => 948,
        'CLF' => 990,
        'CLP' => 152,
        'CNY' => 156,
        'COP' => 170,
        'COU' => 970,
        'CRC' => 188,
        'CSD' => 891,
        'CSK' => 200,
        'CUC' => 931,
        'CUP' => 192,
        'CVE' => 132,
        'CYP' => 196,
        'CZK' => 203,
        'DDM' => 278,
        'DEM' => 276,
        'DJF' => 262,
        'DKK' => 208,
        'DOP' => 214,
        'DZD' => 12,
        'ECS' => 218,
        'ECV' => 983,
        'EEK' => 233,
        'EGP' => 818,
        'ERN' => 232,
        'ESA' => 996,
        'ESB' => 995,
        'ESP' => 724,
        'ETB' => 230,
        'EUR' => 978,
        'FIM' => 246,
        'FJD' => 242,
        'FKP' => 238,
        'FRF' => 250,
        'GBP' => 826,
        'GEK' => 268,
        'GEL' => 981,
        'GHC' => 288,
        'GHS' => 936,
        'GIP' => 292,
        'GMD' => 270,
        'GNF' => 324,
        'GQE' => 226,
        'GRD' => 300,
        'GTQ' => 320,
        'GWP' => 624,
        'GYD' => 328,
        'HKD' => 344,
        'HNL' => 340,
        'HRD' => 191,
        'HRK' => 191,
        'HTG' => 332,
        'HUF' => 348,
        'IDR' => 360,
        'IEP' => 372,
        'ILS' => 376,
        'INR' => 356,
        'IQD' => 368,
        'IRR' => 364,
        'ISK' => 352,
        'ITL' => 380,
        'JMD' => 388,
        'JOD' => 400,
        'JPY' => 392,
        'KES' => 404,
        'KGS' => 417,
        'KHR' => 116,
        'KMF' => 174,
        'KPW' => 408,
        'KRW' => 410,
        'KWD' => 414,
        'KYD' => 136,
        'KZT' => 398,
        'LAK' => 418,
        'LBP' => 422,
        'LKR' => 144,
        'LRD' => 430,
        'LSL' => 426,
        'LTL' => 440,
        'LTT' => 440,
        'LUC' => 989,
        'LUF' => 442,
        'LUL' => 988,
        'LVL' => 428,
        'LVR' => 428,
        'LYD' => 434,
        'MAD' => 504,
        'MDL' => 498,
        'MGA' => 969,
        'MGF' => 450,
        'MKD' => 807,
        'MLF' => 466,
        'MMK' => 104,
        'MNT' => 496,
        'MOP' => 446,
        'MRO' => 478,
        'MTL' => 470,
        'MUR' => 480,
        'MVR' => 462,
        'MWK' => 454,
        'MXN' => 484,
        'MXV' => 979,
        'MYR' => 458,
        'MZM' => 508,
        'MZN' => 943,
        'NAD' => 516,
        'NGN' => 566,
        'NIO' => 558,
        'NLG' => 528,
        'NOK' => 578,
        'NPR' => 524,
        'NZD' => 554,
        'OMR' => 512,
        'PAB' => 590,
        'PEI' => 604,
        'PEN' => 604,
        'PES' => 604,
        'PGK' => 598,
        'PHP' => 608,
        'PKR' => 586,
        'PLN' => 985,
        'PLZ' => 616,
        'PTE' => 620,
        'PYG' => 600,
        'QAR' => 634,
        'ROL' => 642,
        'RON' => 946,
        'RSD' => 941,
        'RUB' => 643,
        'RUR' => 810,
        'RWF' => 646,
        'SAR' => 682,
        'SBD' => 90,
        'SCR' => 690,
        'SDD' => 736,
        'SDG' => 938,
        'SEK' => 752,
        'SGD' => 702,
        'SHP' => 654,
        'SIT' => 705,
        'SKK' => 703,
        'SLL' => 694,
        'SOS' => 706,
        'SRD' => 968,
        'SRG' => 740,
        'SSP' => 728,
        'STD' => 678,
        'SVC' => 222,
        'SYP' => 760,
        'SZL' => 748,
        'THB' => 764,
        'TJR' => 762,
        'TJS' => 972,
        'TMM' => 795,
        'TMT' => 934,
        'TND' => 788,
        'TOP' => 776,
        'TPE' => 626,
        'TRL' => 792,
        'TRY' => 949,
        'TTD' => 780,
        'TWD' => 901,
        'TZS' => 834,
        'UAH' => 980,
        'UAK' => 804,
        'UGX' => 800,
        'USD' => 840,
        'USN' => 997,
        'USS' => 998,
        'UYI' => 940,
        'UYU' => 858,
        'UZS' => 860,
        'VEB' => 862,
        'VEF' => 937,
        'VND' => 704,
        'VUV' => 548,
        'WST' => 882,
        'XAF' => 950,
        'XCD' => 951,
        'XEU' => 954,
        'XOF' => 952,
        'XPF' => 953,
        'YDD' => 720,
        'YER' => 886,
        'YUM' => 891,
        'YUN' => 890,
        'ZAL' => 991,
        'ZAR' => 710,
        'ZMK' => 894,
        'ZMW' => 967,
        'ZRN' => 180,
        'ZRZ' => 180,
        'ZWD' => 716,
        'ZWL' => 932,
        'ZWR' => 935,
    );

    /**
     * @var CurrencyDataProvider
     */
    protected $dataProvider;

    protected function setUp()
    {
        parent::setUp();

        $this->dataProvider = new CurrencyDataProvider(
            $this->getDataDirectory().'/'.Intl::CURRENCY_DIR,
            $this->createEntryReader()
        );
    }

    abstract protected function getDataDirectory();

    public function testGetCurrencies()
    {
        $this->assertSame(static::$currencies, $this->dataProvider->getCurrencies());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        $names = $this->dataProvider->getNames($displayLocale);

        $keys = array_keys($names);

        sort($keys);

        $this->assertEquals(static::$currencies, $keys);

        // Names should be sorted
        $sortedNames = $names;
        $collator = new \Collator($displayLocale);
        $collator->asort($names);

        $this->assertSame($sortedNames, $names);
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
        $expected = $this->dataProvider->getNames($displayLocale);
        $actual = array();

        foreach ($expected as $currency => $name) {
            $actual[$currency] = $this->dataProvider->getName($currency, $displayLocale);
        }

        $this->assertSame($expected, $actual);
    }

    public function testGetNameDefaultLocale()
    {
        Locale::setDefault('de_AT');

        $expected = $this->dataProvider->getNames('de_AT');
        $actual = array();

        foreach ($expected as $currency => $name) {
            $actual[$currency] = $this->dataProvider->getName($currency);
        }

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetSymbol($displayLocale)
    {
        $currencies = $this->dataProvider->getCurrencies();

        foreach ($currencies as $currency) {
            $this->assertGreaterThan(0, mb_strlen($this->dataProvider->getSymbol($currency, $displayLocale)));
        }
    }

    public function provideCurrencies()
    {
        return array_map(
            function ($currency) { return array($currency); },
            static::$currencies
        );
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetFractionDigits($currency)
    {
        $this->assertInternalType('numeric', $this->dataProvider->getFractionDigits($currency));
    }

    /**
     * @dataProvider provideCurrencies
     */
    public function testGetRoundingIncrement($currency)
    {
        $this->assertInternalType('numeric', $this->dataProvider->getRoundingIncrement($currency));
    }

    public function provideCurrenciesWithNumericEquivalent()
    {
        return array_map(
            function ($value) { return array($value); },
            array_keys(static::$alpha3ToNumeric)
        );
    }

    /**
     * @dataProvider provideCurrenciesWithNumericEquivalent
     */
    public function testGetNumericCode($currency)
    {
        $this->assertSame(static::$alpha3ToNumeric[$currency], $this->dataProvider->getNumericCode($currency));
    }

    public function provideCurrenciesWithoutNumericEquivalent()
    {
        return array_map(
            function ($value) { return array($value); },
            array_diff(static::$currencies, array_keys(static::$alpha3ToNumeric))
        );
    }

    /**
     * @dataProvider provideCurrenciesWithoutNumericEquivalent
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testGetNumericCodeFailsIfNoNumericEquivalent($currency)
    {
        $this->dataProvider->getNumericCode($currency);
    }

    public function provideValidNumericCodes()
    {
        $numericToAlpha3 = $this->getNumericToAlpha3Mapping();

        return array_map(
            function ($numeric, $alpha3) { return array($numeric, $alpha3); },
            array_keys($numericToAlpha3),
            $numericToAlpha3
        );
    }

    /**
     * @dataProvider provideValidNumericCodes
     */
    public function testForNumericCode($numeric, $expected)
    {
        $actual = $this->dataProvider->forNumericCode($numeric);

        // Make sure that a different array order doesn't break the test
        sort($actual);
        sort($expected);

        $this->assertEquals($expected, $actual);
    }

    public function provideInvalidNumericCodes()
    {
        $validNumericCodes = array_keys($this->getNumericToAlpha3Mapping());
        $invalidNumericCodes = array_diff(range(0, 1000), $validNumericCodes);

        return array_map(
            function ($value) { return array($value); },
            $invalidNumericCodes
        );
    }

    /**
     * @dataProvider provideInvalidNumericCodes
     * @expectedException \Symfony\Component\Intl\Exception\MissingResourceException
     */
    public function testForNumericCodeFailsIfInvalidNumericCode($currency)
    {
        $this->dataProvider->forNumericCode($currency);
    }

    private function getNumericToAlpha3Mapping()
    {
        $numericToAlpha3 = array();

        foreach (static::$alpha3ToNumeric as $alpha3 => $numeric) {
            if (!isset($numericToAlpha3[$numeric])) {
                $numericToAlpha3[$numeric] = array();
            }

            $numericToAlpha3[$numeric][] = $alpha3;
        }

        return $numericToAlpha3;
    }
}
