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

use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\WeekType;
use Symfony\Component\Form\FormError;

/**
 * Abstract class providing test cases for the Bootstrap 5 Twig form theme.
 *
 * @author Romain Monteil <monteil.romain@gmail.com>
 */
abstract class AbstractBootstrap5LayoutTestCase extends AbstractBootstrap4LayoutTestCase
{
    public function testRow()
    {
        $form = $this->factory->createNamed('')->add('name', TextType::class);
        $form->get('name')->addError(new FormError('[trans]Error![/trans]'));
        $html = $this->renderRow($form->get('name')->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3"]
    [
        ./label[@for="name"]
        /following-sibling::input[@id="name"]
        /following-sibling::div
            [@class="invalid-feedback d-block"]
            [.="[trans]Error![/trans]"]
    ]
    [count(./div)=1]
'
        );
    }

    public function testRowWithCustomClass()
    {
        $form = $this->factory->createNamed('')->add('name', TextType::class);
        $form->get('name')->addError(new FormError('[trans]Error![/trans]'));
        $html = $this->renderRow($form->get('name')->createView(), [
            'row_attr' => [
                'class' => 'mb-5',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-5"]
    [
        ./label[@for="name"]
        /following-sibling::input[@id="name"]
        /following-sibling::div
            [@class="invalid-feedback d-block"]
            [.="[trans]Error![/trans]"]
    ]
    [count(./div)=1]
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
    [@class="form-label required"]
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
    [@class="my&class form-label required"]
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
    [@class="my&class form-label required"]
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
    [@class="my&class form-label required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelHtmlDefaultIsFalse()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'label' => '<b>Bolded label</b>',
        ]);

        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class form-label required"][.="[trans]<b>Bolded label</b>[/trans]"]');
        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class form-label required"]/b[.="Bolded label"]', 0);
    }

    public function testLabelHtmlIsTrue()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'label' => '<b>Bolded label</b>',
            'label_html' => true,
        ]);

        $html = $this->renderLabel($form->createView(), null, [
            'label_attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class form-label required"][.="[trans]<b>Bolded label</b>[/trans]"]', 0);
        $this->assertMatchesXpath($html, '/label[@for="name"][@class="my&class form-label required"]/b[.="Bolded label"]');
    }

    public function testHelp()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'help' => 'Help text test!',
        ]);
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testHelpAttr()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'help' => 'Help text test!',
            'help_attr' => [
                'class' => 'class-test',
            ],
        ]);
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="class-test form-text mb-0 help-text"]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testHelpHtmlDefaultIsFalse()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'help' => 'Help <b>text</b> test!',
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    /b
    [.="text"]
', 0
        );
    }

    public function testHelpHtmlIsFalse()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'help' => 'Help <b>text</b> test!',
            'help_html' => false,
        ]);

        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
'
        );

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    /b
    [.="text"]
', 0
        );
    }

    public function testHelpHtmlIsTrue()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'help' => 'Help <b>text</b> test!',
            'help_html' => true,
        ]);
        $html = $this->renderHelp($form->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    [.="[trans]Help <b>text</b> test![/trans]"]
', 0
        );

        $this->assertMatchesXpath($html,
            '/div
    [@id="name_help"]
    [@class="form-text mb-0 help-text"]
    /b
    [.="text"]
'
        );
    }

    public function testErrors()
    {
        self::markTestSkipped('This method has been split into testRootErrors() and testRowErrors().');
    }

    public function testRootErrors()
    {
        $form = $this->factory->createNamed('');
        $form->addError(new FormError('[trans]Error 1[/trans]'));
        $form->addError(new FormError('[trans]Error 2[/trans]'));
        $html = $this->renderErrors($form->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@class="alert alert-danger d-block"]
    [.="[trans]Error 1[/trans]"]
    /following-sibling::div
    [@class="alert alert-danger d-block"]
    [.="[trans]Error 2[/trans]"]
'
        );
    }

    public function testRowErrors()
    {
        $form = $this->factory->createNamed('')->add('name', TextType::class);
        $form->get('name')->addError(new FormError('[trans]Error 1[/trans]'));
        $form->get('name')->addError(new FormError('[trans]Error 2[/trans]'));
        $html = $this->renderErrors($form->get('name')->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@class="invalid-feedback d-block"]
    [.="[trans]Error 1[/trans]"]
    /following-sibling::div
    [@class="invalid-feedback d-block"]
    [.="[trans]Error 2[/trans]"]
'
        );
    }

    public function testErrorWithNoLabel()
    {
        self::markTestSkipped('Errors are no longer rendered inside label with Bootstrap 5.');
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
    [@class="bar&baz form-select"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testCheckboxRowWithHelp()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class);
        $html = $this->renderRow($form->createView(), ['label' => 'foo', 'help' => 'really helpful text']);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3"]
    [
        ./div[@class="form-check"]
        [
            ./input
                [@type="checkbox"]
                [@id="name"]
                [@name="name"]
                [@required="required"]
                [@aria-describedby="name_help"]
                [@class="form-check-input"]
                [@value="1"]
            /following-sibling::label
                [@class="form-check-label required"]
                [@for="name"]
                [.="[trans]foo[/trans]"]
        ]
        /following-sibling::div
            [@class="form-text mb-0 help-text"]
            [.="[trans]really helpful text[/trans]"]
    ]
'
        );
    }

    public function testCheckboxSwitchWithValue()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class, false, [
            'value' => 'foo&bar',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class'], 'label_attr' => ['class' => 'checkbox-switch']],
            '/div
    [@class="form-check form-switch"]
    [
        ./input[@type="checkbox"][@name="name"][@id="my&id"][@class="my&class form-check-input"][@value="foo&bar"]
        /following-sibling::label
            [@class="checkbox-switch form-check-label required"]
            [.="[trans]Name[/trans]"]
    ]
'
        );
    }

    public function testCheckboxToggleWithValue()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class, false, [
            'value' => 'foo&bar',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'btn-check my&class'], 'label_attr' => ['class' => 'btn btn-primary']],
            '/input[@type="checkbox"][@name="name"][@id="my&id"][@class="btn-check my&class"][@value="foo&bar"]
        /following-sibling::label
            [@class="btn btn-primary required"]
            [.="[trans]Name[/trans]"]
'
        );
    }

    public function testMultipleChoiceSkipsPlaceholder()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => true,
            'expanded' => false,
            'placeholder' => 'Test&Me',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name[]"]
    [@class="my&class form-select"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoice()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'choice_translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="Choice&A"]
        /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithPlaceholderWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value=""][not(@selected)][not(@disabled)][.="Placeholder&Not&Translated"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="Choice&A"]
        /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceAttributes()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][@class="foo&bar"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithPreferred()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => '-- sep --', 'attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.="-- sep --"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=4]
'
        );
    }

    public function testSingleChoiceWithSelectedPreferred()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&a'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => '-- sep --', 'attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&a"][not(@selected)][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.="-- sep --"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=4]
'
        );
    }

    public function testSingleChoiceWithPreferredAndNoSeparator()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => null, 'attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceWithPreferredAndBlankSeparator()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => '', 'attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.=""]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=4]
'
        );
    }

    public function testChoiceWithOnlyPreferred()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&a', '&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@class="my&class form-select"]
    [count(./option)=5]
'
        );
    }

    public function testSingleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value=""][.=""]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequiredNoneSelected()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, null, [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value=""][.=""]
        /following-sibling::option[@value="&a"][not(@selected)][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequiredWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'placeholder' => 'Select&Anything&Not&Me',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [
        ./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Anything&Not&Me[/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceRequiredWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => true,
            'multiple' => false,
            'expanded' => false,
            'placeholder' => 'Test&Me',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [@required="required"]
    [
        ./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Test&Me[/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceRequiredWithPlaceholderViaView()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => true,
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['placeholder' => '', 'attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [@required="required"]
    [
        ./option[@value=""][not(@selected)][not(@disabled)][.=""]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceGrouped()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, '&a', [
            'choices' => [
                'Group&1' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
                'Group&2' => ['Choice&C' => '&c'],
            ],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [./optgroup[@label="[trans]Group&1[/trans]"]
        [
            ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
            /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        ]
        [count(./option)=2]
    ]
    [./optgroup[@label="[trans]Group&2[/trans]"]
        [./option[@value="&c"][not(@selected)][.="[trans]Choice&C[/trans]"]]
        [count(./option)=1]
    ]
    [count(./optgroup)=2]
'
        );
    }

    public function testMultipleChoice()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => true,
            'multiple' => true,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name[]"]
    [@class="my&class form-select"]
    [@required="required"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testMultipleChoiceAttributes()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'required' => true,
            'multiple' => true,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name[]"]
    [@class="my&class form-select"]
    [@required="required"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][@class="foo&bar"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testMultipleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('name', ChoiceType::class, ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => false,
            'multiple' => true,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name[]"]
    [@class="my&class form-select"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testRadioRowWithHelp()
    {
        $form = $this->factory->createNamed('name', RadioType::class, false);
        $html = $this->renderRow($form->createView(), ['label' => 'foo', 'help' => 'really helpful text']);

        $this->assertMatchesXpath($html,
            '/div
    [@class="mb-3"]
    [
        ./div
            [@class="form-text mb-0 help-text"]
            [.="[trans]really helpful text[/trans]"]
    ]
'
        );
    }

    public function testFile()
    {
        $form = $this->factory->createNamed('name', FileType::class);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'n/a', 'attr' => ['class' => 'my&class']],
            '/input
    [@type="file"]
    [@name="name"]
    [@class="my&class form-control"]
'
        );
    }

    public function testFileLabelIdNotDuplicated()
    {
        $this->markTestSkipped('The Bootstrap 5 form theme does not use the file widget shipped with the Bootstrap 4 theme.');
    }

    public function testFileWithGroup()
    {
        $form = $this->factory->createNamed('name', FileType::class);
        $html = $this->renderRow($form->createView(), [
            'id' => 'n/a',
            'attr' => [
                'class' => 'my&class',
            ],
            'row_attr' => [
                'class' => 'input-group mb-3',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/div
    [@class="input-group mb-3"]
    [
        ./label
            [@class="input-group-text required"]
            [.="[trans]Name[/trans]"]
        /following-sibling::input
            [@type="file"]
            [@name="name"]
            [@class="my&class form-control"]
    ]
'
        );
    }

    public function testFileWithPlaceholder()
    {
        self::markTestSkipped('Placeholder does not apply on input file.');
    }

    public function testCountry()
    {
        $form = $this->factory->createNamed('name', CountryType::class, 'AT');

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>200]
'
        );
    }

    public function testCountryWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', CountryType::class, 'AT', [
            'placeholder' => 'Select&Country',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Country[/trans]"]]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>201]
'
        );
    }

    public function testDateTime()
    {
        $form = $this->factory->createNamed('name', DateTimeType::class, date('Y').'-02-03 04:05:06', [
            'input' => 'string',
            'with_seconds' => false,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [

                ./select
                    [@id="name_date_month"]
                    [@class="form-select"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [@class="form-select"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [@class="form-select"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
       /following-sibling::div
            [@class="visually-hidden"]
       /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_time_hour"]
                    [@class="form-select"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [@class="form-select"]
                    [./option[@value="5"][@selected="selected"]]
            ]
    ]
    [count(.//select)=5]
'
        );
    }

    public function testDateTimeWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', DateTimeType::class, null, [
            'input' => 'string',
            'placeholder' => 'Change&Me',
            'required' => false,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_date_month"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            ]
        /following-sibling::div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_time_hour"]
                    [@class="form-select"]
                    [./option[@value=""][.="[trans]Change&Me[/trans]"]]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [@class="form-select"]
                    [./option[@value=""][.="[trans]Change&Me[/trans]"]]
            ]
    ]
    [count(.//select)=5]
'
        );
    }

    public function testDateTimeWithHourAndMinute()
    {
        $data = ['year' => date('Y'), 'month' => '2', 'day' => '3', 'hour' => '4', 'minute' => '5'];

        $form = $this->factory->createNamed('name', DateTimeType::class, $data, [
            'input' => 'array',
            'required' => false,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_date_month"]
                    [@class="form-select"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [@class="form-select"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [@class="form-select"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
        /following-sibling::div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_time_hour"]
                    [@class="form-select"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [@class="form-select"]
                    [./option[@value="5"][@selected="selected"]]
            ]
    ]
    [count(.//select)=5]
'
        );
    }

    public function testDateTimeWithSeconds()
    {
        $form = $this->factory->createNamed('name', DateTimeType::class, date('Y').'-02-03 04:05:06', [
            'input' => 'string',
            'with_seconds' => true,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_date_month"]
                    [@class="form-select"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [@class="form-select"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [@class="form-select"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
        /following-sibling::div
            [@class="visually-hidden"]
        /following-sibling::div
            [@class="input-group"]
            [
                ./select
                    [@id="name_time_hour"]
                    [@class="form-select"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [@class="form-select"]
                    [./option[@value="5"][@selected="selected"]]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_time_second"]
                    [@class="form-select"]
                    [./option[@value="6"][@selected="selected"]]
            ]
    ]
    [count(.//select)=6]
'
        );
    }

    public function testDateTimeSingleText()
    {
        $form = $this->factory->createNamed('name', DateTimeType::class, '2011-02-03 04:05:06', [
            'input' => 'string',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./input
            [@type="date"]
            [@id="name_date"]
            [@name="name[date]"]
            [@class="form-control"]
            [@value="2011-02-03"]
        /following-sibling::input
            [@type="time"]
            [@id="name_time"]
            [@name="name[time]"]
            [@class="form-control"]
            [@value="04:05"]
    ]
'
        );
    }

    public function testDateChoice()
    {
        $form = $this->factory->createNamed('name', DateType::class, date('Y').'-02-03', [
            'input' => 'string',
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_month"]
                    [@class="form-select"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_day"]
                    [@class="form-select"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_year"]
                    [@class="form-select"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
            [count(./select)=3]
    ]
'
        );
    }

    public function testDateChoiceWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', DateType::class, null, [
            'input' => 'string',
            'widget' => 'choice',
            'placeholder' => 'Change&Me',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_month"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_day"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_year"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            ]
            [count(./select)=3]
    ]
'
        );
    }

    public function testDateChoiceWithPlaceholderOnYear()
    {
        $form = $this->factory->createNamed('name', DateType::class, null, [
            'input' => 'string',
            'widget' => 'choice',
            'required' => false,
            'placeholder' => ['year' => 'Change&Me'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_month"]
                    [@class="form-select"]
                    [./option[@value="1"]]
                /following-sibling::select
                    [@id="name_day"]
                    [@class="form-select"]
                    [./option[@value="1"]]
                /following-sibling::select
                    [@id="name_year"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            ]
            [count(./select)=3]
    ]
'
        );
    }

    public function testDateText()
    {
        $form = $this->factory->createNamed('name', DateType::class, '2011-02-03', [
            'input' => 'string',
            'widget' => 'text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./input
                    [@id="name_month"]
                    [@type="text"]
                    [@class="form-control"]
                    [@value="2"]
                /following-sibling::input
                    [@id="name_day"]
                    [@type="text"]
                    [@class="form-control"]
                    [@value="3"]
                /following-sibling::input
                    [@id="name_year"]
                    [@type="text"]
                    [@class="form-control"]
                    [@value="2011"]
            ]
            [count(./input)=3]
    ]
'
        );
    }

    public function testBirthDay()
    {
        $form = $this->factory->createNamed('name', BirthdayType::class, '2000-02-03', [
            'input' => 'string',
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_month"]
                    [@class="form-select"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_day"]
                    [@class="form-select"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_year"]
                    [@class="form-select"]
                    [./option[@value="2000"][@selected="selected"]]
            ]
            [count(./select)=3]
    ]
'
        );
    }

    public function testBirthDayWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', BirthdayType::class, '1950-01-01', [
            'input' => 'string',
            'placeholder' => '',
            'required' => false,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_month"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.=""]]
                    [./option[@value="1"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_day"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.=""]]
                    [./option[@value="1"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_year"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.=""]]
                    [./option[@value="1950"][@selected="selected"]]
            ]
            [count(./select)=3]
    ]
'
        );
    }

    public function testLanguage()
    {
        $form = $this->factory->createNamed('name', LanguageType::class, 'de');

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [./option[@value="de"][@selected="selected"][.="German"]]
    [count(./option)>200]
'
        );
    }

    public function testLocale()
    {
        $form = $this->factory->createNamed('name', LocaleType::class, 'de_AT');

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [./option[@value="de_AT"][@selected="selected"][.="German (Austria)"]]
    [count(./option)>200]
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
    [@class="input-group "]
    [
        ./span
            [@class="input-group-text"]
            [contains(.., "€")]
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
        $form = $this->factory->createNamed('name', PercentType::class, 0.1, ['rounding_mode' => \NumberFormatter::ROUND_CEILING]);

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
            /following-sibling::span
                [@class="input-group-text"]
                [contains(.., "%")]
    ]
'
        );
    }

    public function testPercentCustomSymbol()
    {
        $form = $this->factory->createNamed('name', PercentType::class, 0.1, ['symbol' => '‱', 'rounding_mode' => \NumberFormatter::ROUND_CEILING]);
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
            /following-sibling::span
                [@class="input-group-text"]
                [contains(.., "‱")]
    ]
'
        );
    }

    public function testRange()
    {
        $form = $this->factory->createNamed('name', RangeType::class, 42, ['attr' => ['min' => 5]]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/input
    [@type="range"]
    [@name="name"]
    [@value="42"]
    [@min="5"]
    [@class="my&class form-range"]
'
        );
    }

    public function testRangeWithMinMaxValues()
    {
        $form = $this->factory->createNamed('name', RangeType::class, 42, ['attr' => ['min' => 5, 'max' => 57]]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/input
    [@type="range"]
    [@name="name"]
    [@value="42"]
    [@min="5"]
    [@max="57"]
    [@class="my&class form-range"]
'
        );
    }

    public function testColor()
    {
        $color = '#0000ff';
        $form = $this->factory->createNamed('name', ColorType::class, $color);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/input
    [@type="color"]
    [@name="name"]
    [@class="my&class form-control form-control-color"]
    [@value="#0000ff"]
'
        );
    }

    public function testTime()
    {
        $form = $this->factory->createNamed('name', TimeType::class, '04:05:06', [
            'input' => 'string',
            'with_seconds' => false,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_hour"]
                    [@class="form-select"]
                    [not(@size)]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_minute"]
                    [@class="form-select"]
                    [not(@size)]
                    [./option[@value="5"][@selected="selected"]]
            ]
            [count(./select)=2]
    ]
'
        );
    }

    public function testTimeWithSeconds()
    {
        $form = $this->factory->createNamed('name', TimeType::class, '04:05:06', [
            'input' => 'string',
            'with_seconds' => true,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_hour"]
                    [@class="form-select"]
                    [not(@size)]
                    [./option[@value="4"][@selected="selected"]]
                    [count(./option)>23]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_minute"]
                    [@class="form-select"]
                    [not(@size)]
                    [./option[@value="5"][@selected="selected"]]
                    [count(./option)>59]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_second"]
                    [@class="form-select"]
                    [not(@size)]
                    [./option[@value="6"][@selected="selected"]]
                    [count(./option)>59]
            ]
            [count(./select)=3]
    ]
'
        );
    }

    public function testTimeText()
    {
        $form = $this->factory->createNamed('name', TimeType::class, '04:05:06', [
            'input' => 'string',
            'widget' => 'text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./input
                    [@type="text"]
                    [@id="name_hour"]
                    [@name="name[hour]"]
                    [@class="form-control"]
                    [@value="04"]
                    [@required="required"]
                    [not(@size)]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::input
                    [@type="text"]
                    [@id="name_minute"]
                    [@name="name[minute]"]
                    [@class="form-control"]
                    [@value="05"]
                    [@required="required"]
                    [not(@size)]
            ]
            [count(./input)=2]
    ]
'
        );
    }

    public function testTimeWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', TimeType::class, null, [
            'input' => 'string',
            'placeholder' => 'Change&Me',
            'required' => false,
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
        [@class="input-group"]
        [
            ./select
                [@id="name_hour"]
                [@class="form-select"]
                [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                [count(./option)>24]
            /following-sibling::span
                [@class="input-group-text"]
            /following-sibling::select
                [@id="name_minute"]
                [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                [count(./option)>60]
        ]
        [count(./select)=2]
    ]
'
        );
    }

    public function testTimeWithPlaceholderOnYear()
    {
        $form = $this->factory->createNamed('name', TimeType::class, null, [
            'input' => 'string',
            'required' => false,
            'placeholder' => ['hour' => 'Change&Me'],
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./div
            [@class="input-group"]
            [
                ./select
                    [@id="name_hour"]
                    [@class="form-select"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                    [count(./option)>24]
                /following-sibling::span
                    [@class="input-group-text"]
                /following-sibling::select
                    [@id="name_minute"]
                    [./option[@value="1"]]
                    [count(./option)>59]
            ]
            [count(./select)=2]
    ]
'
        );
    }

    public function testTimezone()
    {
        $form = $this->factory->createNamed('name', TimezoneType::class, 'Europe/Vienna');

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@name="name"]
    [@class="my&class form-select"]
    [not(@required)]
    [./option[@value="Europe/Vienna"][@selected="selected"][.="Europe / Vienna"]]
    [count(.//option)>200]
'
        );
    }

    public function testTimezoneWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', TimezoneType::class, null, [
            'placeholder' => 'Select&Timezone',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/select
    [@class="my&class form-select"]
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Timezone[/trans]"]]
    [count(.//option)>201]
'
        );
    }

    public function testWeekChoices()
    {
        $this->requiresFeatureSet(404);

        $data = ['year' => (int) date('Y'), 'week' => 1];

        $form = $this->factory->createNamed('name', WeekType::class, $data, [
            'input' => 'array',
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/div
    [@class="my&class"]
    [
        ./select
            [@id="name_year"]
            [@class="form-select"]
            [./option[@value="'.$data['year'].'"][@selected="selected"]]
        /following-sibling::select
            [@id="name_week"]
            [@class="form-select"]
            [./option[@value="'.$data['week'].'"][@selected="selected"]]
    ]
    [count(.//select)=2]'
        );
    }

    public function testFloatingLabel()
    {
        $form = $this->factory->createNamed('name', TextType::class, null, [
            'attr' => [
                'placeholder' => 'name',
            ],
            'row_attr' => [
                'class' => 'form-floating mb-3',
            ],
        ]);

        $html = $this->renderRow($form->createView());

        $this->assertMatchesXpath($html,
            '/div
    [@class="form-floating mb-3"]
    [
        ./input
            [@id="name"]
            [@placeholder="[trans]name[/trans]"]
        /following-sibling::label
            [@for="name"]
    ]
'
        );
    }
}
