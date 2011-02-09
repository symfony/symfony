<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Locale\Stub;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\Stub\StubIntlDateFormatter;

class StubIntlDateFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function formatProvider()
    {
        return array(
            /* general */
            array('y-M-d', 0, '1970-1-1'),
            array("yyyy.MM.dd G 'at' HH:mm:ss zzz", 0, '1970.01.01 AD at 00:00:00 GMT+00:00'),

            /* escaping */
            array("'M'", 0, 'M'),
            array("'yy'", 0, 'yy'),
            array("'''yy'", 0, "'yy"),
            array("''y", 0, "'1970"),
            array("''yy", 0, "'70"),

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

            /* era */
            array('G', 0, 'AD'),
            array('G', -62167222800, 'BC'),

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

            /* timezone */
            array('z', 0, 'GMT+00:00'),
            array('zz', 0, 'GMT+00:00'),
            array('zzz', 0, 'GMT+00:00'),
            array('zzzz', 0, 'GMT+00:00'),
            array('zzzzz', 0, 'GMT+00:00'),
        );
    }

    public function formatWithTimezoneProvider()
    {
        return array(
            array(0, 'UTC', '1970-01-01 00:00:00'),
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
            array(0, 'Australia/Melbourne', '1970-01-01 10:00:00'),
            array(0, 'Europe/Berlin', '1970-01-01 01:00:00'),
            array(0, 'Europe/Dublin', '1970-01-01 01:00:00'),
            array(0, 'Europe/Warsaw', '1970-01-01 01:00:00'),
            array(0, 'Pacific/Fiji', '1970-01-01 12:00:00'),

            array(0, 'Foo/Bar', '1970-01-01 00:00:00'),
        );
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        $formatter = new StubIntlDateFormatter('pt_BR', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
    }

    public function testConstructor()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, 'Y-M-d');
        $this->assertEquals('Y-M-d', $formatter->getPattern());
    }

    /**
    * @dataProvider formatProvider
    */
    public function testFormat($pattern, $timestamp, $expected)
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, $pattern);
        $this->assertSame($expected, $formatter->format($timestamp), 'Check date format with stub implementation.');

        if (extension_loaded('intl')) {
            $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC', \IntlDateFormatter::GREGORIAN, $pattern);
            $this->assertSame($expected, $formatter->format($timestamp), 'Check date format with intl extension.');
        }
    }

    /**
    * @dataProvider formatWithTimezoneProvider
    */
    public function testFormatWithTimezone($timestamp, $timezone, $expected)
    {
        $pattern = 'yyyy-MM-dd HH:mm:ss';

        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, $timezone, StubIntlDateFormatter::GREGORIAN, $pattern);
        $this->assertSame($expected, $formatter->format($timestamp), 'Check date format with stub implementation.');

        if (extension_loaded('intl')) {
            $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $timezone, \IntlDateFormatter::GREGORIAN, $pattern);
            $this->assertSame($expected, $formatter->format($timestamp), 'Check date format with intl extension.');
        }
    }

    /**
     * @expectedException RuntimeException
     */
    public function testGetCalendar()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC');
        $formatter->getCalendar();
    }
}
