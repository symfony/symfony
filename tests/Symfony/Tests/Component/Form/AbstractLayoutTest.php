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
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractLayoutTest extends \PHPUnit_Framework_TestCase
{
    protected $csrfProvider;

    protected $factory;

    protected function setUp()
    {
        \Locale::setDefault('en');

        $dispatcher = new EventDispatcher();
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');
        $storage = new \Symfony\Component\HttpFoundation\File\TemporaryStorage('foo', \sys_get_temp_dir());

        $this->factory = new FormFactory(array(
            new CoreExtension($storage),
            new CsrfExtension($this->csrfProvider),
        ));
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
            return $this->fail(sprintf(
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

    abstract protected function renderLabel(FormView $view, $label = null);

    abstract protected function renderErrors(FormView $view);

    abstract protected function renderWidget(FormView $view, array $vars = array());

    abstract protected function renderRow(FormView $view, array $vars = array());

    abstract protected function renderRest(FormView $view, array $vars = array());

    public function testEnctype()
    {
        $form = $this->factory->createNamedBuilder('form', 'na&me', null, array(
                'property_path' => 'name',
            ))
            ->add('file', 'file')
            ->getForm();

        $this->assertEquals('enctype="multipart/form-data"', $this->renderEnctype($form->createView()));
    }

    public function testNoEnctype()
    {
        $form = $this->factory->createNamedBuilder('form', 'na&me', null, array(
                'property_path' => 'name',
            ))
            ->add('text', 'text')
            ->getForm();

        $this->assertEquals('', $this->renderEnctype($form->createView()));
    }

    public function testLabel()
    {
        $form = $this->factory->createNamed('text', 'na&me', null, array(
            'property_path' => 'name',
        ));
        $html = $this->renderLabel($form->createView());

        $this->assertMatchesXpath($html,
'/label
    [@for="na&me"]
    [.="[trans]Na&me[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedAsOption()
    {
        $form = $this->factory->createNamed('text', 'na&me', null, array(
            'property_path' => 'name',
            'label' => 'Custom label',
        ));
        $html = $this->renderLabel($form->createView());

        $this->assertMatchesXpath($html,
'/label
    [@for="na&me"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testLabelWithCustomTextPassedDirectly()
    {
        $form = $this->factory->createNamed('text', 'na&me', null, array(
            'property_path' => 'name',
        ));
        $html = $this->renderLabel($form->createView(), 'Custom label');

        $this->assertMatchesXpath($html,
'/label
    [@for="na&me"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testErrors()
    {
        $form = $this->factory->createNamed('text', 'na&me', null, array(
            'property_path' => 'name',
        ));
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
        $form = $this->factory->createNamed('text', 'text_id');
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
        $form = $this->factory->createNamed('checkbox', 'na&me', true, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="na&me"]
    [@checked="checked"]
    [@value="1"]
'
        );
    }

    public function testCheckedCheckboxWithValue()
    {
        $form = $this->factory->createNamed('checkbox', 'na&me', true, array(
            'property_path' => 'name',
            'value' => 'foo&bar',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="na&me"]
    [@checked="checked"]
    [@value="foo&bar"]
'
        );
    }

    public function testUncheckedCheckbox()
    {
        $form = $this->factory->createNamed('checkbox', 'na&me', false, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="checkbox"]
    [@name="na&me"]
    [not(@checked)]
'
        );
    }

    public function testSingleChoice()
    {
        $form = $this->factory->createNamed('choice', 'na&me', '&a', array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [
        ./option[@value="&a"][@selected="selected"][.="Choice&A"]
        /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithPreferred()
    {
        $form = $this->factory->createNamed('choice', 'na&me', '&a', array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'preferred_choices' => array('&b'),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('separator' => '-- sep --'),
'/select
    [@name="na&me"]
    [
        ./option[@value="&b"][not(@selected)][.="Choice&B"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.="-- sep --"]
        /following-sibling::option[@value="&a"][@selected="selected"][.="Choice&A"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('choice', 'na&me', '&a', array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'required' => false,
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [
        ./option[@value=""][.=""]
        /following-sibling::option[@value="&a"][@selected="selected"][.="Choice&A"]
        /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceGrouped()
    {
        $form = $this->factory->createNamed('choice', 'na&me', '&a', array(
            'property_path' => 'name',
            'choices' => array(
                'Group&1' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
                'Group&2' => array('&c' => 'Choice&C'),
            ),
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [./optgroup[@label="Group&1"]
        [
            ./option[@value="&a"][@selected="selected"][.="Choice&A"]
            /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
        ]
        [count(./option)=2]
    ]
    [./optgroup[@label="Group&2"]
        [./option[@value="&c"][not(@selected)][.="Choice&C"]]
        [count(./option)=1]
    ]
    [count(./optgroup)=2]
'
        );
    }

    public function testMultipleChoice()
    {
        $form = $this->factory->createNamed('choice', 'na&me', array('&a'), array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me[]"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="Choice&A"]
        /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testMultipleChoiceNonRequired()
    {
        $form = $this->factory->createNamed('choice', 'na&me', array('&a'), array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'required' => false,
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me[]"]
    [@multiple="multiple"]
    [
        ./option[@value="&a"][@selected="selected"][.="Choice&A"]
        /following-sibling::option[@value="&b"][not(@selected)][.="Choice&B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->createNamed('choice', 'na&me', '&a', array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B'),
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="radio"][@name="na&me"][@id="na&me_&a"][@checked]
        /following-sibling::label[@for="na&me_&a"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="radio"][@name="na&me"][@id="na&me_&b"][not(@checked)]
        /following-sibling::label[@for="na&me_&b"][.="[trans]Choice&B[/trans]"]
    ]
    [count(./input)=2]
'
        );
    }

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->createNamed('choice', 'na&me', array('&a', '&c'), array(
            'property_path' => 'name',
            'choices' => array('&a' => 'Choice&A', '&b' => 'Choice&B', '&c' => 'Choice&C'),
            'multiple' => true,
            'expanded' => true,
            'required' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="checkbox"][@name="na&me[&a]"][@id="na&me_&a"][@checked][not(@required)]
        /following-sibling::label[@for="na&me_&a"][.="[trans]Choice&A[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="na&me[&b]"][@id="na&me_&b"][not(@checked)][not(@required)]
        /following-sibling::label[@for="na&me_&b"][.="[trans]Choice&B[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="na&me[&c]"][@id="na&me_&c"][@checked][not(@required)]
        /following-sibling::label[@for="na&me_&c"][.="[trans]Choice&C[/trans]"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testCountry()
    {
        $form = $this->factory->createNamed('country', 'na&me', 'AT', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>200]
'
        );
    }

    public function testCsrf()
    {
        $this->csrfProvider->expects($this->any())
            ->method('generateCsrfToken')
            ->will($this->returnValue('foo&bar'));

        $form = $this->factory->createNamed('csrf', 'na&me', null, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="hidden"]
    [@value="foo&bar"]
'
        );
    }

    public function testDateTime()
    {
        $form = $this->factory->createNamed('datetime', 'na&me', '2011-02-03 04:05:06', array(
            'property_path' => 'name',
            'input' => 'string',
            'with_seconds' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@id="na&me_date"]
            [
                ./select
                    [@id="na&me_date_month"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_date_day"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_date_year"]
                    [./option[@value="2011"][@selected="selected"]]
            ]
        /following-sibling::div
            [@id="na&me_time"]
            [
                ./select
                    [@id="na&me_time_hour"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_time_minute"]
                    [./option[@value="5"][@selected="selected"]]
            ]
    ]
    [count(.//select)=5]
'
        );
    }

    public function testDateTimeWithSeconds()
    {
        $form = $this->factory->createNamed('datetime', 'na&me', '2011-02-03 04:05:06', array(
            'property_path' => 'name',
            'input' => 'string',
            'with_seconds' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./div
            [@id="na&me_date"]
            [
                ./select
                    [@id="na&me_date_month"]
                    [./option[@value="2"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_date_day"]
                    [./option[@value="3"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_date_year"]
                    [./option[@value="2011"][@selected="selected"]]
            ]
        /following-sibling::div
            [@id="na&me_time"]
            [
                ./select
                    [@id="na&me_time_hour"]
                    [./option[@value="4"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_time_minute"]
                    [./option[@value="5"][@selected="selected"]]
                /following-sibling::select
                    [@id="na&me_time_second"]
                    [./option[@value="6"][@selected="selected"]]
            ]
    ]
    [count(.//select)=6]
'
        );
    }

    public function testDateChoice()
    {
        $form = $this->factory->createNamed('date', 'na&me', '2011-02-03', array(
            'property_path' => 'name',
            'input' => 'string',
            'widget' => 'choice',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="na&me_month"]
            [./option[@value="2"][@selected="selected"]]
        /following-sibling::select
            [@id="na&me_day"]
            [./option[@value="3"][@selected="selected"]]
        /following-sibling::select
            [@id="na&me_year"]
            [./option[@value="2011"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testDateText()
    {
        $form = $this->factory->createNamed('date', 'na&me', '2011-02-03', array(
            'property_path' => 'name',
            'input' => 'string',
            'widget' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input
            [@id="na&me_month"]
            [@type="text"]
            [@value="2"]
        /following-sibling::input
            [@id="na&me_day"]
            [@type="text"]
            [@value="3"]
        /following-sibling::input
            [@id="na&me_year"]
            [@type="text"]
            [@value="2011"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testDateSingleText()
    {
        $form = $this->factory->createNamed('date', 'na&me', '2011-02-03', array(
            'property_path' => 'name',
            'input' => 'string',
            'widget' => 'single_text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="Feb 3, 2011"]
'
        );
    }

    public function testEmail()
    {
        $form = $this->factory->createNamed('email', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="email"]
    [@name="na&me"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testEmailWithMaxLength()
    {
        $form = $this->factory->createNamed('email', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
            'max_length' => 123,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="email"]
    [@name="na&me"]
    [@value="foo&bar"]
    [@maxlength="123"]
'
        );
    }

    public function testFile()
    {
        $form = $this->factory->createNamed('file', 'na&me', null, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="file"][@id="na&me_file"]
        /following-sibling::input[@type="hidden"][@id="na&me_token"]
        /following-sibling::input[@type="hidden"][@id="na&me_name"]
        /following-sibling::input[@type="hidden"][@id="na&me_originalName"]
    ]
    [count(./input)=4]
'
        );
    }

    public function testHidden()
    {
        $form = $this->factory->createNamed('hidden', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="hidden"]
    [@name="na&me"]
    [@value="foo&bar"]
'
        );
    }

    public function testInteger()
    {
        $form = $this->factory->createNamed('integer', 'na&me', 123, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="number"]
    [@name="na&me"]
    [@value="123"]
'
        );
    }

    public function testLanguage()
    {
        $form = $this->factory->createNamed('language', 'na&me', 'de', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [./option[@value="de"][@selected="selected"][.="German"]]
    [count(./option)>200]
'
        );
    }

    public function testLocale()
    {
        $form = $this->factory->createNamed('locale', 'na&me', 'de_AT', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [./option[@value="de_AT"][@selected="selected"][.="German (Austria)"]]
    [count(./option)>200]
'
        );
    }

    public function testMoney()
    {
        $form = $this->factory->createNamed('money', 'na&me', 1234.56, array(
            'property_path' => 'name',
            'currency' => 'EUR',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="1234.56"]
    [contains(.., "€")]
'
        );
    }

    public function testNumber()
    {
        $form = $this->factory->createNamed('number', 'na&me', 1234.56, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="1234.56"]
'
        );
    }

    public function testPassword()
    {
        $form = $this->factory->createNamed('password', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="na&me"]
    [@value=""]
'
        );
    }

    public function testPasswordBoundNotAlwaysEmpty()
    {
        $form = $this->factory->createNamed('password', 'na&me', null, array(
            'property_path' => 'name',
            'always_empty' => false,
        ));
        $form->bind('foo&bar');

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="na&me"]
    [@value="foo&bar"]
'
        );
    }

    public function testPasswordWithMaxLength()
    {
        $form = $this->factory->createNamed('password', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
            'max_length' => 123,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="password"]
    [@name="na&me"]
    [@value=""]
    [@maxlength="123"]
'
        );
    }

    public function testPercent()
    {
        $form = $this->factory->createNamed('percent', 'na&me', 0.1, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="10"]
    [contains(.., "%")]
'
        );
    }

    public function testCheckedRadio()
    {
        $form = $this->factory->createNamed('radio', 'na&me', true, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="na&me"]
    [@checked="checked"]
    [@value=""]
'
        );
    }

    public function testCheckedRadioWithValue()
    {
        $form = $this->factory->createNamed('radio', 'na&me', true, array(
            'property_path' => 'name',
            'value' => 'foo&bar',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="na&me"]
    [@checked="checked"]
    [@value="foo&bar"]
'
        );
    }

    public function testUncheckedRadio()
    {
        $form = $this->factory->createNamed('radio', 'na&me', false, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="radio"]
    [@name="na&me"]
    [not(@checked)]
'
        );
    }

    public function testTextarea()
    {
        $form = $this->factory->createNamed('textarea', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/textarea
    [@name="na&me"]
    [.="foo&bar"]
'
        );
    }

    public function testText()
    {
        $form = $this->factory->createNamed('text', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testTextWithMaxLength()
    {
        $form = $this->factory->createNamed('text', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
            'max_length' => 123,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="foo&bar"]
    [@maxlength="123"]
'
        );
    }

    public function testSearch()
    {
        $form = $this->factory->createNamed('search', 'na&me', 'foo&bar', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="search"]
    [@name="na&me"]
    [@value="foo&bar"]
    [not(@maxlength)]
'
        );
    }

    public function testTime()
    {
        $form = $this->factory->createNamed('time', 'na&me', '04:05:06', array(
            'property_path' => 'name',
            'input' => 'string',
            'with_seconds' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="na&me_hour"]
            [@size="1"]
            [./option[@value="4"][@selected="selected"]]
        /following-sibling::select
            [@id="na&me_minute"]
            [@size="1"]
            [./option[@value="5"][@selected="selected"]]
    ]
    [count(./select)=2]
'
        );
    }

    public function testTimeWithSeconds()
    {
        $form = $this->factory->createNamed('time', 'na&me', '04:05:06', array(
            'property_path' => 'name',
            'input' => 'string',
            'with_seconds' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./select
            [@id="na&me_hour"]
            [@size="1"]
            [./option[@value="4"][@selected="selected"]]
        /following-sibling::select
            [@id="na&me_minute"]
            [@size="1"]
            [./option[@value="5"][@selected="selected"]]
        /following-sibling::select
            [@id="na&me_second"]
            [@size="1"]
            [./option[@value="6"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testTimezone()
    {
        $form = $this->factory->createNamed('timezone', 'na&me', 'Europe/Vienna', array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [./optgroup
        [@label="Europe"]
        [./option[@value="Europe/Vienna"][@selected="selected"][.="Vienna"]]
    ]
    [count(./optgroup)>10]
    [count(.//option)>200]
'
        );
    }

    public function testUrl()
    {
        $url = 'http://www.google.com?foo1=bar1&foo2=bar2';
        $form = $this->factory->createNamed('url', 'na&me', $url, array(
            'property_path' => 'name',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="url"]
    [@name="na&me"]
    [@value="http://www.google.com?foo1=bar1&foo2=bar2"]
'
        );
    }
}
