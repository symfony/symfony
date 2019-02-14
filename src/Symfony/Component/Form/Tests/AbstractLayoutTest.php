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

use PHPUnit\Framework\SkippedTestError;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\FormIntegrationTestCase;

abstract class AbstractLayoutTest extends FormIntegrationTestCase
{
    use VersionAwareTest;

    protected $csrfTokenManager;
    protected $testableFeatures = [];

    protected function setUp()
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }

        \Locale::setDefault('en');

        $this->csrfTokenManager = $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();

        parent::setUp();
    }

    protected function getExtensions()
    {
        return [
            new CsrfExtension($this->csrfTokenManager),
        ];
    }

    protected function tearDown()
    {
        $this->csrfTokenManager = null;

        parent::tearDown();
    }

    protected function assertXpathNodeValue(\DOMElement $element, $expression, $nodeValue)
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodeList = $xpath->evaluate($expression);
        $this->assertEquals(1, $nodeList->length);
        $this->assertEquals($nodeValue, $nodeList->item(0)->nodeValue);
    }

    protected function assertMatchesXpath($html, $expression, $count = 1)
    {
        $dom = new \DOMDocument('UTF-8');
        try {
            // Wrap in <root> node so we can load HTML with multiple tags at
            // the top level
            $dom->loadXML('<root>'.$html.'</root>');
        } catch (\Exception $e) {
            $this->fail(sprintf(
                "Failed loading HTML:\n\n%s\n\nError: %s",
                $html,
                $e->getMessage()
            ));
        }
        $xpath = new \DOMXPath($dom);
        $nodeList = $xpath->evaluate('/root'.$expression);

        if ($nodeList->length != $count) {
            $dom->formatOutput = true;
            $this->fail(sprintf(
                "Failed asserting that \n\n%s\n\nmatches exactly %s. Matches %s in \n\n%s",
                $expression,
                1 == $count ? 'once' : $count.' times',
                1 == $nodeList->length ? 'once' : $nodeList->length.' times',
                // strip away <root> and </root>
                substr($dom->saveHTML(), 6, -8)
            ));
        } else {
            $this->addToAssertionCount(1);
        }
    }

    protected function assertWidgetMatchesXpath(FormView $view, array $vars, $xpath)
    {
        // include ampersands everywhere to validate escaping
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
    [@class="my&class"]';
        }

        $this->assertMatchesXpath($html, $xpath);
    }

    abstract protected function renderForm(FormView $view, array $vars = []);

    abstract protected function renderLabel(FormView $view, $label = null, array $vars = []);

    protected function renderHelp(FormView $view)
    {
        $this->markTestSkipped(sprintf('%s::renderHelp() is not implemented.', \get_class($this)));
    }

    abstract protected function renderErrors(FormView $view);

    abstract protected function renderWidget(FormView $view, array $vars = []);

    abstract protected function renderRow(FormView $view, array $vars = []);

    abstract protected function renderRest(FormView $view, array $vars = []);

    abstract protected function renderStart(FormView $view, array $vars = []);

    abstract protected function renderEnd(FormView $view, array $vars = []);

    abstract protected function setTheme(FormView $view, array $themes, $useDefaultThemes = true);

    public function testLabel()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $view = $form->createView();
        $this->renderWidget($view, ['label' => 'foo']);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'translation_domain' => false,
        ]);

        $this->assertMatchesXpath($this->renderLabel($form->createView()),
'/label
    [@for="name"]
    [.="Name"]
'
        );
    }

    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType');
        $view = $form->createView();
        $this->renderWidget($view, ['label' => 'foo']);
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@class="required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedAsOption()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'label' => 'Custom label',
        ]);
        $html = $this->renderLabel($form->createView());

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderLabel($form->createView(), 'Custom label');

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedAsOptionAndDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'label' => 'Custom label',
        ]);
        $html = $this->renderLabel($form->createView(), 'Overridden label');

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Overridden label[/trans]"]
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
    [@class="required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
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

    // https://github.com/symfony/symfony/issues/5029
    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
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

    public function testLabelFormatName()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();
        $view = $form->get('myfield')->createView();
        $html = $this->renderLabel($view, null, ['label_format' => 'form.%name%']);

        $this->assertMatchesXpath($html,
'/label
    [@for="myform_myfield"]
    [.="[trans]form.myfield[/trans]"]
'
        );
    }

    public function testLabelFormatId()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();
        $view = $form->get('myfield')->createView();
        $html = $this->renderLabel($view, null, ['label_format' => 'form.%id%']);

        $this->assertMatchesXpath($html,
'/label
    [@for="myform_myfield"]
    [.="[trans]form.myform_myfield[/trans]"]
'
        );
    }

    public function testLabelFormatAsFormOption()
    {
        $options = ['label_format' => 'form.%name%'];

        $form = $this->factory->createNamedBuilder('myform', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, $options)
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();
        $view = $form->get('myfield')->createView();
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@for="myform_myfield"]
    [.="[trans]form.myfield[/trans]"]
'
        );
    }

    public function testLabelFormatOverriddenOption()
    {
        $options = ['label_format' => 'form.%name%'];

        $form = $this->factory->createNamedBuilder('myform', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, $options)
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['label_format' => 'field.%name%'])
            ->getForm();
        $view = $form->get('myfield')->createView();
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@for="myform_myfield"]
    [.="[trans]field.myfield[/trans]"]
'
        );
    }

    public function testLabelWithoutTranslationOnButton()
    {
        $form = $this->factory->createNamedBuilder('myform', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'translation_domain' => false,
            ])
            ->add('mybutton', 'Symfony\Component\Form\Extension\Core\Type\ButtonType')
            ->getForm();
        $view = $form->get('mybutton')->createView();
        $html = $this->renderWidget($view);

        $this->assertMatchesXpath($html,
'/button
    [@type="button"]
    [@name="myform[mybutton]"]
    [.="Mybutton"]
'
        );
    }

    public function testLabelFormatOnButton()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'Symfony\Component\Form\Extension\Core\Type\ButtonType')
            ->getForm();
        $view = $form->get('mybutton')->createView();
        $html = $this->renderWidget($view, ['label_format' => 'form.%name%']);

        $this->assertMatchesXpath($html,
'/button
    [@type="button"]
    [@name="myform[mybutton]"]
    [.="[trans]form.mybutton[/trans]"]
'
        );
    }

    public function testLabelFormatOnButtonId()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'Symfony\Component\Form\Extension\Core\Type\ButtonType')
            ->getForm();
        $view = $form->get('mybutton')->createView();
        $html = $this->renderWidget($view, ['label_format' => 'form.%id%']);

        $this->assertMatchesXpath($html,
'/button
    [@type="button"]
    [@name="myform[mybutton]"]
    [.="[trans]form.myform_mybutton[/trans]"]
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
'/p
    [@id="name_help"]
    [@class="help-text"]
    [.="[trans]Help text test![/trans]"]
'
        );
    }

    public function testHelpNotSet()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $view = $form->createView();
        $html = $this->renderHelp($view);

        $this->assertMatchesXpath($html, '/p', 0);
    }

    public function testHelpSetLinkFromWidget()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'help' => 'Help text test!',
        ]);
        $view = $form->createView();
        $html = $this->renderRow($view);

        // Test if renderHelp method is implemented (throw SkippedTestError if not)
        $this->renderHelp($view);

        $this->assertMatchesXpath($html,
'//input
    [@aria-describedby="name_help"]
'
        );
    }

    public function testHelpNotSetNotLinkedFromWidget()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $view = $form->createView();
        $html = $this->renderRow($view);

        // Test if renderHelp method is implemented (throw SkippedTestError if not)
        $this->renderHelp($view);

        $this->assertMatchesXpath($html,
'//input
    [not(@aria-describedby)]
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
'/ul
    [
        ./li[.="[trans]Error 1[/trans]"]
        /following-sibling::li[.="[trans]Error 2[/trans]"]
    ]
    [count(./li)=2]
'
        );
    }

    public function testOverrideWidgetBlock()
    {
        // see custom_widgets.html.twig
        $form = $this->factory->createNamed('text_id', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $html = $this->renderWidget($form->createView());

        $this->assertMatchesXpath($html,
'/div
    [
        ./input
        [@type="text"]
        [@id="text_id"]
    ]
    [@id="container"]
'
        );
    }

    public function testCheckedCheckbox()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', true);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="checkbox"]
    [@name="name"]
    [@checked="checked"]
    [@value="1"]
'
        );
    }

    public function testUncheckedCheckbox()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', false);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="checkbox"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testCheckboxWithValue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', false, [
            'value' => 'foo&bar',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="checkbox"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testSingleChoice()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        // If the field is collapsed, has no "multiple" attribute, is required but
        // has *no* empty value, the "required" must not be added, otherwise
        // the resulting HTML is invalid.
        // https://github.com/symfony/symfony/issues/8942

        // HTML 5 spec
        // http://www.w3.org/html/wg/drafts/html/master/forms.html#placeholder-label-option

        // "If a select element has a required attribute specified, does not
        //  have a multiple attribute specified, and has a display size of 1,
        //  then the select element must have a placeholder label option."

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSelectWithSizeBiggerThanOneCanBeRequired()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, [
            'choices' => ['a', 'b'],
            'multiple' => false,
            'expanded' => false,
            'attr' => ['size' => 2],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [@required="required"]
    [@size="2"]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'choice_translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][@class="foo&bar"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
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
    [@class="bar&baz"]
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
        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testSingleChoiceWithPreferred()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => '-- sep --'],
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.="-- sep --"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceWithPreferredAndNoSeparator()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => null],
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithPreferredAndBlankSeparator()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['separator' => ''],
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.=""]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testChoiceWithOnlyPreferred()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'preferred_choices' => ['&a', '&b'],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'placeholder' => 'Select&Anything&Not&Me',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => true,
            'multiple' => false,
            'expanded' => false,
            'placeholder' => 'Test&Me',
        ]);

        // The "disabled" attribute was removed again due to a bug in the
        // BlackBerry 10 browser.
        // See https://github.com/symfony/symfony/pull/7678
        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => true,
            'multiple' => false,
            'expanded' => false,
        ]);

        // The "disabled" attribute was removed again due to a bug in the
        // BlackBerry 10 browser.
        // See https://github.com/symfony/symfony/pull/7678
        $this->assertWidgetMatchesXpath($form->createView(), ['placeholder' => ''],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', [
            'choices' => [
                'Group&1' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
                'Group&2' => ['Choice&C' => '&c'],
            ],
            'multiple' => false,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => true,
            'multiple' => true,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name[]"]
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
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'choice_attr' => ['Choice&B' => ['class' => 'foo&bar']],
            'required' => true,
            'multiple' => true,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name[]"]
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

    public function testMultipleChoiceSkipsPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'multiple' => true,
            'expanded' => false,
            'placeholder' => 'Test&Me',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name[]"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testMultipleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', ['&a'], [
            'choices' => ['Choice&A' => '&a', 'Choice&B' => '&b'],
            'required' => false,
            'multiple' => true,
            'expanded' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name[]"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=2]
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
        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=3]
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
        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
        /following-sibling::label[@for="name_0"][.="Choice&A"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="Choice&B"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=3]
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
        ./input[@type="radio"][@name="name"][@id="name_0"][@value="&a"][@checked]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][@value="&b"][@class="foo&bar"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=3]
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
        ./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]
        /following-sibling::label[@for="name_placeholder"][.="[trans]Test&Me[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_0"][@checked]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=4]
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
        ./input[@type="radio"][@name="name"][@id="name_placeholder"][not(@checked)]
        /following-sibling::label[@for="name_placeholder"][.="Placeholder&Not&Translated"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_0"][@checked]
        /following-sibling::label[@for="name_0"][.="Choice&A"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="Choice&B"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=4]
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
        ./input[@type="radio"][@name="name"][@id="name_0"][@checked]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_1"][not(@checked)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=3]
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
        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
        /following-sibling::label[@for="name_2"][.="[trans]Choice&C[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=4]
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
        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
        /following-sibling::label[@for="name_0"][.="Choice&A"]
        /following-sibling::input[@type="checkbox"][@name="name[]"][@id="name_1"][not(@checked)][not(@required)]
        /following-sibling::label[@for="name_1"][.="Choice&B"]
        /following-sibling::input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
        /following-sibling::label[@for="name_2"][.="Choice&C"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=4]
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
        ./input[@type="checkbox"][@name="name[]"][@id="name_0"][@checked][not(@required)]
        /following-sibling::label[@for="name_0"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="name[]"][@id="name_1"][@class="foo&bar"][not(@checked)][not(@required)]
        /following-sibling::label[@for="name_1"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="name[]"][@id="name_2"][@checked][not(@required)]
        /following-sibling::label[@for="name_2"][.="[trans]Choice&C[/trans]"]
        /following-sibling::input[@type="hidden"][@id="name__token"]
    ]
    [count(./input)=4]
'
        );
    }

    public function testCountry()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CountryType', 'AT');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>200]
'
        );
    }

    public function testCountryWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CountryType', 'AT', [
            'placeholder' => 'Select&Country',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Country[/trans]"]]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>201]
'
        );
    }

    public function testDateTime()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', date('Y').'-02-03 04:05:06', [
            'input' => 'string',
            'with_seconds' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@id="name_date"]
            [
                ./select
                    [@id="name_date_month"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
        /following-sibling::div
            [@id="name_time"]
            [
                ./select
                    [@id="name_time_hour"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [./option[@value="5"][@selected="selected"]]
            ]
    ]
    [count(.//select)=5]
'
        );
    }

    public function testDateTimeWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', null, [
            'input' => 'string',
            'placeholder' => 'Change&Me',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@id="name_date"]
            [
                ./select
                    [@id="name_date_month"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            ]
        /following-sibling::div
            [@id="name_time"]
            [
                ./select
                    [@id="name_time_hour"]
                    [./option[@value=""][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_time_minute"]
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

        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', $data, [
            'input' => 'array',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@id="name_date"]
            [
                ./select
                    [@id="name_date_month"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
        /following-sibling::div
            [@id="name_time"]
            [
                ./select
                    [@id="name_time_hour"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [./option[@value="5"][@selected="selected"]]
            ]
    ]
    [count(.//select)=5]
'
        );
    }

    public function testDateTimeWithSeconds()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', date('Y').'-02-03 04:05:06', [
            'input' => 'string',
            'with_seconds' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./div
            [@id="name_date"]
            [
                ./select
                    [@id="name_date_month"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [./option[@value="'.date('Y').'"][@selected="selected"]]
            ]
        /following-sibling::div
            [@id="name_time"]
            [
                ./select
                    [@id="name_time_hour"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_time_minute"]
                    [./option[@value="5"][@selected="selected"]]
                /following-sibling::select
                    [@id="name_time_second"]
                    [./option[@value="6"][@selected="selected"]]
            ]
    ]
    [count(.//select)=6]
'
        );
    }

    public function testDateTimeSingleText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', '2011-02-03 04:05:06', [
            'input' => 'string',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./input
            [@type="date"]
            [@id="name_date"]
            [@name="name[date]"]
            [@value="2011-02-03"]
        /following-sibling::input
            [@type="time"]
            [@id="name_time"]
            [@name="name[time]"]
            [@value="04:05"]
    ]
'
        );
    }

    public function testDateTimeWithWidgetSingleText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', '2011-02-03 04:05:06', [
            'input' => 'string',
            'widget' => 'single_text',
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="datetime-local"]
    [@name="name"]
    [@value="2011-02-03T04:05:06"]
'
        );
    }

    public function testDateTimeWithWidgetSingleTextIgnoreDateAndTimeWidgets()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', '2011-02-03 04:05:06', [
            'input' => 'string',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'widget' => 'single_text',
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="datetime-local"]
    [@name="name"]
    [@value="2011-02-03T04:05:06"]
'
        );
    }

    public function testDateChoice()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', date('Y').'-02-03', [
            'input' => 'string',
            'widget' => 'choice',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value="2"][@selected="selected"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value="3"][@selected="selected"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value="'.date('Y').'"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testDateChoiceWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', null, [
            'input' => 'string',
            'widget' => 'choice',
            'placeholder' => 'Change&Me',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testDateChoiceWithPlaceholderOnYear()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', null, [
            'input' => 'string',
            'widget' => 'choice',
            'required' => false,
            'placeholder' => ['year' => 'Change&Me'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value="1"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value="1"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testDateText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', '2011-02-03', [
            'input' => 'string',
            'widget' => 'text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./input
            [@id="name_month"]
            [@type="text"]
            [@value="2"]
        /following-sibling::input
            [@id="name_day"]
            [@type="text"]
            [@value="3"]
        /following-sibling::input
            [@id="name_year"]
            [@type="text"]
            [@value="2011"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testDateSingleText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', '2011-02-03', [
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="date"]
    [@name="name"]
    [@value="2011-02-03"]
'
        );
    }

    public function testDateErrorBubbling()
    {
        $form = $this->factory->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('date', 'Symfony\Component\Form\Extension\Core\Type\DateType')
            ->getForm();
        $form->get('date')->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();

        $this->assertEmpty($this->renderErrors($view));
        $this->assertNotEmpty($this->renderErrors($view['date']));
    }

    public function testBirthDay()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\BirthdayType', '2000-02-03', [
            'input' => 'string',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value="2"][@selected="selected"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value="3"][@selected="selected"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value="2000"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testBirthDayWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\BirthdayType', '1950-01-01', [
            'input' => 'string',
            'placeholder' => '',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value=""][not(@selected)][not(@disabled)][.=""]]
            [./option[@value="1"][@selected="selected"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value=""][not(@selected)][not(@disabled)][.=""]]
            [./option[@value="1"][@selected="selected"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value=""][not(@selected)][not(@disabled)][.=""]]
            [./option[@value="1950"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testEmail()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="email"]
    [@name="name"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testEmailWithMaxLength()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType', 'foo&bar', [
            'attr' => ['maxlength' => 123],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="email"]
    [@name="name"]
    [@value="foo&bar"]
    [@maxlength="123"]
'
        );
    }

    public function testFile()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\FileType');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="file"]
'
        );
    }

    public function testHidden()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\HiddenType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="hidden"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testDisabled()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, [
            'disabled' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@disabled="disabled"]
'
        );
    }

    public function testInteger()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\IntegerType', 123);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="number"]
    [@name="name"]
    [@value="123"]
'
        );
    }

    public function testIntegerTypeWithGroupingRendersAsTextInput()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\IntegerType', 123, [
            'grouping' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="123"]
'
        );
    }

    public function testLanguage()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\LanguageType', 'de');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [./option[@value="de"][@selected="selected"][.="German"]]
    [count(./option)>200]
'
        );
    }

    public function testLocale()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\LocaleType', 'de_AT');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [./option[@value="de_AT"][@selected="selected"][.="German (Austria)"]]
    [count(./option)>200]
'
        );
    }

    public function testMoney()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\MoneyType', 1234.56, [
            'currency' => 'EUR',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
    [contains(.., "€")]
'
        );
    }

    public function testNumber()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\NumberType', 1234.56);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
'
        );
    }

    public function testPassword()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="password"]
    [@name="name"]
'
        );
    }

    public function testPasswordSubmittedWithNotAlwaysEmpty()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', null, [
            'always_empty' => false,
        ]);
        $form->submit('foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="password"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testPasswordWithMaxLength()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', 'foo&bar', [
            'attr' => ['maxlength' => 123],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="password"]
    [@name="name"]
    [@maxlength="123"]
'
        );
    }

    public function testPercent()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PercentType', 0.1);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="10"]
    [contains(.., "%")]
'
        );
    }

    public function testCheckedRadio()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', true);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="radio"]
    [@name="name"]
    [@checked="checked"]
    [@value="1"]
'
        );
    }

    public function testUncheckedRadio()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', false);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="radio"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testRadioWithValue()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', false, [
            'value' => 'foo&bar',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="radio"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testRange()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RangeType', 42, ['attr' => ['min' => 5]]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="range"]
    [@name="name"]
    [@value="42"]
    [@min="5"]
'
        );
    }

    public function testRangeWithMinMaxValues()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RangeType', 42, ['attr' => ['min' => 5, 'max' => 57]]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="range"]
    [@name="name"]
    [@value="42"]
    [@min="5"]
    [@max="57"]
'
        );
    }

    public function testTextarea()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextareaType', 'foo&bar', [
            'attr' => ['pattern' => 'foo'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/textarea
    [@name="name"]
    [@pattern="foo"]
    [.="foo&bar"]
'
        );
    }

    public function testText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testTextWithMaxLength()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'foo&bar', [
            'attr' => ['maxlength' => 123],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="foo&bar"]
    [@maxlength="123"]
'
        );
    }

    public function testSearch()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\SearchType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="search"]
    [@name="name"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testTime()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', [
            'input' => 'string',
            'with_seconds' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_hour"]
            [not(@size)]
            [./option[@value="4"][@selected="selected"]]
        /following-sibling::select
            [@id="name_minute"]
            [not(@size)]
            [./option[@value="5"][@selected="selected"]]
    ]
    [count(./select)=2]
'
        );
    }

    public function testTimeWithSeconds()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', [
            'input' => 'string',
            'with_seconds' => true,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_hour"]
            [not(@size)]
            [./option[@value="4"][@selected="selected"]]
            [count(./option)>23]
        /following-sibling::select
            [@id="name_minute"]
            [not(@size)]
            [./option[@value="5"][@selected="selected"]]
            [count(./option)>59]
        /following-sibling::select
            [@id="name_second"]
            [not(@size)]
            [./option[@value="6"][@selected="selected"]]
            [count(./option)>59]
    ]
    [count(./select)=3]
'
        );
    }

    public function testTimeText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', [
            'input' => 'string',
            'widget' => 'text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./input
            [@type="text"]
            [@id="name_hour"]
            [@name="name[hour]"]
            [@value="04"]
            [@size="1"]
            [@required="required"]
        /following-sibling::input
            [@type="text"]
            [@id="name_minute"]
            [@name="name[minute]"]
            [@value="05"]
            [@size="1"]
            [@required="required"]
    ]
    [count(./input)=2]
'
        );
    }

    public function testTimeSingleText()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', [
            'input' => 'string',
            'widget' => 'single_text',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="time"]
    [@name="name"]
    [@value="04:05"]
    [not(@size)]
'
        );
    }

    public function testTimeWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', null, [
            'input' => 'string',
            'placeholder' => 'Change&Me',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_hour"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            [count(./option)>24]
        /following-sibling::select
            [@id="name_minute"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            [count(./option)>60]
    ]
    [count(./select)=2]
'
        );
    }

    public function testTimeWithPlaceholderOnYear()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', null, [
            'input' => 'string',
            'required' => false,
            'placeholder' => ['hour' => 'Change&Me'],
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/div
    [
        ./select
            [@id="name_hour"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Change&Me[/trans]"]]
            [count(./option)>24]
        /following-sibling::select
            [@id="name_minute"]
            [./option[@value="1"]]
            [count(./option)>59]
    ]
    [count(./select)=2]
'
        );
    }

    public function testTimeErrorBubbling()
    {
        $form = $this->factory->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('time', 'Symfony\Component\Form\Extension\Core\Type\TimeType')
            ->getForm();
        $form->get('time')->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();

        $this->assertEmpty($this->renderErrors($view));
        $this->assertNotEmpty($this->renderErrors($view['time']));
    }

    public function testTimezone()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimezoneType', 'Europe/Vienna');

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [@name="name"]
    [not(@required)]
    [./optgroup
        [@label="Europe"]
        [./option[@value="Europe/Vienna"][@selected="selected"][.="Vienna"]]
    ]
    [count(./optgroup)>10]
    [count(.//option)>200]
'
        );
    }

    public function testTimezoneWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimezoneType', null, [
            'placeholder' => 'Select&Timezone',
            'required' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/select
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Timezone[/trans]"]]
    [count(./optgroup)>10]
    [count(.//option)>201]
'
        );
    }

    public function testUrlWithDefaultProtocol()
    {
        $url = 'http://www.google.com?foo1=bar1&foo2=bar2';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\UrlType', $url, ['default_protocol' => 'http']);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="text"]
    [@name="name"]
    [@value="http://www.google.com?foo1=bar1&foo2=bar2"]
    [@inputmode="url"]
'
        );
    }

    public function testUrlWithoutDefaultProtocol()
    {
        $url = 'http://www.google.com?foo1=bar1&foo2=bar2';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\UrlType', $url, ['default_protocol' => null]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
'/input
    [@type="url"]
    [@name="name"]
    [@value="http://www.google.com?foo1=bar1&foo2=bar2"]
'
        );
    }

    public function testCollectionPrototype()
    {
        $form = $this->factory->createNamedBuilder('name', 'Symfony\Component\Form\Extension\Core\Type\FormType', ['items' => ['one', 'two', 'three']])
            ->add('items', 'Symfony\Component\Form\Extension\Core\Type\CollectionType', ['allow_add' => true])
            ->getForm()
            ->createView();

        $html = $this->renderWidget($form);

        $this->assertMatchesXpath($html,
            '//div[@id="name_items"][@data-prototype]
            |
            //table[@id="name_items"][@data-prototype]'
        );
    }

    public function testEmptyRootFormName()
    {
        $form = $this->factory->createNamedBuilder('', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $this->assertMatchesXpath($this->renderWidget($form->createView()),
            '//input[@type="hidden"][@id="_token"][@name="_token"]
            |
             //input[@type="text"][@id="child"][@name="child"]', 2);
    }

    public function testButton()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ButtonType');

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/button[@type="button"][@name="name"][.="[trans]Name[/trans]"]'
        );
    }

    public function testButtonLabelIsEmpty()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ButtonType');

        $this->assertSame('', $this->renderLabel($form->createView()));
    }

    public function testButtonlabelWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, [
            'translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/button[@type="button"][@name="name"][.="Name"]'
        );
    }

    public function testSubmit()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\SubmitType');

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/button[@type="submit"][@name="name"]'
        );
    }

    public function testReset()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ResetType');

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/button[@type="reset"][@name="name"]'
        );
    }

    public function testStartTag()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory">', $html);
    }

    public function testStartTagForPutRequest()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView());

        $this->assertMatchesXpath($html.'</form>',
'/form
    [./input[@type="hidden"][@name="_method"][@value="PUT"]]
    [@method="post"]
    [@action="http://example.com/directory"]'
        );
    }

    public function testStartTagWithOverriddenVars()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView(), [
            'method' => 'post',
            'action' => 'http://foo.com/directory',
        ]);

        $this->assertSame('<form name="form" method="post" action="http://foo.com/directory">', $html);
    }

    public function testStartTagForMultipartForm()
    {
        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'method' => 'get',
                'action' => 'http://example.com/directory',
            ])
            ->add('file', 'Symfony\Component\Form\Extension\Core\Type\FileType')
            ->getForm();

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" enctype="multipart/form-data">', $html);
    }

    public function testStartTagWithExtraAttributes()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ]);

        $html = $this->renderStart($form->createView(), [
            'attr' => ['class' => 'foobar'],
        ]);

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="foobar">', $html);
    }

    public function testWidgetAttributes()
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', [
            'required' => true,
            'disabled' => true,
            'attr' => ['readonly' => true, 'maxlength' => 10, 'pattern' => '\d+', 'class' => 'foobar', 'data-foo' => 'bar'],
        ]);

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<input type="text" id="text" name="text" disabled="disabled" required="required" readonly="readonly" maxlength="10" pattern="\d+" class="foobar" data-foo="bar" value="value" />', $html);
    }

    public function testWidgetAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', [
            'attr' => ['foo' => true],
        ]);

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<input type="text" id="text" name="text" required="required" foo="foo" value="value" />', $html);
    }

    public function testWidgetAttributeHiddenIfFalse()
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', [
            'attr' => ['foo' => false],
        ]);

        $html = $this->renderWidget($form->createView());

        $this->assertNotContains('foo="', $html);
    }

    public function testButtonAttributes()
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, [
            'disabled' => true,
            'attr' => ['class' => 'foobar', 'data-foo' => 'bar'],
        ]);

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<button type="button" id="button" name="button" disabled="disabled" class="foobar" data-foo="bar">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, [
            'attr' => ['foo' => true],
        ]);

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<button type="button" id="button" name="button" foo="foo">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeHiddenIfFalse()
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, [
            'attr' => ['foo' => false],
        ]);

        $html = $this->renderWidget($form->createView());

        $this->assertNotContains('foo="', $html);
    }

    public function testTextareaWithWhitespaceOnlyContentRetainsValue()
    {
        $form = $this->factory->createNamed('textarea', 'Symfony\Component\Form\Extension\Core\Type\TextareaType', '  ');

        $html = $this->renderWidget($form->createView());

        $this->assertContains('>  </textarea>', $html);
    }

    public function testTextareaWithWhitespaceOnlyContentRetainsValueWhenRenderingForm()
    {
        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', ['textarea' => '  '])
            ->add('textarea', 'Symfony\Component\Form\Extension\Core\Type\TextareaType')
            ->getForm();

        $html = $this->renderForm($form->createView());

        $this->assertContains('>  </textarea>', $html);
    }

    public function testWidgetContainerAttributeHiddenIfFalse()
    {
        $form = $this->factory->createNamed('form', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
            'attr' => ['foo' => false],
        ]);

        $html = $this->renderWidget($form->createView());

        // no foo
        $this->assertNotContains('foo="', $html);
    }

    public function testTranslatedAttributes()
    {
        $view = $this->factory->createNamedBuilder('name', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['attr' => ['title' => 'Foo']])
            ->add('lastName', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['attr' => ['placeholder' => 'Bar']])
            ->getForm()
            ->createView();

        $html = $this->renderForm($view);

        $this->assertMatchesXpath($html, '/form//input[@title="[trans]Foo[/trans]"]');
        $this->assertMatchesXpath($html, '/form//input[@placeholder="[trans]Bar[/trans]"]');
    }

    public function testAttributesNotTranslatedWhenTranslationDomainIsFalse()
    {
        $view = $this->factory->createNamedBuilder('name', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'translation_domain' => false,
            ])
            ->add('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['attr' => ['title' => 'Foo']])
            ->add('lastName', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['attr' => ['placeholder' => 'Bar']])
            ->getForm()
            ->createView();

        $html = $this->renderForm($view);

        $this->assertMatchesXpath($html, '/form//input[@title="Foo"]');
        $this->assertMatchesXpath($html, '/form//input[@placeholder="Bar"]');
    }

    public function testTel()
    {
        $tel = '0102030405';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TelType', $tel);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/input
    [@type="tel"]
    [@name="name"]
    [@value="0102030405"]
'
        );
    }

    public function testColor()
    {
        $color = '#0000ff';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ColorType', $color);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/input
    [@type="color"]
    [@name="name"]
    [@value="#0000ff"]
'
        );
    }
}
