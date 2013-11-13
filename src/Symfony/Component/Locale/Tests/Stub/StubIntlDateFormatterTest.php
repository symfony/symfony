<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Tests\Stub;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Stub\StubIntl;
use Symfony\Component\Locale\Stub\StubIntlDateFormatter;
use Symfony\Component\Locale\Tests\TestCase as LocaleTestCase;

class StubIntlDateFormatterTest extends LocaleTestCase
{
    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        $formatter = new StubIntlDateFormatter('pt_BR', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
    }

    public function testConstructor()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    /**
     * When a time zone is not specified, it uses the system default however it returns null in the getter method
     * @covers Symfony\Component\Locale\Stub\StubIntlDateFormatter::getTimeZoneId
     * @covers Symfony\Component\Locale\Stub\StubIntlDateFormatter::setTimeZoneId
     * @see StubIntlDateFormatterTest::testDefaultTimeZoneIntl()
     */
    public function testConstructorDefaultTimeZoneStub()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);

        // In PHP 5.5 default timezone depends on `date_default_timezone_get()` method
        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $this->assertEquals(date_default_timezone_get(), $formatter->getTimeZoneId());
        } else {
            $this->assertNull($formatter->getTimeZoneId());
        }
    }

    public function testConstructorDefaultTimeZoneIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = new \IntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);

        // In PHP 5.5 default timezone depends on `date_default_timezone_get()` method
        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $this->assertEquals(date_default_timezone_get(), $formatter->getTimeZoneId());
        } else {
            $this->assertNull($formatter->getTimeZoneId());
        }
    }

    public function testFormatWithUnsupportedTimestampArgument()
    {
        $formatter = $this->createStubFormatter();

        $localtime = array(
            'tm_sec'   => 59,
            'tm_min'   => 3,
            'tm_hour'  => 15,
            'tm_mday'  => 15,
            'tm_mon'   => 3,
            'tm_year'  => 112,
            'tm_wday'  => 0,
            'tm_yday'  => 105,
            'tm_isdst' => 0
        );

        try {
            $formatter->format($localtime);
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException', $e);

            if ($this->isGreaterOrEqualThanPhpVersion('5.3.4')) {
                $this->assertStringEndsWith('Only integer unix timestamps and DateTime objects are supported.  Please install the \'intl\' extension for full localization capabilities.', $e->getMessage());
            } else {
                $this->assertStringEndsWith('Only integer unix timestamps are supported.  Please install the \'intl\' extension for full localization capabilities.', $e->getMessage());
            }
        }
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFormatStub($pattern, $timestamp, $expected)
    {
        $errorCode = StubIntl::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->createStubFormatter($pattern);
        $this->assertSame($expected, $formatter->format($timestamp));
        $this->assertSame($errorMessage, StubIntl::getErrorMessage());
        $this->assertSame($errorCode, StubIntl::getErrorCode());
        $this->assertFalse(StubIntl::isFailure(StubIntl::getErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertFalse(StubIntl::isFailure($formatter->getErrorCode()));
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFormatIntl($pattern, $timestamp, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();

        $errorCode = StubIntl::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->createIntlFormatter($pattern);
        $this->assertSame($expected, $formatter->format($timestamp));
        $this->assertSame($errorMessage, intl_get_error_message());
        $this->assertSame($errorCode, intl_get_error_code());
        $this->assertFalse(intl_is_failure(intl_get_error_code()));
    }

    public function formatProvider()
    {
        $formatData = array(
            /* general */
            array('y-M-d', 0, '1970-1-1'),
            array("EEE, MMM d, ''yy", 0, "Thu, Jan 1, '70"),
            array('h:mm a', 0, '12:00 AM'),
            array('yyyyy.MMMM.dd hh:mm aaa', 0, '01970.January.01 12:00 AM'),

            /* escaping */
            array("'M'", 0, 'M'),
            array("'yy'", 0, 'yy'),
            array("'''yy'", 0, "'yy"),
            array("''y", 0, "'1970"),
            array("''yy", 0, "'70"),
            array("H 'o'' clock'", 0, "0 o' clock"),

            /* month */
            array('M', 0, '1'),
            array('MM', 0, '01'),
            array('MMM', 0, 'Jan'),
            array('MMMM', 0, 'January'),
            array('MMMMM', 0, 'J'),
            array('MMMMMM', 0, '000001'),

            array('L', 0, '1'),
            array('LL', 0, '01'),
            array('LLL', 0, 'Jan'),
            array('LLLL', 0, 'January'),
            array('LLLLL', 0, 'J'),
            array('LLLLLL', 0, '000001'),

            /* year */
            array('y', 0, '1970'),
            array('yy', 0, '70'),
            array('yyy', 0, '1970'),
            array('yyyy', 0, '1970'),
            array('yyyyy', 0, '01970'),
            array('yyyyyy', 0, '001970'),

            /* day */
            array('d', 0, '1'),
            array('dd', 0, '01'),
            array('ddd', 0, '001'),

            /* quarter */
            array('Q', 0, '1'),
            array('QQ', 0, '01'),
            array('QQQ', 0, 'Q1'),
            array('QQQQ', 0, '1st quarter'),
            array('QQQQQ', 0, '1st quarter'),

            array('q', 0, '1'),
            array('qq', 0, '01'),
            array('qqq', 0, 'Q1'),
            array('qqqq', 0, '1st quarter'),
            array('qqqqq', 0, '1st quarter'),

            // 4 months
            array('Q', 7776000, '2'),
            array('QQ', 7776000, '02'),
            array('QQQ', 7776000, 'Q2'),
            array('QQQQ', 7776000, '2nd quarter'),

            // 7 months
            array('QQQQ', 15638400, '3rd quarter'),

            // 10 months
            array('QQQQ', 23587200, '4th quarter'),

            /* 12-hour (1-12) */
            array('h', 0, '12'),
            array('hh', 0, '12'),
            array('hhh', 0, '012'),

            array('h', 1, '12'),
            array('h', 3600, '1'),
            array('h', 43200, '12'), // 12 hours

            /* day of year */
            array('D', 0, '1'),
            array('D', 86400, '2'), // 1 day
            array('D', 31536000, '1'), // 1 year
            array('D', 31622400, '2'), // 1 year + 1 day

            /* day of week */
            array('E', 0, 'Thu'),
            array('EE', 0, 'Thu'),
            array('EEE', 0, 'Thu'),
            array('EEEE', 0, 'Thursday'),
            array('EEEEE', 0, 'T'),
            array('EEEEEE', 0, 'Thu'),

            array('E', 1296540000, 'Tue'), // 2011-02-01
            array('E', 1296950400, 'Sun'), // 2011-02-06

            /* am/pm marker */
            array('a', 0, 'AM'),
            array('aa', 0, 'AM'),
            array('aaa', 0, 'AM'),
            array('aaaa', 0, 'AM'),

            // 12 hours
            array('a', 43200, 'PM'),
            array('aa', 43200, 'PM'),
            array('aaa', 43200, 'PM'),
            array('aaaa', 43200, 'PM'),

            /* 24-hour (0-23) */
            array('H', 0, '0'),
            array('HH', 0, '00'),
            array('HHH', 0, '000'),

            array('H', 1, '0'),
            array('H', 3600, '1'),
            array('H', 43200, '12'),
            array('H', 46800, '13'),

            /* 24-hour (1-24) */
            array('k', 0, '24'),
            array('kk', 0, '24'),
            array('kkk', 0, '024'),

            array('k', 1, '24'),
            array('k', 3600, '1'),
            array('k', 43200, '12'),
            array('k', 46800, '13'),

            /* 12-hour (0-11) */
            array('K', 0, '0'),
            array('KK', 0, '00'),
            array('KKK', 0, '000'),

            array('K', 1, '0'),
            array('K', 3600, '1'),
            array('K', 43200, '0'), // 12 hours

            /* minute */
            array('m', 0, '0'),
            array('mm', 0, '00'),
            array('mmm', 0, '000'),

            array('m', 1, '0'),
            array('m', 60, '1'),
            array('m', 120, '2'),
            array('m', 180, '3'),
            array('m', 3600, '0'),
            array('m', 3660, '1'),
            array('m', 43200, '0'), // 12 hours

            /* second */
            array('s', 0, '0'),
            array('ss', 0, '00'),
            array('sss', 0, '000'),

            array('s', 1, '1'),
            array('s', 2, '2'),
            array('s', 5, '5'),
            array('s', 30, '30'),
            array('s', 59, '59'),
            array('s', 60, '0'),
            array('s', 120, '0'),
            array('s', 180, '0'),
            array('s', 3600, '0'),
            array('s', 3601, '1'),
            array('s', 3630, '30'),
            array('s', 43200, '0'), // 12 hours
        );

        // Timezone
        if ($this->isIntlExtensionLoaded() && $this->isGreaterOrEqualThanIcuVersion('4.8')) {
            // general
            $formatData[] = array("yyyy.MM.dd 'at' HH:mm:ss zzz", 0, '1970.01.01 at 00:00:00 GMT');
            $formatData[] = array('K:mm a, z', 0, '0:00 AM, GMT');

            // timezone
            $formatData[] = array('z', 0, 'GMT');
            $formatData[] = array('zz', 0, 'GMT');
            $formatData[] = array('zzz', 0, 'GMT');
            $formatData[] = array('zzzz', 0, 'GMT');
            $formatData[] = array('zzzzz', 0, 'GMT');
        }

        // As of PHP 5.3.4, IntlDateFormatter::format() accepts DateTime instances
        if ($this->isGreaterOrEqualThanPhpVersion('5.3.4')) {
            $dateTime = new \DateTime('@0');

            /* general, DateTime */
            $formatData[] = array('y-M-d', $dateTime, '1970-1-1');
            $formatData[] = array("EEE, MMM d, ''yy", $dateTime, "Thu, Jan 1, '70");
            $formatData[] = array('h:mm a', $dateTime, '12:00 AM');
            $formatData[] = array('yyyyy.MMMM.dd hh:mm aaa', $dateTime, '01970.January.01 12:00 AM');

            if ($this->isIntlExtensionLoaded() && $this->isGreaterOrEqualThanIcuVersion('4.8')) {
                $formatData[] = array("yyyy.MM.dd 'at' HH:mm:ss zzz", $dateTime, '1970.01.01 at 00:00:00 GMT');
                $formatData[] = array('K:mm a, z', $dateTime, '0:00 AM, GMT');
            }
        }

        return $formatData;
    }

    /**
     * @dataProvider formatErrorProvider
     */
    public function testFormatIllegalArgumentErrorStub($pattern, $timestamp, $errorMessage)
    {
        $errorCode = StubIntl::U_ILLEGAL_ARGUMENT_ERROR;

        $formatter = $this->createStubFormatter($pattern);
        $this->assertFalse($formatter->format($timestamp));
        $this->assertSame($errorMessage, StubIntl::getErrorMessage());
        $this->assertSame($errorCode, StubIntl::getErrorCode());
        $this->assertTrue(StubIntl::isFailure(StubIntl::getErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertTrue(StubIntl::isFailure($formatter->getErrorCode()));
    }

    /**
     * @dataProvider formatErrorProvider
     */
    public function testFormatIllegalArgumentErrorIntl($pattern, $timestamp, $errorMessage)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();

        $errorCode = StubIntl::U_ILLEGAL_ARGUMENT_ERROR;

        $formatter = $this->createIntlFormatter($pattern);
        $this->assertFalse($formatter->format($timestamp));
        $this->assertSame($errorMessage, intl_get_error_message());
        $this->assertSame($errorCode, intl_get_error_code());
        $this->assertTrue(intl_is_failure(intl_get_error_code()));
    }

    public function formatErrorProvider()
    {
        // With PHP 5.5 IntlDateFormatter accepts empty values ('0')
        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            return array(
                array('y-M-d', 'foobar', 'datefmt_format: string \'foobar\' is not numeric, which would be required for it to be a valid date: U_ILLEGAL_ARGUMENT_ERROR')
            );
        }

        $message = 'datefmt_format: takes either an array  or an integer timestamp value : U_ILLEGAL_ARGUMENT_ERROR';

        if ($this->isGreaterOrEqualThanPhpVersion('5.3.4')) {
            $message = 'datefmt_format: takes either an array or an integer timestamp value or a DateTime object: U_ILLEGAL_ARGUMENT_ERROR';
        }

        return array(
            array('y-M-d', '0', $message),
            array('y-M-d', 'foobar', $message),
        );
    }

    /**
     * @dataProvider formatWithTimezoneProvider
     */
    public function testFormatWithTimezoneStub($timestamp, $timezone, $expected)
    {
        $pattern = 'yyyy-MM-dd HH:mm:ss';
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, $timezone, StubIntlDateFormatter::GREGORIAN, $pattern);
        $this->assertSame($expected, $formatter->format($timestamp));
    }

    /**
     * @dataProvider formatWithTimezoneProvider
     */
    public function testFormatWithTimezoneIntl($timestamp, $timezone, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $pattern = 'yyyy-MM-dd HH:mm:ss';
        $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $timezone, \IntlDateFormatter::GREGORIAN, $pattern);
        $this->assertSame($expected, $formatter->format($timestamp));
    }

    public function formatWithTimezoneProvider()
    {
        $data = array(
            array(0, 'UTC', '1970-01-01 00:00:00'),
            array(0, 'GMT', '1970-01-01 00:00:00'),
            array(0, 'GMT-03:00', '1969-12-31 21:00:00'),
            array(0, 'GMT+03:00', '1970-01-01 03:00:00'),
            array(0, 'Europe/Zurich', '1970-01-01 01:00:00'),
            array(0, 'Europe/Paris', '1970-01-01 01:00:00'),
            array(0, 'Africa/Cairo', '1970-01-01 02:00:00'),
            array(0, 'Africa/Casablanca', '1970-01-01 00:00:00'),
            array(0, 'Africa/Djibouti', '1970-01-01 03:00:00'),
            array(0, 'Africa/Johannesburg', '1970-01-01 02:00:00'),
            array(0, 'America/Antigua', '1969-12-31 20:00:00'),
            array(0, 'America/Toronto', '1969-12-31 19:00:00'),
            array(0, 'America/Vancouver', '1969-12-31 16:00:00'),
            array(0, 'Asia/Aqtau', '1970-01-01 05:00:00'),
            array(0, 'Asia/Bangkok', '1970-01-01 07:00:00'),
            array(0, 'Asia/Dubai', '1970-01-01 04:00:00'),
            array(0, 'Australia/Brisbane', '1970-01-01 10:00:00'),
            array(0, 'Australia/Eucla', '1970-01-01 08:45:00'),
            array(0, 'Australia/Melbourne', '1970-01-01 10:00:00'),
            array(0, 'Europe/Berlin', '1970-01-01 01:00:00'),
            array(0, 'Europe/Dublin', '1970-01-01 01:00:00'),
            array(0, 'Europe/Warsaw', '1970-01-01 01:00:00'),
            array(0, 'Pacific/Fiji', '1970-01-01 12:00:00'),
        );

        // As of PHP 5.5, intl ext no longer fallbacks invalid time zones to UTC
        if (!$this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            // When time zone not exists, uses UTC by default
            $data[] = array(0, 'Foo/Bar', '1970-01-01 00:00:00');
            $data[] = array(0, 'UTC+04:30', '1970-01-01 00:00:00');
            $data[] = array(0, 'UTC+04:AA', '1970-01-01 00:00:00');
        }

        return $data;
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\NotImplementedException
     */
    public function testFormatWithTimezoneFormatOptionAndDifferentThanUtcStub()
    {
        $formatter = $this->createStubFormatter('zzzz');

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone('Pacific/Fiji');
        } else {
            $formatter->setTimeZoneId('Pacific/Fiji');
        }

        $formatter->format(0);
    }

    public function testFormatWithTimezoneFormatOptionAndDifferentThanUtcIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->createIntlFormatter('zzzz');

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone('Pacific/Fiji');
        } else {
            $formatter->setTimeZoneId('Pacific/Fiji');
        }

        $expected = $this->isGreaterOrEqualThanIcuVersion('49') ? 'Fiji Standard Time' : 'Fiji Time';
        $this->assertEquals($expected, $formatter->format(0));
    }

    public function testFormatWithGmtTimezoneStub()
    {
        $formatter = $this->createStubFormatter('zzzz');

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone('GMT+03:00');
        } else {
            $formatter->setTimeZoneId('GMT+03:00');
        }

        $this->assertEquals('GMT+03:00', $formatter->format(0));
    }

    public function testFormatWithGmtTimezoneIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->createIntlFormatter('zzzz');

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone('GMT+03:00');
        } else {
            $formatter->setTimeZoneId('GMT+03:00');
        }

        $this->assertEquals('GMT+03:00', $formatter->format(0));
    }

    public function testFormatWithConstructorTimezoneStub()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC');
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals(
            $this->createDateTime(0, 'UTC')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );
    }

    public function testFormatWithConstructorTimezoneIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();

        $formatter = new \IntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC');
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals(
            $this->createDateTime(0, 'UTC')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );
    }

    public function testFormatWithDefaultTimezoneStubShouldUseTheTzEnvironmentVariableWhenAvailable()
    {
        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $this->markTestSkipped('StubIntlDateFormatter in PHP 5.5 no longer depends on TZ environment.');
        }

        $tz = getenv('TZ');
        putenv('TZ=Europe/London');

        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals(
            $this->createDateTime(0, 'Europe/London')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );

        $this->assertEquals('Europe/London', getenv('TZ'));

        // Restores TZ.
        putenv('TZ='.$tz);
    }

    public function testFormatWithDefaultTimezoneStubShouldUseDefaultDateTimeZoneVariable()
    {
        if (!$this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $this->markTestSkipped('Only in PHP 5.5 StubIntlDateFormatter depends on default timezone (`date_default_timezone_get()`).');
        }

        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/London');

        $formatter = new \IntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals(
            $this->createDateTime(0, 'Europe/London')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );

        $this->assertEquals('Europe/London', date_default_timezone_get());

        // Restores TZ.
        date_default_timezone_set($tz);
    }

    /**
     * It seems IntlDateFormatter caches the timezone id when not explicitly set via constructor or by the
     * setTimeZoneId() method. Since testFormatWithDefaultTimezoneIntl() runs using the default environment
     * time zone, this test would use it too if not running in a separated process.
     *
     * @runInSeparateProcess
     */
    public function testFormatWithDefaultTimezoneIntlShouldUseTheTzEnvironmentVariableWhenAvailable()
    {
        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $this->markTestSkipped('IntlDateFormatter in PHP 5.5 no longer depends on TZ environment.');
        }

        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();

        $tz = getenv('TZ');
        putenv('TZ=Europe/Paris');

        $formatter = new \IntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals('Europe/Paris', getenv('TZ'));

        $this->assertEquals(
            $this->createDateTime(0, 'Europe/Paris')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );

        // Restores TZ.
        putenv('TZ='.$tz);
    }

    /**
     * @runInSeparateProcess
     */
    public function testFormatWithDefaultTimezoneIntlShouldUseDefaultDateTimeZoneVariable()
    {
        if (!$this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $this->markTestSkipped('Only in PHP 5.5 IntlDateFormatter depends on default timezone (`date_default_timezone_get()`).');
        }

        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();

        $tz = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        $formatter = new \IntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
        $formatter->setPattern('yyyy-MM-dd HH:mm:ss');

        $this->assertEquals('Europe/Paris', date_default_timezone_get());

        $this->assertEquals(
            $this->createDateTime(0, 'Europe/Paris')->format('Y-m-d H:i:s'),
            $formatter->format(0)
        );

        // Restores TZ.
        date_default_timezone_set($tz);
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\NotImplementedException
     */
    public function testFormatWithUnimplementedCharsStub()
    {
        $pattern = 'Y';
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, $pattern);
        $formatter->format(0);
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\NotImplementedException
     */
    public function testFormatWithNonIntegerTimestamp()
    {
        $formatter = $this->createStubFormatter();
        $formatter->format(array());
    }

    /**
    * @dataProvider dateAndTimeTypeProvider
    */
    public function testDateAndTimeTypeStub($timestamp, $datetype, $timetype, $expected)
    {
        $formatter = new StubIntlDateFormatter('en', $datetype, $timetype, 'UTC');
        $this->assertSame($expected, $formatter->format($timestamp));
    }

    /**
    * @dataProvider dateAndTimeTypeProvider
    */
    public function testDateAndTimeTypeIntl($timestamp, $datetype, $timetype, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = new \IntlDateFormatter('en', $datetype, $timetype, 'UTC');
        $this->assertSame($expected, $formatter->format($timestamp));
    }

    public function dateAndTimeTypeProvider()
    {
        $data = array(
            array(0, StubIntlDateFormatter::FULL, StubIntlDateFormatter::NONE, 'Thursday, January 1, 1970'),
            array(0, StubIntlDateFormatter::LONG, StubIntlDateFormatter::NONE, 'January 1, 1970'),
            array(0, StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::NONE, 'Jan 1, 1970'),
            array(0, StubIntlDateFormatter::SHORT, StubIntlDateFormatter::NONE, '1/1/70'),
        );

        if ($this->isIntlExtensionLoaded() && $this->isGreaterOrEqualThanIcuVersion('4.8')) {
            $data[] = array(0, StubIntlDateFormatter::NONE, StubIntlDateFormatter::FULL, '12:00:00 AM GMT');
            $data[] = array(0, StubIntlDateFormatter::NONE, StubIntlDateFormatter::LONG, '12:00:00 AM GMT');
        }

        $data[] = array(0, StubIntlDateFormatter::NONE, StubIntlDateFormatter::MEDIUM, '12:00:00 AM');
        $data[] = array(0, StubIntlDateFormatter::NONE, StubIntlDateFormatter::SHORT, '12:00 AM');

        return $data;
    }

    public function testGetCalendar()
    {
        $formatter = $this->createStubFormatter();
        $this->assertEquals(StubIntlDateFormatter::GREGORIAN, $formatter->getCalendar());
    }

    public function testGetDateType()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::FULL, StubIntlDateFormatter::NONE);
        $this->assertEquals(StubIntlDateFormatter::FULL, $formatter->getDateType());
    }

    public function testGetErrorCode()
    {
        $formatter = $this->createStubFormatter();
        $this->assertEquals(StubIntl::getErrorCode(), $formatter->getErrorCode());
    }

    public function testGetErrorMessage()
    {
        $formatter = $this->createStubFormatter();
        $this->assertEquals(StubIntl::getErrorMessage(), $formatter->getErrorMessage());
    }

    public function testGetLocale()
    {
        $formatter = $this->createStubFormatter();
        $this->assertEquals('en', $formatter->getLocale());
    }

    public function testGetPattern()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::FULL, StubIntlDateFormatter::NONE, 'UTC', StubIntlDateFormatter::GREGORIAN, 'yyyy-MM-dd');
        $this->assertEquals('yyyy-MM-dd', $formatter->getPattern());
    }

    public function testGetTimeType()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::NONE, StubIntlDateFormatter::FULL);
        $this->assertEquals(StubIntlDateFormatter::FULL, $formatter->getTimeType());
    }

    public function testIsLenient()
    {
        $formatter = $this->createStubFormatter();
        $this->assertFalse($formatter->isLenient());
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testLocaltime()
    {
        $formatter = $this->createStubFormatter();
        $formatter->localtime('Wednesday, December 31, 1969 4:00:00 PM PT');
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParseIntl($pattern, $value, $expected)
    {
        $errorCode = StubIntl::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->createIntlFormatter($pattern);
        $this->assertSame($expected, $formatter->parse($value));
        $this->assertSame($errorMessage, intl_get_error_message());
        $this->assertSame($errorCode, intl_get_error_code());
        $this->assertFalse(intl_is_failure(intl_get_error_code()));
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParseStub($pattern, $value, $expected)
    {
        $errorCode = StubIntl::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->createStubFormatter($pattern);
        $this->assertSame($expected, $formatter->parse($value));
        $this->assertSame($errorMessage, StubIntl::getErrorMessage());
        $this->assertSame($errorCode, StubIntl::getErrorCode());
        $this->assertFalse(StubIntl::isFailure(StubIntl::getErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertFalse(StubIntl::isFailure($formatter->getErrorCode()));
    }

    public function parseProvider()
    {
        $data = array(
            // years
            array('y-M-d', '1970-1-1', 0),
            array('yy-M-d', '70-1-1', 0),

            // months
            array('y-M-d', '1970-1-1', 0),
            array('y-MMM-d', '1970-Jan-1', 0),
            array('y-MMMM-d', '1970-January-1', 0),

            // standalone months
            array('y-L-d', '1970-1-1', 0),
            array('y-LLL-d', '1970-Jan-1', 0),
            array('y-LLLL-d', '1970-January-1', 0),

            // days
            array('y-M-d', '1970-1-1', 0),
            array('y-M-dd', '1970-1-01', 0),
            array('y-M-ddd', '1970-1-001', 0),

            // 12 hours (1-12)
            array('y-M-d h', '1970-1-1 1', 3600),
            array('y-M-d h', '1970-1-1 10', 36000),
            array('y-M-d hh', '1970-1-1 11', 39600),
            array('y-M-d hh', '1970-1-1 12', 0),
            array('y-M-d hh a', '1970-1-1 0 AM', 0),
            array('y-M-d hh a', '1970-1-1 1 AM', 3600),
            array('y-M-d hh a', '1970-1-1 10 AM', 36000),
            array('y-M-d hh a', '1970-1-1 11 AM', 39600),
            array('y-M-d hh a', '1970-1-1 12 AM', 0),
            array('y-M-d hh a', '1970-1-1 23 AM', 82800),
            array('y-M-d hh a', '1970-1-1 24 AM', 86400),
            array('y-M-d hh a', '1970-1-1 0 PM', 43200),
            array('y-M-d hh a', '1970-1-1 1 PM', 46800),
            array('y-M-d hh a', '1970-1-1 10 PM', 79200),
            array('y-M-d hh a', '1970-1-1 11 PM', 82800),
            array('y-M-d hh a', '1970-1-1 12 PM', 43200),
            array('y-M-d hh a', '1970-1-1 23 PM', 126000),
            array('y-M-d hh a', '1970-1-1 24 PM', 129600),

            // 12 hours (0-11)
            array('y-M-d K', '1970-1-1 1', 3600),
            array('y-M-d K', '1970-1-1 10', 36000),
            array('y-M-d KK', '1970-1-1 11', 39600),
            array('y-M-d KK', '1970-1-1 12', 43200),
            array('y-M-d KK a', '1970-1-1 0 AM', 0),
            array('y-M-d KK a', '1970-1-1 1 AM', 3600),
            array('y-M-d KK a', '1970-1-1 10 AM', 36000),
            array('y-M-d KK a', '1970-1-1 11 AM', 39600),
            array('y-M-d KK a', '1970-1-1 12 AM', 43200),
            array('y-M-d KK a', '1970-1-1 23 AM', 82800),
            array('y-M-d KK a', '1970-1-1 24 AM', 86400),
            array('y-M-d KK a', '1970-1-1 0 PM', 43200),
            array('y-M-d KK a', '1970-1-1 1 PM', 46800),
            array('y-M-d KK a', '1970-1-1 10 PM', 79200),
            array('y-M-d KK a', '1970-1-1 11 PM', 82800),
            array('y-M-d KK a', '1970-1-1 12 PM', 86400),
            array('y-M-d KK a', '1970-1-1 23 PM', 126000),
            array('y-M-d KK a', '1970-1-1 24 PM', 129600),

            // 24 hours (0-23)
            array('y-M-d H', '1970-1-1 0', 0),
            array('y-M-d H', '1970-1-1 1', 3600),
            array('y-M-d H', '1970-1-1 10', 36000),
            array('y-M-d HH', '1970-1-1 11', 39600),
            array('y-M-d HH', '1970-1-1 12', 43200),
            array('y-M-d HH', '1970-1-1 23', 82800),
            array('y-M-d HH a', '1970-1-1 0 AM', 0),
            array('y-M-d HH a', '1970-1-1 1 AM', 0),
            array('y-M-d HH a', '1970-1-1 10 AM', 0),
            array('y-M-d HH a', '1970-1-1 11 AM', 0),
            array('y-M-d HH a', '1970-1-1 12 AM', 0),
            array('y-M-d HH a', '1970-1-1 23 AM', 0),
            array('y-M-d HH a', '1970-1-1 24 AM', 0),
            array('y-M-d HH a', '1970-1-1 0 PM', 43200),
            array('y-M-d HH a', '1970-1-1 1 PM', 43200),
            array('y-M-d HH a', '1970-1-1 10 PM', 43200),
            array('y-M-d HH a', '1970-1-1 11 PM', 43200),
            array('y-M-d HH a', '1970-1-1 12 PM', 43200),
            array('y-M-d HH a', '1970-1-1 23 PM', 43200),
            array('y-M-d HH a', '1970-1-1 24 PM', 43200),

            // 24 hours (1-24)
            array('y-M-d k', '1970-1-1 1', 3600),
            array('y-M-d k', '1970-1-1 10', 36000),
            array('y-M-d kk', '1970-1-1 11', 39600),
            array('y-M-d kk', '1970-1-1 12', 43200),
            array('y-M-d kk', '1970-1-1 23', 82800),
            array('y-M-d kk', '1970-1-1 24', 0),
            array('y-M-d kk a', '1970-1-1 0 AM', 0),
            array('y-M-d kk a', '1970-1-1 1 AM', 0),
            array('y-M-d kk a', '1970-1-1 10 AM', 0),
            array('y-M-d kk a', '1970-1-1 11 AM', 0),
            array('y-M-d kk a', '1970-1-1 12 AM', 0),
            array('y-M-d kk a', '1970-1-1 23 AM', 0),
            array('y-M-d kk a', '1970-1-1 24 AM', 0),
            array('y-M-d kk a', '1970-1-1 0 PM', 43200),
            array('y-M-d kk a', '1970-1-1 1 PM', 43200),
            array('y-M-d kk a', '1970-1-1 10 PM', 43200),
            array('y-M-d kk a', '1970-1-1 11 PM', 43200),
            array('y-M-d kk a', '1970-1-1 12 PM', 43200),
            array('y-M-d kk a', '1970-1-1 23 PM', 43200),
            array('y-M-d kk a', '1970-1-1 24 PM', 43200),

            // minutes
            array('y-M-d HH:m', '1970-1-1 0:1', 60),
            array('y-M-d HH:mm', '1970-1-1 0:10', 600),

            // seconds
            array('y-M-d HH:mm:s', '1970-1-1 00:01:1', 61),
            array('y-M-d HH:mm:ss', '1970-1-1 00:01:10', 70),

            // timezone
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-03:00', 10800),
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-04:00', 14400),
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-00:00', 0),
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT+03:00', -10800),
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT+04:00', -14400),
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT-0300', 10800),
            array('y-M-d HH:mm:ss zzzz', '1970-1-1 00:00:00 GMT+0300', -10800),

            // a previous timezoned parsing should not change the timezone for the next parsing
            array('y-M-d HH:mm:ss', '1970-1-1 00:00:00', 0),

            // AM/PM (already covered by hours tests)
            array('y-M-d HH:mm:ss a', '1970-1-1 00:00:00 AM', 0),
            array('y-M-d HH:mm:ss a', '1970-1-1 00:00:00 PM', 43200),

            // regExp metachars in the pattern string
            array('y[M-d', '1970[1-1', 0),
            array('y[M/d', '1970[1/1', 0),

            // quote characters
            array("'M'", 'M', 0),
            array("'yy'", 'yy', 0),
            array("'''yy'", "'yy", 0),
            array("''y", "'1970", 0),
            array("H 'o'' clock'", "0 o' clock", 0),
        );

        if ($this->isIntlExtensionLoaded() && $this->isGreaterOrEqualThanIcuVersion('4.8')) {
            $data[] = array('y-M-d', '1970/1/1', 0);
            $data[] = array('yy-M-d', '70/1/1', 0);
            $data[] = array('y/M/d', '1970-1-1', 0);
            $data[] = array('yy/M/d', '70-1-1', 0);
        }

        return $data;
    }

    /**
     * @dataProvider parseErrorProvider
     */
    public function testParseErrorIntl($pattern, $value)
    {
        $errorCode = StubIntl::U_PARSE_ERROR;
        $errorMessage = 'Date parsing failed: U_PARSE_ERROR';

        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->createIntlFormatter($pattern);
        $this->assertFalse($formatter->parse($value));
        $this->assertSame($errorMessage, intl_get_error_message());
        $this->assertSame($errorCode, intl_get_error_code());
        $this->assertTrue(intl_is_failure(intl_get_error_code()));
    }

    /**
     * @dataProvider parseErrorProvider
     */
    public function testParseErrorStub($pattern, $value)
    {
        $errorCode = StubIntl::U_PARSE_ERROR;
        $errorMessage = 'Date parsing failed: U_PARSE_ERROR';

        $formatter = $this->createStubFormatter($pattern);
        $this->assertFalse($formatter->parse($value));
        $this->assertSame($errorMessage, StubIntl::getErrorMessage());
        $this->assertSame($errorCode, StubIntl::getErrorCode());
        $this->assertTrue(StubIntl::isFailure(StubIntl::getErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertTrue(StubIntl::isFailure($formatter->getErrorCode()));
    }

    public function parseErrorProvider()
    {
        $data = array(
            // 1 char month
            array('y-MMMMM-d', '1970-J-1'),
            array('y-MMMMM-d', '1970-S-1'),

            // standalone 1 char month
            array('y-LLLLL-d', '1970-J-1'),
            array('y-LLLLL-d', '1970-S-1'),
        );

        if (!$this->isIntlExtensionLoaded() || $this->isLowerThanIcuVersion('4.8')) {
            $data[] = array('y-M-d', '1970/1/1');
            $data[] = array('yy-M-d', '70/1/1');
        }

        return $data;
    }

    /*
     * https://github.com/symfony/symfony/issues/4242
     */
    public function testParseAfterErrorIntl()
    {
        $this->testParseErrorIntl('y-MMMMM-d', '1970-J-1');
        $this->testParseIntl('y-M-d', '1970-1-1', 0);
    }

    /*
     * https://github.com/symfony/symfony/issues/4242
     */
    public function testParseAfterErrorStub()
    {
        $this->testParseErrorStub('y-MMMMM-d', '1970-J-1');
        $this->testParseStub('y-M-d', '1970-1-1', 0);
    }

    /**
     * Just to document the differences between the stub and the intl implementations. The intl can parse
     * any of the tested formats alone. The stub does not implement them as it would be needed to add more
     * abstraction, passing more context to the transformers objects. Any of the formats are ignored alone
     * or with date/time data (years, months, days, hours, minutes and seconds).
     *
     * Also in intl, format like 'ss E' for '10 2' (2nd day of year + 10 seconds) are added, then we have
     * 86,400 seconds (24h * 60min * 60s) + 10 seconds
     *
     * @dataProvider parseDifferences()
     */
    public function testParseDifferencesStub($pattern, $value, $stubExpected, $intlExpected)
    {
        $formatter = $this->createStubFormatter($pattern);
        $this->assertSame($stubExpected, $formatter->parse($value));
    }

    /**
     * @dataProvider parseDifferences()
     */
    public function testParseDifferencesIntl($pattern, $value, $stubExpected, $intlExpected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();
        $formatter = $this->createIntlFormatter($pattern);
        $this->assertSame($intlExpected, $formatter->parse($value));
    }

    public function parseDifferences()
    {
        return array(
            // AM/PM, ignored if alone
            array('a', 'AM', 0, 0),
            array('a', 'PM', 0, 43200),

            // day of week
            array('E', 'Thu', 0, 0),
            array('EE', 'Thu', 0, 0),
            array('EEE', 'Thu', 0, 0),
            array('EEEE', 'Thursday', 0, 0),
            array('EEEEE', 'T', 0, 432000),
            array('EEEEEE', 'Thu', 0, 0),

            // day of year
            array('D', '1', 0, 0),
            array('D', '2', 0, 86400),

            // quarter
            array('Q', '1', 0, 0),
            array('QQ', '01', 0, 0),
            array('QQQ', 'Q1', 0, 0),
            array('QQQQ', '1st quarter', 0, 0),
            array('QQQQQ', '1st quarter', 0, 0),

            array('Q', '2', 0, 7776000),
            array('QQ', '02', 0, 7776000),
            array('QQQ', 'Q2', 0, 7776000),
            array('QQQQ', '2nd quarter', 0, 7776000),
            array('QQQQQ', '2nd quarter', 0, 7776000),

            array('q', '1', 0, 0),
            array('qq', '01', 0, 0),
            array('qqq', 'Q1', 0, 0),
            array('qqqq', '1st quarter', 0, 0),
            array('qqqqq', '1st quarter', 0, 0),
        );
    }

    public function testParseWithNullPositionValueStub()
    {
        $position = null;
        $formatter = $this->createStubFormatter('y');
        $this->assertSame(0, $formatter->parse('1970', $position));
        $this->assertNull($position);
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodArgumentNotImplementedException
     */
    public function testParseWithNotNullPositionValueStub()
    {
        $position = 0;
        $formatter = $this->createStubFormatter('y');
        $this->assertSame(0, $formatter->parse('1970', $position));
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testSetCalendar()
    {
        $formatter = $this->createStubFormatter();
        $formatter->setCalendar(StubIntlDateFormatter::GREGORIAN);
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetLenient()
    {
        $formatter = $this->createStubFormatter();
        $formatter->setLenient(true);
    }

    public function testSetPattern()
    {
        $formatter = $this->createStubFormatter();
        $formatter->setPattern('yyyy-MM-dd');
        $this->assertEquals('yyyy-MM-dd', $formatter->getPattern());
    }

    /**
     * @covers Symfony\Component\Locale\Stub\StubIntlDateFormatter::getTimeZoneId
     * @dataProvider setTimeZoneIdProvider()
     */
    public function testSetTimeZoneIdStub($timeZoneId, $expectedTimeZoneId)
    {
        $formatter = $this->createStubFormatter();

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone($timeZoneId);
        } else {
            $formatter->setTimeZoneId($timeZoneId);
        }

        $this->assertEquals($timeZoneId, $formatter->getTimeZoneId());
    }

    /**
     * @dataProvider setTimeZoneIdProvider()
     */
    public function testSetTimeZoneIdIntl($timeZoneId, $expectedTimeZoneId)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->createIntlFormatter();

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone($timeZoneId);
        } else {
            $formatter->setTimeZoneId($timeZoneId);
        }

        $this->assertEquals($expectedTimeZoneId, $formatter->getTimeZoneId());
    }

    public function setTimeZoneIdProvider()
    {
        $data = array(
            array('UTC', 'UTC'),
            array('GMT', 'GMT'),
            array('GMT-03:00', 'GMT-03:00'),
            array('Europe/Zurich', 'Europe/Zurich'),
        );

        // When time zone not exists, uses UTC by default
        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $data[] = array('GMT-0300', 'UTC');
            $data[] = array('Foo/Bar', 'UTC');
            $data[] = array('GMT+00:AA', 'UTC');
            $data[] = array('GMT+00AA', 'UTC');
        } else {
            $data[] = array('GMT-0300', 'GMT-0300');
            $data[] = array('Foo/Bar', 'Foo/Bar');
            $data[] = array('GMT+00:AA', 'GMT+00:AA');
            $data[] = array('GMT+00AA', 'GMT+00AA');
        }

        return $data;
    }

    /**
     * @expectedException \Symfony\Component\Locale\Exception\NotImplementedException
     */
    public function testSetTimeZoneIdWithGmtTimeZoneWithMinutesOffsetStub()
    {
        $formatter = $this->createStubFormatter();

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone('GMT+00:30');
        } else {
            $formatter->setTimeZoneId('GMT+00:30');
        }
    }

    public function testSetTimeZoneIdWithGmtTimeZoneWithMinutesOffsetIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->createIntlFormatter();

        if ($this->isGreaterOrEqualThanPhpVersion('5.5.0-dev')) {
            $formatter->setTimeZone('GMT+00:30');
        } else {
            $formatter->setTimeZoneId('GMT+00:30');
        }

        $this->assertEquals('GMT+00:30', $formatter->getTimeZoneId());
    }

    public function testStaticCreate()
    {
        $formatter = StubIntlDateFormatter::create('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
        $this->assertInstanceOf('Symfony\Component\Locale\Stub\StubIntlDateFormatter', $formatter);
    }

    protected function createStubFormatter($pattern = null)
    {
        return new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, $pattern);
    }

    protected function createIntlFormatter($pattern = null)
    {
        return new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC', \IntlDateFormatter::GREGORIAN, $pattern);
    }

    protected function createDateTime($timestamp, $timeZone)
    {

        $dateTime = new \DateTime();
        $dateTime->setTimestamp(null === $timestamp ? time() : $timestamp);
        $dateTime->setTimeZone(new \DateTimeZone($timeZone));

        return $dateTime;
    }
}
