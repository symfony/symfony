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

    public function testFormat()
    {
        $formatter = new SimpleNumberFormatter('en', SimpleNumberFormatter::DECIMAL);

        // Rounds to the next highest integer
        $formatter->setAttribute(SimpleNumberFormatter::ROUNDING_MODE, SimpleNumberFormatter::ROUND_CEILING);
        $this->assertSame('10', $formatter->format(9.5));

        // Use the defined fraction digits
        $formatter->setAttribute(SimpleNumberFormatter::FRACTION_DIGITS, 2);
        $this->assertSame('10.00', $formatter->format(9.5));
        $formatter->setAttribute(SimpleNumberFormatter::FRACTION_DIGITS, -1);
        $this->assertSame('10', $formatter->format(9.5));

        // Set the grouping size
        $formatter->setAttribute(SimpleNumberFormatter::ROUNDING_MODE, SimpleNumberFormatter::ROUND_HALFEVEN);
        $formatter->setAttribute(SimpleNumberFormatter::FRACTION_DIGITS, 2);
        $this->assertSame('9.56', $formatter->format(9.555));
        $this->assertSame('1,000,000.12', $formatter->format(1000000.123));
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
}
