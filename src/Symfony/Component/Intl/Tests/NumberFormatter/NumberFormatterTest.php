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

use Symfony\Component\Intl\Exception\MethodArgumentNotImplementedException;
use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException;
use Symfony\Component\Intl\Exception\MethodNotImplementedException;
use Symfony\Component\Intl\Exception\NotImplementedException;
use Symfony\Component\Intl\Globals\IntlGlobals;
use Symfony\Component\Intl\NumberFormatter\NumberFormatter;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 *
 * @group legacy
 */
class NumberFormatterTest extends AbstractNumberFormatterTest
{
    public function testConstructorWithUnsupportedLocale()
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        $this->getNumberFormatter('pt_BR');
    }

    public function testConstructorWithUnsupportedStyle()
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        $this->getNumberFormatter('en', NumberFormatter::PATTERN_DECIMAL);
    }

    public function testConstructorWithPatternDifferentThanNull()
    {
        $this->expectException(MethodArgumentNotImplementedException::class);
        $this->getNumberFormatter('en', NumberFormatter::DECIMAL, '');
    }

    public function testSetAttributeWithUnsupportedAttribute()
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::LENIENT_PARSE, 100);
    }

    public function testSetAttributeInvalidRoundingMode()
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::ROUNDING_MODE, -1);
    }

    public function testConstructWithoutLocale()
    {
        $this->assertInstanceOf(NumberFormatter::class, $this->getNumberFormatter(null, NumberFormatter::DECIMAL));
    }

    public function testCreate()
    {
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $this->assertInstanceOf(NumberFormatter::class, $formatter::create('en', NumberFormatter::DECIMAL));
    }

    public function testFormatWithCurrencyStyle()
    {
        $this->expectException(\RuntimeException::class);
        parent::testFormatWithCurrencyStyle();
    }

    /**
     * @dataProvider formatTypeInt32Provider
     */
    public function testFormatTypeInt32($formatter, $value, $expected, $message = '')
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        parent::testFormatTypeInt32($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt32WithCurrencyStyleProvider
     */
    public function testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message = '')
    {
        $this->expectException(NotImplementedException::class);
        parent::testFormatTypeInt32WithCurrencyStyle($formatter, $value, $expected, $message);
    }

    /**
     * @dataProvider formatTypeInt64Provider
     */
    public function testFormatTypeInt64($formatter, $value, $expected)
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        parent::testFormatTypeInt64($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeInt64WithCurrencyStyleProvider
     */
    public function testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected)
    {
        $this->expectException(NotImplementedException::class);
        parent::testFormatTypeInt64WithCurrencyStyle($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleProvider
     */
    public function testFormatTypeDouble($formatter, $value, $expected)
    {
        $this->expectException(MethodArgumentValueNotImplementedException::class);
        parent::testFormatTypeDouble($formatter, $value, $expected);
    }

    /**
     * @dataProvider formatTypeDoubleWithCurrencyStyleProvider
     */
    public function testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected)
    {
        $this->expectException(NotImplementedException::class);
        parent::testFormatTypeDoubleWithCurrencyStyle($formatter, $value, $expected);
    }

    public function testGetPattern()
    {
        $this->expectException(MethodNotImplementedException::class);
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
        $this->expectException(MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $currency = 'USD';
        $formatter->parseCurrency(3, $currency);
    }

    public function testSetPattern()
    {
        $this->expectException(MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setPattern('#0');
    }

    public function testSetSymbol()
    {
        $this->expectException(MethodNotImplementedException::class);
        $formatter = $this->getNumberFormatter('en', NumberFormatter::DECIMAL);
        $formatter->setSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL, '*');
    }

    public function testSetTextAttribute()
    {
        $this->expectException(MethodNotImplementedException::class);
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
