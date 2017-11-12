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
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Test\FormIntegrationTestCase;

abstract class AbstractLayoutTest extends FormIntegrationTestCase
{
    protected $csrfTokenManager;
    protected $testableFeatures = array();

    protected function setUp(): void
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('Extension intl is required.');
        }

        \Locale::setDefault('en');

        $this->csrfTokenManager = $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();

        parent::setUp();
    }

    protected function getExtensions()
    {
        return array(
            new CsrfExtension($this->csrfTokenManager),
        );
    }

    protected function tearDown(): void
    {
        $this->csrfTokenManager = null;

        parent::tearDown();
    }

    protected function assertXpathNodeValue(\DOMElement $element, $expression, $nodeValue): void
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodeList = $xpath->evaluate($expression);
        $this->assertEquals(1, $nodeList->length);
        $this->assertEquals($nodeValue, $nodeList->item(0)->nodeValue);
    }

    protected function assertMatchesXpath($html, $expression, $count = 1): void
    {
        $dom = new \DomDocument('UTF-8');
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

    protected function assertWidgetMatchesXpath(FormView $view, array $vars, $xpath): void
    {
        // include ampersands everywhere to validate escaping
        $html = $this->renderWidget($view, array_merge(array(
            'id' => 'my&id',
            'attr' => array('class' => 'my&class'),
        ), $vars));

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

    abstract protected function renderForm(FormView $view, array $vars = array()): void;

    abstract protected function renderLabel(FormView $view, $label = null, array $vars = array()): void;

    abstract protected function renderErrors(FormView $view): void;

    abstract protected function renderWidget(FormView $view, array $vars = array()): void;

    abstract protected function renderRow(FormView $view, array $vars = array()): void;

    abstract protected function renderRest(FormView $view, array $vars = array()): void;

    abstract protected function renderStart(FormView $view, array $vars = array()): void;

    abstract protected function renderEnd(FormView $view, array $vars = array()): void;

    abstract protected function setTheme(FormView $view, array $themes, $useDefaultThemes = true): void;

    public function testLabel(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $view = $form->createView();
        $this->renderWidget($view, array('label' => 'foo'));
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelWithoutTranslation(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
            'translation_domain' => false,
        ));

        $this->assertMatchesXpath($this->renderLabel($form->createView()),
'/label
    [@for="name"]
    [.="Name"]
'
        );
    }

    public function testLabelOnForm(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType');
        $view = $form->createView();
        $this->renderWidget($view, array('label' => 'foo'));
        $html = $this->renderLabel($view);

        $this->assertMatchesXpath($html,
'/label
    [@class="required"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedAsOption(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
            'label' => 'Custom label',
        ));
        $html = $this->renderLabel($form->createView());

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedDirectly(): void
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

    public function testLabelWithCustomTextPassedAsOptionAndDirectly(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
            'label' => 'Custom label',
        ));
        $html = $this->renderLabel($form->createView(), 'Overridden label');

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Overridden label[/trans]"]
'
        );
    }

    public function testLabelDoesNotRenderFieldAttributes(): void
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
    [@class="required"]
'
        );
    }

    public function testLabelWithCustomAttributesPassedDirectly(): void
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
    [@class="my&class required"]
'
        );
    }

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly(): void
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
    [@class="my&class required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    // https://github.com/symfony/symfony/issues/5029
    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly(): void
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
    [@class="my&class required"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelFormatName(): void
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();
        $view = $form->get('myfield')->createView();
        $html = $this->renderLabel($view, null, array('label_format' => 'form.%name%'));

        $this->assertMatchesXpath($html,
'/label
    [@for="myform_myfield"]
    [.="[trans]form.myfield[/trans]"]
'
        );
    }

    public function testLabelFormatId(): void
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();
        $view = $form->get('myfield')->createView();
        $html = $this->renderLabel($view, null, array('label_format' => 'form.%id%'));

        $this->assertMatchesXpath($html,
'/label
    [@for="myform_myfield"]
    [.="[trans]form.myform_myfield[/trans]"]
'
        );
    }

    public function testLabelFormatAsFormOption(): void
    {
        $options = array('label_format' => 'form.%name%');

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

    public function testLabelFormatOverriddenOption(): void
    {
        $options = array('label_format' => 'form.%name%');

        $form = $this->factory->createNamedBuilder('myform', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, $options)
            ->add('myfield', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('label_format' => 'field.%name%'))
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

    public function testLabelWithoutTranslationOnButton(): void
    {
        $form = $this->factory->createNamedBuilder('myform', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
                'translation_domain' => false,
            ))
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

    public function testLabelFormatOnButton(): void
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'Symfony\Component\Form\Extension\Core\Type\ButtonType')
            ->getForm();
        $view = $form->get('mybutton')->createView();
        $html = $this->renderWidget($view, array('label_format' => 'form.%name%'));

        $this->assertMatchesXpath($html,
'/button
    [@type="button"]
    [@name="myform[mybutton]"]
    [.="[trans]form.mybutton[/trans]"]
'
        );
    }

    public function testLabelFormatOnButtonId(): void
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'Symfony\Component\Form\Extension\Core\Type\ButtonType')
            ->getForm();
        $view = $form->get('mybutton')->createView();
        $html = $this->renderWidget($view, array('label_format' => 'form.%id%'));

        $this->assertMatchesXpath($html,
'/button
    [@type="button"]
    [@name="myform[mybutton]"]
    [.="[trans]form.myform_mybutton[/trans]"]
'
        );
    }

    public function testErrors(): void
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

    public function testOverrideWidgetBlock(): void
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

    public function testCheckedCheckbox(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', true);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [@checked="checked"]
    [@value="1"]
'
        );
    }

    public function testUncheckedCheckbox(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', false);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testCheckboxWithValue(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', false, array(
            'value' => 'foo&bar',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testSingleChoice(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => false,
        ));

        // If the field is collapsed, has no "multiple" attribute, is required but
        // has *no* empty value, the "required" must not be added, otherwise
        // the resulting HTML is invalid.
        // https://github.com/symfony/symfony/issues/8942

        // HTML 5 spec
        // http://www.w3.org/html/wg/drafts/html/master/forms.html#placeholder-label-option

        // "If a select element has a required attribute specified, does not
        //  have a multiple attribute specified, and has a display size of 1,
        //  then the select element must have a placeholder label option."

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSelectWithSizeBiggerThanOneCanBeRequired(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => array('a', 'b'),
            'multiple' => false,
            'expanded' => false,
            'attr' => array('size' => 2),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [@required="required"]
    [@size="2"]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithoutTranslation(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => false,
            'choice_translation_domain' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceWithPlaceholderWithoutTranslation(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceAttributes(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_attr' => array('Choice&B' => array('class' => 'foo&bar')),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceAttributesWithMainAttributes(): void
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

    public function testSingleExpandedChoiceAttributesWithMainAttributes(): void
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

    public function testSingleChoiceWithPreferred(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'preferred_choices' => array('&b'),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('separator' => '-- sep --'),
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

    public function testSingleChoiceWithPreferredAndNoSeparator(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'preferred_choices' => array('&b'),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('separator' => null),
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

    public function testSingleChoiceWithPreferredAndBlankSeparator(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'preferred_choices' => array('&b'),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('separator' => ''),
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

    public function testChoiceWithOnlyPreferred(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'preferred_choices' => array('&a', '&b'),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceNonRequired(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceNonRequiredNoneSelected(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceNonRequiredWithPlaceholder(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'placeholder' => 'Select&Anything&Not&Me',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceRequiredWithPlaceholder(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'required' => true,
            'multiple' => false,
            'expanded' => false,
            'placeholder' => 'Test&Me',
        ));

        // The "disabled" attribute was removed again due to a bug in the
        // BlackBerry 10 browser.
        // See https://github.com/symfony/symfony/pull/7678
        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceRequiredWithPlaceholderViaView(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'required' => true,
            'multiple' => false,
            'expanded' => false,
        ));

        // The "disabled" attribute was removed again due to a bug in the
        // BlackBerry 10 browser.
        // See https://github.com/symfony/symfony/pull/7678
        $this->assertWidgetMatchesXpath($form->createView(), array('placeholder' => ''),
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

    public function testSingleChoiceGrouped(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array(
                'Group&1' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
                'Group&2' => array('Choice&C' => '&c'),
            ),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testMultipleChoice(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'required' => true,
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testMultipleChoiceAttributes(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'choice_attr' => array('Choice&B' => array('class' => 'foo&bar')),
            'required' => true,
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testMultipleChoiceSkipsPlaceholder(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => true,
            'expanded' => false,
            'placeholder' => 'Test&Me',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testMultipleChoiceNonRequired(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array('&a'), array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'required' => false,
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceExpanded(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceExpandedWithoutTranslation(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', '&a', array(
            'choices' => array('Choice&A' => '&a', 'Choice&B' => '&b'),
            'multiple' => false,
            'expanded' => true,
            'choice_translation_domain' => false,
            'placeholder' => 'Placeholder&Not&Translated',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testSingleChoiceExpandedAttributes(): void
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

    public function testSingleChoiceExpandedWithPlaceholder(): void
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

    public function testSingleChoiceExpandedWithPlaceholderWithoutTranslation(): void
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

    public function testSingleChoiceExpandedWithBooleanValue(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', true, array(
            'choices' => array('Choice&A' => '1', 'Choice&B' => '0'),
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testMultipleChoiceExpanded(): void
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

    public function testMultipleChoiceExpandedWithoutTranslation(): void
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

    public function testMultipleChoiceExpandedAttributes(): void
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

    public function testCountry(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CountryType', 'AT');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>200]
'
        );
    }

    public function testCountryWithPlaceholder(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\CountryType', 'AT', array(
            'placeholder' => 'Select&Country',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Country[/trans]"]]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>201]
'
        );
    }

    public function testDateTime(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', date('Y').'-02-03 04:05:06', array(
            'input' => 'string',
            'with_seconds' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateTimeWithPlaceholderGlobal(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', null, array(
            'input' => 'string',
            'placeholder' => 'Change&Me',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateTimeWithHourAndMinute(): void
    {
        $data = array('year' => date('Y'), 'month' => '2', 'day' => '3', 'hour' => '4', 'minute' => '5');

        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', $data, array(
            'input' => 'array',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateTimeWithSeconds(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', date('Y').'-02-03 04:05:06', array(
            'input' => 'string',
            'with_seconds' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateTimeSingleText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', '2011-02-03 04:05:06', array(
            'input' => 'string',
            'date_widget' => 'single_text',
            'time_widget' => 'single_text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateTimeWithWidgetSingleText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', '2011-02-03 04:05:06', array(
            'input' => 'string',
            'widget' => 'single_text',
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="datetime"]
    [@name="name"]
    [@value="2011-02-03T04:05:06Z"]
'
        );
    }

    public function testDateTimeWithWidgetSingleTextIgnoreDateAndTimeWidgets(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateTimeType', '2011-02-03 04:05:06', array(
            'input' => 'string',
            'date_widget' => 'choice',
            'time_widget' => 'choice',
            'widget' => 'single_text',
            'model_timezone' => 'UTC',
            'view_timezone' => 'UTC',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="datetime"]
    [@name="name"]
    [@value="2011-02-03T04:05:06Z"]
'
        );
    }

    public function testDateChoice(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', date('Y').'-02-03', array(
            'input' => 'string',
            'widget' => 'choice',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateChoiceWithPlaceholderGlobal(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'input' => 'string',
            'widget' => 'choice',
            'placeholder' => 'Change&Me',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateChoiceWithPlaceholderOnYear(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', null, array(
            'input' => 'string',
            'widget' => 'choice',
            'required' => false,
            'placeholder' => array('year' => 'Change&Me'),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', '2011-02-03', array(
            'input' => 'string',
            'widget' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testDateSingleText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\DateType', '2011-02-03', array(
            'input' => 'string',
            'widget' => 'single_text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="date"]
    [@name="name"]
    [@value="2011-02-03"]
'
        );
    }

    public function testDateErrorBubbling(): void
    {
        $form = $this->factory->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('date', 'Symfony\Component\Form\Extension\Core\Type\DateType')
            ->getForm();
        $form->get('date')->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();

        $this->assertEmpty($this->renderErrors($view));
        $this->assertNotEmpty($this->renderErrors($view['date']));
    }

    public function testBirthDay(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\BirthdayType', '2000-02-03', array(
            'input' => 'string',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testBirthDayWithPlaceholder(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\BirthdayType', '1950-01-01', array(
            'input' => 'string',
            'placeholder' => '',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testEmail(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="email"]
    [@name="name"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testEmailWithMaxLength(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\EmailType', 'foo&bar', array(
            'attr' => array('maxlength' => 123),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="email"]
    [@name="name"]
    [@value="foo&bar"]
    [@maxlength="123"]
'
        );
    }

    public function testFile(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\FileType');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="file"]
'
        );
    }

    public function testHidden(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\HiddenType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="hidden"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testDisabled(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array(
            'disabled' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@disabled="disabled"]
'
        );
    }

    public function testInteger(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\IntegerType', 123);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="number"]
    [@name="name"]
    [@value="123"]
'
        );
    }

    public function testLanguage(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\LanguageType', 'de');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value="de"][@selected="selected"][.="German"]]
    [count(./option)>200]
'
        );
    }

    public function testLocale(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\LocaleType', 'de_AT');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value="de_AT"][@selected="selected"][.="German (Austria)"]]
    [count(./option)>200]
'
        );
    }

    public function testMoney(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\MoneyType', 1234.56, array(
            'currency' => 'EUR',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
    [contains(.., "€")]
'
        );
    }

    public function testNumber(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\NumberType', 1234.56);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
'
        );
    }

    public function testPassword(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="name"]
'
        );
    }

    public function testPasswordSubmittedWithNotAlwaysEmpty(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', null, array(
            'always_empty' => false,
        ));
        $form->submit('foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testPasswordWithMaxLength(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', 'foo&bar', array(
            'attr' => array('maxlength' => 123),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="name"]
    [@maxlength="123"]
'
        );
    }

    public function testPercent(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\PercentType', 0.1);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="10"]
    [contains(.., "%")]
'
        );
    }

    public function testCheckedRadio(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', true);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="name"]
    [@checked="checked"]
    [@value="1"]
'
        );
    }

    public function testUncheckedRadio(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', false);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testRadioWithValue(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RadioType', false, array(
            'value' => 'foo&bar',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testRange(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RangeType', 42, array('attr' => array('min' => 5)));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="range"]
    [@name="name"]
    [@value="42"]
    [@min="5"]
'
        );
    }

    public function testRangeWithMinMaxValues(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\RangeType', 42, array('attr' => array('min' => 5, 'max' => 57)));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="range"]
    [@name="name"]
    [@value="42"]
    [@min="5"]
    [@max="57"]
'
        );
    }

    public function testTextarea(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextareaType', 'foo&bar', array(
            'attr' => array('pattern' => 'foo'),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/textarea
    [@name="name"]
    [@pattern="foo"]
    [.="foo&bar"]
'
        );
    }

    public function testText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testTextWithMaxLength(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'foo&bar', array(
            'attr' => array('maxlength' => 123),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="foo&bar"]
    [@maxlength="123"]
'
        );
    }

    public function testSearch(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\SearchType', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="search"]
    [@name="name"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testTime(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', array(
            'input' => 'string',
            'with_seconds' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testTimeWithSeconds(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', array(
            'input' => 'string',
            'with_seconds' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testTimeText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', array(
            'input' => 'string',
            'widget' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testTimeSingleText(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', '04:05:06', array(
            'input' => 'string',
            'widget' => 'single_text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="time"]
    [@name="name"]
    [@value="04:05"]
    [not(@size)]
'
        );
    }

    public function testTimeWithPlaceholderGlobal(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', null, array(
            'input' => 'string',
            'placeholder' => 'Change&Me',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testTimeWithPlaceholderOnYear(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimeType', null, array(
            'input' => 'string',
            'required' => false,
            'placeholder' => array('hour' => 'Change&Me'),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testTimeErrorBubbling(): void
    {
        $form = $this->factory->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('time', 'Symfony\Component\Form\Extension\Core\Type\TimeType')
            ->getForm();
        $form->get('time')->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();

        $this->assertEmpty($this->renderErrors($view));
        $this->assertNotEmpty($this->renderErrors($view['time']));
    }

    public function testTimezone(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimezoneType', 'Europe/Vienna');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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

    public function testTimezoneWithPlaceholder(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TimezoneType', null, array(
            'placeholder' => 'Select&Timezone',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Timezone[/trans]"]]
    [count(./optgroup)>10]
    [count(.//option)>201]
'
        );
    }

    public function testUrl(): void
    {
        $url = 'http://www.google.com?foo1=bar1&foo2=bar2';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\UrlType', $url);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="url"]
    [@name="name"]
    [@value="http://www.google.com?foo1=bar1&foo2=bar2"]
'
        );
    }

    public function testCollectionPrototype(): void
    {
        $form = $this->factory->createNamedBuilder('name', 'Symfony\Component\Form\Extension\Core\Type\FormType', array('items' => array('one', 'two', 'three')))
            ->add('items', 'Symfony\Component\Form\Extension\Core\Type\CollectionType', array('allow_add' => true))
            ->getForm()
            ->createView();

        $html = $this->renderWidget($form);

        $this->assertMatchesXpath($html,
            '//div[@id="name_items"][@data-prototype]
            |
            //table[@id="name_items"][@data-prototype]'
        );
    }

    public function testEmptyRootFormName(): void
    {
        $form = $this->factory->createNamedBuilder('', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $this->assertMatchesXpath($this->renderWidget($form->createView()),
            '//input[@type="hidden"][@id="_token"][@name="_token"]
            |
             //input[@type="text"][@id="child"][@name="child"]', 2);
    }

    public function testButton(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ButtonType');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="button"][@name="name"][.="[trans]Name[/trans]"]'
        );
    }

    public function testButtonLabelIsEmpty(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ButtonType');

        $this->assertSame('', $this->renderLabel($form->createView()));
    }

    public function testButtonlabelWithoutTranslation(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, array(
            'translation_domain' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="button"][@name="name"][.="Name"]'
        );
    }

    public function testSubmit(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\SubmitType');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="submit"][@name="name"]'
        );
    }

    public function testReset(): void
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ResetType');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="reset"][@name="name"]'
        );
    }

    public function testStartTag(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory">', $html);
    }

    public function testStartTagForPutRequest(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertMatchesXpath($html.'</form>',
'/form
    [./input[@type="hidden"][@name="_method"][@value="PUT"]]
    [@method="post"]
    [@action="http://example.com/directory"]'
        );
    }

    public function testStartTagWithOverriddenVars(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView(), array(
            'method' => 'post',
            'action' => 'http://foo.com/directory',
        ));

        $this->assertSame('<form name="form" method="post" action="http://foo.com/directory">', $html);
    }

    public function testStartTagForMultipartForm(): void
    {
        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
                'method' => 'get',
                'action' => 'http://example.com/directory',
            ))
            ->add('file', 'Symfony\Component\Form\Extension\Core\Type\FileType')
            ->getForm();

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" enctype="multipart/form-data">', $html);
    }

    public function testStartTagWithExtraAttributes(): void
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView(), array(
            'attr' => array('class' => 'foobar'),
        ));

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="foobar">', $html);
    }

    public function testWidgetAttributes(): void
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', array(
            'required' => true,
            'disabled' => true,
            'attr' => array('readonly' => true, 'maxlength' => 10, 'pattern' => '\d+', 'class' => 'foobar', 'data-foo' => 'bar'),
        ));

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<input type="text" id="text" name="text" disabled="disabled" required="required" readonly="readonly" maxlength="10" pattern="\d+" class="foobar" data-foo="bar" value="value" />', $html);
    }

    public function testWidgetAttributeNameRepeatedIfTrue(): void
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', array(
            'attr' => array('foo' => true),
        ));

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<input type="text" id="text" name="text" required="required" foo="foo" value="value" />', $html);
    }

    public function testWidgetAttributeHiddenIfFalse(): void
    {
        $form = $this->factory->createNamed('text', 'Symfony\Component\Form\Extension\Core\Type\TextType', 'value', array(
            'attr' => array('foo' => false),
        ));

        $html = $this->renderWidget($form->createView());

        $this->assertNotContains('foo="', $html);
    }

    public function testButtonAttributes(): void
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, array(
            'disabled' => true,
            'attr' => array('class' => 'foobar', 'data-foo' => 'bar'),
        ));

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<button type="button" id="button" name="button" disabled="disabled" class="foobar" data-foo="bar">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeNameRepeatedIfTrue(): void
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, array(
            'attr' => array('foo' => true),
        ));

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<button type="button" id="button" name="button" foo="foo">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeHiddenIfFalse(): void
    {
        $form = $this->factory->createNamed('button', 'Symfony\Component\Form\Extension\Core\Type\ButtonType', null, array(
            'attr' => array('foo' => false),
        ));

        $html = $this->renderWidget($form->createView());

        $this->assertNotContains('foo="', $html);
    }

    public function testTextareaWithWhitespaceOnlyContentRetainsValue(): void
    {
        $form = $this->factory->createNamed('textarea', 'Symfony\Component\Form\Extension\Core\Type\TextareaType', '  ');

        $html = $this->renderWidget($form->createView());

        $this->assertContains('>  </textarea>', $html);
    }

    public function testTextareaWithWhitespaceOnlyContentRetainsValueWhenRenderingForm(): void
    {
        $form = $this->factory->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', array('textarea' => '  '))
            ->add('textarea', 'Symfony\Component\Form\Extension\Core\Type\TextareaType')
            ->getForm();

        $html = $this->renderForm($form->createView());

        $this->assertContains('>  </textarea>', $html);
    }

    public function testWidgetContainerAttributeHiddenIfFalse(): void
    {
        $form = $this->factory->createNamed('form', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
            'attr' => array('foo' => false),
        ));

        $html = $this->renderWidget($form->createView());

        // no foo
        $this->assertNotContains('foo="', $html);
    }

    public function testTranslatedAttributes(): void
    {
        $view = $this->factory->createNamedBuilder('name', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('attr' => array('title' => 'Foo')))
            ->add('lastName', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('attr' => array('placeholder' => 'Bar')))
            ->getForm()
            ->createView();

        $html = $this->renderForm($view);

        $this->assertMatchesXpath($html, '/form//input[@title="[trans]Foo[/trans]"]');
        $this->assertMatchesXpath($html, '/form//input[@placeholder="[trans]Bar[/trans]"]');
    }

    public function testAttributesNotTranslatedWhenTranslationDomainIsFalse(): void
    {
        $view = $this->factory->createNamedBuilder('name', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
                'translation_domain' => false,
            ))
            ->add('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('attr' => array('title' => 'Foo')))
            ->add('lastName', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('attr' => array('placeholder' => 'Bar')))
            ->getForm()
            ->createView();

        $html = $this->renderForm($view);

        $this->assertMatchesXpath($html, '/form//input[@title="Foo"]');
        $this->assertMatchesXpath($html, '/form//input[@placeholder="Bar"]');
    }

    public function testTel(): void
    {
        $tel = '0102030405';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\TelType', $tel);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/input
    [@type="tel"]
    [@name="name"]
    [@value="0102030405"]
'
        );
    }

    public function testColor(): void
    {
        $color = '#0000ff';
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ColorType', $color);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/input
    [@type="color"]
    [@name="name"]
    [@value="#0000ff"]
'
        );
    }
}
