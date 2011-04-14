<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FormError;

abstract class AbstractDivLayoutTest extends AbstractLayoutTest
{
    public function testRow()
    {
        $form = $this->factory->create('text', 'name');
        $form->addError(new FormError('Error!'));
        $context = $form->getContext();
        $html = $this->renderRow($context);

        $this->assertMatchesXpath($html,
'/div
    [
        ./label[@for="name"]
        /following-sibling::ul
            [./li[.="[trans]Error![/trans]"]]
            [count(./li)=1]
        /following-sibling::input[@id="name"]
    ]
'
        );
    }

    public function testRepeatedRow()
    {
        $form = $this->factory->create('repeated', 'name');
        $form->addError(new FormError('Error!'));
        $context = $form->getContext();
        $html = $this->renderRow($context);

        $this->assertMatchesXpath($html,
'/ul
    [./li[.="[trans]Error![/trans]"]]
    [count(./li)=1]
/following-sibling::div
    [
        ./label[@for="name_first"]
        /following-sibling::input[@id="name_first"]
    ]
/following-sibling::div
    [
        ./label[@for="name_second"]
        /following-sibling::input[@id="name_second"]
    ]
'
        );
    }

    public function testRest()
    {
        $context = $this->factory->createBuilder('form', 'name')
            ->add('field1', 'text')
            ->add('field2', 'repeated')
            ->add('field3', 'text')
            ->add('field4', 'text')
            ->getForm()
            ->getContext();

        // Render field2 row -> does not implicitely call renderWidget because
        // it is a repeated field!
        $this->renderRow($context['field2']);

        // Render field3 widget
        $this->renderWidget($context['field3']);

        // Rest should only contain field1 and field4
        $html = $this->renderRest($context);

        $this->assertMatchesXpath($html,
'/input
    [@type="hidden"]
    [@id="name__token"]
/following-sibling::div
    [
        ./label[@for="name_field1"]
        /following-sibling::input[@type="text"][@id="name_field1"]
    ]
/following-sibling::div
    [
        ./label[@for="name_field4"]
        /following-sibling::input[@type="text"][@id="name_field4"]
    ]
    [count(../div)=2]
    [count(..//label)=2]
    [count(..//input)=3]
'
        );
    }

    public function testCollection()
    {
        $form = $this->factory->create('collection', 'name', array(
            'type' => 'text',
            'data' => array('a', 'b'),
        ));

        $this->assertWidgetMatchesXpath($form->getContext(), array(),
'/div
    [
        ./div[./input[@type="text"][@value="a"]]
        /following-sibling::div[./input[@type="text"][@value="b"]]
    ]
    [count(./div[./input])=2]
'
        );
    }

    public function testForm()
    {
        $form = $this->factory->createBuilder('form', 'name')
            ->add('firstName', 'text')
            ->add('lastName', 'text')
            ->getForm();

        $this->assertWidgetMatchesXpath($form->getContext(), array(),
'/div
    [
        ./input[@type="hidden"][@id="name__token"]
        /following-sibling::div
            [
                ./label[@for="name_firstName"]
                /following-sibling::input[@type="text"][@id="name_firstName"]
            ]
        /following-sibling::div
            [
                ./label[@for="name_lastName"]
                /following-sibling::input[@type="text"][@id="name_lastName"]
            ]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testRepeated()
    {
        $form = $this->factory->create('repeated', 'name', array(
            'type' => 'text',
            'data' => 'foobar',
        ));

        $this->assertWidgetMatchesXpath($form->getContext(), array(),
'/div
    [
        ./div
            [
                ./label[@for="name_first"]
                /following-sibling::input[@type="text"][@id="name_first"]
            ]
        /following-sibling::div
            [
                ./label[@for="name_second"]
                /following-sibling::input[@type="text"][@id="name_second"]
            ]
    ]
    [count(.//input)=2]
'
        );
    }
}
