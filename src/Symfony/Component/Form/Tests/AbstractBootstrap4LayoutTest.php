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

/**
 * Abstract class providing test cases for the Bootstrap 4 Twig form theme.
 *
 * @author Hidde Wieringa <hidde@hiddewieringa.nl>
 */
abstract class AbstractBootstrap4LayoutTest extends AbstractBootstrap3LayoutTest
{
    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType');
        $view = $form->createView();
        $this->renderWidget($view, array('label' => 'foo'));
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/legend
    [@class="col-form-legend required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, array(
            'attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="form-control-label required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, array(
            'label_attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class form-control-label required"]
'
        );
    }

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), 'Custom label', array(
            'label_attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class form-control-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
            'label' => 'Custom label',
        ));
        $html = $this->renderLabel($form->createView(), null, array(
            'label_attr' => array(
                'class' => 'my&class',
            ),
        ));

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class form-control-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLegendOnExpandedType()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'label' => 'Custom label',
            'expanded' => true,
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
        ));
        $view = $form->createView();
        $this->renderWidget($view);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/legend
    [@class="col-form-legend required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testErrors()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $form->addError(new FormError('[trans]Error 1[/trans]'));
        $form->addError(new FormError('[trans]Error 2[/trans]'));
        $view = $form->createView();
        $html = $this->renderErrors($view);

        $this->assertMatchesXpath($html,
'/div
    [@class="alert alert-danger"]
    [
        ./ul
            [@class="list-unstyled mb-0"]
            [
                ./li
                    [.="[trans]Error 1[/trans]"]
                /following-sibling::li
                    [.="[trans]Error 2[/trans]"]
            ]
            [count(./li)=2]
    ]
'
        );
    }

    public function testCheckedCheckbox()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', true);

        $this->assertWidgetMatchesXpath($form->createView(), array('id' => 'my&id', 'attr' => array('class' => 'my&class')),
'/div
    [@class="form-check"]
    [
        ./label
            [.=" [trans]Name[/trans]"]
            [@class="form-check-label required"]
            [
                ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][@checked="checked"][@value="1"]
            ]
    ]
'
        );
    }

    public function testSingleChoiceAttributesWithMainAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => false,
            'attr' => array('class' => 'bar&baz'),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('attr' => array('class' => 'bar&baz')),
'/select
    [@name="name"]
    [@class="bar&baz form-control"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleExpandedChoiceAttributesWithMainAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
            'attr' => array('class' => 'bar&baz'),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('attr' => array('class' => 'bar&baz')),
'/div
    [@class="bar&baz"]
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testUncheckedCheckbox()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', false);

        $this->assertWidgetMatchesXpath($form->createView(), array('id' => 'my&id', 'attr' => array('class' => 'my&class')),
'/div
    [@class="form-check"]
    [
        ./label
            [.=" [trans]Name[/trans]"]
            [
                ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][not(@checked)]
            ]
    ]
'
        );
    }

    public function testCheckboxWithValue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', false, array(
            'value' => 'foo&bar',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('id' => 'my&id', 'attr' => array('class' => 'my&class')),
'/div
    [@class="form-check"]
    [
        ./label
            [.=" [trans]Name[/trans]"]
            [
                ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][@value="foo&bar"]
            ]
    ]
'
        );
    }

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsAsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_label' => false,
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsSetByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'),
            'choice_label' => function ($choice, $label, $value) {
                if ('&b' === $choice) {
                    return false;
                }

                return 'label.'.$value;
            },
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]label.&a[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]label.&c[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_2"][@value="&c"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsSetFalseByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_label' => function () {
                return false;
            },
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
            'choice_translation_domain' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&A"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&B"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_attr' => array('Choice&B' => array('class' => 'foo&bar')),
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)][@class="foo&bar form-check-input"]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
            'placeholder' => 'Test&Me',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Test&Me[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithPlaceholderWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choice_translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" Placeholder&Not&Translated"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&A"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&B"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithBooleanValue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', true, array(
            'choices' => array('Choice&A' => '1', 'Choice&B' => '0'),
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a', '&c'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'),
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&C[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsAsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_label' => false,
            'multiple' => true,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsSetByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'),
            'choice_label' => function ($choice, $label, $value) {
                if ('&b' === $choice) {
                    return false;
                }

                return 'label.'.$value;
            },
            'multiple' => true,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]label.&a[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]label.&c[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@value="&c"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsSetFalseByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_label' => function () {
                return false;
            },
            'multiple' => true,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a', '&c'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'),
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'choice_translation_domain' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&A"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&B"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" Choice&C"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a', '&c'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'),
            'choice_attr' => array('Choice&B' => array('class' => 'foo&bar')),
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&A[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&B[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)][@class="foo&bar form-check-input"]
                    ]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./label
                    [.=" [trans]Choice&C[/trans]"]
                    [
                        ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
                    ]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testCheckedRadio()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', true);

        $this->assertWidgetMatchesXpath($form->createView(), array('id' => 'my&id', 'attr' => array('class' => 'my&class')),
'/div
    [@class="form-check"]
    [
        ./label
            [@class="form-check-label required"]
            [
                ./input
                    [@id="my&id"]
                    [@type="radio"]
                    [@name="name"]
                    [@class="my&class form-check-input"]
                    [@checked="checked"]
                    [@value="1"]
            ]
    ]
'
        );
    }

    public function testUncheckedRadio()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', false);

        $this->assertWidgetMatchesXpath($form->createView(), array('id' => 'my&id', 'attr' => array('class' => 'my&class')),
'/div
    [@class="form-check"]
    [
        ./label
            [@class="form-check-label required"]
            [
                ./input
                    [@id="my&id"]
                    [@type="radio"]
                    [@name="name"]
                    [@class="my&class form-check-input"]
                    [not(@checked)]
            ]
    ]
'
        );
    }

    public function testRadioWithValue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', false, array(
            'value' => 'foo&bar',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('id' => 'my&id', 'attr' => array('class' => 'my&class')),
'/div
    [@class="form-check"]
    [
        ./label
            [@class="form-check-label required"]
            [
                ./input
                    [@id="my&id"]
                    [@type="radio"]
                    [@name="name"]
                    [@class="my&class form-check-input"]
                    [@value="foo&bar"]
            ]
    ]
'
        );
    }

    public function testButtonAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, array(
            'attr' => array('foo' => true),
        ));

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<button type="button" id="button" name="button" foo="foo" class="btn-secondary btn">[trans]Button[/trans]</button>', $html);
    }

    public function testFile()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\FileType');

        $this->assertWidgetMatchesXpath($form->createView(), array('attr' => array('class' => 'my&class form-control-file')),
'/input
    [@type="file"]
'
        );
    }
}
