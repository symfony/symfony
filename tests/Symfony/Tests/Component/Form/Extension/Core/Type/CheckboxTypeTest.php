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

use Symfony\Component\Form\CallbackTransformer;

class CheckboxTypeTest extends TypeTestCase
{
    public function testPassValueToView()
    {
        $form = $this->factory->create('checkbox', null, array('value' => 'foobar'));
        $view = $form->createView();

        $this->assertEquals('foobar', $view->get('value'));
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(true);
        $view = $form->createView();

        $this->assertTrue($view->get('checked'));
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(false);
        $view = $form->createView();

        $this->assertFalse($view->get('checked'));
    }

    /**
     * @dataProvider proviceTransformedData
     */
    public function testTransformedData($data, $expected)
    {
        // present a binary status field as a checkbox
        $transformer = new CallbackTransformer(
            function ($value)
            {
                return 'expedited' == $value;
            },
            function ($value)
            {
                return $value ? 'expedited' : 'standard';
            }
        );

        $form = $this->builder
            ->create('expedited_shipping', 'checkbox')
            ->prependClientTransformer($transformer)
            ->getForm();
        $form->setData($data);
        $view = $form->createView();

        $this->assertEquals($expected, $view->get('checked'));
    }

    public function proviceTransformedData()
    {
        return array(
            array('expedited', true),
            array('standard', false),
        );
    }
}
