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

class MoneyTypeTest extends LocalizedTestCase
{
    public function testPassMoneyPatternToView()
    {
        \Locale::setDefault('de_DE');

        $form = $this->factory->create('money');
        $view = $form->createView();

        $this->assertSame('{{ widget }} €', $view->get('money_pattern'));
    }

    public function testMoneyPatternWorksForYen()
    {
        \Locale::setDefault('en_US');

        $form = $this->factory->create('money', null, array('currency' => 'JPY'));
        $view = $form->createView();
        $this->assertTrue((Boolean) strstr($view->get('money_pattern'), '¥'));
    }

    /**
     * @dataProvider getLocalizedMoneyData
     */
    public function testThatValueIsLocalized($locale, $value, $expectedWidgetValue, $expectedDataValue)
    {
        \Locale::setDefault($locale);

        $form = $this->factory->create('money');
        $form->bind($value);
        $view = $form->createView();

        $this->assertEquals($expectedWidgetValue, $view->get('value'));
        $this->assertEquals($expectedDataValue, $form->getData());
    }

    public function testThatCanShowMoneyWithoutCurrency()
    {
        \Locale::setDefault('de_DE');

        $form = $this->factory->create('money', null, array('currency' => false));
        $view = $form->createView();

        $this->assertSame('{{ widget }}', $view->get('money_pattern'));
    }

    public function testThatShowsOnlyWidgetWhenCurrencyIsNonSense()
    {
        \Locale::setDefault('de_DE');

        $form = $this->factory->create('money', null, array('currency' => 'currencyFromSpace'));
        $view = $form->createView();

        $this->assertSame('{{ widget }}', $view->get('money_pattern'));
    }

    public static function getLocalizedMoneyData()
    {
        return array(
            array('en_EN', 12000, '12000.00', 12000),
            array('de_DE', 12000, '12000,00', 12000),
            array('pl_PL', '0,5312', '0,53', 0.5312),
        );
    }
}
