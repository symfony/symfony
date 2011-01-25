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

use Symfony\Component\Locale\NumberFormatter;

class NumberFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The intl extension is not available.');
        }
    }

    public function testConstructor()
    {
        $formatter = $this->createFormatter();
        $this->assertInstanceOf('Symfony\Component\Locale\NumberFormatter', $formatter);
    }

    public function testFormatCurrency()
    {
        $formatter = $this->createFormatter(NumberFormatter::CURRENCY);
        $this->assertEquals('R$1.000,00', $formatter->formatCurrency(1000, 'BRL'));
    }

    public function testFormat()
    {
        $formatter = $this->createFormatter();
        $this->assertEquals('1.000', $formatter->format(1000));
    }

    public function testGetAttribute()
    {
        $formatter = $this->createFormatter();
        $this->assertEquals(3, $formatter->getAttribute(NumberFormatter::MAX_FRACTION_DIGITS));
    }

    public function testGetErrorCode()
    {
        $formatter = $this->createFormatter();
        $this->assertInternalType('int', $formatter->getErrorCode());

        // It's strange but as NumberFormat::DEFAULT_STYLE have the same value
        // that NumberFormat::DECIMAL, this warning is triggered. The same
        // applies to getErrorMessage().
        // http://icu-project.org/apiref/icu4c/unum_8h.html
        $this->assertEquals(-127, $formatter->getErrorCode());
    }

    public function testGetErrorMessage()
    {
        $formatter = $this->createFormatter();
        $this->assertInternalType('string', $formatter->getErrorMessage());
        $this->assertEquals('U_USING_DEFAULT_WARNING', $formatter->getErrorMessage());
    }

    /**
     * @todo Update Locale class used (use the class from Locale component)
     */
    public function testGetLocale()
    {
        $formatter = $this->createFormatter();
        $this->assertEquals('pt', $formatter->getLocale(\Locale::ACTUAL_LOCALE));
        $this->assertEquals('pt_BR', $formatter->getLocale(\Locale::VALID_LOCALE));
    }

    public function testGetPattern()
    {
        $formatter = $this->createFormatter();
        $this->assertEquals('#,##0.###', $formatter->getPattern());
    }

    public function testGetSymbol()
    {
        $formatter = $this->createFormatter();
        $this->assertEquals('R$', $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL));
    }

    public function testGetTextAttribute()
    {
        $formatter = $this->createFormatter();
        $this->assertEquals('-', $formatter->getTextAttribute(NumberFormatter::NEGATIVE_PREFIX));
    }

    public function testParseCurrency()
    {
        $formatter = $this->createFormatter(NumberFormatter::CURRENCY);

        $position  = 0;
        $value = $formatter->parseCurrency('(US$1.000,00)', $currency, $position);

        $this->assertEquals(-1000, $value);
        $this->assertEquals('USD', $currency);
        $this->assertEquals(13, $position);
    }

    public function testParse()
    {
        $formatter = $this->createFormatter();

        $position = 0;
        $value = $formatter->parse('1.000,00', NumberFormatter::TYPE_DOUBLE, $position);

        $this->assertEquals(1000.00, $value);
        $this->assertEquals(8, $position);
    }

    public function testSetAttribute()
    {
        $formatter = $this->createFormatter();
        $this->assertTrue($formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2));
        $this->assertEquals(2, $formatter->getAttribute(NumberFormatter::MAX_FRACTION_DIGITS));
    }

    public function testSetPattern()
    {
        $formatter = $this->createFormatter();
        $this->assertTrue($formatter->setPattern('#,##0.###'));
        $this->assertEquals('#,##0.###', $formatter->getPattern());
    }

    public function testSetSymbol()
    {
        $formatter = $this->createFormatter();
        $this->assertTrue($formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, 'BRL'));
        $this->assertEquals('BRL', $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL));
    }

    public function testSetTextAttribute()
    {
        $formatter = $this->createFormatter();
        $this->assertTrue($formatter->setTextAttribute(NumberFormatter::POSITIVE_PREFIX, '+'));
        $this->assertEquals('+', $formatter->getTextAttribute(NumberFormatter::POSITIVE_PREFIX));
    }

    private function createFormatter($style = NumberFormatter::DECIMAL)
    {
        return new NumberFormatter('pt_BR', $style);
    }
}
