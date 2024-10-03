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

use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Intl\Util\IntlTestHelper;

class MoneyTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = MoneyType::class;

    private string $defaultLocale;

    protected function setUp(): void
    {
        // we test against different locales, so we need the full
        // implementation
        IntlTestHelper::requireFullIntl($this, false);

        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->defaultLocale);
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

    public function testHtml5EnablesSpecificFormatting()
    {
        // Since we test against "de_CH", we need the full implementation
        IntlTestHelper::requireFullIntl($this, false);

        \Locale::setDefault('de_CH');

        $form = $this->factory->create(static::TESTED_TYPE, null, ['html5' => true, 'scale' => 2]);
        $form->setData('12345.6');

        $this->assertSame('12345.60', $form->createView()->vars['value']);
        $this->assertSame('number', $form->createView()->vars['type']);
    }

    public function testDefaultInput()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['divisor' => 100]);
        $form->submit('12345.67');

        $this->assertSame(1234567.0, $form->getData());
    }

    public function testIntegerInput()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['divisor' => 100, 'input' => 'integer']);
        $form->submit('12345.67');

        $this->assertSame(1234567, $form->getData());
    }

    public function testIntegerInputWithoutDivisor()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'integer']);
        $form->submit('1234567');

        $this->assertSame(1234567, $form->getData());
    }
}
