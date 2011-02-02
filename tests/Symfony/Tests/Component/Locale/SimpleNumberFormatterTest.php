<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Locale;

use Symfony\Component\Locale\Locale;
use Symfony\Component\Locale\SimpleNumberFormatter;

class SimpleNumberFormatterTest extends \PHPUnit_Framework_TestCase
{
    private static $int64Upper = 9223372036854775807;

    /**
     * Strangely, using -9223372036854775808 directly in code make PHP type
     * juggle the value to float. Then, use this value with an explicit typecast
     * to int, e.g.: (int) self::$int64Lower.
     */
    private static $int64Lower = -9223372036854775808;

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithUnsupportedLocale()
    {
        $formatter = new SimpleNumberFormatter('pt_BR');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithUnsupportedStyle()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::PATTERN_DECIMAL);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorWithPatternDifferentThanNull()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL, '');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetAttributeWithUnsupportedAttribute()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $formatter->setAttribute(SimpleNumberFormatter::LENIENT_PARSE, null);
    }

    public function testSetAttributeInvalidRoundingMode()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);

        $ret = $formatter->setAttribute(SimpleNumberFormatter::ROUNDING_MODE, null);
        $roundingMode = $formatter->getAttribute(SimpleNumberFormatter::ROUNDING_MODE);

        $this->assertFalse($ret);
        $this->assertEquals(SimpleNumberFormatter::ROUND_HALFEVEN, $roundingMode);
    }

    public function testSetAttributeInvalidGroupingUsedValue()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);

        $ret = $formatter->setAttribute(SimpleNumberFormatter::GROUPING_USED, null);
        $groupingUsed = $formatter->getAttribute(SimpleNumberFormatter::GROUPING_USED);

        $this->assertFalse($ret);
        $this->assertEquals(SimpleNumberFormatter::GROUPING_USED, $groupingUsed);
    }

    /**
     * @dataProvider formatCurrencyProvider
     * @see  SimpleNumberFormatter::formatCurrency()
     * @todo Test with ROUND_CEILING and ROUND_FLOOR modes
     */
    public function testFormatCurrency($value, $currency, $expected)
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $this->assertEquals($expected, $formatter->formatCurrency($value, $currency));

        if (extension_loaded('intl')) {
            $numberFormatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);

            $this->assertEquals(
                $numberFormatter->formatCurrency($value, $currency),
                $formatter->formatCurrency($value, $currency)
            );
        }
    }

    public function formatCurrencyProvider()
    {
        return array(
            array(100, 'ALL', 'ALL100'),
            array(100, 'BRL', 'R$100.00'),
            array(100, 'CRC', '₡100'),
            array(-100, 'ALL', '(ALL100)'),
            array(-100, 'BRL', '(R$100.00)'),
            array(-100, 'CRC', '(₡100)'),
            array(1000.12, 'ALL', 'ALL1,000'),
            array(1000.12, 'BRL', 'R$1,000.12'),
            array(1000.12, 'CRC', '₡1,000'),
            // Test with other rounding modes
            // array(1000.127, 'ALL', 'ALL1,000'),
            // array(1000.127, 'BRL', 'R$1,000.12'),
            // array(1000.127, 'CRC', '₡1,000'),
        );
    }

    public function testFormat()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);

        // Use the defined fraction digits
        $formatter->setAttribute(SimpleNumberFormatter::FRACTION_DIGITS, 2);
        $this->assertSame('9.56', $formatter->format(9.555));
        $this->assertSame('1,000,000.12', $formatter->format(1000000.123));

        $formatter->setAttribute(SimpleNumberFormatter::FRACTION_DIGITS, -1);
        $this->assertSame('10', $formatter->format(9.5));

        // Don't use number grouping
        $formatter->setAttribute(SimpleNumberFormatter::FRACTION_DIGITS, 2);
        $formatter->setAttribute(SimpleNumberFormatter::GROUPING_USED, 0);
        $this->assertSame('1000000.12', $formatter->format(1000000.123));
    }

    public function testGetErrorCode()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $this->assertEquals(SimpleNumberFormatter::U_ZERO_ERROR, $formatter->getErrorCode());
    }

    public function testGetErrorMessage()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $this->assertEquals(SimpleNumberFormatter::U_ZERO_ERROR_MESSAGE, $formatter->getErrorMessage());
    }

    public function testParseValueWithStringInTheBeginning()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $value = $formatter->parse('R$1,234,567.89', SimpleNumberFormatter::TYPE_DOUBLE);
        $this->assertFalse($value);

        $formatter = new \NumberFormatter('en', \NumberFormatter::DECIMAL);
        $value = $formatter->parse('R$1,234,567.89', \NumberFormatter::TYPE_DOUBLE);
        $this->assertFalse($value);
    }

    public function testParseValueWithStringAtTheEnd()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $value = $formatter->parse('1,234,567.89', SimpleNumberFormatter::TYPE_DOUBLE);
        $this->assertEquals(1234567.89, $value);

        $formatter = new \NumberFormatter('en', \NumberFormatter::DECIMAL);
        $value = $formatter->parse('1,234,567.89R$', \NumberFormatter::TYPE_DOUBLE);
        $this->assertEquals(1234567.89, $value);
    }

    public function testParse()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);

        $value = $formatter->parse('9,223,372,036,854,775,808', SimpleNumberFormatter::TYPE_DOUBLE);
        $this->assertSame(9223372036854775808, $value);

        // int 32
        $value = $formatter->parse('2,147,483,648', SimpleNumberFormatter::TYPE_INT32);
        $this->assertSame(2147483647, $value);

        $value = $formatter->parse('-2,147,483,649', SimpleNumberFormatter::TYPE_INT32);
        $this->assertSame(-2147483648, $value);

        // int 64
        $value = $formatter->parse('9,223,372,036,854,775,808', SimpleNumberFormatter::TYPE_INT64);
        $this->assertSame(9223372036854775807, $value);

        $value = $formatter->parse('-9,223,372,036,854,775,809', SimpleNumberFormatter::TYPE_INT64);
        $this->assertSame((int) self::$int64Lower, $value);
    }

    /**
     * @dataProvider parseDetectTypeProvider
     */
    public function testParseDetectType($parseValue, $expectedType, $expectedValue)
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $value = $formatter->parse($parseValue, SimpleNumberFormatter::TYPE_DEFAULT);
        $this->assertInternalType($expectedType, $value);
        $this->assertSame($expectedValue, $value);
    }

    public function parseDetectTypeProvider()
    {
        return array(
            array('1', 'integer', 1),
            array('1.1', 'float', 1.1),

            // int 32
            array('2,147,483,647', 'integer', 2147483647),
            array('-2,147,483,648', 'integer', -2147483648),

            // int 64
            array('9,223,372,036,854,775,807', 'integer', self::$int64Upper),
            array('-9,223,372,036,854,775,808', 'integer', (int) self::$int64Lower),

            // int 64 overflow
            array('9,223,372,036,854,775,808', 'float', 9223372036854775808),
            array('-9,223,372,036,854,775,809', 'float', -9223372036854775809)
        );
    }

    /**
     * @expectedException RuntimeException
     */
    public function testParseWithPositionValue()
    {
        $position = 1;
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);
        $formatter->parse('123', SimpleNumberFormatter::TYPE_DEFAULT, $position);
    }
}
