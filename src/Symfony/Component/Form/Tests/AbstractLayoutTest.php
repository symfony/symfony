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

abstract class AbstractLayoutTest extends FormIntegrationTestCase
{
    protected $csrfProvider;

    protected $factory;

    protected function setUp()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The "intl" extension is not available');
        }

        \Locale::setDefault('en');

        $this->csrfProvider = $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');

        parent::setUp();
    }

    protected function getExtensions()
    {
        return array(
            new CsrfExtension($this->csrfProvider),
        );
    }

    protected function tearDown()
    {
        $this->csrfProvider = null;
        $this->factory = null;
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
                $count == 1 ? 'once' : $count . ' times',
                $nodeList->length == 1 ? 'once' : $nodeList->length . ' times',
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

    abstract protected function renderEnctype(FormView $view);

    abstract protected function renderLabel(FormView $view, $label = null, array $vars = array());

    abstract protected function renderErrors(FormView $view);

    abstract protected function renderWidget(FormView $view, array $vars = array());

    abstract protected function renderRow(FormView $view, array $vars = array());

    abstract protected function renderRest(FormView $view, array $vars = array());

    abstract protected function setTheme(FormView $view, array $themes);

    public function testEnctype()
    {
        $form = $this->factory->createNamedBuilder('name', 'form')
            ->add('file', 'file')
            ->getForm();

        $this->assertEquals('enctype="multipart/form-data"', $this->renderEnctype($form->createView()));
    }

    public function testNoEnctype()
    {
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
                'class' => 'my&class'
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
                'class' => 'my&class'
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
                'class' => 'my&class'
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
                'class' => 'my&class'
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

    public function testErrors()
    {
        $form = $this->factory->createNamed('name', 'text');
        $form->addError(new FormError('Error 1'));
        $form->addError(new FormError('Error 2'));
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

    public function testWidgetById()
    {
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

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [@required="required"]
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
    [@required="required"]
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
    [@required="required"]
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
    [@required="required"]
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

    public function testSingleChoiceWithNonRequiredEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'empty_value' => 'Select&Anything&Not&Me',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [not(@required)]
    [
        ./option[@value=""][not(@selected)][.="[trans]Select&Anything&Not&Me[/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceRequiredWithEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'required' => true,
            'multiple' => false,
            'expanded' => false,
            'empty_value' => 'Test&Me'
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [@required="required"]
    [
        ./option[@value=""][.="[trans]Test&Me[/trans]"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="[trans]Choice&A[/trans]"]
        /following-sibling::option[@value="&b"][not(@selected)][.="[trans]Choice&B[/trans]"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceRequiredWithEmptyValueViaView()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'required' => true,
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('empty_value' => ''),
'/select
    [@name="name"]
    [@required="required"]
    [
        ./option[@value=""][.="[trans][/trans]"]
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

    public function testMultipleChoiceSkipEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'choice', array('&a'), array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => true,
            'expanded' => false,
            'empty_value' => 'Test&Me'
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

    public function testSingleChoiceExpandedSkipEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'choice', '&a', array(
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => false,
            'expanded' => true,
            'empty_value' => 'Test&Me'
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

    public function testCountryWithEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'country', 'AT', array(
            'empty_value' => 'Select&Country',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="name"]
    [./option[@value=""][not(@selected)][.="[trans]Select&Country[/trans]"]]
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

    public function testDateTimeWithEmptyValueGlobal()
    {
        $form = $this->factory->createNamed('name', 'datetime', null, array(
            'input' => 'string',
            'empty_value' => 'Change&Me',
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
                    [./option[@value=""][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_date_day"]
                    [./option[@value=""][.="[trans]Change&Me[/trans]"]]
                /following-sibling::select
                    [@id="name_date_year"]
                    [./option[@value=""][.="[trans]Change&Me[/trans]"]]
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

    public function testDateTimeWithEmptyValueOnTime()
    {
        $data = array('year' => '2011', 'month' => '2', 'day' => '3', 'hour' => '', 'minute' => '');

        $form = $this->factory->createNamed('name', 'datetime', $data, array(
            'input' => 'array',
            'empty_value' => array('hour' => 'Change&Me', 'minute' => 'Change&Me'),
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

    public function testDateChoiceWithEmptyValueGlobal()
    {
        $form = $this->factory->createNamed('name', 'date', null, array(
            'input' => 'string',
            'widget' => 'choice',
            'empty_value' => 'Change&Me',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testDateChoiceWithEmptyValueOnYear()
    {
        $form = $this->factory->createNamed('name', 'date', null, array(
            'input' => 'string',
            'widget' => 'choice',
            'required' => false,
            'empty_value' => array('year' => 'Change&Me'),
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
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
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
        $child = $this->factory->createNamed('date', 'date');
        $form = $this->factory->createNamed('form', 'form')->add($child);
        $child->addError(new FormError('Error!'));
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

    public function testBirthDayWithEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'birthday', '1950-01-01', array(
            'input' => 'string',
            'empty_value' => '',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="name_month"]
            [./option[@value=""][.="[trans][/trans]"]]
            [./option[@value="1"][@selected="selected"]]
        /following-sibling::select
            [@id="name_day"]
            [./option[@value=""][.="[trans][/trans]"]]
            [./option[@value="1"][@selected="selected"]]
        /following-sibling::select
            [@id="name_year"]
            [./option[@value=""][.="[trans][/trans]"]]
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
            'max_length' => 123,
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

    public function testPasswordBoundNotAlwaysEmpty()
    {
        $form = $this->factory->createNamed('name', 'password', null, array(
            'always_empty' => false,
        ));
        $form->bind('foo&bar');

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
            'max_length' => 123,
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
            'pattern' => 'foo',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/textarea
    [@name="name"]
    [not(@pattern)]
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
            'max_length' => 123,
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

    public function testTimeWithEmptyValueGlobal()
    {
        $form = $this->factory->createNamed('name', 'time', null, array(
            'input' => 'string',
            'empty_value' => 'Change&Me',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="name_hour"]
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
            [count(./option)>24]
        /following-sibling::select
            [@id="name_minute"]
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
            [count(./option)>60]
    ]
    [count(./select)=2]
'
        );
    }

    public function testTimeWithEmptyValueOnYear()
    {
        $form = $this->factory->createNamed('name', 'time', null, array(
            'input' => 'string',
            'required' => false,
            'empty_value' => array('hour' => 'Change&Me'),
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="name_hour"]
            [./option[@value=""][.="[trans]Change&Me[/trans]"]]
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
        $child = $this->factory->createNamed('time', 'time');
        $form = $this->factory->createNamed('form', 'form')->add($child);
        $child->addError(new FormError('Error!'));
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
    [@required="required"]
    [./optgroup
        [@label="[trans]Europe[/trans]"]
        [./option[@value="Europe/Vienna"][@selected="selected"][.="[trans]Vienna[/trans]"]]
    ]
    [count(./optgroup)>10]
    [count(.//option)>200]
'
        );
    }

    public function testTimezoneWithEmptyValue()
    {
        $form = $this->factory->createNamed('name', 'timezone', null, array(
            'empty_value' => 'Select&Timezone',
            'required' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [./option[@value=""][.="[trans]Select&Timezone[/trans]"]]
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
             //table[@id="name_items"][@data-prototype]

'
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
             //input[@type="text"][@id="child"][@name="child"]'
        , 2);
    }
}
