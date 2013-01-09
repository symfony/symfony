<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\FormError;

abstract class AbstractTableLayoutTest extends AbstractLayoutTest
{
    public function testRow()
    {
        $form = $this->factory->createNamed('name', 'text');
        $form->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();
        $html = $this->renderRow($view);

        $this->assertMatchesXpath($html,
'/tr
    [
        ./td
            [./label[@for="name"]]
        /following-sibling::td
            [
                ./ul
                    [./li[.="[trans]Error![/trans]"]]
                    [count(./li)=1]
                /following-sibling::input[@id="name"]
            ]
    ]
'
        );
    }

    public function testLabelIsNotRenderedWhenSetToFalse()
    {
        $form = $this->factory->createNamed('name', 'text', null, array(
            'label' => false
        ));
        $html = $this->renderRow($form->createView());

        $this->assertMatchesXpath($html,
'/tr
    [
        ./td
            [count(//label)=0]
        /following-sibling::td
            [./input[@id="name"]]
    ]
'
        );
    }

    public function testRepeatedRow()
    {
        $form = $this->factory->createNamed('name', 'repeated');
        $html = $this->renderRow($form->createView());

        $this->assertMatchesXpath($html,
'/tr
    [
        ./td
            [./label[@for="name_first"]]
        /following-sibling::td
            [./input[@id="name_first"]]
    ]
/following-sibling::tr
    [
        ./td
            [./label[@for="name_second"]]
        /following-sibling::td
            [./input[@id="name_second"]]
    ]
/following-sibling::tr[@style="display: none"]
    [./td[@colspan="2"]/input
        [@type="hidden"]
        [@id="name__token"]
    ]
    [count(../tr)=3]
'
        );
    }

    public function testRepeatedRowWithErrors()
    {
        $form = $this->factory->createNamed('name', 'repeated');
        $form->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();
        $html = $this->renderRow($view);

        // The errors of the form are not rendered by intention!
        // In practice, repeated fields cannot have errors as all errors
        // on them are mapped to the first child.
        // (see RepeatedTypeValidatorExtension)

        $this->assertMatchesXpath($html,
'/tr
    [
        ./td
            [./label[@for="name_first"]]
        /following-sibling::td
            [./input[@id="name_first"]]
    ]
/following-sibling::tr
    [
        ./td
            [./label[@for="name_second"]]
        /following-sibling::td
            [./input[@id="name_second"]]
    ]
/following-sibling::tr[@style="display: none"]
    [./td[@colspan="2"]/input
        [@type="hidden"]
        [@id="name__token"]
    ]
    [count(../tr)=3]
'
        );
    }

    public function testRest()
    {
        $view = $this->factory->createNamedBuilder('name', 'form')
            ->add('field1', 'text')
            ->add('field2', 'repeated')
            ->add('field3', 'text')
            ->add('field4', 'text')
            ->getForm()
            ->createView();

        // Render field2 row -> does not implicitly call renderWidget because
        // it is a repeated field!
        $this->renderRow($view['field2']);

        // Render field3 widget
        $this->renderWidget($view['field3']);

        // Rest should only contain field1 and field4
        $html = $this->renderRest($view);

        $this->assertMatchesXpath($html,
'/tr
    [
        ./td
            [./label[@for="name_field1"]]
        /following-sibling::td
            [./input[@id="name_field1"]]
    ]
/following-sibling::tr
    [
        ./td
            [./label[@for="name_field4"]]
        /following-sibling::td
            [./input[@id="name_field4"]]
    ]
    [count(../tr)=3]
    [count(..//label)=2]
    [count(..//input)=3]
/following-sibling::tr[@style="display: none"]
    [./td[@colspan="2"]/input
        [@type="hidden"]
        [@id="name__token"]
    ]
'
        );
    }

    public function testCollection()
    {
        $form = $this->factory->createNamed('name', 'collection', array('a', 'b'), array(
            'type' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [
        ./tr[./td/input[@type="text"][@value="a"]]
        /following-sibling::tr[./td/input[@type="text"][@value="b"]]
        /following-sibling::tr[@style="display: none"][./td[@colspan="2"]/input[@type="hidden"][@id="name__token"]]
    ]
    [count(./tr[./td/input])=3]
'
        );
    }

    public function testEmptyCollection()
    {
        $form = $this->factory->createNamed('name', 'collection', array(), array(
            'type' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [./tr[@style="display: none"][./td[@colspan="2"]/input[@type="hidden"][@id="name__token"]]]
    [count(./tr[./td/input])=1]
'
        );
    }

    public function testForm()
    {
        $view = $this->factory->createNamedBuilder('name', 'form')
            ->add('firstName', 'text')
            ->add('lastName', 'text')
            ->getForm()
            ->createView();

        $this->assertWidgetMatchesXpath($view, array(),
'/table
    [
        ./tr
            [
                ./td
                    [./label[@for="name_firstName"]]
                /following-sibling::td
                    [./input[@id="name_firstName"]]
            ]
        /following-sibling::tr
            [
                ./td
                    [./label[@for="name_lastName"]]
                /following-sibling::td
                    [./input[@id="name_lastName"]]
            ]
        /following-sibling::tr[@style="display: none"]
            [./td[@colspan="2"]/input
                [@type="hidden"]
                [@id="name__token"]
            ]
    ]
    [count(.//input)=3]
'
        );
    }

    // https://github.com/symfony/symfony/issues/2308
    public function testNestedFormError()
    {
        $form = $this->factory->createNamedBuilder('name', 'form')
            ->add($this->factory
                ->createNamedBuilder('child', 'form', null, array('error_bubbling' => false))
                ->add('grandChild', 'form')
            )
            ->getForm();

        $form->get('child')->addError(new FormError('[trans]Error![/trans]'));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [
        ./tr/td/ul[./li[.="[trans]Error![/trans]"]]
        /following-sibling::table[@id="name_child"]
    ]
    [count(.//li[.="[trans]Error![/trans]"])=1]
'
        );
    }

    public function testCsrf()
    {
        $this->csrfProvider->expects($this->any())
            ->method('generateCsrfToken')
            ->will($this->returnValue('foo&bar'));

        $form = $this->factory->createNamedBuilder('name', 'form')
            ->add($this->factory
                // No CSRF protection on nested forms
                ->createNamedBuilder('child', 'form')
                ->add($this->factory->createNamedBuilder('grandchild', 'text'))
            )
            ->getForm();

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [
        ./tr[@style="display: none"]
            [./td[@colspan="2"]/input
                [@type="hidden"]
                [@id="name__token"]
            ]
    ]
    [count(.//input[@type="hidden"])=1]
'
        );
    }

    public function testRepeated()
    {
        $form = $this->factory->createNamed('name', 'repeated', 'foobar', array(
            'type' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [
        ./tr
            [
                ./td
                    [./label[@for="name_first"]]
                /following-sibling::td
                    [./input[@type="text"][@id="name_first"]]
            ]
        /following-sibling::tr
            [
                ./td
                    [./label[@for="name_second"]]
                /following-sibling::td
                    [./input[@type="text"][@id="name_second"]]
            ]
        /following-sibling::tr[@style="display: none"]
            [./td[@colspan="2"]/input
                [@type="hidden"]
                [@id="name__token"]
            ]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testRepeatedWithCustomOptions()
    {
        $form = $this->factory->createNamed('name', 'repeated', 'foobar', array(
            'type'           => 'password',
            'first_options'  => array('label' => 'Test', 'required' => false),
            'second_options' => array('label' => 'Test2')
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [
        ./tr
            [
                ./td
                    [./label[@for="name_first"][.="[trans]Test[/trans]"]]
                /following-sibling::td
                    [./input[@type="password"][@id="name_first"][@required="required"]]
            ]
        /following-sibling::tr
            [
                ./td
                    [./label[@for="name_second"][.="[trans]Test2[/trans]"]]
                /following-sibling::td
                    [./input[@type="password"][@id="name_second"][@required="required"]]
            ]
        /following-sibling::tr[@style="display: none"]
            [./td[@colspan="2"]/input
                [@type="hidden"]
                [@id="name__token"]
            ]
    ]
    [count(.//input)=3]
'
        );
    }

    /**
     * The block "_name_child_label" should be overridden in the theme of the
     * implemented driver.
     */
    public function testCollectionRowWithCustomBlock()
    {
        $collection = array('one', 'two', 'three');
        $form = $this->factory->createNamedBuilder('name', 'collection', $collection)
            ->getForm();

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/table
    [
        ./tr[./td/label[.="Custom label: [trans]0[/trans]"]]
        /following-sibling::tr[./td/label[.="Custom label: [trans]1[/trans]"]]
        /following-sibling::tr[./td/label[.="Custom label: [trans]2[/trans]"]]
    ]
'
        );
    }
}
