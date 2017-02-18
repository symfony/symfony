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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Locale\Stub\StubLocale;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @group legacy
 */
class StubLocaleTest extends TestCase
{
    public function testGetCurrenciesData()
    {
        $currencies = StubLocale::getCurrenciesData('en');
        $this->assertEquals('R$', $currencies['BRL']['symbol']);
        $this->assertEquals('Brazilian Real', $currencies['BRL']['name']);
        $this->assertEquals(2, $currencies['BRL']['fractionDigits']);
        $this->assertEquals(0, $currencies['BRL']['roundingIncrement']);
    }

    public function testGetDisplayCurrencies()
    {
        $currencies = StubLocale::getDisplayCurrencies('en');
        $this->assertEquals('Brazilian Real', $currencies['BRL']);

        // Checking that the cache is being used
        $currencies = StubLocale::getDisplayCurrencies('en');
        $this->assertEquals('Argentine Peso', $currencies['ARS']);
    }

    public function testGetCurrencies()
    {
        $currencies = StubLocale::getCurrencies();
        $this->assertTrue(in_array('BRL', $currencies));
    }
}
