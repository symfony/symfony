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
use Symfony\Component\Intl\Timezones;
use Symfony\Component\Intl\Util\IntlTestHelper;

/**
 * @group intl-data
 */
class TimezonesTest extends ResourceBundleTestCase
{
    // The below arrays document the state of the ICU data bundled with this package.

    private const ZONES = [
        'Africa/Abidjan',
        'Africa/Accra',
        'Africa/Addis_Ababa',
        'Africa/Algiers',
        'Africa/Asmera',
        'Africa/Bamako',
        'Africa/Bangui',
        'Africa/Banjul',
        'Africa/Bissau',
        'Africa/Blantyre',
        'Africa/Brazzaville',
        'Africa/Bujumbura',
        'Africa/Cairo',
        'Africa/Casablanca',
        'Africa/Ceuta',
        'Africa/Conakry',
        'Africa/Dakar',
        'Africa/Dar_es_Salaam',
        'Africa/Djibouti',
        'Africa/Douala',
        'Africa/El_Aaiun',
        'Africa/Freetown',
        'Africa/Gaborone',
        'Africa/Harare',
        'Africa/Johannesburg',
        'Africa/Juba',
        'Africa/Kampala',
        'Africa/Khartoum',
        'Africa/Kigali',
        'Africa/Kinshasa',
        'Africa/Lagos',
        'Africa/Libreville',
        'Africa/Lome',
        'Africa/Luanda',
        'Africa/Lubumbashi',
        'Africa/Lusaka',
        'Africa/Malabo',
        'Africa/Maputo',
        'Africa/Maseru',
        'Africa/Mbabane',
        'Africa/Mogadishu',
        'Africa/Monrovia',
        'Africa/Nairobi',
        'Africa/Ndjamena',
        'Africa/Niamey',
        'Africa/Nouakchott',
        'Africa/Ouagadougou',
        'Africa/Porto-Novo',
        'Africa/Sao_Tome',
        'Africa/Tripoli',
        'Africa/Tunis',
        'Africa/Windhoek',
        'America/Adak',
        'America/Anchorage',
        'America/Anguilla',
        'America/Antigua',
        'America/Araguaina',
        'America/Argentina/La_Rioja',
        'America/Argentina/Rio_Gallegos',
        'America/Argentina/Salta',
        'America/Argentina/San_Juan',
        'America/Argentina/San_Luis',
        'America/Argentina/Tucuman',
        'America/Argentina/Ushuaia',
        'America/Aruba',
        'America/Asuncion',
        'America/Bahia',
        'America/Bahia_Banderas',
        'America/Barbados',
        'America/Belem',
        'America/Belize',
        'America/Blanc-Sablon',
        'America/Boa_Vista',
        'America/Bogota',
        'America/Boise',
        'America/Buenos_Aires',
        'America/Cambridge_Bay',
        'America/Campo_Grande',
        'America/Cancun',
        'America/Caracas',
        'America/Catamarca',
        'America/Cayenne',
        'America/Cayman',
        'America/Chicago',
        'America/Chihuahua',
        'America/Ciudad_Juarez',
        'America/Coral_Harbour',
        'America/Cordoba',
        'America/Costa_Rica',
        'America/Creston',
        'America/Cuiaba',
        'America/Curacao',
        'America/Danmarkshavn',
        'America/Dawson',
        'America/Dawson_Creek',
        'America/Denver',
        'America/Detroit',
        'America/Dominica',
        'America/Edmonton',
        'America/Eirunepe',
        'America/El_Salvador',
        'America/Fort_Nelson',
        'America/Fortaleza',
        'America/Glace_Bay',
        'America/Godthab',
        'America/Goose_Bay',
        'America/Grand_Turk',
        'America/Grenada',
        'America/Guadeloupe',
        'America/Guatemala',
        'America/Guayaquil',
        'America/Guyana',
        'America/Halifax',
        'America/Havana',
        'America/Hermosillo',
        'America/Indiana/Knox',
        'America/Indiana/Marengo',
        'America/Indiana/Petersburg',
        'America/Indiana/Tell_City',
        'America/Indiana/Vevay',
        'America/Indiana/Vincennes',
        'America/Indiana/Winamac',
        'America/Indianapolis',
        'America/Inuvik',
        'America/Iqaluit',
        'America/Jamaica',
        'America/Jujuy',
        'America/Juneau',
        'America/Kentucky/Monticello',
        'America/Kralendijk',
        'America/La_Paz',
        'America/Lima',
        'America/Los_Angeles',
        'America/Louisville',
        'America/Lower_Princes',
        'America/Maceio',
        'America/Managua',
        'America/Manaus',
        'America/Marigot',
        'America/Martinique',
        'America/Matamoros',
        'America/Mazatlan',
        'America/Mendoza',
        'America/Menominee',
        'America/Merida',
        'America/Metlakatla',
        'America/Mexico_City',
        'America/Miquelon',
        'America/Moncton',
        'America/Monterrey',
        'America/Montevideo',
        'America/Montserrat',
        'America/Nassau',
        'America/New_York',
        'America/Nome',
        'America/Noronha',
        'America/North_Dakota/Beulah',
        'America/North_Dakota/Center',
        'America/North_Dakota/New_Salem',
        'America/Ojinaga',
        'America/Panama',
        'America/Paramaribo',
        'America/Phoenix',
        'America/Port-au-Prince',
        'America/Port_of_Spain',
        'America/Porto_Velho',
        'America/Puerto_Rico',
        'America/Punta_Arenas',
        'America/Rankin_Inlet',
        'America/Recife',
        'America/Regina',
        'America/Resolute',
        'America/Rio_Branco',
        'America/Santarem',
        'America/Santiago',
        'America/Santo_Domingo',
        'America/Sao_Paulo',
        'America/Scoresbysund',
        'America/Sitka',
        'America/St_Barthelemy',
        'America/St_Johns',
        'America/St_Kitts',
        'America/St_Lucia',
        'America/St_Thomas',
        'America/St_Vincent',
        'America/Swift_Current',
        'America/Tegucigalpa',
        'America/Thule',
        'America/Tijuana',
        'America/Toronto',
        'America/Tortola',
        'America/Vancouver',
        'America/Whitehorse',
        'America/Winnipeg',
        'America/Yakutat',
        'Antarctica/Casey',
        'Antarctica/Davis',
        'Antarctica/DumontDUrville',
        'Antarctica/Macquarie',
        'Antarctica/Mawson',
        'Antarctica/McMurdo',
        'Antarctica/Palmer',
        'Antarctica/Rothera',
        'Antarctica/Syowa',
        'Antarctica/Troll',
        'Antarctica/Vostok',
        'Arctic/Longyearbyen',
        'Asia/Aden',
        'Asia/Almaty',
        'Asia/Amman',
        'Asia/Anadyr',
        'Asia/Aqtau',
        'Asia/Aqtobe',
        'Asia/Ashgabat',
        'Asia/Atyrau',
        'Asia/Baghdad',
        'Asia/Bahrain',
        'Asia/Baku',
        'Asia/Bangkok',
        'Asia/Barnaul',
        'Asia/Beirut',
        'Asia/Bishkek',
        'Asia/Brunei',
        'Asia/Calcutta',
        'Asia/Chita',
        'Asia/Colombo',
        'Asia/Damascus',
        'Asia/Dhaka',
        'Asia/Dili',
        'Asia/Dubai',
        'Asia/Dushanbe',
        'Asia/Famagusta',
        'Asia/Gaza',
        'Asia/Hebron',
        'Asia/Hong_Kong',
        'Asia/Hovd',
        'Asia/Irkutsk',
        'Asia/Jakarta',
        'Asia/Jayapura',
        'Asia/Jerusalem',
        'Asia/Kabul',
        'Asia/Kamchatka',
        'Asia/Karachi',
        'Asia/Katmandu',
        'Asia/Khandyga',
        'Asia/Krasnoyarsk',
        'Asia/Kuala_Lumpur',
        'Asia/Kuching',
        'Asia/Kuwait',
        'Asia/Macau',
        'Asia/Magadan',
        'Asia/Makassar',
        'Asia/Manila',
        'Asia/Muscat',
        'Asia/Nicosia',
        'Asia/Novokuznetsk',
        'Asia/Novosibirsk',
        'Asia/Omsk',
        'Asia/Oral',
        'Asia/Phnom_Penh',
        'Asia/Pontianak',
        'Asia/Pyongyang',
        'Asia/Qatar',
        'Asia/Qostanay',
        'Asia/Qyzylorda',
        'Asia/Rangoon',
        'Asia/Riyadh',
        'Asia/Saigon',
        'Asia/Sakhalin',
        'Asia/Samarkand',
        'Asia/Seoul',
        'Asia/Shanghai',
        'Asia/Singapore',
        'Asia/Srednekolymsk',
        'Asia/Taipei',
        'Asia/Tashkent',
        'Asia/Tbilisi',
        'Asia/Tehran',
        'Asia/Thimphu',
        'Asia/Tokyo',
        'Asia/Tomsk',
        'Asia/Ulaanbaatar',
        'Asia/Urumqi',
        'Asia/Ust-Nera',
        'Asia/Vientiane',
        'Asia/Vladivostok',
        'Asia/Yakutsk',
        'Asia/Yekaterinburg',
        'Asia/Yerevan',
        'Atlantic/Azores',
        'Atlantic/Bermuda',
        'Atlantic/Canary',
        'Atlantic/Cape_Verde',
        'Atlantic/Faeroe',
        'Atlantic/Madeira',
        'Atlantic/Reykjavik',
        'Atlantic/South_Georgia',
        'Atlantic/St_Helena',
        'Atlantic/Stanley',
        'Australia/Adelaide',
        'Australia/Brisbane',
        'Australia/Broken_Hill',
        'Australia/Darwin',
        'Australia/Eucla',
        'Australia/Hobart',
        'Australia/Lindeman',
        'Australia/Lord_Howe',
        'Australia/Melbourne',
        'Australia/Perth',
        'Australia/Sydney',
        'Etc/GMT',
        'Etc/UTC',
        'Europe/Amsterdam',
        'Europe/Andorra',
        'Europe/Astrakhan',
        'Europe/Athens',
        'Europe/Belgrade',
        'Europe/Berlin',
        'Europe/Bratislava',
        'Europe/Brussels',
        'Europe/Bucharest',
        'Europe/Budapest',
        'Europe/Busingen',
        'Europe/Chisinau',
        'Europe/Copenhagen',
        'Europe/Dublin',
        'Europe/Gibraltar',
        'Europe/Guernsey',
        'Europe/Helsinki',
        'Europe/Isle_of_Man',
        'Europe/Istanbul',
        'Europe/Jersey',
        'Europe/Kaliningrad',
        'Europe/Kiev',
        'Europe/Kirov',
        'Europe/Lisbon',
        'Europe/Ljubljana',
        'Europe/London',
        'Europe/Luxembourg',
        'Europe/Madrid',
        'Europe/Malta',
        'Europe/Mariehamn',
        'Europe/Minsk',
        'Europe/Monaco',
        'Europe/Moscow',
        'Europe/Oslo',
        'Europe/Paris',
        'Europe/Podgorica',
        'Europe/Prague',
        'Europe/Riga',
        'Europe/Rome',
        'Europe/Samara',
        'Europe/San_Marino',
        'Europe/Sarajevo',
        'Europe/Saratov',
        'Europe/Simferopol',
        'Europe/Skopje',
        'Europe/Sofia',
        'Europe/Stockholm',
        'Europe/Tallinn',
        'Europe/Tirane',
        'Europe/Ulyanovsk',
        'Europe/Vaduz',
        'Europe/Vatican',
        'Europe/Vienna',
        'Europe/Vilnius',
        'Europe/Volgograd',
        'Europe/Warsaw',
        'Europe/Zagreb',
        'Europe/Zurich',
        'Indian/Antananarivo',
        'Indian/Chagos',
        'Indian/Christmas',
        'Indian/Cocos',
        'Indian/Comoro',
        'Indian/Kerguelen',
        'Indian/Mahe',
        'Indian/Maldives',
        'Indian/Mauritius',
        'Indian/Mayotte',
        'Indian/Reunion',
        'Pacific/Apia',
        'Pacific/Auckland',
        'Pacific/Bougainville',
        'Pacific/Chatham',
        'Pacific/Easter',
        'Pacific/Efate',
        'Pacific/Enderbury',
        'Pacific/Fakaofo',
        'Pacific/Fiji',
        'Pacific/Funafuti',
        'Pacific/Galapagos',
        'Pacific/Gambier',
        'Pacific/Guadalcanal',
        'Pacific/Guam',
        'Pacific/Honolulu',
        'Pacific/Kiritimati',
        'Pacific/Kosrae',
        'Pacific/Kwajalein',
        'Pacific/Majuro',
        'Pacific/Marquesas',
        'Pacific/Midway',
        'Pacific/Nauru',
        'Pacific/Niue',
        'Pacific/Norfolk',
        'Pacific/Noumea',
        'Pacific/Pago_Pago',
        'Pacific/Palau',
        'Pacific/Pitcairn',
        'Pacific/Ponape',
        'Pacific/Port_Moresby',
        'Pacific/Rarotonga',
        'Pacific/Saipan',
        'Pacific/Tahiti',
        'Pacific/Tarawa',
        'Pacific/Tongatapu',
        'Pacific/Truk',
        'Pacific/Wake',
        'Pacific/Wallis',
    ];
    private const ZONES_NO_COUNTRY = [
        'Antarctica/Troll',
        'CST6CDT',
        'EST5EDT',
        'MST7MDT',
        'PST8PDT',
        'Etc/GMT',
        'Etc/UTC',
    ];

    public function testGetIds()
    {
        $this->assertEquals(self::ZONES, Timezones::getIds());
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetNames($displayLocale)
    {
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $zones = array_keys(Timezones::getNames($displayLocale));

        sort($zones);

        $this->assertNotEmpty($zones);
        $this->assertEmpty(array_diff($zones, self::ZONES));
    }

    public function testGetNamesDefaultLocale()
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $this->assertSame(Timezones::getNames('de_AT'), Timezones::getNames());
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
        $this->assertEquals(Timezones::getNames($ofLocale), Timezones::getNames($alias));
    }

    /**
     * @dataProvider provideLocales
     */
    public function testGetName($displayLocale)
    {
        if ('en' !== $displayLocale) {
            IntlTestHelper::requireFullIntl($this);
        }

        $names = Timezones::getNames($displayLocale);

        foreach ($names as $language => $name) {
            $this->assertSame($name, Timezones::getName($language, $displayLocale));
        }
    }

    public function testGetNameDefaultLocale()
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('de_AT');

        $names = Timezones::getNames('de_AT');

        foreach ($names as $language => $name) {
            $this->assertSame($name, Timezones::getName($language));
        }
    }

    public function testGetNameWithInvalidTimezone()
    {
        $this->expectException(MissingResourceException::class);
        Timezones::getName('foo');
    }

    public function testGetNameWithAliasTimezone()
    {
        $this->expectException(MissingResourceException::class);
        Timezones::getName('US/Pacific'); // alias in icu (not compiled), name unavailable in php
    }

    public function testExists()
    {
        $this->assertTrue(Timezones::exists('Europe/Amsterdam'));
        $this->assertTrue(Timezones::exists('US/Pacific')); // alias in icu (not compiled), identifier available in php
        $this->assertFalse(Timezones::exists('Etc/Unknown'));
    }

    public function testGetRawOffset()
    {
        // timezones free from DST changes to avoid time-based variance
        $this->assertSame(0, Timezones::getRawOffset('Etc/UTC'));
        $this->assertSame(-10800, Timezones::getRawOffset('America/Buenos_Aires'));
        $this->assertSame(20700, Timezones::getRawOffset('Asia/Katmandu'));

        // ensure we support identifiers available in php (not compiled from icu)
        Timezones::getRawOffset('US/Pacific');
    }

    public function testGetRawOffsetWithUnknownTimezone()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unknown or bad timezone (foobar)');
        Timezones::getRawOffset('foobar');
    }

    public function testGetGmtOffset()
    {
        // timezones free from DST changes to avoid time-based variance
        $this->assertSame('GMT+00:00', Timezones::getGmtOffset('Etc/UTC'));
        $this->assertSame('UTC+00:00', Timezones::getGmtOffset('Etc/UTC', null, 'fr'));
        $this->assertSame('GMT +00:00', Timezones::getGmtOffset('Etc/GMT', null, 'ur'));
        $this->assertSame('GMT+00:00', Timezones::getGmtOffset('Etc/GMT', null, 'ur_IN'));
        $this->assertSame('GMT-03:00', Timezones::getGmtOffset('America/Buenos_Aires'));
        $this->assertSame('ཇི་ཨེམ་ཏི་-03:00', Timezones::getGmtOffset('America/Buenos_Aires', null, 'dz'));
        $this->assertSame('GMT+05:45', Timezones::getGmtOffset('Asia/Katmandu'));
        $this->assertSame('GMT+5:45', Timezones::getGmtOffset('Asia/Katmandu', null, 'cs'));
    }

    public function testGetCountryCode()
    {
        $this->assertSame('NL', Timezones::getCountryCode('Europe/Amsterdam'));
        $this->assertSame('US', Timezones::getCountryCode('America/New_York'));
    }

    public function testForCountryCode()
    {
        $this->assertSame(['Europe/Amsterdam'], Timezones::forCountryCode('NL'));
        $this->assertSame(['Europe/Berlin', 'Europe/Busingen'], Timezones::forCountryCode('DE'));
    }

    public function testForCountryCodeWithUnknownCountry()
    {
        $this->expectException(MissingResourceException::class);
        Timezones::forCountryCode('foobar');
    }

    public function testForCountryCodeWithWrongCountryCode()
    {
        $this->expectException(MissingResourceException::class);
        $this->expectExceptionMessage('Country codes must be in uppercase, but "nl" was passed. Try with "NL" country code instead.');
        Timezones::forCountryCode('nl');
    }

    public function testGetCountryCodeWithUnknownTimezone()
    {
        $this->expectException(MissingResourceException::class);
        Timezones::getCountryCode('foobar');
    }

    /**
     * @dataProvider provideTimezones
     */
    public function testGetGmtOffsetAvailability(string $timezone)
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $this->markTestSkipped(\sprintf('The timezone "%s" is not available.', $timezone));
        }

        // ensure each timezone identifier has a corresponding GMT offset
        Timezones::getRawOffset($timezone);
        Timezones::getGmtOffset($timezone);

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider provideTimezones
     */
    public function testGetCountryCodeAvailability(string $timezone)
    {
        try {
            // ensure each timezone identifier has a corresponding country code
            Timezones::getCountryCode($timezone);

            $this->addToAssertionCount(1);
        } catch (MissingResourceException $e) {
            if (\in_array($timezone, self::ZONES_NO_COUNTRY, true)) {
                $this->markTestSkipped();
            } else {
                $this->fail();
            }
        }
    }

    public static function provideTimezones(): iterable
    {
        return array_map(fn ($timezone) => [$timezone], self::ZONES);
    }

    /**
     * @dataProvider provideCountries
     */
    public function testForCountryCodeAvailability(string $country)
    {
        // ensure each country code has a list of timezone identifiers (possibly empty)
        Timezones::forCountryCode($country);

        $this->addToAssertionCount(1);
    }

    public static function provideCountries(): iterable
    {
        return array_map(fn ($country) => [$country], Countries::getCountryCodes());
    }

    public function testGetRawOffsetChangeTimeCountry()
    {
        $this->assertSame(7200, Timezones::getRawOffset('Europe/Paris', (new \DateTimeImmutable('2022-07-16 00:00:00+00:00'))->getTimestamp()));
        $this->assertSame(3600, Timezones::getRawOffset('Europe/Paris', (new \DateTimeImmutable('2022-02-16 00:00:00+00:00'))->getTimestamp()));
    }
}
