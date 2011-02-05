<?php

namespace Symfony\Tests\Component\Locale\Stub\Learning;

class NumberFormatterTest extends \PHPUnit_Framework_TestCase
{
    private $formatter = null;

    public function setUp()
    {
        $this->formatter = $this->getDecimalFormatter();
    }

    private function getDecimalFormatter()
    {
        return new \NumberFormatter('en', \NumberFormatter::DECIMAL);
    }

    private function getCurrencyFormatter()
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);
        $formatter->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, 'SFD');
        return $formatter;
    }

    /**
     * @dataProvider formatCurrencyProvider
     */
    public function testFormatCurrency(\NumberFormatter $formatter, $value, $currency, $expectedValue)
    {
        $formattedCurrency = $formatter->formatCurrency($value, $currency);
        $this->assertEquals($expectedValue, $formattedCurrency);
    }

    public function formatCurrencyProvider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        return array(
            array($df, 100, 'BRL', '100'),
            array($df, 100.1, 'BRL', '100.1'),
            array($cf, 100, 'BRL', 'R$100.00'),
            array($cf, 100.1, 'BRL', 'R$100.10')
        );
    }

    /**
     * @dataProvider formatProvider
     */
    public function testFormat($formatter, $value, $expectedValue)
    {
        $formattedValue = $formatter->format($value);
        $this->assertEquals($expectedValue, $formattedValue);
    }

    public function formatProvider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        return array(
            array($df, 1, '1'),
            array($df, 1.1, '1.1'),
            array($cf, 1, 'SFD1.00'),
            array($cf, 1.1, 'SFD1.10')
        );
    }

    /**
     * @dataProvider formatTypeDefaultProvider
     */
    public function testFormatTypeDefault($formatter, $value, $expectedValue)
    {
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_DEFAULT);
        $this->assertEquals($expectedValue, $formattedValue);
    }

    public function formatTypeDefaultProvider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        return array(
            array($df, 1, '1'),
            array($df, 1.1, '1.1'),
            array($cf, 1, 'SFD1.00'),
            array($cf, 1.1, 'SFD1.10')
        );
    }

    /**
     * @dataProvider formatTypeInt32Provider
     */
    public function testFormatTypeInt32($formatter, $value, $expectedValue, $message = '')
    {
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_INT32);
        $this->assertEquals($expectedValue, $formattedValue, $message);
    }

    public function formatTypeInt32Provider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        $message = '->format() TYPE_INT32 formats incosistencily an integer if out of 32 bit range.';

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
     * The parse() method works differently with integer out of the 32 bit range. format() works fine.
     * @dataProvider formatTypeInt64Provider
     */
    public function testFormatTypeInt64($formatter, $value, $expectedValue)
    {
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_INT64);
        $this->assertEquals($expectedValue, $formattedValue);
    }

    public function formatTypeInt64Provider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

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
     * @dataProvider formatTypeDoubleProvider
     */
    public function testFormatTypeDouble($formatter, $value, $expectedValue)
    {
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_DOUBLE);
        $this->assertEquals($expectedValue, $formattedValue);
    }

    public function formatTypeDoubleProvider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        return array(
            array($df, 1, '1'),
            array($df, 1.1, '1.1'),
            array($cf, 1, 'SFD1.00'),
            array($cf, 1.1, 'SFD1.10'),
        );
    }

    /**
     * @dataProvider formatTypeCurrencyProvider
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testFormatTypeCurrency($formatter, $value)
    {
        $formattedValue = $formatter->format($value, \NumberFormatter::TYPE_CURRENCY);
    }

    public function formatTypeCurrencyProvider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        return array(
            array($df, 1),
            array($df, 1),
        );
    }

    /**
     * @dataProvider parseCurrencyProvider
     */
    public function testParseCurrency($formatter, $value, $expectedValue, $expectedCurrency)
    {
        $currency = '';
        $parsedValue = $formatter->parseCurrency($value, $currency);
        $this->assertEquals($expectedValue, $parsedValue);
        $this->assertEquals($expectedCurrency, $currency);
    }

    public function parseCurrencyProvider()
    {
        $df = $this->getDecimalFormatter();
        $cf = $this->getCurrencyFormatter();

        return array(
            array($df, 1, 1, ''),
            array($df, 1.1, 1.1, ''),
            array($cf, '$1.00', 1, 'USD'),
            array($cf, '€1.00', 1, 'EUR'),
            array($cf, 'R$1.00', 1, 'BRL')
        );
    }

    public function testParse()
    {
        $parsedValue = $this->formatter->parse('1');
        $this->assertInternalType('float', $parsedValue, '->parse() as double by default.');
        $this->assertEquals(1, $parsedValue);

        $parsedValue = $this->formatter->parse('1', \NumberFormatter::TYPE_DOUBLE, $position);
        $this->assertNull($position, '->parse() returns null to the $position reference if it doesn\'t had a defined value.');

        $position = 0;
        $parsedValue = $this->formatter->parse('1', \NumberFormatter::TYPE_DOUBLE, $position);
        $this->assertEquals(1, $position);

        $parsedValue = $this->formatter->parse('prefix1', \NumberFormatter::TYPE_DOUBLE);
        $this->assertFalse($parsedValue, '->parse() does not parse a number with a string prefix.');

        $parsedValue = $this->formatter->parse('1suffix', \NumberFormatter::TYPE_DOUBLE);
        $this->assertEquals(1, $parsedValue, '->parse() parses a number with a string suffix.');

        $position = 0;
        $parsedValue = $this->formatter->parse('1suffix', \NumberFormatter::TYPE_DOUBLE, $position);
        $this->assertEquals(1, $parsedValue);
        $this->assertEquals(1, $position, '->parse() ignores anything not a number before the number.');
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testParseTypeDefault()
    {
        $this->formatter->parse('1', \NumberFormatter::TYPE_DEFAULT);
    }

    public function testParseTypeInt32()
    {
        $parsedValue = $this->formatter->parse('1', \NumberFormatter::TYPE_INT32);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(1, $parsedValue);

        $parsedValue = $this->formatter->parse('1.1', \NumberFormatter::TYPE_INT32);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(1, $parsedValue, '->parse() TYPE_INT32 ignores the decimal part of a number and uses only the integer one.');

        // int 32 out of range
        $parsedValue = $this->formatter->parse('2,147,483,648', \NumberFormatter::TYPE_INT32);
        $this->assertFalse($parsedValue, '->parse() TYPE_INT32 returns false if the value is out of range.');
        $parsedValue = $this->formatter->parse('-2,147,483,649', \NumberFormatter::TYPE_INT32);
        $this->assertFalse($parsedValue, '->parse() TYPE_INT32 returns false if the value is out of range.');
    }

    public function testParseInt64()
    {
        // int 64 parsing
        $parsedValue = $this->formatter->parse('2,147,483,647', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(2147483647, $parsedValue);

        $parsedValue = $this->formatter->parse('-2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(-2147483648, $parsedValue);

        // int 64 using only 32 bit range strangeness
        $parsedValue = $this->formatter->parse('2,147,483,648', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(-2147483648, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range.');

        $parsedValue = $this->formatter->parse('-2,147,483,649', \NumberFormatter::TYPE_INT64);
        $this->assertInternalType('integer', $parsedValue);
        $this->assertEquals(2147483647, $parsedValue, '->parse() TYPE_INT64 does not use true 64 bit integers, using only the 32 bit range.');
    }

    public function testParseTypeDouble()
    {
        $parsedValue = $this->formatter->parse('1', \NumberFormatter::TYPE_DOUBLE);
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(1, $parsedValue);

        $parsedValue = $this->formatter->parse('1.1');
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(1.1, $parsedValue);

        $parsedValue = $this->formatter->parse('1,1');
        $this->assertInternalType('float', $parsedValue);
        $this->assertEquals(11, $parsedValue);
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Warning
     */
    public function testParseTypeCurrency()
    {
        $this->formatter->parse('1', \NumberFormatter::TYPE_CURRENCY);
    }
}
