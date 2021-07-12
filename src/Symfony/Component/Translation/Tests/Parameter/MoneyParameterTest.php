<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Parameter;

use Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Parameter\MoneyParameter;

class MoneyParameterTest extends TestCase
{
    /**
     * @dataProvider getValues()
     */
    public function testFormat(string $expected, MoneyParameter $parameter, string $locale)
    {
        // Non-breakable spaces are added differently depending the PHP version
        $cleaned = str_replace(["\u{202f}", "\u{a0}"], ['', ''], $parameter->format($locale));
        $this->assertSame($expected, $cleaned);
    }

    public function getValues(): iterable
    {
        $parameterEuros = new MoneyParameter(1000, 'EUR');
        $parameterDollars = new MoneyParameter(1000, 'USD');

        yield 'Euros in French' => ['1000,00€', $parameterEuros, 'fr_FR'];
        yield 'Euros in US English' => ['€1,000.00', $parameterEuros, 'en_US'];
        yield 'US Dollars in French' => ['1000,00$US', $parameterDollars, 'fr_FR'];
        yield 'US Dollars in US English' => ['$1,000.00', $parameterDollars, 'en_US'];

        if (version_compare(\PHP_VERSION, '7.4.1') >= 0) {
            $parameterEuros = new MoneyParameter(-1000, 'EUR', \NumberFormatter::CURRENCY_ACCOUNTING);
            yield 'Accounting style in French' => ['(1000,00€)', $parameterEuros, 'fr_FR'];
            yield 'Accounting style in US English' => ['(€1,000.00)', $parameterEuros, 'en_US'];
        }

        $parameterEuros = MoneyParameter::fromMoney(Money::EUR(100000));
        yield 'Euros in French from Money' => ['1000,00€', $parameterEuros, 'fr_FR'];
        yield 'Euros in US English from Money' => ['€1,000.00', $parameterEuros, 'en_US'];

        $parameterDollars = MoneyParameter::fromMoney(Money::USD(100000));
        yield 'US Dollars in French from Money' => ['1000,00$US', $parameterDollars, 'fr_FR'];
        yield 'US Dollars in US English from Money' => ['$1,000.00', $parameterDollars, 'en_US'];
    }
}
