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
    public function testPassValueToView()
    {
        $form = $this->factory->create('checkbox', null, array('value' => 'foobar'));
        $view = $form->createView();

        $this->assertEquals('foobar', $view->vars['value']);
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(true);
        $view = $form->createView();

        $this->assertTrue($view->vars['checked']);
    }

    public function testCheckedIfDataTrueWithEmptyValue()
    {
        $form = $this->factory->create('checkbox', null, array('value' => ''));
        $form->setData(true);
        $view = $form->createView();

        $this->assertTrue($view->vars['checked']);
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(false);
        $view = $form->createView();

        $this->assertFalse($view->vars['checked']);
    }

    public function testSubmitWithValueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => 'foobar',
        ));
        $form->submit('foobar');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithRandomValueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => 'foobar',
        ));
        $form->submit('krixikraxi');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testSubmitWithValueUnchecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => 'foobar',
        ));
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testSubmitWithEmptyValueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => '',
        ));
        $form->submit('');

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitWithEmptyValueUnchecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => '',
        ));
        $form->submit(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testBindWithEmptyValueAndFalseUnchecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => '',
        ));
        $form->bind(false);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testBindWithEmptyValueAndTrueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => '',
        ));
        $form->bind(true);

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    /**
     * @dataProvider provideTransformedData
     */
    public function testTransformedData($data, $expected)
    {
        // present a binary status field as a checkbox
        $transformer = new CallbackTransformer(
            function ($value) {
                return 'expedited' == $value;
            },
            function ($value) {
                return $value ? 'expedited' : 'standard';
            }
        );

        $form = $this->builder
            ->create('expedited_shipping', 'checkbox')
            ->addModelTransformer($transformer)
            ->getForm();
        $form->setData($data);
        $view = $form->createView();

        $this->assertEquals($expected, $view->vars['checked']);
    }

    public function provideTransformedData()
    {
        return array(
            array('expedited', true),
            array('standard', false),
        );
    }
}
