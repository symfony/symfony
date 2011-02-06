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
            array('y-M-d', 0, '1970-1-1'),

            /* escaping */
            array("'M", 0, 'M'),
            array("'yy", 0, 'yy'),
            array("'''yy", 0, "'yy"),
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

            array('Q', 7776000, '2'),
            array('QQ', 7776000, '02'),
            array('QQQ', 7776000, 'Q2'),
            array('QQQQ', 7776000, '2nd quarter'),

            array('QQQQ', 15638400, '3rd quarter'),

            array('QQQQ', 23587200, '4th quarter'),

            /* hour */
            array('h', 0, '12'),
            array('hh', 0, '12'),
            array('hhh', 0, '012'),

            array('h', 1, '12'),
            array('h', 3600, '1'),
        );
    }

    /**
    * provides data for cases that are broken in icu/intl
    */
    public function brokenFormatProvider()
    {
        return array(
            /* escaping */
            array("'y-'M-'d", 0, 'y-M-d'),

            /* weird bugs */
            array("WTF 'y-'M", 0, '0T1 y-M'),
            array("n-'M", 0, 'n-M'),
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
    * @dataProvider brokenFormatProvider
    */
    public function testBrokenFormat($pattern, $timestamp, $expected)
    {
        $this->markTestSkipped('icu/intl has some bugs, thus skipping.');

        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, $pattern);
        $this->assertSame($expected, $formatter->format($timestamp), 'Check date format with stub implementation.');

        if (extension_loaded('intl')) {
            $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC', \IntlDateFormatter::GREGORIAN, $pattern);
            $this->assertSame($expected, $formatter->format($timestamp), 'Check date format with intl extension.');
        }
    }

    /**
     * @expectedException RuntimeException
     */
    public function testGetCalendar()
    {
        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT);
        $formatter->getCalendar();
    }
}
