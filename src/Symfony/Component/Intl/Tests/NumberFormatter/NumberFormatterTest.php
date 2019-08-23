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

use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\NumberFormatter\NumberFormatter;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    public function testConstructorWithUnsupportedLocale()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        $this->getNumberFormatter('pt_BR');
    }

    public function testConstructorWithUnsupportedStyle()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        $this->getNumberFormatter('en', NumberFormatter::PATTERN_DECIMAL);
    }

    public function testConstructorWithPatternDifferentThanNull()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException');
        $this->getNumberFormatter('en', NumberFormatter::DECIMAL, '');
    }

    public function testSetAttributeWithUnsupportedAttribute()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::LENIENT_PARSE, 100);
    }

    public function testSetAttributeInvalidRoundingMode()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, -1);
    }

    public function testConstructWithoutLocale()
    {
        $this->assertInstanceOf(
            '\Symfony\Component\Intl\NumberFormatter\NumberFormatter',
            $this->getNumberFormatter(null, NumberFormatter::DECIMAL)
        );
    }

    public function testCreate()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $this->assertInstanceOf(NumberFormatter::class, $formatter::create('en', NumberFormatter::DECIMAL));
    }

    public function testFormatWithCurrencyStyle()
    {
        $this->expectException('RuntimeException');
        parent::testFormatWithCurrencyStyle();
    }

    /**
     * @dataProvider formatTypeInt32Provider
     */
    public function testFormatTypeInt32($formatter, $value, $expected, $message = '')
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        parent::testFormatTypeInt32($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt32WithCurrencyStyleProvider
     */
    public function testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message = '')
    {
        $this->expectException('Symfony\Component\Intl\Exception\NotImplementedException');
        parent::testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt64Provider
     */
    public function testFormatTypeInt64($formatter, $value, $expected)
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        parent::testFormatTypeInt64($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeInt64WithCurrencyStyleProvider
     */
    public function testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected)
    {
        $this->expectException('Symfony\Component\Intl\Exception\NotImplementedException');
        parent::testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleProvider
     */
    public function testFormatTypeDouble($formatter, $value, $expected)
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException');
        parent::testFormatTypeDouble($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleWithCurrencyStyleProvider
     */
    public function testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected)
    {
        $this->expectException('Symfony\Component\Intl\Exception\NotImplementedException');
        parent::testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected);
    }

    public function testGetPattern()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->getPattern();
    }

    public function testGetErrorCode()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $this->assertEquals(IntlGlobals::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    public function testParseCurrency()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $currency = 'USD';
        $formatter->parseCurrency(3, $currency);
    }

    public function testSetPattern()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setPattern('#0');
    }

    public function testSetSymbol()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL, '*');
    }

    public function testSetTextAttribute()
    {
        $this->expectException('Symfony\Component\Intl\Exception\MethodNotImplementedException');
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setTextAttribute(NumberFormatter::NEGATIVE_PREFIX, '-');
    }

    protected function getNumberFormatter(?string $locale = 'en', string $style = null, string $pattern = null): NumberFormatter
    {
        return new class($locale, $style, $pattern) extends NumberFormatter {
        };
    }

    protected function getIntlErrorMessage(): string
    {
        return IntlGlobals::getErrorMessage();
    }

    protected function getIntlErrorCode(): int
    {
        return IntlGlobals::getErrorCode();
    }

    protected function isIntlFailure($errorCode): bool
    {
        return IntlGlobals::isFailure($errorCode);
    }
}
