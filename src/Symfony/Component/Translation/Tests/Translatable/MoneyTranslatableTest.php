<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Translatable;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translatable\MoneyTranslatable;
use Symfony\Contracts\Translation\TranslatorInterface;

class MoneyTranslatableTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }
    }

    /**
     * @dataProvider getValues()
     */
    public function testTrans(string $expected, MoneyTranslatable $parameter, string $locale)
    {
        $translator = $this->createMock(TranslatorInterface::class);
        // DecimalMoneyFormatter output may contain non-breakable spaces:
        // - this is done for good reasons
        // - output "style" changes depending on the PHP version
        // This normalization in only done here in the test so a new PHP version won't break the test
        $normalized = str_replace(["\u{202f}", "\u{a0}"], ['', ''], $parameter->trans($translator, $locale));
        $this->assertSame($expected, $normalized);
    }

    public function getValues(): iterable
    {
        $parameterEuros = new MoneyTranslatable(1000, 'EUR');
        $parameterDollars = new MoneyTranslatable(1000, 'USD');

        yield 'Euros in French' => ['1000,00€', $parameterEuros, 'fr_FR'];
        yield 'Euros in US English' => ['€1,000.00', $parameterEuros, 'en_US'];
        yield 'US Dollars in French' => ['1000,00$US', $parameterDollars, 'fr_FR'];
        yield 'US Dollars in US English' => ['$1,000.00', $parameterDollars, 'en_US'];

        if (\defined('\NumberFormatter::CURRENCY_ACCOUNTING')) {
            $parameterEuros = new MoneyTranslatable(-1000, 'EUR', \NumberFormatter::CURRENCY_ACCOUNTING);
            yield 'Accounting style in French' => ['(1000,00€)', $parameterEuros, 'fr_FR'];
            yield 'Accounting style in US English' => ['(€1,000.00)', $parameterEuros, 'en_US'];
        }
    }
}
