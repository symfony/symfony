<?php

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Extension\Core\Type\MonthType;

class MonthTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = MonthType::class;

    public function testPassTypeToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertSame('month', $view->vars['type']);
    }

    public function testPassMinAttrToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'min' => '2018-03',
        ])
            ->createView();

        $this->assertSame('2018-03', $view->vars['attr']['min']);
    }

    public function testPassMaxAttrToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'max' => '2025-12',
        ])
            ->createView();

        $this->assertSame('2025-12', $view->vars['attr']['max']);
    }

    public function testPassMinAndMaxAttrsToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'min' => '2018-03',
            'max' => '2025-12',
        ])
            ->createView();

        $this->assertSame('2018-03', $view->vars['attr']['min']);
        $this->assertSame('2025-12', $view->vars['attr']['max']);
    }

    public function testPassStepAttrToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'step' => 3,
        ])
            ->createView();

        $this->assertSame(3, $view->vars['attr']['step']);
    }

    public function testUserAttrsAreNotOverridden()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'min' => '2018-03',
            'attr' => [
                'min' => '2017-01',
            ],
        ])
            ->createView();

        $this->assertSame('2017-01', $view->vars['attr']['min']);
    }

    public function testSubmit()
    {
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit('2018-05');

        $this->assertSame('2018-05', $form->getData());
        $this->assertSame('2018-05', $form->getViewData());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
