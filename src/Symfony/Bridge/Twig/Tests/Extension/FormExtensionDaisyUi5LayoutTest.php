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

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;

class FormExtensionDaisyUi5LayoutTest extends AbstractDivLayoutTestCase
{
    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', null, ['widget' => 'choice']);
        $view = $form->createView();
        $this->renderWidget($view, ['label' => 'foo']);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
            '/label
    [@class="label required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), null, [
            'attr' => [
                'class' => 'my&class',
            ],
        ]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [@class="label required"]
'
        );
    }

    public function testHelp()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help text test!',
        ]);
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html,
            '/*[self::div or self::p]
    [@id="name_help"]
    [contains(@class, "mt-1")]
    [contains(@class, "label")]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testSingleChoiceAttributesWithMainAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'attr' => ['class' => 'bar&baz'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'bar&baz']],
            '/select
    [@name="name"]
    [contains(@class, "bar&baz")]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"][not(@id)][not(@name)]
        /following-sibling::option[@value="&b"][not(@class)][not(@selected)][.="[trans]Choice&B[/trans]"][not(@id)][not(@name)]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleExpandedChoiceAttributesWithMainAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'attr' => ['class' => 'bar&baz'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'bar&baz']],
            '/div
    [@class="bar&baz"]
    [
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
            [contains(., "[trans]Choice&A[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
            [contains(., "[trans]Choice&B[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testSingleChoiceExpandedWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'choice_translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
            [contains(., "Choice&A")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
            [contains(., "Choice&B")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testSingleChoiceExpandedAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
            [contains(., "[trans]Choice&A[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
            [contains(., "[trans]Choice&B[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testSingleChoiceExpandedWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => true,
            'placeholder' => 'Test&Me',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]]
            [contains(., "[trans]Test&Me[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@checked]]
            [contains(., "[trans]Choice&A[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]]
            [contains(., "[trans]Choice&B[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testSingleChoiceExpandedWithPlaceholderWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
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
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]]
            [contains(., "Placeholder&Not&Translated")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@checked]]
            [contains(., "Choice&A")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]]
            [contains(., "Choice&B")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testSingleChoiceExpandedWithBooleanValue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', true, [
            'choices' => ['Choice&A' => '1', 'Choice&B' => '0'],
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@checked]]
            [contains(., "[trans]Choice&A[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]]
            [contains(., "[trans]Choice&B[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a', '&c'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]]
            [contains(., "[trans]Choice&A[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]]
            [contains(., "[trans]Choice&B[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]]
            [contains(., "[trans]Choice&C[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testMultipleChoiceExpandedWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a', '&c'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'choice_translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]]
            [contains(., "Choice&A")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]]
            [contains(., "Choice&B")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]]
            [contains(., "Choice&C")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testMultipleChoiceExpandedAttributes()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a', '&c'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]]
            [contains(., "[trans]Choice&A[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]]
            [contains(., "[trans]Choice&B[/trans]")]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]]
            [contains(., "[trans]Choice&C[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testWidgetAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', [
            'attr' => ['foo' => true],
        ]);

        $html = $this->renderWidget($form->createView());

        $this->assertSame('<input type="text" id="text" name="text" required="required" foo="foo" class="input mt-2" value="value" />', $html);
    }

    public function testButtonAttributes()
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, [
            'disabled' => true,
            'attr' => ['class' => 'foobar', 'data-foo' => 'bar'],
        ]);

        $html = $this->renderWidget($form->createView());

        $this->assertSame('<button type="button" id="button" name="button" disabled="disabled" class="foobar btn" data-foo="bar">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, [
            'attr' => ['foo' => true],
        ]);

        $html = $this->renderWidget($form->createView());

        $this->assertSame('<button type="button" id="button" name="button" foo="foo" class="btn-neutral btn">[trans]Button[/trans]</button>', $html);
    }

    public function testWeekSingleText()
    {
        $form = $this->factory->createNamed('holidays', 'Symfony\Component\Form\Extension\Core\Type\WeekType', '1970-W01', [
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/input
    [@type="week"]
    [@name="holidays"]
    [contains(@class, "my&class")]
    [@value="1970-W01"]
'
        );
    }

    public function testWeekSingleTextNoHtml5()
    {
        $form = $this->factory->createNamed('holidays', 'Symfony\Component\Form\Extension\Core\Type\WeekType', '1970-W01', [
            'input' => 'string',
            'widget' => 'single_text',
            'html5' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/input
    [@type="text"]
    [@name="holidays"]
    [contains(@class, "my&class")]
    [@value="1970-W01"]
'
        );
    }

    public function testRowOverrideVariables()
    {
        $view = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType')->createView();
        $html = $this->renderRow($view, [
            'attr' => ['class' => 'my&class'],
            'label' => 'foo&bar',
            'label_attr' => ['class' => 'my&label&class'],
        ]);

        $this->assertMatchesXpath($html,
            '/div
    [
        ./label[@for="name"][@class="my&label&class required"][.="[trans]foo&bar[/trans]"]
        /following-sibling::input[@id="name"][contains(@class, "my&class")]
    ]
'
        );
    }

    public function testChoiceRowWithCustomBlock()
    {
        $form = $this->factory->createNamedBuilder('name_c', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', 'a', [
            'choices' => ['ChoiceA' => 'a', 'ChoiceB' => 'b'],
            'expanded' => true,
        ])
            ->getForm();

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[contains(., "[trans]ChoiceA[/trans]")]
        /following-sibling::label[contains(., "[trans]ChoiceB[/trans]")]
    ]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsAsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => false,
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
        /following-sibling::label[./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label[./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsSetByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'choice_label' => static function ($choice, $label, $value) {
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
        ./label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
            [contains(., "[trans]label.&a[/trans]")]
        /following-sibling::label
            [./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="radio"][@name="name"][@id="name_2"][@value="&c"][not(@checked)]]
            [contains(., "[trans]label.&c[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testSingleChoiceExpandedWithLabelsSetFalseByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => static fn () => false,
            'multiple' => false,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]]
        /following-sibling::label[./input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label[./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsAsFalse()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => false,
            'multiple' => true,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]]
        /following-sibling::label[./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label[./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsSetByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b', 'Choice&C' => '&c'],
            'choice_label' => static function ($choice, $label, $value) {
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
        ./label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]]
            [contains(., "[trans]label.&a[/trans]")]
        /following-sibling::label
            [./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label[contains(@class, "flex")]
            [./input[@type="checkbox"][@name="name[]"][@id="name_2"][@value="&c"][not(@checked)]]
            [contains(., "[trans]label.&c[/trans]")]
        /following-sibling::label
            [./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=4]
'
        );
    }

    public function testMultipleChoiceExpandedWithLabelsSetFalseByCallable()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_label' => static fn () => false,
            'multiple' => true,
            'expanded' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/div
    [
        ./label[./input[@type="checkbox"][@name="name[]"][@id="name_0"][@value="&a"][@checked]]
        /following-sibling::label[./input[@type="checkbox"][@name="name[]"][@id="name_1"][@value="&b"][not(@checked)]]
        /following-sibling::label[./input[@type="hidden"][@id="name__token"]]
    ]
    [count(.//input)=3]
'
        );
    }

    public function testFormErrorsRenderIdPerError()
    {
        $form = $this->factory->createNamed('name', TextType::class);
        $form->addError(new FormError('[trans]Error 1[/trans]'));
        $form->addError(new FormError('[trans]Error 2[/trans]'));

        $html = $this->renderErrors($form->createView());

        $this->assertMatchesXpath($html,
            '/ul
    [
        ./li[@id="name_error1"][@class="text-error"][.="[trans]Error 1[/trans]"]
        /following-sibling::li[@id="name_error2"][@class="text-error"][.="[trans]Error 2[/trans]"]
    ]
'
        );
    }

    public function testSubmitWidgetIncludesBtnClass()
    {
        $form = $this->factory->createNamedBuilder('post', \Symfony\Component\Form\Extension\Core\Type\FormType::class)
            ->add('go', SubmitType::class)
            ->getForm();

        $html = $this->renderWidget($form->get('go')->createView());

        $this->assertMatchesXpath($html,
            '/button
    [@type="submit"]
    [contains(concat(" ", normalize-space(@class), " "), " btn ")]
    [contains(concat(" ", normalize-space(@class), " "), " btn-primary ")]
    [not(contains(concat(" ", normalize-space(@class), " "), " btn btn "))]
'
        );
    }

    public function testFormWidgetSimpleHonorsCallerWidgetClass()
    {
        $form = $this->factory->createNamed('name', TextType::class);

        $html = $this->renderWidget($form->createView(), [
            'widget_class' => 'custom-widget',
        ]);

        $this->assertMatchesXpath($html,
            '/input
    [@type="text"]
    [@name="name"]
    [contains(concat(" ", normalize-space(@class), " "), " custom-widget ")]
    [not(contains(concat(" ", normalize-space(@class), " "), " input "))]
'
        );
    }

    public function testFileWidgetErrorClassNotDuplicated()
    {
        $form = $this->factory->createNamed('upload', FileType::class);
        $form->addError(new FormError('[trans]Error![/trans]'));

        $html = $this->renderWidget($form->createView());

        $this->assertMatchesXpath($html,
            '/input
    [@type="file"]
    [contains(concat(" ", normalize-space(@class), " "), " file-input ")]
    [contains(concat(" ", normalize-space(@class), " "), " file-input-error ")]
    [not(contains(concat(" ", normalize-space(@class), " "), " input-error "))]
'
        );
    }

    protected function getTemplatePaths(): array
    {
        return [
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ];
    }

    protected function getTwigExtensions(): array
    {
        return [
            new TranslationExtension(new StubTranslator()),
            new FormExtension(),
        ];
    }

    protected function getTwigGlobals(): array
    {
        return [
            'global' => '',
            'dynamic_template_name' => 'child_label',
        ];
    }

    protected function getThemes(): array
    {
        return [
            'daisyui_5_layout.html.twig',
            'custom_widgets.html.twig',
        ];
    }

    protected function assertWidgetMatchesXpath(FormView $view, array $vars, $xpath)
    {
        $html = $this->renderWidget($view, array_merge([
            'id' => 'my&id',
            'attr' => ['class' => 'my&class'],
        ], $vars));

        if (!isset($vars['id'])) {
            $xpath = trim($xpath).'
    [@id="my&id"]';
        }

        if (!isset($vars['attr']['class'])) {
            $xpath .= '
    [contains(@class, "my&class")]';
        }

        $this->assertMatchesXpath($html, $xpath);
    }
}
