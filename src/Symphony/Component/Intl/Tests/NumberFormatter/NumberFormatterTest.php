<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Intl\Tests\NumberFormatter;

use Symphony\Component\Intl\Globals\IntlGlobals;
use Symphony\Component\Intl\NumberFormatter\NumberFormatter;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        new NumberFormatter('pt_BR');
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedStyle()
    {
        new NumberFormatter('en', NumberFormatter::PATTERN_DECIMAL);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentNotImplementedException
     */
    public function testConstructorWithPatternDifferentThanNull()
    {
        new NumberFormatter('en', NumberFormatter::DECIMAL, '');
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetAttributeWithUnsupportedAttribute()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::LENIENT_PARSE, null);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetAttributeInvalidRoundingMode()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, null);
    }

    public function testConstructWithoutLocale()
    {
        $this->assertInstanceOf(
            '\Symphony\Component\Intl\NumberFormatter\NumberFormatter',
            $this->getNumberFormatter(null, NumberFormatter::DECIMAL)
        );
    }

    public function testCreate()
    {
        $this->assertInstanceOf(
            '\Symphony\Component\Intl\NumberFormatter\NumberFormatter',
            NumberFormatter::create('en', NumberFormatter::DECIMAL)
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
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeInt32($formatter, $value, $expected, $message = '')
    {
        parent::testFormatTypeInt32($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt32WithCurrencyStyleProvider
     * @expectedException \Symphony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message = '')
    {
        parent::testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt64Provider
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeInt64($formatter, $value, $expected)
    {
        parent::testFormatTypeInt64($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeInt64WithCurrencyStyleProvider
     * @expectedException \Symphony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected)
    {
        parent::testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleProvider
     * @expectedException \Symphony\Component\Intl\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeDouble($formatter, $value, $expected)
    {
        parent::testFormatTypeDouble($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleWithCurrencyStyleProvider
     * @expectedException \Symphony\Component\Intl\Exception\NotImplementedException
     */
    public function testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected)
    {
        parent::testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testGetPattern()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->getPattern();
    }

    public function testGetErrorCode()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $this->assertEquals(IntlGlobals::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testParseCurrency()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->parseCurrency(null, $currency);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetPattern()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setPattern(null);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetSymbol()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setSymbol(null, null);
    }

    /**
     * @expectedException \Symphony\Component\Intl\Exception\MethodNotImplementedException
     */
    public function testSetTextAttribute()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setTextAttribute(null, null);
    }

    protected function getNumberFormatter($locale = 'en', $style = null, $pattern = null)
    {
        return new NumberFormatter($locale, $style, $pattern);
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
}
