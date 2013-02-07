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

class CheckboxTypeTest extends TypeTestCase
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

    public function testBindWithValueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => 'foobar',
        ));
        $form->bind('foobar');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testBindWithRandomValueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => 'foobar',
        ));
        $form->bind('krixikraxi');

        $this->assertTrue($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
    }

    public function testBindWithValueUnchecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => 'foobar',
        ));
        $form->bind(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
    }

    public function testBindWithEmptyValueChecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => '',
        ));
        $form->bind('');

        $this->assertTrue($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testBindWithEmptyValueUnchecked()
    {
        $form = $this->factory->create('checkbox', null, array(
            'value' => '',
        ));
        $form->bind(null);

        $this->assertFalse($form->getData());
        $this->assertNull($form->getViewData());
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
