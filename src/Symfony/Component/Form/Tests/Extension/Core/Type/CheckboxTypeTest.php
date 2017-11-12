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

use Symfony\Component\Form\CallbackTransformer;

class CheckboxTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CheckboxType';

    public function testDataIsFalseByDefault(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $this->assertFalse($form->getData());
        $this->assertFalse($form->getNormData());
        $this->assertNull($form->getViewData());
    }

    public function testPassValueToView(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array('value' => 'foobar'))
            ->createView();

        $this->assertEquals('foobar', $view->vars['value']);
    }

    public function testCheckedIfDataTrue(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->setData(true)
            ->createView();

        $this->assertTrue($view->vars['checked']);
    }

    public function testCheckedIfDataTrueWithEmptyValue(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, array('value' => ''))
            ->setData(true)
            ->createView();

        $this->assertTrue($view->vars['checked']);
    }

    public function testNotCheckedIfDataFalse(): void
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->setData(false)
            ->createView();

        $this->assertFalse($view->vars['checked']);
    }

    public function testSubmitWithValueChecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => 'foobar',
        ));
        $form->submit('foobar');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithRandomValueChecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => 'foobar',
        ));
        $form->submit('krixikraxi');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithValueUnchecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => 'foobar',
        ));
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueChecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => '',
        ));
        $form->submit('');

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyValueUnchecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => '',
        ));
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndFalseUnchecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => '',
        ));
        $form->submit(false);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndTrueChecked(): void
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'value' => '',
        ));
        $form->submit(true);

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    /**
     * @dataProvider provideCustomModelTransformerData
     */
    public function testCustomModelTransformer($data, $checked): void
    {
        // present a binary status field as a checkbox
        $transformer = new CallbackTransformer(
            function ($value) {
                return 'checked' == $value;
            },
            function ($value) {
                return $value ? 'checked' : 'unchecked';
            }
        );

        $form = $this->factory->createBuilder(static::TESTED_TYPE)
            ->addModelTransformer($transformer)
            ->getForm();

        $form->setData($data);
        $view = $form->createView();

        $this->assertSame($data, $form->getData());
        $this->assertSame($checked, $form->getNormData());
        $this->assertEquals($checked, $view->vars['checked']);
    }

    public function provideCustomModelTransformerData()
    {
        return array(
            array('checked', true),
            array('unchecked', false),
        );
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null): void
    {
        parent::testSubmitNull(false, false, null);
    }
}
