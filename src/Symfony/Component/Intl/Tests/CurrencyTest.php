<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests;

use Symfony\Component\Intl\Currency;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CurrencyTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSymbol()
    {
        $this->assertSame('€', Currency::getSymbol('EUR', 'en'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetSymbolFailsOnInvalidCurrency()
    {
        Currency::getSymbol('FOO');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetSymbolFailsOnInvalidDisplayLocale()
    {
        Currency::getSymbol('EUR', 'foo');
    }

    public function testGetDisplayName()
    {
        $this->assertSame('Euro', Currency::getDisplayName('EUR', 'en'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetDisplayNameFailsOnInvalidCurrency()
    {
        Currency::getDisplayName('FOO');
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetDisplayNameFailsOnInvalidDisplayLocale()
    {
        Currency::getDisplayName('EUR', 'foo');
    }

    public function testGetDisplayNames()
    {
        $names = Currency::getDisplayNames('en');

        $this->assertArrayHasKey('EUR', $names);
        $this->assertSame('Euro', $names['EUR']);
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetDisplayNamesFailsOnInvalidDisplayLocale()
    {
        Currency::getDisplayNames('foo');
    }

    public function testGetFractionDigits()
    {
        $this->assertSame(2, Currency::getFractionDigits('EUR'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetFractionDigitsFailsOnInvalidCurrency()
    {
        Currency::getFractionDigits('FOO');
    }

    public function testGetRoundingIncrement()
    {
        $this->assertSame(0, Currency::getRoundingIncrement('EUR'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetRoundingIncrementFailsOnInvalidCurrency()
    {
        Currency::getRoundingIncrement('FOO');
    }

    public function testGetNumericCode()
    {
        $this->assertSame(978, Currency::getNumericCode('EUR'));
    }

    /**
     * @expectedException \Symfony\Component\Intl\Exception\InvalidArgumentException
     */
    public function testGetNumericCodeFailsOnInvalidCurrency()
    {
        Currency::getNumericCode('FOO');
    }
}
