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
        $form = $this->factory->createNamed('text', 'name');
        $form->addError(new FormError('Error!'));
        $view = $form->createView();
        $html = $this->renderRow($view);

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

    public function testRowForwardsVariables()
    {
        $view = $this->factory->createNamed('text', 'name')->createView();
        $html = $this->renderRow($view, array('label' => 'foo&bar'));

        $this->assertMatchesXpath($html,
'/div
    [
        ./label[@for="name"][.="[trans]foo&bar[/trans]"]
        /following-sibling::input[@id="name"]
    ]
'
        );
    }

    public function testRepeatedRow()
    {
        $form = $this->factory->createNamed('repeated', 'name');
        $form->addError(new FormError('Error!'));
        $view = $form->createView();
        $html = $this->renderRow($view);

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
        $view = $this->factory->createNamedBuilder('form', 'name')
            ->add('field1', 'text')
            ->add('field2', 'repeated')
            ->add('field3', 'text')
            ->add('field4', 'text')
            ->getForm()
            ->createView();

        // Render field2 row -> does not implicitely call renderWidget because
        // it is a repeated field!
        $this->renderRow($view['field2']);

        // Render field3 widget
        $this->renderWidget($view['field3']);

        // Rest should only contain field1 and field4
        $html = $this->renderRest($view);

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

    public function testRestWithChildrenForms()
    {
        $child1 = $this->factory->createNamedBuilder('form', 'child1')
            ->add('field1', 'text')
            ->add('field2', 'text')
            ->getForm();

        $child2 = $this->factory->createNamedBuilder('form', 'child2')
            ->add('field1', 'text')
            ->add('field2', 'text')
            ->getForm();

        $view = $this->factory->createNamedBuilder('form', 'parent')
            ->getForm()
            ->add($child1)
            ->add($child2)
            ->createView();

        // Render child1.field1 row
        $this->renderRow($view['child1']['field1']);

        // Render child2.field2 widget (remember that widget don't render label)
        $this->renderWidget($view['child2']['field2']);

        // Rest should only contain child1.field2 and child2.field1
        $html = $this->renderRest($view);

        $this->assertMatchesXpath($html,
'/input
    [@type="hidden"]
    [@id="parent__token"]
/following-sibling::div
    [
        ./label[@for="parent_child1"]
        /following-sibling::div
            [@id="parent_child1"]
            [
                ./div
                    [
                        ./label[@for="parent_child1_field2"]
                        /following-sibling::input[@id="parent_child1_field2"]
                    ]
            ]
    ]
/following-sibling::div
    [
        ./label[@for="parent_child2"]
        /following-sibling::div
            [@id="parent_child2"]
            [
                ./div
                    [
                        ./label[@for="parent_child2_field1"]
                        /following-sibling::input[@id="parent_child2_field1"]
                    ]
                /following-sibling::div
                    [
                        ./label[@for="parent_child2_field2"]
                    ]
            ]
    ]
'
        );
    }

    public function testRestAndRepeatedWithRow()
    {
        $view = $this->factory->createNamedBuilder('form', 'name')
            ->add('first', 'text')
            ->add('password', 'repeated')
            ->getForm()
            ->createView();

        $this->renderRow($view['password']);

        $html = $this->renderRest($view);

        $this->assertMatchesXpath($html,
'/input
    [@type="hidden"]
    [@id="name__token"]
/following-sibling::div
    [
        ./label[@for="name_first"]
        /following-sibling::input[@type="text"][@id="name_first"]
    ]
    [count(.//input)=1]
'
        );
    }

    public function testRestAndRepeatedWithRowPerField()
    {
        $view = $this->factory->createNamedBuilder('form', 'name')
            ->add('first', 'text')
            ->add('password', 'repeated')
            ->getForm()
            ->createView();

        $this->renderRow($view['password']['first']);
        $this->renderRow($view['password']['second']);

        $html = $this->renderRest($view);

        $this->assertMatchesXpath($html,
'/input
    [@type="hidden"]
    [@id="name__token"]
/following-sibling::div
    [
        ./label[@for="name_first"]
        /following-sibling::input[@type="text"][@id="name_first"]
    ]
    [count(.//input)=1]
    [count(.//label)=1]
'
        );
    }

    public function testRestAndRepeatedWithWidgetPerField()
    {
        $view = $this->factory->createNamedBuilder('form', 'name')
            ->add('first', 'text')
            ->add('password', 'repeated')
            ->getForm()
            ->createView();

        // The password form is considered as rendered as all its childrend
        // are rendered
        $this->renderWidget($view['password']['first']);
        $this->renderWidget($view['password']['second']);

        $html = $this->renderRest($view);

        $this->assertMatchesXpath($html,
'/input
    [@type="hidden"]
    [@id="name__token"]
/following-sibling::div
    [
        ./label[@for="name_first"]
        /following-sibling::input[@type="text"][@id="name_first"]
    ]
    [count(//input)=2]
    [count(//label)=1]
'
        );
    }

    public function testCollection()
    {
        $form = $this->factory->createNamed('collection', 'name', array('a', 'b'), array(
            'type' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div[./input[@type="text"][@value="a"]]
        /following-sibling::div[./input[@type="text"][@value="b"]]
    ]
    [count(./div[./input])=2]
'
        );
    }

    public function testCollectionRow()
    {
        $collection = $this->factory->createNamedBuilder(
            'collection',
            'collection',
            array('a', 'b'),
            array('type' => 'text')
        );

        $form = $this->factory->createNamedBuilder('form', 'form')
          ->add($collection)
          ->getForm();

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="hidden"][@id="form__token"]
        /following-sibling::div
            [
                ./label[not(@for)]
                /following-sibling::div
                    [
                        ./div
                            [
                                ./label[@for="form_collection_0"]
                                /following-sibling::input[@type="text"][@value="a"]
                            ]
                        /following-sibling::div
                            [
                                ./label[@for="form_collection_1"]
                                /following-sibling::input[@type="text"][@value="b"]
                            ]
                    ]
            ]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testForm()
    {
        $form = $this->factory->createNamedBuilder('form', 'name')
            ->add('firstName', 'text')
            ->add('lastName', 'text')
            ->getForm();

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('repeated', 'name', 'foobar', array(
            'type' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
