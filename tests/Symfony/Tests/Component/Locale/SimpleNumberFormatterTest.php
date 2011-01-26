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
    private $formatter = null;

    public function setUp()
    {
        $this->formatter = new SimpleNumberFormatter();
    }

    /**
     * @dataProvider formatCurrencyProvider
     */
    public function testFormatCurrency($value, $currency, $expected)
    {
        // just for testing purposes
        $f = new \NumberFormatter('en', \NumberFormatter::CURRENCY);

        $this->assertEquals(
            //$expected,
            $f->formatCurrency($value, $currency),
            $this->formatter->formatCurrency($value, $currency)
        );
    }

    public function formatCurrencyProvider()
    {
        return array(
            array(100, 'ALL', 'ALL100'),
            array(100, 'BRL', 'R$100.00'),
            array(100, 'CRC', '₡100')
        );
    }
}
