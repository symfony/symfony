<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\DateFormatter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * Test case for IntlDateFormatter implementations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractIntlDateFormatterTest extends TestCase
{
    private $defaultLocale;

    protected function setUp()
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
        \Locale::setDefault('en');
    }

    protected function tearDown()
    {
        parent::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    /**
     * When a time zone is not specified, it uses the system default however it returns null in the getter method.
     *
     * @see StubIntlDateFormatterTest::testDefaultTimeZoneIntl()
     */
    public function testConstructorDefaultTimeZone()
    {
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);

        $this->assertEquals(date_default_timezone_get(), $formatter->getTimeZoneId());

        $this->assertEquals(
            $this->getDateTime(0, $formatter->getTimeZoneId())->format('M j, Y, g:i A'),
            $formatter->format(0)
        );
    }

    public function testConstructorWithoutDateType()
    {
        $formatter = new IntlDateFormatter('en', null, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN);

        $this->assertSame('EEEE, LLLL d, y, h:mm a', $formatter->getPattern());
    }

    public function testConstructorWithoutTimeType()
    {
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::SHORT, null, 'UTC', IntlDateFormatter::GREGORIAN);

        $this->assertSame('M/d/yy, h:mm:ss a zzzz', $formatter->getPattern());
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFormat($pattern, $timestamp, $expected)
    {
        $errorCode = IntlGlobals::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->getDefaultDateFormatter($pattern);
        $this->assertSame($expected, $formatter->format($timestamp));
        $this->assertIsIntlSuccess($formatter, $errorMessage, $errorCode);
    }

    public function formatProvider()
    {
        $dateTime = new \DateTime('@0');
        $dateTimeImmutable = new \DateTimeImmutable('@0');

        $formatData = [
            /* general */
            ['y-M-d', 0, '1970-1-1'],
            ["EEE, MMM d, ''yy", 0, "Thu, Jan 1, '70"],
            ['h:mm a', 0, '12:00 AM'],
            ['yyyyy.MMMM.dd hh:mm aaa', 0, '01970.January.01 12:00 AM'],

            /* escaping */
            ["'M'", 0, 'M'],
            ["'yy'", 0, 'yy'],
            ["'''yy'", 0, "'yy"],
            ["''y", 0, "'1970"],
            ["''yy", 0, "'70"],
            ["H 'o'' clock'", 0, "0 o' clock"],

            /* month */
            ['M', 0, '1'],
            ['MM', 0, '01'],
            ['MMM', 0, 'Jan'],
            ['MMMM', 0, 'January'],
            ['MMMMM', 0, 'J'],
            ['MMMMMM', 0, '000001'],

            ['L', 0, '1'],
            ['LL', 0, '01'],
            ['LLL', 0, 'Jan'],
            ['LLLL', 0, 'January'],
            ['LLLLL', 0, 'J'],
            ['LLLLLL', 0, '000001'],

            /* year */
            ['y', 0, '1970'],
            ['yy', 0, '70'],
            ['yyy', 0, '1970'],
            ['yyyy', 0, '1970'],
            ['yyyyy', 0, '01970'],
            ['yyyyyy', 0, '001970'],

            /* day */
            ['d', 0, '1'],
            ['dd', 0, '01'],
            ['ddd', 0, '001'],

            /* quarter */
            ['Q', 0, '1'],
            ['QQ', 0, '01'],
            ['QQQ', 0, 'Q1'],
            ['QQQQ', 0, '1st quarter'],
            ['QQQQQ', 0, '1st quarter'],

            ['q', 0, '1'],
            ['qq', 0, '01'],
            ['qqq', 0, 'Q1'],
            ['qqqq', 0, '1st quarter'],
            ['qqqqq', 0, '1st quarter'],

            // 4 months
            ['Q', 7776000, '2'],
            ['QQ', 7776000, '02'],
            ['QQQ', 7776000, 'Q2'],
            ['QQQQ', 7776000, '2nd quarter'],

            // 7 months
            ['QQQQ', 15638400, '3rd quarter'],

            // 10 months
            ['QQQQ', 23587200, '4th quarter'],

            /* 12-hour (1-12) */
            ['h', 0, '12'],
            ['hh', 0, '12'],
            ['hhh', 0, '012'],

            ['h', 1, '12'],
            ['h', 3600, '1'],
            ['h', 43200, '12'], // 12 hours

            /* day of year */
            ['D', 0, '1'],
            ['D', 86400, '2'], // 1 day
            ['D', 31536000, '1'], // 1 year
            ['D', 31622400, '2'], // 1 year + 1 day

            /* day of week */
            ['E', 0, 'Thu'],
            ['EE', 0, 'Thu'],
            ['EEE', 0, 'Thu'],
            ['EEEE', 0, 'Thursday'],
            ['EEEEE', 0, 'T'],
            ['EEEEEE', 0, 'Th'],

            ['E', 1296540000, 'Tue'], // 2011-02-01
            ['E', 1296950400, 'Sun'], // 2011-02-06

            /* am/pm marker */
            ['a', 0, 'AM'],
            ['aa', 0, 'AM'],
            ['aaa', 0, 'AM'],
            ['aaaa', 0, 'AM'],

            // 12 hours
            ['a', 43200, 'PM'],
            ['aa', 43200, 'PM'],
            ['aaa', 43200, 'PM'],
            ['aaaa', 43200, 'PM'],

            /* 24-hour (0-23) */
            ['H', 0, '0'],
            ['HH', 0, '00'],
            ['HHH', 0, '000'],

            ['H', 1, '0'],
            ['H', 3600, '1'],
            ['H', 43200, '12'],
            ['H', 46800, '13'],

            /* 24-hour (1-24) */
            ['k', 0, '24'],
            ['kk', 0, '24'],
            ['kkk', 0, '024'],

            ['k', 1, '24'],
            ['k', 3600, '1'],
            ['k', 43200, '12'],
            ['k', 46800, '13'],

            /* 12-hour (0-11) */
            ['K', 0, '0'],
            ['KK', 0, '00'],
            ['KKK', 0, '000'],

            ['K', 1, '0'],
            ['K', 3600, '1'],
            ['K', 43200, '0'], // 12 hours

            /* minute */
            ['m', 0, '0'],
            ['mm', 0, '00'],
            ['mmm', 0, '000'],

            ['m', 1, '0'],
            ['m', 60, '1'],
            ['m', 120, '2'],
            ['m', 180, '3'],
            ['m', 3600, '0'],
            ['m', 3660, '1'],
            ['m', 43200, '0'], // 12 hours

            /* second */
            ['s', 0, '0'],
            ['ss', 0, '00'],
            ['sss', 0, '000'],

            ['s', 1, '1'],
            ['s', 2, '2'],
            ['s', 5, '5'],
            ['s', 30, '30'],
            ['s', 59, '59'],
            ['s', 60, '0'],
            ['s', 120, '0'],
            ['s', 180, '0'],
            ['s', 3600, '0'],
            ['s', 3601, '1'],
            ['s', 3630, '30'],
            ['s', 43200, '0'], // 12 hours
        ];

        /* general, DateTime */
        $formatData[] = ['y-M-d', $dateTime, '1970-1-1'];
        $formatData[] = ["EEE, MMM d, ''yy", $dateTime, "Thu, Jan 1, '70"];
        $formatData[] = ['h:mm a', $dateTime, '12:00 AM'];
        $formatData[] = ['yyyyy.MMMM.dd hh:mm aaa', $dateTime, '01970.January.01 12:00 AM'];

        /* general, DateTimeImmutable */
        $formatData[] = ['y-M-d', $dateTimeImmutable, '1970-1-1'];
        $formatData[] = ["EEE, MMM d, ''yy", $dateTimeImmutable, "Thu, Jan 1, '70"];
        $formatData[] = ['h:mm a', $dateTimeImmutable, '12:00 AM'];
        $formatData[] = ['yyyyy.MMMM.dd hh:mm aaa', $dateTimeImmutable, '01970.January.01 12:00 AM'];

        if (IcuVersion::compare(Intl::getIcuVersion(), '59.1', '>=', 1)) {
            // Before ICU 59.1 GMT was used instead of UTC
            $formatData[] = ["yyyy.MM.dd 'at' HH:mm:ss zzz", 0, '1970.01.01 at 00:00:00 UTC'];
            $formatData[] = ['K:mm a, z', 0, '0:00 AM, UTC'];
            $formatData[] = ["yyyy.MM.dd 'at' HH:mm:ss zzz", $dateTime, '1970.01.01 at 00:00:00 UTC'];
            $formatData[] = ['K:mm a, z', $dateTime, '0:00 AM, UTC'];
        }

        return $formatData;
    }

    /**
     * @requires PHP 5.5.10
     */
    public function testFormatUtcAndGmtAreSplit()
    {
        $pattern = "yyyy.MM.dd 'at' HH:mm:ss zzz";
        $gmtFormatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'GMT', IntlDateFormatter::GREGORIAN, $pattern);
        $utcFormatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, $pattern);

        $this->assertSame('1970.01.01 at 00:00:00 GMT', $gmtFormatter->format(new \DateTime('@0')));
        $this->assertSame('1970.01.01 at 00:00:00 UTC', $utcFormatter->format(new \DateTime('@0')));
        $this->assertSame('1970.01.01 at 00:00:00 GMT', $gmtFormatter->format(new \DateTimeImmutable('@0')));
        $this->assertSame('1970.01.01 at 00:00:00 UTC', $utcFormatter->format(new \DateTimeImmutable('@0')));
    }

    /**
     * @dataProvider formatErrorProvider
     */
    public function testFormatIllegalArgumentError($pattern, $timestamp, $errorMessage)
    {
        $errorCode = IntlGlobals::U_ILLEGAL_ARGUMENT_ERROR;

        $formatter = $this->getDefaultDateFormatter($pattern);
        $this->assertFalse($formatter->format($timestamp));
        $this->assertIsIntlFailure($formatter, $errorMessage, $errorCode);
    }

    public function formatErrorProvider()
    {
        return [
            ['y-M-d', 'foobar', 'datefmt_format: string \'foobar\' is not numeric, which would be required for it to be a valid date: U_ILLEGAL_ARGUMENT_ERROR'],
        ];
    }

    /**
     * @dataProvider formatWithTimezoneProvider
     */
    public function testFormatWithTimezone($timestamp, $timezone, $expected)
    {
        $pattern = 'yyyy-MM-dd HH:mm:ss';
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, $timezone, IntlDateFormatter::GREGORIAN, $pattern);
        $this->assertSame($expected, $formatter->format($timestamp));
    }

    public function formatWithTimezoneProvider()
    {
        $data = [
            [0, 'UTC', '1970-01-01 00:00:00'],
            [0, 'GMT', '1970-01-01 00:00:00'],
            [0, 'GMT-03:00', '1969-12-31 21:00:00'],
            [0, 'GMT+03:00', '1970-01-01 03:00:00'],
            [0, 'Europe/Zurich', '1970-01-01 01:00:00'],
            [0, 'Europe/Paris', '1970-01-01 01:00:00'],
            [0, 'Africa/Cairo', '1970-01-01 02:00:00'],
            [0, 'Africa/Casablanca', '1970-01-01 00:00:00'],
            [0, 'Africa/Djibouti', '1970-01-01 03:00:00'],
            [0, 'Africa/Johannesburg', '1970-01-01 02:00:00'],
            [0, 'America/Antigua', '1969-12-31 20:00:00'],
            [0, 'America/Toronto', '1969-12-31 19:00:00'],
            [0, 'America/Vancouver', '1969-12-31 16:00:00'],
            [0, 'Asia/Aqtau', '1970-01-01 05:00:00'],
            [0, 'Asia/Bangkok', '1970-01-01 07:00:00'],
            [0, 'Asia/Dubai', '1970-01-01 04:00:00'],
            [0, 'Australia/Brisbane', '1970-01-01 10:00:00'],
            [0, 'Australia/Eucla', '1970-01-01 08:45:00'],
            [0, 'Australia/Melbourne', '1970-01-01 10:00:00'],
            [0, 'Europe/Berlin', '1970-01-01 01:00:00'],
            [0, 'Europe/Dublin', '1970-01-01 01:00:00'],
            [0, 'Europe/Warsaw', '1970-01-01 01:00:00'],
            [0, 'Pacific/Fiji', '1970-01-01 12:00:00'],
        ];

        return $data;
    }

    /**
     * @dataProvider formatTimezoneProvider
     * @requires PHP 5.5.10
     */
    public function testFormatTimezone($pattern, $timezone, $expected)
    {
        $formatter = $this->getDefaultDateFormatter($pattern);
        $formatter->setTimeZone(new \DateTimeZone($timezone));

        $this->assertEquals($expected, $formatter->format(0));
    }

    public function formatTimezoneProvider()
    {
        $cases = [
            ['z', 'GMT', 'GMT'],
            ['zz', 'GMT', 'GMT'],
            ['zzz', 'GMT', 'GMT'],
            ['zzzz', 'GMT', 'Greenwich Mean Time'],
            ['zzzzz', 'GMT', 'Greenwich Mean Time'],

            ['z', 'Etc/GMT', 'GMT'],
            ['zz', 'Etc/GMT', 'GMT'],
            ['zzz', 'Etc/GMT', 'GMT'],
            ['zzzz', 'Etc/GMT', 'Greenwich Mean Time'],
            ['zzzzz', 'Etc/GMT', 'Greenwich Mean Time'],

            ['z', 'Etc/GMT+3', 'GMT-3'],
            ['zz', 'Etc/GMT+3', 'GMT-3'],
            ['zzz', 'Etc/GMT+3', 'GMT-3'],
            ['zzzz', 'Etc/GMT+3', 'GMT-03:00'],
            ['zzzzz', 'Etc/GMT+3', 'GMT-03:00'],

            ['z', 'UTC', 'UTC'],
            ['zz', 'UTC', 'UTC'],
            ['zzz', 'UTC', 'UTC'],
            ['zzzz', 'UTC', 'Coordinated Universal Time'],
            ['zzzzz', 'UTC', 'Coordinated Universal Time'],

            ['z', 'Etc/UTC', 'UTC'],
            ['zz', 'Etc/UTC', 'UTC'],
            ['zzz', 'Etc/UTC', 'UTC'],
            ['zzzz', 'Etc/UTC', 'Coordinated Universal Time'],
            ['zzzzz', 'Etc/UTC', 'Coordinated Universal Time'],

            ['z', 'Etc/Universal', 'UTC'],
            ['z', 'Etc/Zulu', 'UTC'],
            ['z', 'Etc/UCT', 'UTC'],
            ['z', 'Etc/Greenwich', 'GMT'],
            ['zzzzz', 'Etc/Universal', 'Coordinated Universal Time'],
            ['zzzzz', 'Etc/Zulu', 'Coordinated Universal Time'],
            ['zzzzz', 'Etc/UCT', 'Coordinated Universal Time'],
            ['zzzzz', 'Etc/Greenwich', 'Greenwich Mean Time'],
        ];

        if (!\defined('HHVM_VERSION')) {
            // these timezones are not considered valid in HHVM
            $cases = array_merge($cases, [
                ['z', 'GMT+03:00', 'GMT+3'],
                ['zz', 'GMT+03:00', 'GMT+3'],
                ['zzz', 'GMT+03:00', 'GMT+3'],
                ['zzzz', 'GMT+03:00', 'GMT+03:00'],
                ['zzzzz', 'GMT+03:00', 'GMT+03:00'],
            ]);
        }

        return $cases;
    }

    public function testFormatWithGmtTimezone()
    {
        $formatter = $this->getDefaultDateFormatter('zzzz');

        $formatter->setTimeZone('GMT+03:00');

        $this->assertEquals('GMT+03:00', $formatter->format(0));
    }

    public function testFormatWithGmtTimeZoneAndMinutesOffset()
    {
        $formatter = $this->getDefaultDateFormatter('zzzz');

        $formatter->setTimeZone('GMT+00:30');

        $this->assertEquals('GMT+00:30', $formatter->format(0));
    }

    public function testFormatWithNonStandardTimezone()
    {
        $formatter = $this->getDefaultDateFormatter('zzzz');

        $formatter->setTimeZone('Pacific/Fiji');

        $this->assertEquals('Fiji Standard Time', $formatter->format(0));
    }

    public function testFormatWithConstructorTimezone()
    {
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC');
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals(
            $this->getDateTime(0, 'UTC')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );
    }

    /**
     * @requires PHP 5.5.10
     */
    public function testFormatWithDateTimeZoneGmt()
    {
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, new \DateTimeZone('GMT'), IntlDateFormatter::GREGORIAN, 'zzz');

        $this->assertEquals('GMT', $formatter->format(0));
    }

    public function testFormatWithDateTimeZoneGmtOffset()
    {
        if (\defined('HHVM_VERSION_ID') || \PHP_VERSION_ID <= 50509) {
            $this->markTestSkipped('DateTimeZone GMT offsets are supported since 5.5.10. See https://github.com/facebook/hhvm/issues/5875 for HHVM.');
        }

        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, new \DateTimeZone('GMT+03:00'), IntlDateFormatter::GREGORIAN, 'zzzz');

        $this->assertEquals('GMT+03:00', $formatter->format(0));
    }

    public function testFormatWithIntlTimeZone()
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }

        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, \IntlTimeZone::createTimeZone('GMT+03:00'), IntlDateFormatter::GREGORIAN, 'zzzz');

        $this->assertEquals('GMT+03:00', $formatter->format(0));
    }

    public function testFormatWithTimezoneFromPhp()
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/London');

        $formatter = $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals(
            $this->getDateTime(0, 'Europe/London')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );

        $this->assertEquals('Europe/London', date_default_timezone_get());

        // Restores TZ.
        date_default_timezone_set($tz);
    }

    /**
     * @dataProvider dateAndTimeTypeProvider
     */
    public function testDateAndTimeType($timestamp, $datetype, $timetype, $expected)
    {
        $formatter = $this->getDateFormatter('en', $datetype, $timetype, 'UTC');
        $this->assertSame($expected, $formatter->format($timestamp));
    }

    public function dateAndTimeTypeProvider()
    {
        return [
            [0, IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Thursday, January 1, 1970'],
            [0, IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'January 1, 1970'],
            [0, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, 'Jan 1, 1970'],
            [0, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, '1/1/70'],
            [0, IntlDateFormatter::NONE, IntlDateFormatter::FULL, '12:00:00 AM Coordinated Universal Time'],
            [0, IntlDateFormatter::NONE, IntlDateFormatter::LONG, '12:00:00 AM UTC'],
            [0, IntlDateFormatter::NONE, IntlDateFormatter::MEDIUM, '12:00:00 AM'],
            [0, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, '12:00 AM'],
        ];
    }

    public function testGetCalendar()
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertEquals(IntlDateFormatter::GREGORIAN, $formatter->getCalendar());
    }

    public function testGetDateType()
    {
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $this->assertEquals(IntlDateFormatter::FULL, $formatter->getDateType());
    }

    public function testGetLocale()
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertEquals('en', $formatter->getLocale());
    }

    public function testGetPattern()
    {
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'UTC', IntlDateFormatter::GREGORIAN, 'yyyy-MM-dd');
        $this->assertEquals('yyyy-MM-dd', $formatter->getPattern());
    }

    public function testGetTimeType()
    {
        $formatter = $this->getDateFormatter('en', IntlDateFormatter::NONE, IntlDateFormatter::FULL);
        $this->assertEquals(IntlDateFormatter::FULL, $formatter->getTimeType());
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParse($pattern, $value, $expected)
    {
        $errorCode = IntlGlobals::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->getDefaultDateFormatter($pattern);
        $this->assertSame($expected, $formatter->parse($value));
        $this->assertIsIntlSuccess($formatter, $errorMessage, $errorCode);
    }

    public function parseProvider()
    {
        return array_merge(
            $this->parseYearProvider(),
            $this->parseQuarterProvider(),
            $this->parseMonthProvider(),
            $this->parseStandaloneMonthProvider(),
            $this->parseDayProvider(),
            $this->parseDayOfWeekProvider(),
            $this->parseDayOfYearProvider(),
            $this->parseHour12ClockOneBasedProvider(),
            $this->parseHour12ClockZeroBasedProvider(),
            $this->parseHour24ClockOneBasedProvider(),
            $this->parseHour24ClockZeroBasedProvider(),
            $this->parseMinuteProvider(),
            $this->parseSecondProvider(),
            $this->parseTimezoneProvider(),
            $this->parseAmPmProvider(),
            $this->parseStandaloneAmPmProvider(),
            $this->parseRegexMetaCharsProvider(),
            $this->parseQuoteCharsProvider(),
            $this->parseDashSlashProvider()
        );
    }

    public function parseYearProvider()
    {
        return [
            ['y-M-d', '1970-1-1', 0],
            ['yy-M-d', '70-1-1', 0],
        ];
    }

    public function parseQuarterProvider()
    {
        return [
            ['Q', '1', 0],
            ['QQ', '01', 0],
            ['QQQ', 'Q1', 0],
            ['QQQQ', '1st quarter', 0],
            ['QQQQQ', '1st quarter', 0],

            ['Q', '2', 7776000],
            ['QQ', '02', 7776000],
            ['QQQ', 'Q2', 7776000],
            ['QQQQ', '2nd quarter', 7776000],
            ['QQQQQ', '2nd quarter', 7776000],

            ['q', '1', 0],
            ['qq', '01', 0],
            ['qqq', 'Q1', 0],
            ['qqqq', '1st quarter', 0],
            ['qqqqq', '1st quarter', 0],
        ];
    }

    public function parseMonthProvider()
    {
        return [
            ['y-M-d', '1970-1-1', 0],
            ['y-MM-d', '1970-1-1', 0],
            ['y-MMM-d', '1970-Jan-1', 0],
            ['y-MMMM-d', '1970-January-1', 0],
        ];
    }

    public function parseStandaloneMonthProvider()
    {
        return [
            ['y-L-d', '1970-1-1', 0],
            ['y-LLL-d', '1970-Jan-1', 0],
            ['y-LLLL-d', '1970-January-1', 0],
        ];
    }

    public function parseDayProvider()
    {
        return [
            ['y-M-d', '1970-1-1', 0],
            ['y-M-dd', '1970-1-1', 0],
            ['y-M-dd', '1970-1-01', 0],
            ['y-M-ddd', '1970-1-001', 0],
        ];
    }

    public function parseDayOfWeekProvider()
    {
        return [
            ['E', 'Thu', 0],
            ['EE', 'Thu', 0],
            ['EEE', 'Thu', 0],
            ['EEEE', 'Thursday', 0],
            ['EEEEE', 'T', 432000],
            ['EEEEEE', 'Th', 0],
        ];
    }

    public function parseDayOfYearProvider()
    {
        return [
            ['D', '1', 0],
            ['D', '2', 86400],
        ];
    }

    public function parseHour12ClockOneBasedProvider()
    {
        return [
            // 12 hours (1-12)
            ['y-M-d h', '1970-1-1 1', 3600],
            ['y-M-d h', '1970-1-1 10', 36000],
            ['y-M-d hh', '1970-1-1 11', 39600],
            ['y-M-d hh', '1970-1-1 12', 0],
            ['y-M-d hh a', '1970-1-1 0 AM', 0],
            ['y-M-d hh a', '1970-1-1 1 AM', 3600],
            ['y-M-d hh a', '1970-1-1 10 AM', 36000],
            ['y-M-d hh a', '1970-1-1 11 AM', 39600],
            ['y-M-d hh a', '1970-1-1 12 AM', 0],
            ['y-M-d hh a', '1970-1-1 23 AM', 82800],
            ['y-M-d hh a', '1970-1-1 24 AM', 86400],
            ['y-M-d hh a', '1970-1-1 0 PM', 43200],
            ['y-M-d hh a', '1970-1-1 1 PM', 46800],
            ['y-M-d hh a', '1970-1-1 10 PM', 79200],
            ['y-M-d hh a', '1970-1-1 11 PM', 82800],
            ['y-M-d hh a', '1970-1-1 12 PM', 43200],
            ['y-M-d hh a', '1970-1-1 23 PM', 126000],
            ['y-M-d hh a', '1970-1-1 24 PM', 129600],
        ];
    }

    public function parseHour12ClockZeroBasedProvider()
    {
        return [
            // 12 hours (0-11)
            ['y-M-d K', '1970-1-1 1', 3600],
            ['y-M-d K', '1970-1-1 10', 36000],
            ['y-M-d KK', '1970-1-1 11', 39600],
            ['y-M-d KK', '1970-1-1 12', 43200],
            ['y-M-d KK a', '1970-1-1 0 AM', 0],
            ['y-M-d KK a', '1970-1-1 1 AM', 3600],
            ['y-M-d KK a', '1970-1-1 10 AM', 36000],
            ['y-M-d KK a', '1970-1-1 11 AM', 39600],
            ['y-M-d KK a', '1970-1-1 12 AM', 43200],
            ['y-M-d KK a', '1970-1-1 23 AM', 82800],
            ['y-M-d KK a', '1970-1-1 24 AM', 86400],
            ['y-M-d KK a', '1970-1-1 0 PM', 43200],
            ['y-M-d KK a', '1970-1-1 1 PM', 46800],
            ['y-M-d KK a', '1970-1-1 10 PM', 79200],
            ['y-M-d KK a', '1970-1-1 11 PM', 82800],
            ['y-M-d KK a', '1970-1-1 12 PM', 86400],
            ['y-M-d KK a', '1970-1-1 23 PM', 126000],
            ['y-M-d KK a', '1970-1-1 24 PM', 129600],
        ];
    }

    public function parseHour24ClockOneBasedProvider()
    {
        return [
            // 24 hours (1-24)
            ['y-M-d k', '1970-1-1 1', 3600],
            ['y-M-d k', '1970-1-1 10', 36000],
            ['y-M-d kk', '1970-1-1 11', 39600],
            ['y-M-d kk', '1970-1-1 12', 43200],
            ['y-M-d kk', '1970-1-1 23', 82800],
            ['y-M-d kk', '1970-1-1 24', 0],
            ['y-M-d kk a', '1970-1-1 0 AM', 0],
            ['y-M-d kk a', '1970-1-1 1 AM', 0],
            ['y-M-d kk a', '1970-1-1 10 AM', 0],
            ['y-M-d kk a', '1970-1-1 11 AM', 0],
            ['y-M-d kk a', '1970-1-1 12 AM', 0],
            ['y-M-d kk a', '1970-1-1 23 AM', 0],
            ['y-M-d kk a', '1970-1-1 24 AM', 0],
            ['y-M-d kk a', '1970-1-1 0 PM', 43200],
            ['y-M-d kk a', '1970-1-1 1 PM', 43200],
            ['y-M-d kk a', '1970-1-1 10 PM', 43200],
            ['y-M-d kk a', '1970-1-1 11 PM', 43200],
            ['y-M-d kk a', '1970-1-1 12 PM', 43200],
            ['y-M-d kk a', '1970-1-1 23 PM', 43200],
            ['y-M-d kk a', '1970-1-1 24 PM', 43200],
        ];
    }

    public function parseHour24ClockZeroBasedProvider()
    {
        return [
            // 24 hours (0-23)
            ['y-M-d H', '1970-1-1 0', 0],
            ['y-M-d H', '1970-1-1 1', 3600],
            ['y-M-d H', '1970-1-1 10', 36000],
            ['y-M-d HH', '1970-1-1 11', 39600],
            ['y-M-d HH', '1970-1-1 12', 43200],
            ['y-M-d HH', '1970-1-1 23', 82800],
            ['y-M-d HH a', '1970-1-1 0 AM', 0],
            ['y-M-d HH a', '1970-1-1 1 AM', 0],
            ['y-M-d HH a', '1970-1-1 10 AM', 0],
            ['y-M-d HH a', '1970-1-1 11 AM', 0],
            ['y-M-d HH a', '1970-1-1 12 AM', 0],
            ['y-M-d HH a', '1970-1-1 23 AM', 0],
            ['y-M-d HH a', '1970-1-1 24 AM', 0],
            ['y-M-d HH a', '1970-1-1 0 PM', 43200],
            ['y-M-d HH a', '1970-1-1 1 PM', 43200],
            ['y-M-d HH a', '1970-1-1 10 PM', 43200],
            ['y-M-d HH a', '1970-1-1 11 PM', 43200],
            ['y-M-d HH a', '1970-1-1 12 PM', 43200],
            ['y-M-d HH a', '1970-1-1 23 PM', 43200],
            ['y-M-d HH a', '1970-1-1 24 PM', 43200],
        ];
    }

    public function parseMinuteProvider()
    {
        return [
            ['y-M-d HH:m', '1970-1-1 0:1', 60],
            ['y-M-d HH:mm', '1970-1-1 0:10', 600],
        ];
    }

    public function parseSecondProvider()
    {
        return [
            ['y-M-d HH:mm:s', '1970-1-1 00:01:1', 61],
            ['y-M-d HH:mm:ss', '1970-1-1 00:01:10', 70],
        ];
    }

    public function parseTimezoneProvider()
    {
        return [
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-03:00', 10800],
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-04:00', 14400],
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-00:00', 0],
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT+03:00', -10800],
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT+04:00', -14400],
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-0300', 10800],
            ['y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT+0300', -10800],

            // a previous timezone parsing should not change the timezone for the next parsing
            ['y-M-d HH:mm:ss', '1970-1-1 00:00:00', 0],
        ];
    }

    public function parseAmPmProvider()
    {
        return [
            // AM/PM (already covered by hours tests)
            ['y-M-d HH:mm:ss a', '1970-1-1 00:00:00 AM', 0],
            ['y-M-d HH:mm:ss a', '1970-1-1 00:00:00 PM', 43200],
        ];
    }

    public function parseStandaloneAmPmProvider()
    {
        return [
            ['a', 'AM', 0],
            ['a', 'PM', 43200],
        ];
    }

    public function parseRegexMetaCharsProvider()
    {
        return [
            // regexp meta chars in the pattern string
            ['y[M-d', '1970[1-1', 0],
            ['y[M/d', '1970[1/1', 0],
        ];
    }

    public function parseQuoteCharsProvider()
    {
        return [
            ["'M'", 'M', 0],
            ["'yy'", 'yy', 0],
            ["'''yy'", "'yy", 0],
            ["''y", "'1970", 0],
            ["H 'o'' clock'", "0 o' clock", 0],
        ];
    }

    public function parseDashSlashProvider()
    {
        return [
            ['y-M-d', '1970/1/1', 0],
            ['yy-M-d', '70/1/1', 0],
            ['y/M/d', '1970-1-1', 0],
            ['yy/M/d', '70-1-1', 0],
        ];
    }

    /**
     * @dataProvider parseErrorProvider
     */
    public function testParseError($pattern, $value)
    {
        $errorCode = IntlGlobals::U_PARSE_ERROR;
        $errorMessage = 'Date parsing failed: U_PARSE_ERROR';

        $formatter = $this->getDefaultDateFormatter($pattern);
        $this->assertFalse($formatter->parse($value));
        $this->assertIsIntlFailure($formatter, $errorMessage, $errorCode);
    }

    public function parseErrorProvider()
    {
        return [
            // 1 char month
            ['y-MMMMM-d', '1970-J-1'],
            ['y-MMMMM-d', '1970-S-1'],

            // standalone 1 char month
            ['y-LLLLL-d', '1970-J-1'],
            ['y-LLLLL-d', '1970-S-1'],
        ];
    }

    /*
     * https://github.com/symfony/symfony/issues/4242
     */
    public function testParseAfterError()
    {
        $this->testParseError('y-MMMMM-d', '1970-J-1');
        $this->testParse('y-M-d', '1970-1-1', 0);
    }

    public function testParseWithNullPositionValue()
    {
        $position = null;
        $formatter = $this->getDefaultDateFormatter('y');
        $this->assertSame(0, $formatter->parse('1970', $position));
        // Since $position is not supported by the Symfony implementation, the following won't work.
        // The intl implementation works this way since 60.2.
        // $this->assertSame(4, $position);
    }

    public function testSetPattern()
    {
        $formatter = $this->getDefaultDateFormatter();
        $formatter->setPattern('yyyy-MM-dd');
        $this->assertEquals('yyyy-MM-dd', $formatter->getPattern());
    }

    /**
     * @dataProvider setTimeZoneIdProvider
     */
    public function testSetTimeZoneId($timeZoneId, $expectedTimeZoneId)
    {
        $formatter = $this->getDefaultDateFormatter();

        $formatter->setTimeZone($timeZoneId);

        $this->assertEquals($expectedTimeZoneId, $formatter->getTimeZoneId());
    }

    public function setTimeZoneIdProvider()
    {
        return [
            ['UTC', 'UTC'],
            ['GMT', 'GMT'],
            ['GMT-03:00', 'GMT-03:00'],
            ['Europe/Zurich', 'Europe/Zurich'],
            [null, date_default_timezone_get()],
            ['Foo/Bar', 'UTC'],
            ['GMT+00:AA', 'UTC'],
            ['GMT+00AA', 'UTC'],
        ];
    }

    protected function getDefaultDateFormatter($pattern = null)
    {
        return $this->getDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, $pattern);
    }

    protected function getDateTime($timestamp, $timeZone)
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp(null === $timestamp ? time() : $timestamp);
        $dateTime->setTimezone(new \DateTimeZone($timeZone ?: getenv('TZ') ?: 'UTC'));

        return $dateTime;
    }

    protected function assertIsIntlFailure($formatter, $errorMessage, $errorCode)
    {
        $this->assertSame($errorMessage, $this->getIntlErrorMessage());
        $this->assertSame($errorCode, $this->getIntlErrorCode());
        $this->assertTrue($this->isIntlFailure($this->getIntlErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertTrue($this->isIntlFailure($formatter->getErrorCode()));
    }

    protected function assertIsIntlSuccess($formatter, $errorMessage, $errorCode)
    {
        /* @var IntlDateFormatter $formatter */
        $this->assertSame($errorMessage, $this->getIntlErrorMessage());
        $this->assertSame($errorCode, $this->getIntlErrorCode());
        $this->assertFalse($this->isIntlFailure($this->getIntlErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertFalse($this->isIntlFailure($formatter->getErrorCode()));
    }

    /**
     * @param $locale
     * @param $datetype
     * @param $timetype
     * @param null $timezone
     * @param int  $calendar
     * @param null $pattern
     *
     * @return mixed
     */
    abstract protected function getDateFormatter($locale, $datetype, $timetype, $timezone = null, $calendar = IntlDateFormatter::GREGORIAN, $pattern = null);

    /**
     * @return string
     */
    abstract protected function getIntlErrorMessage();

    /**
     * @return int
     */
    abstract protected function getIntlErrorCode();

    /**
     * @param int $errorCode
     *
     * @return bool
     */
    abstract protected function isIntlFailure($errorCode);
}
