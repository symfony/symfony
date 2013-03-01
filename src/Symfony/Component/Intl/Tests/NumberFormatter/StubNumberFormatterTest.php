<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\NumberFormatter;

use Symfony\Component\Intl\Globals\StubIntlGlobals;
use Symfony\Component\Intl\NumberFormatter\StubNumberFormatter;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class StubNumberFormatterTest extends AbstractNumberFormatterTest
{
    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        new StubNumberFormatter('pt_BR');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedStyle()
    {
        new StubNumberFormatter('en', StubNumberFormatter::PATTERN_DECIMAL);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException
     */
    public function testConstructorWithPatternDifferentThanNull()
    {
        new StubNumberFormatter('en', StubNumberFormatter::DECIMAL, '');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetAttributeWithUnsupportedAttribute()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->setAttribute(StubNumberFormatter::LENIENT_PARSE, null);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetAttributeInvalidRoundingMode()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->setAttribute(StubNumberFormatter::ROUNDING_MODE, null);
    }

    public function testCreate()
    {
        $this->assertInstanceOf(
            'Symfony\Component\Intl\NumberFormatter\StubNumberFormatter',
            StubNumberFormatter::create('en', StubNumberFormatter::DECIMAL)
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testFormatWithCurrencyStyle()
    {
        parent::testFormatWithCurrencyStyle();
    }

    /**
     * @dataProvider formatTypeInt32Provider
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeInt32($formatter, $value, $expected, $message = '')
    {
        parent::testFormatTypeInt32($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt32WithCurrencyStyleProvider
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message = '')
    {
        parent::testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt64Provider
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeInt64($formatter, $value, $expected)
    {
        parent::testFormatTypeInt64($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeInt64WithCurrencyStyleProvider
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected)
    {
        parent::testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleProvider
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeDouble($formatter, $value, $expected)
    {
        parent::testFormatTypeDouble($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleWithCurrencyStyleProvider
     * @expectedException \Symfony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected)
    {
        parent::testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetPattern()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->getPattern();
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetSymbol()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->getSymbol(null);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetTextAttribute()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->getTextAttribute(null);
    }

    public function testGetErrorCode()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $this->assertEquals(StubIntlGlobals::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testParseCurrency()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->parseCurrency(null, $currency);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException
     */
    public function testParseWithNotNullPositionValue()
    {
        parent::testParseWithNotNullPositionValue();
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetPattern()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->setPattern(null);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetSymbol()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->setSymbol(null, null);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetTextAttribute()
    {
        $formatter = $this->getNumberFormatter('en', StubNumberFormatter::DECIMAL);
        $formatter->setTextAttribute(null, null);
    }

    protected function getNumberFormatter($locale = 'en', $style = null, $pattern = null)
    {
        return new StubNumberFormatter($locale, $style, $pattern);
    }

    protected function getIntlErrorMessage()
    {
        return StubIntlGlobals::getErrorMessage();
    }

    protected function getIntlErrorCode()
    {
        return StubIntlGlobals::getErrorCode();
    }

    protected function isIntlFailure($errorCode)
    {
        return StubIntlGlobals::isFailure($errorCode);
    }
}
