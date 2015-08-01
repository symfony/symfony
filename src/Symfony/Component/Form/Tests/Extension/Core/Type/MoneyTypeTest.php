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

use Symfony\Component\Form\Test\TypeTestCase as TestCase;
use Symfony\Component\Intl\Util\IntlTestHelper;

class MoneyTypeTest extends TestCase
{
    protected function setUp()
    {
        // we test against different locales, so we need the full
        // implementation
        IntlTestHelper::requireFullIntl($this);

        parent::setUp();
    }

    public function testLegacyName()
    {
        $form = $this->factory->create('money');

        $this->assertSame('money', $form->getConfig()->getType()->getName());
    }

    public function testPassMoneyPatternToView()
    {
        \Locale::setDefault('de_DE');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\MoneyType');
        $view = $form->createView();

        $this->assertSame('{{ widget }} €', $view->vars['money_pattern']);
    }

    public function testMoneyPatternWorksForYen()
    {
        \Locale::setDefault('en_US');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\MoneyType', null, array('currency' => 'JPY'));
        $view = $form->createView();
        $this->assertTrue((bool) strstr($view->vars['money_pattern'], '¥'));
    }

    // https://github.com/symfony/symfony/issues/5458
    public function testPassDifferentPatternsForDifferentCurrencies()
    {
        \Locale::setDefault('de_DE');

        $form1 = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\MoneyType', null, array('currency' => 'GBP'));
        $form2 = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\MoneyType', null, array('currency' => 'EUR'));
        $view1 = $form1->createView();
        $view2 = $form2->createView();

        $this->assertSame('{{ widget }} £', $view1->vars['money_pattern']);
        $this->assertSame('{{ widget }} €', $view2->vars['money_pattern']);
    }
}
