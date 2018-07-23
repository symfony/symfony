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

use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Intl\Globals\IntlGlobals;

class IntlDateFormatterTest extends AbstractIntlDateFormatterTest
{
    public function testConstructor()
    {
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    public function testConstructorWithoutLocale()
    {
        $formatter = new IntlDateFormatter(null, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    public function testConstructorWithoutCalendar()
    {
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', null, 'y-M-d');
        $this->assertEquals('y-M-d', $formatter->getPattern());
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        new IntlDateFormatter('pt_BR', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
    }

    public function testStaticCreate()
    {
        $formatter = IntlDateFormatter::create('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT);
        $this->assertInstanceOf('\Symfony\Component\Intl\DateFormatter\IntlDateFormatter', $formatter);
    }

    public function testFormatWithUnsupportedTimestampArgument()
    {
        $formatter = $this->getDefaultDateFormatter();

        $localtime = array(
            'tm_sec' => 59,
            'tm_min' => 3,
            'tm_hour' => 15,
            'tm_mday' => 15,
            'tm_mon' => 3,
            'tm_year' => 112,
            'tm_wday' => 0,
            'tm_yday' => 105,
            'tm_isdst' => 0,
        );

        try {
            $formatter->format($localtime);
        } catch (\Exception $e) {
            $this->assertInstanceOf('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException', $e);

            $this->assertStringEndsWith('Only integer Unix timestamps and DateTime objects are supported.  Please install the "intl" extension for full localization capabilities.', $e->getMessage());
        }
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatWithUnimplementedChars()
    {
        $pattern = 'Y';
        $formatter = new IntlDateFormatter('en', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, 'UTC', IntlDateFormatter::GREGORIAN, $pattern);
        $formatter->format(0);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatWithNonIntegerTimestamp()
    {
        $formatter = $this->getDefaultDateFormatter();
        $formatter->format(array());
    }

    public function testGetErrorCode()
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertEquals(IntlGlobals::getErrorCode(), $formatter->getErrorCode());
    }

    public function testGetErrorMessage()
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertEquals(IntlGlobals::getErrorMessage(), $formatter->getErrorMessage());
    }

    public function testIsLenient()
    {
        $formatter = $this->getDefaultDateFormatter();
        $this->assertFalse($formatter->isLenient());
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testLocaltime()
    {
        $formatter = $this->getDefaultDateFormatter();
        $formatter->localtime('Wednesday, December 31, 1969 4:00:00 PM PT');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException
     */
    public function testParseWithNotNullPositionValue()
    {
        $position = 0;
        $formatter = $this->getDefaultDateFormatter('y');
        $this->assertSame(0, $formatter->parse('1970', $position));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetCalendar()
    {
        $formatter = $this->getDefaultDateFormatter();
        $formatter->setCalendar(IntlDateFormatter::GREGORIAN);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetLenient()
    {
        $formatter = $this->getDefaultDateFormatter();
        $formatter->setLenient(true);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatWithGmtTimeZoneAndMinutesOffset()
    {
        parent::testFormatWithGmtTimeZoneAndMinutesOffset();
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatWithNonStandardTimezone()
    {
        parent::testFormatWithNonStandardTimezone();
    }

    public function parseStandaloneAmPmProvider()
    {
        return $this->notImplemented(parent::parseStandaloneAmPmProvider());
    }

    public function parseDayOfWeekProvider()
    {
        return $this->notImplemented(parent::parseDayOfWeekProvider());
    }

    public function parseDayOfYearProvider()
    {
        return $this->notImplemented(parent::parseDayOfYearProvider());
    }

    public function parseQuarterProvider()
    {
        return $this->notImplemented(parent::parseQuarterProvider());
    }

    protected function getDateFormatter($locale, $datetype, $timetype, $timezone = null, $calendar = IntlDateFormatter::GREGORIAN, $pattern = null)
    {
        return new IntlDateFormatter($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
    }

    protected function getIntlErrorMessage()
    {
        return IntlGlobals::getErrorMessage();
    }

    protected function getIntlErrorCode()
    {
        return IntlGlobals::getErrorCode();
    }

    protected function isIntlFailure($errorCode)
    {
        return IntlGlobals::isFailure($errorCode);
    }

    /**
     * Just to document the differences between the stub and the intl
     * implementations. The intl can parse any of the tested formats alone. The
     * stub does not implement them as it would be needed to add more
     * abstraction, passing more context to the transformers objects. Any of the
     * formats are ignored alone or with date/time data (years, months, days,
     * hours, minutes and seconds).
     *
     * Also in intl, format like 'ss E' for '10 2' (2nd day of year
     * + 10 seconds) are added, then we have 86,400 seconds (24h * 60min * 60s)
     * + 10 seconds
     *
     * @return array
     */
    private function notImplemented(array $dataSets)
    {
        return array_map(function (array $row) {
            return array($row[0], $row[1], 0);
        }, $dataSets);
    }
}
