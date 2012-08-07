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
use Symfony\Component\Locale\Stub\StubNumberFormatter;
use Symfony\Component\Locale\Tests\TestCase as LocaleTestCase;

/**
 * Note that there are some values written like -2147483647 - 1. This is the lower 32bit int max and is a known
 * behavior of PHP.
 */
class StubNumberFormatterTest extends LocaleTestCase
{
    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        $formatter = new StubNumberFormatter('pt_BR');
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testConstructorWithUnsupportedStyle()
    {
        $formatter = new StubNumberFormatter('en', StubNumberFormatter::PATTERN_DECIMAL);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentNotImplementedException
     */
    public function testConstructorWithPatternDifferentThanNull()
    {
        $formatter = new StubNumberFormatter('en', StubNumberFormatter::DECIMAL, '');
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetAttributeWithUnsupportedAttribute()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setAttribute(StubNumberFormatter::LENIENT_PARSE, null);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testSetAttributeInvalidRoundingMode()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $ret = $formatter->setAttribute(StubNumberFormatter::ROUNDING_MODE, null);
    }

    public function testCreateStub()
    {
        $this->assertInstanceOf(
            'Symfony\Component\Locale\Stub\StubNumberFormatter',
            StubNumberFormatter::create('en', StubNumberFormatter::DECIMAL)
        );
    }

    public function testCreateIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->assertInstanceOf('\NumberFormatter', \NumberFormatter::create('en', \NumberFormatter::DECIMAL));
    }

    /**
     * @dataProvider formatCurrencyWithDecimalStyleProvider
     */
    public function testFormatCurrencyWithDecimalStyleStub($value, $currency, $expected)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $this->assertEquals($expected, $formatter->formatCurrency($value, $currency));
    }

    /**
     * @dataProvider formatCurrencyWithDecimalStyleProvider
     */
    public function testFormatCurrencyWithDecimalStyleIntl($value, $currency, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $this->assertEquals($expected, $formatter->formatCurrency($value, $currency));
    }

    public function formatCurrencyWithDecimalStyleProvider()
    {
        return array(
            array(100, 'ALL', '100'),
            array(100, 'BRL', '100.00'),
            array(100, 'CRC', '100'),
            array(100, 'JPY', '100'),
            array(100, 'CHF', '100'),
            array(-100, 'ALL', '-100'),
            array(-100, 'BRL', '-100'),
            array(-100, 'CRC', '-100'),
            array(-100, 'JPY', '-100'),
            array(-100, 'CHF', '-100'),
            array(1000.12, 'ALL', '1,000.12'),
            array(1000.12, 'BRL', '1,000.12'),
            array(1000.12, 'CRC', '1,000.12'),
            array(1000.12, 'JPY', '1,000.12'),
            array(1000.12, 'CHF', '1,000.12')
        );
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleProvider
     */
    public function testFormatCurrencyWithCurrencyStyleStub($value, $currency, $expected)
    {
        $formatter = $this->getStubFormatterWithCurrencyStyle();
        $this->assertEquals($expected, $formatter->formatCurrency($value, $currency));
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleProvider
     */
    public function testFormatCurrencyWithCurrencyStyleIntl($value, $currency, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();
        $formatter = $this->getIntlFormatterWithCurrencyStyle();
        $this->assertEquals($expected, $formatter->formatCurrency($value, $currency));
    }

    public function formatCurrencyWithCurrencyStyleProvider()
    {
        return array(
            array(100, 'ALL', 'ALL100'),
            array(-100, 'ALL', '(ALL100)'),
            array(1000.12, 'ALL', 'ALL1,000'),

            array(100, 'JPY', '¥100'),
            array(-100, 'JPY', '(¥100)'),
            array(1000.12, 'JPY', '¥1,000'),

            array(100, 'EUR', '€100.00'),
            array(-100, 'EUR', '(€100.00)'),
            array(1000.12, 'EUR', '€1,000.12'),
        );
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleCostaRicanColonsRoundingProvider
     */
    public function testFormatCurrencyWithCurrencyStyleCostaRicanColonsRoundingStub($value, $currency, $symbol, $expected)
    {
        $formatter = $this->getStubFormatterWithCurrencyStyle();
        $this->assertEquals(sprintf($expected, $symbol), $formatter->formatCurrency($value, $currency));
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleCostaRicanColonsRoundingProvider
     */
    public function testFormatCurrencyWithCurrencyStyleCostaRicanColonsRoundingIntl($value, $currency, $symbol, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();
        $formatter = $this->getIntlFormatterWithCurrencyStyle();
        $this->assertEquals(sprintf($expected, $symbol), $formatter->formatCurrency($value, $currency));
    }

    public function formatCurrencyWithCurrencyStyleCostaRicanColonsRoundingProvider()
    {
        $crc = $this->isIntlExtensionLoaded() && $this->isGreaterOrEqualThanIcuVersion('4.8') ? 'CRC' : '₡';

        return array(
            array(100, 'CRC', $crc, '%s100'),
            array(-100, 'CRC', $crc, '(%s100)'),
            array(1000.12, 'CRC', $crc, '%s1,000'),
        );
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleBrazilianRealRoundingProvider
     */
    public function testFormatCurrencyWithCurrencyStyleBrazilianRealRoundingStub($value, $currency, $symbol, $expected)
    {
        $formatter = $this->getStubFormatterWithCurrencyStyle();
        $this->assertEquals(sprintf($expected, $symbol), $formatter->formatCurrency($value, $currency));
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleBrazilianRealRoundingProvider
     */
    public function testFormatCurrencyWithCurrencyStyleBrazilianRealRoundingIntl($value, $currency, $symbol, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();
        $formatter = $this->getIntlFormatterWithCurrencyStyle();
        $this->assertEquals(sprintf($expected, $symbol), $formatter->formatCurrency($value, $currency));
    }

    public function formatCurrencyWithCurrencyStyleBrazilianRealRoundingProvider()
    {
        $brl = $this->isIntlExtensionLoaded() && $this->isSameAsIcuVersion('4.8') ? 'BR' : 'R';

        return array(
            array(100, 'BRL', $brl, '%s$100.00'),
            array(-100, 'BRL', $brl, '(%s$100.00)'),
            array(1000.12, 'BRL', $brl, '%s$1,000.12'),

            // Rounding checks
            array(1000.121, 'BRL', $brl, '%s$1,000.12'),
            array(1000.123, 'BRL', $brl, '%s$1,000.12'),
            array(1000.125, 'BRL', $brl, '%s$1,000.12'),
            array(1000.127, 'BRL', $brl, '%s$1,000.13'),
            array(1000.129, 'BRL', $brl, '%s$1,000.13'),
            array(11.50999, 'BRL', $brl, '%s$11.51'),
            array(11.9999464, 'BRL', $brl, '%s$12.00')
        );
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleSwissRoundingProvider
     */
    public function testFormatCurrencyWithCurrencyStyleSwissRoundingStub($value, $currency, $symbol, $expected)
    {
        $formatter = $this->getStubFormatterWithCurrencyStyle();
        $this->assertEquals(sprintf($expected, $symbol), $formatter->formatCurrency($value, $currency));
    }

    /**
     * @dataProvider formatCurrencyWithCurrencyStyleSwissRoundingProvider
     */
    public function testFormatCurrencyWithCurrencyStyleSwissRoundingIntl($value, $currency, $symbol, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();
        $formatter = $this->getIntlFormatterWithCurrencyStyle();
        $this->assertEquals(sprintf($expected, $symbol), $formatter->formatCurrency($value, $currency));
    }

    public function formatCurrencyWithCurrencyStyleSwissRoundingProvider()
    {
        // The currency symbol was updated from 4.2 to the 4.4 version. The ICU CLDR data was updated in 2010-03-03,
        // the 4.2 release is from 2009-05-08 and the 4.4 from 2010-03-17. It's ugly we want to compare if the
        // stub implementation is behaving like the intl one
        // http://bugs.icu-project.org/trac/changeset/27776/icu/trunk/source/data/curr/en.txt
        $chf = $this->isIntlExtensionLoaded() && $this->isLowerThanIcuVersion('4.4') ? 'Fr.' : 'CHF';

        return array(
            array(100, 'CHF', $chf, '%s100.00'),
            array(-100, 'CHF', $chf, '(%s100.00)'),
            array(1000.12, 'CHF', $chf, '%s1,000.10'),
            array('1000.12', 'CHF', $chf, '%s1,000.10'),

            // Rounding checks
            array(1000.121, 'CHF', $chf, '%s1,000.10'),
            array(1000.123, 'CHF', $chf, '%s1,000.10'),
            array(1000.125, 'CHF', $chf, '%s1,000.10'),
            array(1000.127, 'CHF', $chf, '%s1,000.15'),
            array(1000.129, 'CHF', $chf, '%s1,000.15'),

            array(1200000.00, 'CHF', $chf, '%s1,200,000.00'),
            array(1200000.1, 'CHF', $chf, '%s1,200,000.10'),
            array(1200000.10, 'CHF', $chf, '%s1,200,000.10'),
            array(1200000.101, 'CHF', $chf, '%s1,200,000.10')
        );
    }

    public function testFormatStub()
    {
        $errorCode = StubIntl::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->getStubFormatterWithDecimalStyle();
        $this->assertSame('9.555', $formatter->format(9.555));

        $this->assertSame($errorMessage, StubIntl::getErrorMessage());
        $this->assertSame($errorCode, StubIntl::getErrorCode());
        $this->assertFalse(StubIntl::isFailure(StubIntl::getErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertFalse(StubIntl::isFailure($formatter->getErrorCode()));
    }

    public function testFormatIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();

        $errorCode = StubIntl::U_ZERO_ERROR;
        $errorMessage = 'U_ZERO_ERROR';

        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $this->assertSame('9.555', $formatter->format(9.555));

        $this->assertSame($errorMessage, intl_get_error_message());
        $this->assertSame($errorCode, intl_get_error_code());
        $this->assertFalse(intl_is_failure(intl_get_error_code()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertFalse(intl_is_failure($formatter->getErrorCode()));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testFormatWithCurrencyStyleStub()
    {
        $formatter = $this->getStubFormatterWithCurrencyStyle();
        $formatter->format(1);
    }

    public function testFormatWithCurrencyStyleIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithCurrencyStyle();
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, 'SFD');
        $this->assertEquals('SFD1.00', $formatter->format(1));
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeInt32Stub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->format(1, StubNumberFormatter::TYPE_INT32);
    }

    /**
     * @dataProvider formatTypeInt32Provider
     */
    public function testFormatTypeInt32Intl($formatter, $value, $expected, $message = '')
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_INT32);
        $this->assertEquals($expected, $formattedValue, $message);
    }

    public function formatTypeInt32Provider()
    {
        $df = $this->getIntlFormatterWithDecimalStyle();
        $cf = $this->getIntlFormatterWithCurrencyStyle();

        $message = '->format() TYPE_INT32 formats inconsistently an integer if out of the 32 bit range.';

        return array(
            array($df, 1, '1'),
            array($df, 1.1, '1'),
            array($df, 2147483648, '-2,147,483,648', $message),
            array($df, -2147483649, '2,147,483,647', $message),
            array($cf, 1, 'SFD1.00'),
            array($cf, 1.1, 'SFD1.00'),
            array($cf, 2147483648, '(SFD2,147,483,648.00)', $message),
            array($cf, -2147483649, 'SFD2,147,483,647.00', $message)
        );
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeInt64Stub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->format(1, StubNumberFormatter::TYPE_INT64);
    }

    /**
     * The parse() method works differently with integer out of the 32 bit range. format() works fine.
     * @dataProvider formatTypeInt64Provider
     */
    public function testFormatTypeInt64Intl($formatter, $value, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_INT64);
        $this->assertEquals($expected, $formattedValue);
    }

    public function formatTypeInt64Provider()
    {
        $df = $this->getIntlFormatterWithDecimalStyle();
        $cf = $this->getIntlFormatterWithCurrencyStyle();

        return array(
            array($df, 1, '1'),
            array($df, 1.1, '1'),
            array($df, 2147483648, '2,147,483,648'),
            array($df, -2147483649, '-2,147,483,649'),
            array($cf, 1, 'SFD1.00'),
            array($cf, 1.1, 'SFD1.00'),
            array($cf, 2147483648, 'SFD2,147,483,648.00'),
            array($cf, -2147483649, '(SFD2,147,483,649.00)')
        );
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException
     */
    public function testFormatTypeDoubleStub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->format(1, StubNumberFormatter::TYPE_DOUBLE);
    }

    /**
     * @dataProvider formatTypeDoubleProvider
     */
    public function testFormatTypeDoubleIntl($formatter, $value, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_DOUBLE);
        $this->assertEquals($expected, $formattedValue);
    }

    public function formatTypeDoubleProvider()
    {
        $df = $this->getIntlFormatterWithDecimalStyle();
        $cf = $this->getIntlFormatterWithCurrencyStyle();

        return array(
            array($df, 1, '1'),
            array($df, 1.1, '1.1'),
            array($cf, 1, 'SFD1.00'),
            array($cf, 1.1, 'SFD1.10'),
        );
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testFormatTypeCurrencyStub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->format(1, StubNumberFormatter::TYPE_CURRENCY);
    }

    public function testFormatTypeCurrencyReturnStub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $this->assertFalse(@$formatter->format(1, StubNumberFormatter::TYPE_CURRENCY));
    }

    /**
     * @dataProvider formatTypeCurrencyProvider
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testFormatTypeCurrencyIntl($formatter, $value)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_CURRENCY);
    }

    public function formatTypeCurrencyProvider()
    {
        $df = $this->getIntlFormatterWithDecimalStyle();
        $cf = $this->getIntlFormatterWithCurrencyStyle();

        return array(
            array($df, 1),
            array($df, 1),
        );
    }

    /**
     * @dataProvider formatFractionDigitsProvider
     */
    public function testFormatFractionDigitsStub($value, $expected, $fractionDigits = null, $expectedFractionDigits = 1)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();

        if (null !== $fractionDigits) {
            $attributeRet = $formatter->setAttribute(StubNumberFormatter::FRACTION_DIGITS, $fractionDigits);
        }

        $formattedValue = $formatter->format($value);
        $this->assertSame($expected, $formattedValue);
        $this->assertSame($expectedFractionDigits, $formatter->getAttribute(StubNumberFormatter::FRACTION_DIGITS));

        if (isset($attributeRet)) {
            $this->assertTrue($attributeRet);
        }
    }

    /**
     * @dataProvider formatFractionDigitsProvider
     */
    public function testFormatFractionDigitsIntl($value, $expected, $fractionDigits = null, $expectedFractionDigits = 1)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();

        if (null !== $fractionDigits) {
            $attributeRet = $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $fractionDigits);
        }

        $formattedValue = $formatter->format($value);
        $this->assertSame($expected, $formattedValue);
        $this->assertSame($expectedFractionDigits, $formatter->getAttribute(\NumberFormatter::FRACTION_DIGITS));

        if (isset($attributeRet)) {
            $this->assertTrue($attributeRet);
        }
    }

    public function formatFractionDigitsProvider()
    {
        return array(
            array(1.123, '1.123', null, 0),
            array(1.123, '1', 0, 0),
            array(1.123, '1.1', 1, 1),
            array(1.123, '1.12', 2, 2),
            array(1.123, '1', -1, 0),
            array(1.123, '1', 'abc', 0)
        );
    }

    /**
     * @dataProvider formatGroupingUsedProvider
     */
    public function testFormatGroupingUsedStub($value, $expected, $groupingUsed = null, $expectedGroupingUsed = 1)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();

        if (null !== $groupingUsed) {
            $attributeRet = $formatter->setAttribute(StubNumberFormatter::GROUPING_USED, $groupingUsed);
        }

        $formattedValue = $formatter->format($value);
        $this->assertSame($expected, $formattedValue);
        $this->assertSame($expectedGroupingUsed, $formatter->getAttribute(StubNumberFormatter::GROUPING_USED));

        if (isset($attributeRet)) {
            $this->assertTrue($attributeRet);
        }
    }

    /**
     * @dataProvider formatGroupingUsedProvider
     */
    public function testFormatGroupingUsedIntl($value, $expected, $groupingUsed = null, $expectedGroupingUsed = 1)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();

        if (null !== $groupingUsed) {
            $attributeRet = $formatter->setAttribute(\NumberFormatter::GROUPING_USED, $groupingUsed);
        }

        $formattedValue = $formatter->format($value);
        $this->assertSame($expected, $formattedValue);
        $this->assertSame($expectedGroupingUsed, $formatter->getAttribute(\NumberFormatter::GROUPING_USED));

        if (isset($attributeRet)) {
            $this->assertTrue($attributeRet);
        }
    }

    public function formatGroupingUsedProvider()
    {
        return array(
            array(1000, '1,000', null, 1),
            array(1000, '1000', 0, 0),
            array(1000, '1,000', 1, 1),
            array(1000, '1,000', 2, 1),
            array(1000, '1000', 'abc', 0),
            array(1000, '1,000', -1, 1),
        );
    }

    /**
     * @dataProvider formatRoundingModeRoundHalfUpProvider
     */
    public function testFormatRoundingModeStubRoundHalfUp($value, $expected)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setAttribute(StubNumberFormatter::FRACTION_DIGITS, 2);

        $formatter->setAttribute(StubNumberFormatter::ROUNDING_MODE, StubNumberFormatter::ROUND_HALFUP);
        $this->assertSame($expected, $formatter->format($value), '->format() with ROUND_HALFUP rounding mode.');
    }

    /**
     * @dataProvider formatRoundingModeRoundHalfUpProvider
     */
    public function testFormatRoundingModeHalfUpIntl($value, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);

        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);
        $this->assertSame($expected, $formatter->format($value), '->format() with ROUND_HALFUP rounding mode.');
    }

    public function formatRoundingModeRoundHalfUpProvider()
    {
        // The commented value is differently rounded by intl's NumberFormatter in 32 and 64 bit architectures
        return array(
            array(1.121, '1.12'),
            array(1.123, '1.12'),
            // array(1.125, '1.13'),
            array(1.127, '1.13'),
            array(1.129, '1.13'),
        );
    }

    /**
     * @dataProvider formatRoundingModeRoundHalfDownProvider
     */
    public function testFormatRoundingModeStubRoundHalfDown($value, $expected)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setAttribute(StubNumberFormatter::FRACTION_DIGITS, 2);

        $formatter->setAttribute(StubNumberFormatter::ROUNDING_MODE, StubNumberFormatter::ROUND_HALFDOWN);
        $this->assertSame($expected, $formatter->format($value), '->format() with ROUND_HALFDOWN rounding mode.');
    }

    /**
     * @dataProvider formatRoundingModeRoundHalfDownProvider
     */
    public function testFormatRoundingModeHalfDownIntl($value, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);

        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFDOWN);
        $this->assertSame($expected, $formatter->format($value), '->format() with ROUND_HALFDOWN rounding mode.');
    }

    public function formatRoundingModeRoundHalfDownProvider()
    {
        return array(
            array(1.121, '1.12'),
            array(1.123, '1.12'),
            array(1.125, '1.12'),
            array(1.127, '1.13'),
            array(1.129, '1.13'),
        );
    }

    /**
     * @dataProvider formatRoundingModeRoundHalfEvenProvider
     */
    public function testFormatRoundingModeStubRoundHalfEven($value, $expected)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setAttribute(StubNumberFormatter::FRACTION_DIGITS, 2);

        $formatter->setAttribute(StubNumberFormatter::ROUNDING_MODE, StubNumberFormatter::ROUND_HALFEVEN);
        $this->assertSame($expected, $formatter->format($value), '->format() with ROUND_HALFEVEN rounding mode.');
    }

    /**
     * @dataProvider formatRoundingModeRoundHalfEvenProvider
     */
    public function testFormatRoundingModeHalfEvenIntl($value, $expected)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);

        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFEVEN);
        $this->assertSame($expected, $formatter->format($value), '->format() with ROUND_HALFEVEN rounding mode.');
    }

    public function formatRoundingModeRoundHalfEvenProvider()
    {
        return array(
            array(1.121, '1.12'),
            array(1.123, '1.12'),
            array(1.125, '1.12'),
            array(1.127, '1.13'),
            array(1.129, '1.13'),
        );
    }

    public function testGetErrorCode()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $this->assertEquals(StubIntl::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    public function testGetLocale()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $this->assertEquals('en', $formatter->getLocale());
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetPattern()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->getPattern();
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetSymbol()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->getSymbol(null);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testGetTextAttribute()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->getTextAttribute(null);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testParseCurrency()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->parseCurrency(null, $currency);
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParseStub($value, $expected, $message = '')
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse($value, StubNumberFormatter::TYPE_DOUBLE);
        $this->assertSame($expected, $parsedValue, $message);

        if ($expected === false) {
            $errorCode = StubIntl::U_PARSE_ERROR;
            $errorMessage = 'Number parsing failed: U_PARSE_ERROR';
        } else {
            $errorCode = StubIntl::U_ZERO_ERROR;
            $errorMessage = 'U_ZERO_ERROR';
        }

        $this->assertSame($errorMessage, StubIntl::getErrorMessage());
        $this->assertSame($errorCode, StubIntl::getErrorCode());
        $this->assertSame($errorCode !== 0, StubIntl::isFailure(StubIntl::getErrorCode()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertSame($errorCode !== 0, StubIntl::isFailure($formatter->getErrorCode()));
    }

    /**
     * @dataProvider parseProvider
     */
    public function testParseIntl($value, $expected, $message = '')
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfICUVersionIsTooOld();

        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse($value, \NumberFormatter::TYPE_DOUBLE);
        $this->assertSame($expected, $parsedValue, $message);

        if ($expected === false) {
            $errorCode = StubIntl::U_PARSE_ERROR;
            $errorMessage = 'Number parsing failed: U_PARSE_ERROR';
        } else {
            $errorCode = StubIntl::U_ZERO_ERROR;
            $errorMessage = 'U_ZERO_ERROR';
        }

        $this->assertSame($errorMessage, intl_get_error_message());
        $this->assertSame($errorCode, intl_get_error_code());
        $this->assertSame($errorCode > 0, intl_is_failure(intl_get_error_code()));
        $this->assertSame($errorMessage, $formatter->getErrorMessage());
        $this->assertSame($errorCode, $formatter->getErrorCode());
        $this->assertSame($errorCode > 0, intl_is_failure($formatter->getErrorCode()));
    }

    public function parseProvider()
    {
        return array(
            array('prefix1', false, '->parse() does not parse a number with a string prefix.'),
            array('1suffix', (float) 1, '->parse() parses a number with a string suffix.'),
        );
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testParseTypeDefaultStub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->parse('1', StubNumberFormatter::TYPE_DEFAULT);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testParseTypeDefaultIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $formatter->parse('1', \NumberFormatter::TYPE_DEFAULT);
    }

    /**
     * @dataProvider parseTypeInt32Provider
     */
    public function testParseTypeInt32Stub($value, $expected, $message = '')
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse($value, StubNumberFormatter::TYPE_INT32);
        $this->assertSame($expected, $parsedValue);
    }

    /**
     * @dataProvider parseTypeInt32Provider
     */
    public function testParseTypeInt32Intl($value, $expected, $message = '')
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse($value, \NumberFormatter::TYPE_INT32);
        $this->assertSame($expected, $parsedValue);
    }

    public function parseTypeInt32Provider()
    {
        return array(
            array('1', 1),
            array('1.1', 1),
            array('2,147,483,647', 2147483647),
            array('-2,147,483,648', -2147483647 - 1),
            array('2,147,483,648', false, '->parse() TYPE_INT32 returns false when the number is greater than the integer positive range.'),
            array('-2,147,483,649', false, '->parse() TYPE_INT32 returns false when the number is greater than the integer negative range.')
        );
    }

    // Stub Tests

    public function testParseTypeInt64StubWith32BitIntegerInPhp32Bit()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfNot32Bit();

        $formatter = $this->getStubFormatterWithDecimalStyle();

        $parsedValue = $formatter->parse('2,147,483,647', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(2147483647, $parsedValue);

        $parsedValue = $formatter->parse('-2,147,483,648', \NumberFormatter::TYPE_INT64);

        // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
        // The negative PHP_INT_MAX was being converted to float
        if (
            (version_compare(PHP_VERSION, '5.4.0', '<') && version_compare(PHP_VERSION, '5.3.14', '>=')) ||
            version_compare(PHP_VERSION, '5.4.4', '>=')
        ) {
            $this->assertInternalType('int', $parsedValue);
        } else {
            $this->assertInternalType('float', $parsedValue);
        }

        $this->assertEquals(-2147483648, $parsedValue);
    }

    public function testParseTypeInt64StubWith32BitIntegerInPhp64Bit()
    {
        $this->skipIfNot64Bit();

        $formatter = $this->getStubFormatterWithDecimalStyle();

        $parsedValue = $formatter->parse('2,147,483,647', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(2147483647, $parsedValue);

        $parsedValue = $formatter->parse('-2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(-2147483647 - 1, $parsedValue);
    }

    /**
     * If PHP is compiled in 32bit mode, the returned value for a 64bit integer are float numbers.
     */
    public function testParseTypeInt64StubWith64BitIntegerInPhp32Bit()
    {
        $this->skipIfNot32Bit();

        $formatter = $this->getStubFormatterWithDecimalStyle();

        // int 64 using only 32 bit range strangeness
        $parsedValue = $formatter->parse('2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(2147483648, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range.');

        $parsedValue = $formatter->parse('-2,147,483,649', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(-2147483649, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range.');
    }

    /**
     * If PHP is compiled in 64bit mode, the returned value for a 64bit integer are 32bit integer numbers.
     */
    public function testParseTypeInt64StubWith64BitIntegerInPhp64Bit()
    {
        $this->skipIfNot64Bit();

        $formatter = $this->getStubFormatterWithDecimalStyle();

        $parsedValue = $formatter->parse('2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);

        // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
        // A 32 bit integer was being generated instead of a 64 bit integer
        if (
            (version_compare(PHP_VERSION, '5.3.14', '<')) ||
            (version_compare(PHP_VERSION, '5.4.0', '>=') && version_compare(PHP_VERSION, '5.4.4', '<'))
        ) {
            $this->assertEquals(-2147483648, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range (PHP < 5.3.14 and PHP < 5.4.4).');
        } else {
            $this->assertEquals(2147483648, $parsedValue, '->parse() TYPE_INT64 uses true 64 bit integers (PHP >= 5.3.14 and PHP >= 5.4.4).');
        }

        $parsedValue = $formatter->parse('-2,147,483,649', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);

        // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
        // A 32 bit integer was being generated instead of a 64 bit integer
        if (
            (version_compare(PHP_VERSION, '5.3.14', '<')) ||
            (version_compare(PHP_VERSION, '5.4.0', '>=') && version_compare(PHP_VERSION, '5.4.4', '<'))
        ) {
            $this->assertEquals(2147483647, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range  (PHP < 5.3.14 and PHP < 5.4.4).');
        } else {
            $this->assertEquals(-2147483649, $parsedValue, '->parse() TYPE_INT64 uses true 64 bit integers (PHP >= 5.3.14 and PHP >= 5.4.4).');
        }
    }

    // Intl Tests

    public function testParseTypeInt64IntlWith32BitIntegerInPhp32Bit()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfNot32Bit();

        $formatter = $this->getIntlFormatterWithDecimalStyle();

        $parsedValue = $formatter->parse('2,147,483,647', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(2147483647, $parsedValue);

        $parsedValue = $formatter->parse('-2,147,483,648', \NumberFormatter::TYPE_INT64);

        // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
        // The negative PHP_INT_MAX was being converted to float.
        if (
            (version_compare(PHP_VERSION, '5.4.0', '<') && version_compare(PHP_VERSION, '5.3.14', '>=')) ||
            version_compare(PHP_VERSION, '5.4.4', '>=')
        ) {
            $this->assertInternalType('int', $parsedValue);
        } else {
            $this->assertInternalType('float', $parsedValue);
        }

        $this->assertEquals(-2147483648, $parsedValue);
    }

    public function testParseTypeInt64IntlWith32BitIntegerInPhp64Bit()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfNot64Bit();

        $formatter = $this->getIntlFormatterWithDecimalStyle();

        $parsedValue = $formatter->parse('2,147,483,647', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(2147483647, $parsedValue);

        $parsedValue = $formatter->parse('-2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(-2147483647 - 1, $parsedValue);
    }

    /**
     * If PHP is compiled in 32bit mode, the returned value for a 64bit integer are float numbers.
     */
    public function testParseTypeInt64IntlWith64BitIntegerInPhp32Bit()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfNot32Bit();

        $formatter = $this->getIntlFormatterWithDecimalStyle();

        // int 64 using only 32 bit range strangeness
        $parsedValue = $formatter->parse('2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(2147483648, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range.');

        $parsedValue = $formatter->parse('-2,147,483,649', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(-2147483649, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range.');
    }

    /**
     * If PHP is compiled in 64bit mode, the returned value for a 64bit integer are 32bit integer numbers.
     */
    public function testParseTypeInt64IntlWith64BitIntegerInPhp64Bit()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $this->skipIfNot64Bit();

        $formatter = $this->getIntlFormatterWithDecimalStyle();

        $parsedValue = $formatter->parse('2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);

        // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
        // A 32 bit integer was being generated instead of a 64 bit integer
        if (
            (version_compare(PHP_VERSION, '5.3.14', '<')) ||
            (version_compare(PHP_VERSION, '5.4.0', '>=') && version_compare(PHP_VERSION, '5.4.4', '<'))
        ) {
            $this->assertEquals(-2147483648, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range (PHP < 5.3.14 and PHP < 5.4.4).');
        } else {
            $this->assertEquals(2147483648, $parsedValue, '->parse() TYPE_INT64 uses true 64 bit integers (PHP >= 5.3.14 and PHP >= 5.4.4).');
        }

        $parsedValue = $formatter->parse('-2,147,483,649', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);

        // Bug #59597 was fixed on PHP 5.3.14 and 5.4.4
        // A 32 bit integer was being generated instead of a 64 bit integer
        if (
            (version_compare(PHP_VERSION, '5.3.14', '<')) ||
            (version_compare(PHP_VERSION, '5.4.0', '>=') && version_compare(PHP_VERSION, '5.4.4', '<'))
        ) {
            $this->assertEquals(2147483647, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range  (PHP < 5.3.14 and PHP < 5.4.4).');
        } else {
            $this->assertEquals(-2147483649, $parsedValue, '->parse() TYPE_INT64 uses true 64 bit integers (PHP >= 5.3.14 and PHP >= 5.4.4).');
        }
    }

    /**
     * @dataProvider parseTypeDoubleProvider
     */
    public function testParseTypeDoubleStub($value, $expectedValue)
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse($value, StubNumberFormatter::TYPE_DOUBLE);
        $this->assertSame($expectedValue, $parsedValue);
    }

    /**
     * @dataProvider parseTypeDoubleProvider
     */
    public function testParseTypeDoubleIntl($value, $expectedValue)
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse($value, \NumberFormatter::TYPE_DOUBLE);
        $this->assertSame($expectedValue, $parsedValue);
    }

    public function parseTypeDoubleProvider()
    {
        return array(
            array('1', (float) 1),
            array('1.1', 1.1),
            array('9,223,372,036,854,775,808', 9223372036854775808),
            array('-9,223,372,036,854,775,809', -9223372036854775809),
        );
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testParseTypeCurrencyStub()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->parse('1', StubNumberFormatter::TYPE_CURRENCY);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testParseTypeCurrencyIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $formatter->parse('1', \NumberFormatter::TYPE_CURRENCY);
    }

    public function testParseWithNullPositionValueStub()
    {
        $position = null;
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->parse('123', StubNumberFormatter::TYPE_INT32, $position);
        $this->assertNull($position);
    }

    public function testParseWithNullPositionValueIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $position = 0;
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse('123', \NumberFormatter::TYPE_DOUBLE, $position);
        $this->assertEquals(3, $position);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodArgumentNotImplementedException
     */
    public function testParseWithNotNullPositionValueStub()
    {
        $position = 1;
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->parse('123', StubNumberFormatter::TYPE_INT32, $position);
    }

    public function testParseWithNotNullPositionValueIntl()
    {
        $this->skipIfIntlExtensionIsNotLoaded();
        $position = 1;
        $formatter = $this->getIntlFormatterWithDecimalStyle();
        $parsedValue = $formatter->parse('123', \NumberFormatter::TYPE_DOUBLE, $position);
        $this->assertEquals(3, $position);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testSetPattern()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setPattern(null);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testSetSymbol()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setSymbol(null, null);
    }

    /**
     * @expectedException Symfony\Component\Locale\Exception\MethodNotImplementedException
     */
    public function testSetTextAttribute()
    {
        $formatter = $this->getStubFormatterWithDecimalStyle();
        $formatter->setTextAttribute(null, null);
    }

    protected function getStubFormatterWithDecimalStyle()
    {
        return new StubNumberFormatter('en', StubNumberFormatter::DECIMAL);
    }

    protected function getStubFormatterWithCurrencyStyle()
    {
        return new StubNumberFormatter('en', StubNumberFormatter::CURRENCY);
    }

    protected function getIntlFormatterWithDecimalStyle()
    {
        if (!$this->isIntlExtensionLoaded()) {
            return null;
        }

        return new \NumberFormatter('en', \NumberFormatter::DECIMAL);
    }

    protected function getIntlFormatterWithCurrencyStyle()
    {
        if (!$this->isIntlExtensionLoaded()) {
            return null;
        }

        $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, 'SFD');

        return $formatter;
    }
}
