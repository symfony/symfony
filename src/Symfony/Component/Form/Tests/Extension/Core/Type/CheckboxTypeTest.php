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

class CheckboxTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    /**
     * @group legacy
     */
    public function testLegacyName()
    {
        $form = $this->factory->create('checkbox');

        $this->assertSame('checkbox', $form->getConfig()->getType()->getName());
    }

    public function testDataIsFalseByDefault()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType');

        $this->assertFalse($form->getData());
        $this->assertFalse($form->getNormData());
        $this->assertNull($form->getViewData());
    }

    public function testPassValueToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array('value' => 'foobar'));
        $view = $form->createView();

        $this->assertEquals('foobar', $view->vars['value']);
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType');
        $form->setData(true);
        $view = $form->createView();

        $this->assertTrue($view->vars['checked']);
    }

    public function testCheckedIfDataTrueWithEmptyValue()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array('value' => ''));
        $form->setData(true);
        $view = $form->createView();

        $this->assertTrue($view->vars['checked']);
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType');
        $form->setData(false);
        $view = $form->createView();

        $this->assertFalse($view->vars['checked']);
    }

    public function testSubmitWithValueChecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => 'foobar',
        ));
        $form->submit('foobar');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithRandomValueChecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => 'foobar',
        ));
        $form->submit('krixikraxi');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithValueUnchecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => 'foobar',
        ));
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueChecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => '',
        ));
        $form->submit('');

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyValueUnchecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => '',
        ));
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndFalseUnchecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => '',
        ));
        $form->submit(false);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueAndTrueChecked()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CheckboxType', null, array(
            'value' => '',
        ));
        $form->submit(true);

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    /**
     * @dataProvider provideCustomModelTransformerData
     */
    public function testCustomModelTransformer($data, $checked)
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

        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\CheckboxType')
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
}
