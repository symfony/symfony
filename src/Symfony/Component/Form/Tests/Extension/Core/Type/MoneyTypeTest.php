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

        $this->assertSame('{{ widget }} €', $view->vars['money_pattern']);
    }

    public function testMoneyPatternWorksForYen()
    {
        \Locale::setDefault('en_US');

        $form = $this->factory->create('money', null, array('currency' => 'JPY'));
        $view = $form->createView();
        $this->assertTrue((Boolean) strstr($view->vars['money_pattern'], '¥'));
    }

    /**
     * Because of the bug reported in the pull request #5216, the money type
     * object might cache the result of the first request and return the same
     * value on the second request with another currency
     */
    public function testMoneyPatternCacheBug()
    {
        \Locale::setDefault('lv_LV');

        $form = $this->factory->create('money', null, array('currency' => 'JPY'));
        $view = $form->createView();
        $this->assertTrue((Boolean) strstr($view->vars['money_pattern'], '¥'));

        $form = $this->factory->create('money', null, array('currency' => 'EUR'));
        $view = $form->createView();
        $this->assertSame('{{ widget }} €', $view->vars['money_pattern']);
    }
}
