<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

require_once __DIR__ . '/LocalizedTestCase.php';

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

    // https://github.com/symfony/symfony/issues/5458
    public function testPassDifferentPatternsForDifferentCurrencies()
    {
        \Locale::setDefault('de_DE');

        $form1 = $this->factory->create('money', null, array('currency' => 'GBP'));
        $form2 = $this->factory->create('money', null, array('currency' => 'EUR'));
        $view1 = $form1->createView();
        $view2 = $form2->createView();

        $this->assertSame('{{ widget }} £', $view1->get('money_pattern'));
        $this->assertSame('{{ widget }} €', $view2->get('money_pattern'));
    }
}
