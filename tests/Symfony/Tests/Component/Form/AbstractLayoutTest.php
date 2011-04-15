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
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractLayoutTest extends \PHPUnit_Framework_TestCase
{
    protected $csrfProvider;

    protected $factory;

    protected function setUp()
    {
        \Locale::setDefault('en');

        $dispatcher = new EventDispatcher();
        $validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
        $storage = new \Symfony\Component\HttpFoundation\File\TemporaryStorage('foo', 1, \sys_get_temp_dir());
        $loader = new DefaultTypeLoader($validator, $this->csrfProvider , $storage);

        $this->factory = new FormFactory($loader);
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
        $form = $this->factory->createBuilder('form', 'na&me', array('property_path' => 'name'))
            ->add('file', 'file')
            ->getForm();

        $this->assertEquals('enctype="multipart/form-data"', $this->renderEnctype($form->createView()));
    }

    public function testNoEnctype()
    {
        $form = $this->factory->createBuilder('form', 'na&me', array('property_path' => 'name'))
            ->add('text', 'text')
            ->getForm();

        $this->assertEquals('', $this->renderEnctype($form->createView()));
    }

    public function testLabel()
    {
        $form = $this->factory->create('text', 'na&me', array('property_path' => 'name'));
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
        $form = $this->factory->create('text', 'na&me', array(
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
        $form = $this->factory->create('text', 'na&me', array('property_path' => 'name'));
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
        $form = $this->factory->create('text', 'na&me', array('property_path' => 'name'));
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

    public function testCheckedCheckbox()
    {
        $form = $this->factory->create('checkbox', 'na&me', array(
            'property_path' => 'name',
            'data' => true,
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
        $form = $this->factory->create('checkbox', 'na&me', array(
            'property_path' => 'name',
            'value' => 'foo&bar',
            'data' => true,
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
        $form = $this->factory->create('checkbox', 'na&me', array(
            'property_path' => 'name',
            'data' => false,
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
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B'),
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [
        ./option[@value="a"][@selected="selected"][.="Choice A"]
        /following-sibling::option[@value="b"][not(@selected)][.="Choice B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithPreferred()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B'),
            'preferred_choices' => array('b'),
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array('separator' => '-- sep --'),
'/select
    [@name="na&me"]
    [
        ./option[@value="b"][not(@selected)][.="Choice B"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.="-- sep --"]
        /following-sibling::option[@value="a"][@selected="selected"][.="Choice A"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequired()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B'),
            'required' => false,
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [
        ./option[@value=""][.=""]
        /following-sibling::option[@value="a"][@selected="selected"][.="Choice A"]
        /following-sibling::option[@value="b"][not(@selected)][.="Choice B"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceGrouped()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array(
                'Group1' => array('a' => 'Choice A', 'b' => 'Choice B'),
                'Group2' => array('c' => 'Choice C'),
            ),
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me"]
    [./optgroup[@label="Group1"]
        [
            ./option[@value="a"][@selected="selected"][.="Choice A"]
            /following-sibling::option[@value="b"][not(@selected)][.="Choice B"]
        ]
        [count(./option)=2]
    ]
    [./optgroup[@label="Group2"]
        [./option[@value="c"][not(@selected)][.="Choice C"]]
        [count(./option)=1]
    ]
    [count(./optgroup)=2]
'
        );
    }

    public function testMultipleChoice()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B'),
            'data' => array('a'),
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me[]"]
    [@multiple="multiple"]
    [
        ./option[@value="a"][@selected="selected"][.="Choice A"]
        /following-sibling::option[@value="b"][not(@selected)][.="Choice B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testMultipleChoiceNonRequired()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B'),
            'data' => array('a'),
            'required' => false,
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/select
    [@name="na&me[]"]
    [@multiple="multiple"]
    [
        ./option[@value="a"][@selected="selected"][.="Choice A"]
        /following-sibling::option[@value="b"][not(@selected)][.="Choice B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B'),
            'data' => 'a',
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="radio"][@name="na&me"][@id="na&me_a"][@checked]
        /following-sibling::label[@for="na&me_a"][.="[trans]Choice A[/trans]"]
        /following-sibling::input[@type="radio"][@name="na&me"][@id="na&me_b"][not(@checked)]
        /following-sibling::label[@for="na&me_b"][.="[trans]Choice B[/trans]"]
    ]
    [count(./input)=2]
'
        );
    }

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->create('choice', 'na&me', array(
            'property_path' => 'name',
            'choices' => array('a' => 'Choice A', 'b' => 'Choice B', 'c' => 'Choice C'),
            'data' => array('a', 'c'),
            'multiple' => true,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="checkbox"][@name="na&me[a]"][@id="na&me_a"][@checked]
        /following-sibling::label[@for="na&me_a"][.="[trans]Choice A[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="na&me[b]"][@id="na&me_b"][not(@checked)]
        /following-sibling::label[@for="na&me_b"][.="[trans]Choice B[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="na&me[c]"][@id="na&me_c"][@checked]
        /following-sibling::label[@for="na&me_c"][.="[trans]Choice C[/trans]"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testCountry()
    {
        $form = $this->factory->create('country', 'na&me', array(
            'property_path' => 'name',
            'data' => 'AT',
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

        $form = $this->factory->create('csrf', 'na&me', array('property_path' => 'name'));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="hidden"]
    [@value="foo&bar"]
'
        );
    }

    public function testCsrfWithNonRootParent()
    {
        $form = $this->factory->create('csrf', 'na&me', array('property_path' => 'name'));
        $form->setParent($this->factory->create('form'));
        $form->getParent()->setParent($this->factory->create('form'));

        $html = $this->renderWidget($form->createView());

        $this->assertEquals('', trim($html));
    }

    public function testDateTime()
    {
        $form = $this->factory->create('datetime', 'na&me', array(
            'property_path' => 'name',
            'data' => '2011-02-03 04:05:06',
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
        $form = $this->factory->create('datetime', 'na&me', array(
            'property_path' => 'name',
            'data' => '2011-02-03 04:05:06',
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
        $form = $this->factory->create('date', 'na&me', array(
            'property_path' => 'name',
            'data' => '2011-02-03',
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
        $form = $this->factory->create('date', 'na&me', array(
            'property_path' => 'name',
            'data' => '2011-02-03',
            'input' => 'string',
            'widget' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/input
    [@type="text"]
    [@name="na&me"]
    [@value="Feb 3, 2011"]
'
        );
    }

    public function testFile()
    {
        $form = $this->factory->create('file', 'na&me', array('property_path' => 'name'));

        $this->assertWidgetMatchesXpath($form->createView(), array(),
'/div
    [
        ./input[@type="file"][@id="na&me_file"]
        /following-sibling::input[@type="hidden"][@id="na&me_token"]
        /following-sibling::input[@type="hidden"][@id="na&me_name"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testHidden()
    {
        $form = $this->factory->create('hidden', 'na&me', array(
            'property_path' => 'name',
            'data' => 'foo&bar',
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
        $form = $this->factory->create('integer', 'na&me', array(
            'property_path' => 'name',
            'data' => '123',
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
        $form = $this->factory->create('language', 'na&me', array(
            'property_path' => 'name',
            'data' => 'de',
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
        $form = $this->factory->create('locale', 'na&me', array(
            'property_path' => 'name',
            'data' => 'de_AT',
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
        $form = $this->factory->create('money', 'na&me', array(
            'property_path' => 'name',
            'data' => 1234.56,
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
        $form = $this->factory->create('number', 'na&me', array(
            'property_path' => 'name',
            'data' => 1234.56,
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
        $form = $this->factory->create('password', 'na&me', array(
            'property_path' => 'name',
            'data' => 'foo&bar',
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
        $form = $this->factory->create('password', 'na&me', array(
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
        $form = $this->factory->create('password', 'na&me', array(
            'property_path' => 'name',
            'data' => 'foo&bar',
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
        $form = $this->factory->create('percent', 'na&me', array(
            'property_path' => 'name',
            'data' => 0.1,
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
        $form = $this->factory->create('radio', 'na&me', array(
            'property_path' => 'name',
            'data' => true,
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
        $form = $this->factory->create('radio', 'na&me', array(
            'property_path' => 'name',
            'data' => true,
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
        $form = $this->factory->create('radio', 'na&me', array(
            'property_path' => 'name',
            'data' => false,
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
        $form = $this->factory->create('textarea', 'na&me', array(
            'property_path' => 'name',
            'data' => 'foo&bar',
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
        $form = $this->factory->create('text', 'na&me', array(
            'property_path' => 'name',
            'data' => 'foo&bar',
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
        $form = $this->factory->create('text', 'na&me', array(
            'property_path' => 'name',
            'data' => 'foo&bar',
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

    public function testTime()
    {
        $form = $this->factory->create('time', 'na&me', array(
            'property_path' => 'name',
            'data' => '04:05:06',
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
        $form = $this->factory->create('time', 'na&me', array(
            'property_path' => 'name',
            'data' => '04:05:06',
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
        $form = $this->factory->create('timezone', 'na&me', array(
            'property_path' => 'name',
            'data' => 'Europe/Vienna',
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
        $form = $this->factory->create('url', 'na&me', array(
            'property_path' => 'name',
            'data' => 'http://www.google.com?foo1=bar1&foo2=bar2',
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
