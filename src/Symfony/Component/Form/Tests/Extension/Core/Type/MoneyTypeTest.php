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
    const TESTED_TYPE = 'money';

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

        $this->assertSame('{{ widget }} &euro;', $view->vars['money_pattern']);
    }

    public function testMoneyPatternWorksForYen()
    {
        \Locale::setDefault('en_US');

        $view = $this->factory->create(static::TESTED_TYPE, null, array('currency' => 'JPY'))
            ->createView();

        $this->assertTrue((bool) strstr($view->vars['money_pattern'], '&yen;'));
    }

    // https://github.com/symfony/symfony/issues/5458
    public function testPassDifferentPatternsForDifferentCurrencies()
    {
        \Locale::setDefault('de_DE');

        $view1 = $this->factory->create(static::TESTED_TYPE, null, array('currency' => 'GBP'))->createView();
        $view2 = $this->factory->create(static::TESTED_TYPE, null, array('currency' => 'EUR'))->createView();

        $this->assertSame('{{ widget }} &pound;', $view1->vars['money_pattern']);
        $this->assertSame('{{ widget }} &euro;', $view2->vars['money_pattern']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
