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

            /* months */
            array('M', 0, '1'),
            array('MM', 0, '01'),
            array('MMM', 0, 'Jan'),
            array('MMMM', 0, 'January'),
            array('MMMMM', 0, 'J'),
            /* this is stupid */
            array('MMMMMM', 0, '00001'),

            /* years */
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
        $this->assertEquals($expected, $formatter->format($timestamp), 'Check date format with stub implementation.');

        if (extension_loaded('intl')) {
            $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC', \IntlDateFormatter::GREGORIAN, $pattern);
            $this->assertEquals($expected, $formatter->format($timestamp), 'Check date format with intl extension.');
        }
    }

    /**
    * @dataProvider brokenFormatProvider
    */
    public function testBrokenFormat($pattern, $timestamp, $expected)
    {
        $this->markTestSkipped('icu/intl has some bugs, thus skipping.');

        $formatter = new StubIntlDateFormatter('en', StubIntlDateFormatter::MEDIUM, StubIntlDateFormatter::SHORT, 'UTC', StubIntlDateFormatter::GREGORIAN, $pattern);
        $this->assertEquals($expected, $formatter->format($timestamp), 'Check date format with stub implementation.');

        if (extension_loaded('intl')) {
            $formatter = new \IntlDateFormatter('en', \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, 'UTC', \IntlDateFormatter::GREGORIAN, $pattern);
            $this->assertEquals($expected, $formatter->format($timestamp), 'Check date format with intl extension.');
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
