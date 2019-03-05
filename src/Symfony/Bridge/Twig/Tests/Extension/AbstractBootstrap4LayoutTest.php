<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;

/**
 * Abstract class providing test cases for the Bootstrap 4 Twig form theme.
 *
 * @author Hidde Wieringa <hidde@hiddewieringa.nl>
 */
abstract class AbstractBootstrap4LayoutTest extends AbstractBootstrap3LayoutTest
{
    public function testRow()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $form->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();
        $html = $this->renderRow($view);

        $this->assertMatchesXpath($html,
            '/div
    [
        ./label[@for="name"]
        [
            ./span[@class="alert alert-danger d-block"]
                [./span[@class="d-block"]
                    [./span[.="[trans]Error[/trans]"]]
                    [./span[.="[trans]Error![/trans]"]]
                ]
                [count(./span)=1]
        ]
        /following-sibling::input[@id="name"]
    ]
'
        );
    }

    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', DateType::class);
        $view = $form->createView();
        $this->renderWidget($view, ['label' => 'foo']);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/legend
    [@class="col-form-label required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $html = $this->renderLabel($form->createView(), null, [
            'attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class required"]
'
        );
    }

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $html = $this->renderLabel($form->createView(), 'Custom label', [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'label' => 'Custom label',
        ]);
        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [@class="my&class required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLegendOnExpandedType()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, null, [
            'label' => 'Custom label',
            'expanded' => true,
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
        ]);
        $view = $form->createView();
        $this->renderWidget($view);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/legend
    [@class="col-form-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testHelp()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'help' => 'Help text test!',
        ]);
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
'/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testHelpAttr()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help text test!',
            'help_attr' => [
                'class' => 'class-test',
            ],
        ]);
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="class-test form-text text-muted"]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testHelpHtmlDefaultIsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help <b>text</b> test!',
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    /b
    [.="text"]
', 0
        );
    }

    public function testHelpHtmlIsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help <b>text</b> test!',
            'help_html' => false,
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    /b
    [.="text"]
', 0
        );
    }

    public function testHelpHtmlIsTrue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help <b>text</b> test!',
            'help_html' => true,
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    [.="[trans]Help <b>text</b> test![/trans]"]
', 0
        );

        $this->assertMatchesXpath($html,
            '/small
    [@id="name_help"]
    [@class="form-text text-muted"]
    /b
    [.="text"]
'
        );
    }

    public function testErrors()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $form->addError(new FormError('[trans]Error 1[/trans]'));
        $form->addError(new FormError('[trans]Error 2[/trans]'));
        $view = $form->createView();
        $html = $this->renderErrors($view);

        $this->assertMatchesXpath($html,
'/span
    [@class="alert alert-danger d-block"]
    [
        ./span[@class="d-block"]
            [./span[.="[trans]Error[/trans]"]]
            [./span[.="[trans]Error 1[/trans]"]]

        /following-sibling::span[@class="d-block"]
            [./span[.="[trans]Error[/trans]"]]
            [./span[.="[trans]Error 2[/trans]"]]
    ]
    [count(./span)=2]
'
        );
    }

    public function testErrorWithNoLabel()
    {
        $form = $this->factory->createNamed('name', TextType::class, ['label' => false]);
        $form->addError(new FormError('[trans]Error 1[/trans]'));
        $view = $form->createView();
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html, '//span[.="[trans]Error[/trans]"]');
    }

    public function testCheckedCheckbox()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class, true);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="form-check"]
    [
        ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][@checked="checked"][@value="1"]
        /following-sibling::label
            [.="[trans]Name[/trans]"]
            [@class="form-check-label required"]
    ]
'
        );
    }

    public function testSingleChoiceAttributesWithMainAttributes()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'attr' => ['class' => 'bar&baz'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'bar&baz']],
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
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'attr' => ['class' => 'bar&baz'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'bar&baz']],
'/div
    [@class="bar&baz"]
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testUncheckedCheckbox()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class, false);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="form-check"]
    [
        ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][not(@checked)]
        /following-sibling::label
            [.="[trans]Name[/trans]"]
    ]
'
        );
    }

    public function testCheckboxWithValue()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class, false, [
            'value' => 'foo&bar',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="form-check"]
    [
        ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][@value="foo&bar"]
        /following-sibling::label
            [.="[trans]Name[/trans]"]
    ]
'
        );
    }

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsAsFalse()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => false,
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsSetByCallable()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'choice_label' => function ($choice, $label, $value) {
                if ('&b' === $choice) {
                    return false;
                }

                return 'label.'.$value;
            },
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
                    [.="[trans]label.&a[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_2"][@value="&c"][not(@checked)]
                /following-sibling::label
                    [.="[trans]label.&c[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsSetFalseByCallable()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => function () {
                return false;
            },
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'choice_translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
                    [.="Choice&A"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
                    [.="Choice&B"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedAttributes()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)][@class="foo&bar form-check-input"]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'placeholder' => 'Test&Me',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]
                /following-sibling::label
                    [.="[trans]Test&Me[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithPlaceholderWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choice_translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]
                /following-sibling::label
                    [.="Placeholder&Not&Translated"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
                /following-sibling::label
                    [.="Choice&A"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
                /following-sibling::label
                    [.="Choice&B"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithBooleanValue()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, true, [
            'choices' => ['Choice&A' => '1', 'Choice&B' => '0'],
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a', '&c'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
                /following-sibling::label
                    [.="[trans]Choice&C[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsAsFalse()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => false,
            'multiple' => true,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsSetByCallable()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'choice_label' => function ($choice, $label, $value) {
                if ('&b' === $choice) {
                    return false;
                }

                return 'label.'.$value;
            },
            'multiple' => true,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
                    [.="[trans]label.&a[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@value="&c"][not(@checked)]
                /following-sibling::label
                    [.="[trans]label.&c[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsSetFalseByCallable()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => function () {
                return false;
            },
            'multiple' => true,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]
                /following-sibling::label
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]
                /following-sibling::label
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a', '&c'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'choice_translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
                /following-sibling::label
                    [.="Choice&A"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]
                /following-sibling::label
                    [.="Choice&B"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
                /following-sibling::label
                    [.="Choice&C"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testMultipleChoiceExpandedAttributes()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a', '&c'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
                /following-sibling::label
                    [.="[trans]Choice&A[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)][@class="foo&bar form-check-input"]
                /following-sibling::label
                    [.="[trans]Choice&B[/trans]"]
            ]
        /following-sibling::div
            [@class="form-check"]
            [
                ./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
                /following-sibling::label
                    [.="[trans]Choice&C[/trans]"]
            ]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
'
        );
    }

    public function testCheckedRadio()
    {
        $form = $this->factory->createNamed('name', RadioType::class, true);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="form-check"]
    [
        ./input
            [@id="my&id"]
            [@type="radio"]
            [@name="name"]
            [@class="my&class form-check-input"]
            [@checked="checked"]
            [@value="1"]
        /following-sibling::label
            [@class="form-check-label required"]
    ]
'
        );
    }

    public function testUncheckedRadio()
    {
        $form = $this->factory->createNamed('name', RadioType::class, false);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="form-check"]
    [
        ./input
            [@id="my&id"]
            [@type="radio"]
            [@name="name"]
            [@class="my&class form-check-input"]
            [not(@checked)]
        /following-sibling::label
            [@class="form-check-label required"]
    ]
'
        );
    }

    public function testRadioWithValue()
    {
        $form = $this->factory->createNamed('name', RadioType::class, false, [
            'value' => 'foo&bar',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="form-check"]
    [
        ./input
            [@id="my&id"]
            [@type="radio"]
            [@name="name"]
            [@class="my&class form-check-input"]
            [@value="foo&bar"]
        /following-sibling::label
            [@class="form-check-label required"]
            [@for="my&id"]
    ]
'
        );
    }

    public function testButtonAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('button', ButtonType::class, null, [
            'attr' => ['foo' => true],
        ]);

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<button type="button" id="button" name="button" foo="foo" class="btn-secondary btn">[trans]Button[/trans]</button>', $html);
    }

    public function testFile()
    {
        $form = $this->factory->createNamed('name', FileType::class);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'n/a', 'attr' => ['class' => 'my&class form-control-file']],
'/div
    [@class="custom-file"]
    [
        ./input
            [@type="file"]
            [@name="name"]
        /following-sibling::label
            [@for="name"]
    ]
'
        );
    }

    public function testFileWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', FileType::class);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'n/a', 'attr' => ['class' => 'my&class form-control-file', 'placeholder' => 'Custom Placeholder']],
'/div
    [@class="custom-file"]
    [
        ./input
            [@type="file"]
            [@name="name"]
        /following-sibling::label
            [@for="name" and text() = "[trans]Custom Placeholder[/trans]"]
    ]
'
        );
    }

    public function testMoney()
    {
        $form = $this->factory->createNamed('name', MoneyType::class, 1234.56, [
            'currency' => 'EUR',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="input-group"]
    [
        ./div
            [@class="input-group-prepend"]
            [
                ./span
                    [@class="input-group-text"]
                    [contains(.., "€")]
            ]
        /following-sibling::input
            [@id="my&id"]
            [@type="text"]
            [@name="name"]
            [@class="my&class form-control"]
            [@value="1234.56"]
    ]
'
        );
    }

    public function testPercent()
    {
        $form = $this->factory->createNamed('name', PercentType::class, 0.1);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="input-group"]
    [
        ./input
            [@id="my&id"]
            [@type="text"]
            [@name="name"]
            [@class="my&class form-control"]
            [@value="10"]
            /following-sibling::div
                [@class="input-group-append"]
                [
                    ./span
                    [@class="input-group-text"]
                    [contains(.., "%")]
                ]
    ]
'
        );
    }

    public function testPercentNoSymbol()
    {
        $form = $this->factory->createNamed('name', PercentType::class, 0.1, ['symbol' => false]);
        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/input
    [@id="my&id"]
    [@type="text"]
    [@name="name"]
    [@class="my&class form-control"]
    [@value="10"]
'
        );
    }

    public function testPercentCustomSymbol()
    {
        $form = $this->factory->createNamed('name', PercentType::class, 0.1, ['symbol' => '‱']);
        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
'/div
    [@class="input-group"]
    [
        ./input
            [@id="my&id"]
            [@type="text"]
            [@name="name"]
            [@class="my&class form-control"]
            [@value="10"]
            /following-sibling::div
                [@class="input-group-append"]
                [
                    ./span
                    [@class="input-group-text"]
                    [contains(.., "‱")]
                ]
    ]
'
        );
    }
}
