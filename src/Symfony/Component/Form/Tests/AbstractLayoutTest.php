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

abstract class AbstractLayoutTest extends \Symfony\Component\Form\Test\FormIntegrationTestCase
{
    protected $csrfTokenManager;

    protected function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The "intl" extension is not available');
        }

        \Locale::setDefault('en');

        $this->csrfTokenManager = $this->getMock('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface');

        parent::setUp();
    }

    protected function getExtensions()
    {
        return array(
            new CsrfExtension($this->csrfTokenManager),
        );
    }

    protected function tearDown()
    {
        $this->csrfTokenManager = null;

        parent::tearDown();
    }

    protected function assertXpathNodeValue(\DomElement $element, $expression, $nodeValue)
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodeList = $xpath->evaluate($expression);
        $this->assertEquals(1, $nodeList->length);
        $this->assertEquals($nodeValue, $nodeList->item(0)->nodeValue);
    }

    protected function assertMatchesXpath($html, $expression, $count = 1)
    {
        $dom = new \DomDocument('UTF-8');
        try {
            // Wrap in <root> node so we can load HTML with multiple tags at
            // the top level
            $dom->loadXml('<root>'.$html.'</root>');
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
                $count == 1 ? 'once' : $count.' times',
                $nodeList->length == 1 ? 'once' : $nodeList->length.' times',
                // strip away <root> and </root>
                substr($dom->saveHTML(), 6, -8)
            ));
        }
    }

    protected function assertWidgetMatchesXpath(FormView $view, array $vars, $xpath)
    {
        // include ampersands everywhere to validate escaping
        $html = $this->renderWidget($view, array_merge(array(
            'id' => 'my&id',
            'attr' => array('class' => 'my&class'),
        ), $vars));

        $xpath = trim($xpath).'
    [@id="my&id"]
    [@class="my&class"]';

        $this->assertMatchesXpath($html, $xpath);
    }

    abstract protected function renderForm(FormView $view, array $vars = array());

    protected function renderEnctype(FormView $view)
    {
        $this->markTestSkipped(sprintf('Legacy %s::renderEnctype() is not implemented.', get_class($this)));
    }

    abstract protected function renderLabel(FormView $view, $label = null, array $vars = array());

    abstract protected function renderErrors(FormView $view);

    abstract protected function renderWidget(FormView $view, array $vars = array());

    abstract protected function renderRow(FormView $view, array $vars = array());

    abstract protected function renderRest(FormView $view, array $vars = array());

    abstract protected function renderStart(FormView $view, array $vars = array());

    abstract protected function renderEnd(FormView $view, array $vars = array());

    abstract protected function setTheme(FormView $view, array $themes);

    /**
     * @group legacy
     */
    public function testLegacyEnctype()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $form = $this->factory->createNamedBuilder('name', 'form')
            ->add('file', 'file')
            ->getForm();

        $this->assertEquals('enctype="multipart/form-data"', $this->renderEnctype($form->createView()));
    }

    /**
     * @group legacy
     */
    public function testLegacyNoEnctype()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $form = $this->factory->createNamedBuilder('name', 'form')
            ->add('text', 'text')
            ->getForm();

        $this->assertEquals('', $this->renderEnctype($form->createView()));
    }

    public function testLabel()
    {
        $form = $this->factory->createNamed('name', 'text');
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

    public function testLabelOnForm()
    {
        $form = $this->factory->createNamed('name', 'date');
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

    public function testLabelWithCustomTextPassedAsOption()
    {
        $form = $this->factory->createNamed('name', 'text', null, array(
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

    public function testLabelWithCustomTextPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'text');
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
        $form = $this->factory->createNamed('name', 'text', null, array(
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

    public function testLabelDoesNotRenderFieldAttributes()
    {
        $form = $this->factory->createNamed('name', 'text');
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

    public function testLabelWithCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'text');
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

    public function testLabelWithCustomTextAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'text');
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
    public function testLabelWithCustomTextAsOptionAndCustomAttributesPassedDirectly()
    {
        $form = $this->factory->createNamed('name', 'text', null, array(
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

    public function testLabelFormatName()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('myfield', 'text')
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

    public function testLabelFormatId()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('myfield', 'text')
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

    public function testLabelFormatAsFormOption()
    {
        $options = array('label_format' => 'form.%name%');

        $form = $this->factory->createNamedBuilder('myform', 'form', null, $options)
            ->add('myfield', 'text')
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
        $options = array('label_format' => 'form.%name%');

        $form = $this->factory->createNamedBuilder('myform', 'form', null, $options)
            ->add('myfield', 'text', array('label_format' => 'field.%name%'))
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

    public function testLabelFormatOnButton()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'button')
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

    public function testLabelFormatOnButtonId()
    {
        $form = $this->factory->createNamedBuilder('myform')
            ->add('mybutton', 'button')
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

    public function testErrors()
    {
        $form = $this->factory->createNamed('name', 'text');
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
        $form = $this->factory->createNamed('text_id', 'text');
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
        $form = $this->factory->createNamed('name', 'checkbox', true);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('name', 'checkbox', false);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testCheckboxWithValue()
    {
        $form = $this->factory->createNamed('name', 'checkbox', false, array(
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

    public function testSingleChoice()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceWithPreferred()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceWithPreferredAndNoSeparator()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceWithPreferredAndBlankSeparator()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testChoiceWithOnlyPreferred()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value=""][.="[trans][/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequiredNoneSelected()
    {
        $form = $this->factory->createNamed('name', 'choice', null, array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value=""][.="[trans][/trans]"]
        /following-sibling::option[@value="&a"][not(@selected)][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequiredWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceRequiredWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceRequiredWithPlaceholderViaView()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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
        ./option[@value=""][not(@selected)][not(@disabled)][.="[trans][/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceGrouped()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array(
                'Group&1' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
                'Group&2' => array('&c' => 'Choice&C'),
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

    public function testMultipleChoice()
    {
        $form = $this->factory->createNamed('name', 'choice', array('&a'), array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testMultipleChoiceSkipsPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'choice', array('&a'), array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testMultipleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('name', 'choice', array('&a'), array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
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

    public function testSingleChoiceExpandedWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => false,
            'expanded' => true,
            'placeholder' => 'Test&Me',
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

    public function testSingleChoiceExpandedWithBooleanValue()
    {
        $form = $this->factory->createNamed('name', 'choice', true, array(
            'choices' => array('1' => 'Choice&A', '0' => 'Choice&B'),
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

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->createNamed('name', 'choice', array('&a', '&c'), array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B', '&c' => 'Choice&C'),
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

    public function testCountry()
    {
        $form = $this->factory->createNamed('name', 'country', 'AT');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value="AT"][@selected="selected"][.="[trans]Austria[/trans]"]]
    [count(./option)>200]
'
        );
    }

    public function testCountryWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'country', 'AT', array(
            'placeholder' => 'Select&Country',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value=""][not(@selected)][not(@disabled)][.="[trans]Select&Country[/trans]"]]
    [./option[@value="AT"][@selected="selected"][.="[trans]Austria[/trans]"]]
    [count(./option)>201]
'
        );
    }

    public function testDateTime()
    {
        $form = $this->factory->createNamed('name', 'datetime', '2011-02-03 04:05:06', array(
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
                    [./option[@value="2011"][@selected="selected"]]
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
        $form = $this->factory->createNamed('name', 'datetime', null, array(
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

    public function testDateTimeWithHourAndMinute()
    {
        $data = array('year' => '2011', 'month' => '2', 'day' => '3', 'hour' => '4', 'minute' => '5');

        $form = $this->factory->createNamed('name', 'datetime', $data, array(
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
                    [./option[@value="2011"][@selected="selected"]]
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
        $form = $this->factory->createNamed('name', 'datetime', '2011-02-03 04:05:06', array(
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
                    [./option[@value="2011"][@selected="selected"]]
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
        $form = $this->factory->createNamed('name', 'datetime', '2011-02-03 04:05:06', array(
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

    public function testDateTimeWithWidgetSingleText()
    {
        $form = $this->factory->createNamed('name', 'datetime', '2011-02-03 04:05:06', array(
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

    public function testDateTimeWithWidgetSingleTextIgnoreDateAndTimeWidgets()
    {
        $form = $this->factory->createNamed('name', 'datetime', '2011-02-03 04:05:06', array(
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

    public function testDateChoice()
    {
        $form = $this->factory->createNamed('name', 'date', '2011-02-03', array(
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
            [./option[@value="2011"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testDateChoiceWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', 'date', null, array(
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

    public function testDateChoiceWithPlaceholderOnYear()
    {
        $form = $this->factory->createNamed('name', 'date', null, array(
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

    public function testDateText()
    {
        $form = $this->factory->createNamed('name', 'date', '2011-02-03', array(
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

    public function testDateSingleText()
    {
        $form = $this->factory->createNamed('name', 'date', '2011-02-03', array(
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

    public function testDateErrorBubbling()
    {
        $form = $this->factory->createNamedBuilder('form', 'form')
            ->add('date', 'date')
            ->getForm();
        $form->get('date')->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();

        $this->assertEmpty($this->renderErrors($view));
        $this->assertNotEmpty($this->renderErrors($view['date']));
    }

    public function testBirthDay()
    {
        $form = $this->factory->createNamed('name', 'birthday', '2000-02-03', array(
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

    public function testBirthDayWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'birthday', '1950-01-01', array(
            'input' => 'string',
            'placeholder' => '',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans][/trans]"]]
            [./option[@value="1"][@selected="selected"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans][/trans]"]]
            [./option[@value="1"][@selected="selected"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value=""][not(@selected)][not(@disabled)][.="[trans][/trans]"]]
            [./option[@value="1950"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testEmail()
    {
        $form = $this->factory->createNamed('name', 'email', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('name', 'email', 'foo&bar', array(
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

    public function testFile()
    {
        $form = $this->factory->createNamed('name', 'file');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="file"]
'
        );
    }

    public function testHidden()
    {
        $form = $this->factory->createNamed('name', 'hidden', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="hidden"]
    [@name="name"]
    [@value="foo&bar"]
'
        );
    }

    public function testReadOnly()
    {
        $form = $this->factory->createNamed('name', 'text', null, array(
            'read_only' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@readonly="readonly"]
'
        );
    }

    public function testDisabled()
    {
        $form = $this->factory->createNamed('name', 'text', null, array(
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

    public function testInteger()
    {
        $form = $this->factory->createNamed('name', 'integer', 123);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="number"]
    [@name="name"]
    [@value="123"]
'
        );
    }

    public function testLanguage()
    {
        $form = $this->factory->createNamed('name', 'language', 'de');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value="de"][@selected="selected"][.="[trans]German[/trans]"]]
    [count(./option)>200]
'
        );
    }

    public function testLocale()
    {
        $form = $this->factory->createNamed('name', 'locale', 'de_AT');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value="de_AT"][@selected="selected"][.="[trans]German (Austria)[/trans]"]]
    [count(./option)>200]
'
        );
    }

    public function testMoney()
    {
        $form = $this->factory->createNamed('name', 'money', 1234.56, array(
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

    public function testNumber()
    {
        $form = $this->factory->createNamed('name', 'number', 1234.56);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
'
        );
    }

    public function testPassword()
    {
        $form = $this->factory->createNamed('name', 'password', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="name"]
'
        );
    }

    public function testPasswordSubmittedWithNotAlwaysEmpty()
    {
        $form = $this->factory->createNamed('name', 'password', null, array(
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

    public function testPasswordWithMaxLength()
    {
        $form = $this->factory->createNamed('name', 'password', 'foo&bar', array(
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

    public function testPercent()
    {
        $form = $this->factory->createNamed('name', 'percent', 0.1);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('name', 'radio', true);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('name', 'radio', false);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testRadioWithValue()
    {
        $form = $this->factory->createNamed('name', 'radio', false, array(
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

    public function testTextarea()
    {
        $form = $this->factory->createNamed('name', 'textarea', 'foo&bar', array(
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

    public function testText()
    {
        $form = $this->factory->createNamed('name', 'text', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('name', 'text', 'foo&bar', array(
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

    public function testSearch()
    {
        $form = $this->factory->createNamed('name', 'search', 'foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
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
        $form = $this->factory->createNamed('name', 'time', '04:05:06', array(
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

    public function testTimeWithSeconds()
    {
        $form = $this->factory->createNamed('name', 'time', '04:05:06', array(
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

    public function testTimeText()
    {
        $form = $this->factory->createNamed('name', 'time', '04:05:06', array(
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

    public function testTimeSingleText()
    {
        $form = $this->factory->createNamed('name', 'time', '04:05:06', array(
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

    public function testTimeWithPlaceholderGlobal()
    {
        $form = $this->factory->createNamed('name', 'time', null, array(
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

    public function testTimeWithPlaceholderOnYear()
    {
        $form = $this->factory->createNamed('name', 'time', null, array(
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

    public function testTimeErrorBubbling()
    {
        $form = $this->factory->createNamedBuilder('form', 'form')
            ->add('time', 'time')
            ->getForm();
        $form->get('time')->addError(new FormError('[trans]Error![/trans]'));
        $view = $form->createView();

        $this->assertEmpty($this->renderErrors($view));
        $this->assertNotEmpty($this->renderErrors($view['time']));
    }

    public function testTimezone()
    {
        $form = $this->factory->createNamed('name', 'timezone', 'Europe/Vienna');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [not(@required)]
    [./optgroup
        [@label="[trans]Europe[/trans]"]
        [./option[@value="Europe/Vienna"][@selected="selected"][.="[trans]Vienna[/trans]"]]
    ]
    [count(./optgroup)>10]
    [count(.//option)>200]
'
        );
    }

    public function testTimezoneWithPlaceholder()
    {
        $form = $this->factory->createNamed('name', 'timezone', null, array(
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

    public function testUrl()
    {
        $url = 'http://www.google.com?foo1=bar1&foo2=bar2';
        $form = $this->factory->createNamed('name', 'url', $url);

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="url"]
    [@name="name"]
    [@value="http://www.google.com?foo1=bar1&foo2=bar2"]
'
        );
    }

    public function testCollectionPrototype()
    {
        $form = $this->factory->createNamedBuilder('name', 'form', array('items' => array('one', 'two', 'three')))
            ->add('items', 'collection', array('allow_add' => true))
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
        $form = $this->factory->createNamedBuilder('', 'form')
            ->add('child', 'text')
            ->getForm();

        $this->assertMatchesXpath($this->renderWidget($form->createView()),
            '//input[@type="hidden"][@id="_token"][@name="_token"]
            |
             //input[@type="text"][@id="child"][@name="child"]', 2);
    }

    public function testButton()
    {
        $form = $this->factory->createNamed('name', 'button');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="button"][@name="name"][.="[trans]Name[/trans]"]'
        );
    }

    public function testButtonLabelIsEmpty()
    {
        $form = $this->factory->createNamed('name', 'button');

        $this->assertSame('', $this->renderLabel($form->createView()));
    }

    public function testSubmit()
    {
        $form = $this->factory->createNamed('name', 'submit');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="submit"][@name="name"]'
        );
    }

    public function testReset()
    {
        $form = $this->factory->createNamed('name', 'reset');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
            '/button[@type="reset"][@name="name"]'
        );
    }

    public function testStartTag()
    {
        $form = $this->factory->create('form', null, array(
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory">', $html);
    }

    public function testStartTagForPutRequest()
    {
        $form = $this->factory->create('form', null, array(
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

    public function testStartTagWithOverriddenVars()
    {
        $form = $this->factory->create('form', null, array(
            'method' => 'put',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView(), array(
            'method' => 'post',
            'action' => 'http://foo.com/directory',
        ));

        $this->assertSame('<form name="form" method="post" action="http://foo.com/directory">', $html);
    }

    public function testStartTagForMultipartForm()
    {
        $form = $this->factory->createBuilder('form', null, array(
                'method' => 'get',
                'action' => 'http://example.com/directory',
            ))
            ->add('file', 'file')
            ->getForm();

        $html = $this->renderStart($form->createView());

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" enctype="multipart/form-data">', $html);
    }

    public function testStartTagWithExtraAttributes()
    {
        $form = $this->factory->create('form', null, array(
            'method' => 'get',
            'action' => 'http://example.com/directory',
        ));

        $html = $this->renderStart($form->createView(), array(
            'attr' => array('class' => 'foobar'),
        ));

        $this->assertSame('<form name="form" method="get" action="http://example.com/directory" class="foobar">', $html);
    }

    public function testWidgetAttributes()
    {
        $form = $this->factory->createNamed('text', 'text', 'value', array(
            'required' => true,
            'disabled' => true,
            'read_only' => true,
            'attr' => array('maxlength' => 10, 'pattern' => '\d+', 'class' => 'foobar', 'data-foo' => 'bar'),
        ));

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<input type="text" id="text" name="text" readonly="readonly" disabled="disabled" required="required" maxlength="10" pattern="\d+" class="foobar" data-foo="bar" value="value" />', $html);
    }

    public function testWidgetAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('text', 'text', 'value', array(
            'attr' => array('foo' => true),
        ));

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<input type="text" id="text" name="text" required="required" foo="foo" value="value" />', $html);
    }

    public function testWidgetAttributeHiddenIfFalse()
    {
        $form = $this->factory->createNamed('text', 'text', 'value', array(
            'attr' => array('foo' => false),
        ));

        $html = $this->renderWidget($form->createView());

        $this->assertNotContains('foo="', $html);
    }

    public function testButtonAttributes()
    {
        $form = $this->factory->createNamed('button', 'button', null, array(
            'disabled' => true,
            'attr' => array('class' => 'foobar', 'data-foo' => 'bar'),
        ));

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<button type="button" id="button" name="button" disabled="disabled" class="foobar" data-foo="bar">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('button', 'button', null, array(
            'attr' => array('foo' => true),
        ));

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<button type="button" id="button" name="button" foo="foo">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeHiddenIfFalse()
    {
        $form = $this->factory->createNamed('button', 'button', null, array(
            'attr' => array('foo' => false),
        ));

        $html = $this->renderWidget($form->createView());

        $this->assertNotContains('foo="', $html);
    }

    public function testTextareaWithWhitespaceOnlyContentRetainsValue()
    {
        $form = $this->factory->createNamed('textarea', 'textarea', '  ');

        $html = $this->renderWidget($form->createView());

        $this->assertContains('>  </textarea>', $html);
    }

    public function testTextareaWithWhitespaceOnlyContentRetainsValueWhenRenderingForm()
    {
        $form = $this->factory->createBuilder('form', array('textarea' => '  '))
            ->add('textarea', 'textarea')
            ->getForm();

        $html = $this->renderForm($form->createView());

        $this->assertContains('>  </textarea>', $html);
    }

    public function testWidgetContainerAttributeHiddenIfFalse()
    {
        $form = $this->factory->createNamed('form', 'form', null, array(
            'attr' => array('foo' => false),
        ));

        $html = $this->renderWidget($form->createView());

        // no foo
        $this->assertNotContains('foo="', $html);
    }

    public function testTranslatedAttributes()
    {
        $view = $this->factory->createNamedBuilder('name', 'form')
            ->add('firstName', 'text', array('attr' => array('title' => 'Foo')))
            ->add('lastName', 'text', array('attr' => array('placeholder' => 'Bar')))
            ->getForm()
            ->createView();

        $html = $this->renderForm($view);

        $this->assertMatchesXpath($html, '/form//input[@title="[trans]Foo[/trans]"]');
        $this->assertMatchesXpath($html, '/form//input[@placeholder="[trans]Bar[/trans]"]');
    }
}
