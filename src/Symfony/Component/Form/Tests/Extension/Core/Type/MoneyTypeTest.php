<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Intl\Util\IntlTestHelper;

class MoneyTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\MoneyType';

    protected function setUp()
    {
        // we test against different locales, so we need the full
        // implementation
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();
    }

    public function testPassMoneyPatternToView()
    {
        \Locale::setDefault('de_DE');

        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertSame('{{ widget }} €', $view->vars['money_pattern']);
    }

    public function testMoneyPatternWorksForYen()
    {
        \Locale::setDefault('en_US');

        $view = $this->factory->create(static::TESTED_TYPE, null, ['currency' => 'JPY'])
            ->createView();

        $this->assertSame('¥ {{ widget }}', $view->vars['money_pattern']);
    }

    // https://github.com/symfony/symfony/issues/5458
    public function testPassDifferentPatternsForDifferentCurrencies()
    {
        \Locale::setDefault('de_DE');

        $view1 = $this->factory->create(static::TESTED_TYPE, null, ['currency' => 'GBP'])->createView();
        $view2 = $this->factory->create(static::TESTED_TYPE, null, ['currency' => 'EUR'])->createView();

        $this->assertSame('{{ widget }} £', $view1->vars['money_pattern']);
        $this->assertSame('{{ widget }} €', $view2->vars['money_pattern']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testMoneyPatternWithoutCurrency()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, ['currency' => false])
            ->createView();

        $this->assertSame('{{ widget }}', $view->vars['money_pattern']);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = '10.00', $expectedData = 10.0)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }

    public function testDefaultFormattingWithDefaultRounding()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['scale' => 0]);
        $form->setData('12345.54321');

        $this->assertSame('12346', $form->createView()->vars['value']);
    }

    public function testDefaultFormattingWithSpecifiedRounding()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['scale' => 0, 'rounding_mode' => \NumberFormatter::ROUND_DOWN]);
        $form->setData('12345.54321');

        $this->assertSame('12345', $form->createView()->vars['value']);
    }
}
