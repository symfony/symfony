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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\TemplateContext;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Type\Loader\DefaultTypeLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class AbstractLayoutTest extends \PHPUnit_Framework_TestCase
{
    protected $factory;

    protected function setUp()
    {
        \Locale::setDefault('en');

        $dispatcher = new EventDispatcher();
        $validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $csrfProvider = new DefaultCsrfProvider('foo');
        $storage = new \Symfony\Component\HttpFoundation\File\TemporaryStorage('foo', 1, \sys_get_temp_dir());
        $loader = new DefaultTypeLoader($validator, $csrfProvider , $storage);

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

    protected function assertWidgetMatchesXpath(FormInterface $form, array $vars, $xpath)
    {
        $html = $this->renderWidget($form, array_merge(array(
            'id' => 'my_id',
            'attr' => array('class' => 'my_class'),
        ), $vars));

        $xpath = trim($xpath).'
    [@id="my_id"]
    [@class="my_class"]';

        $this->assertMatchesXpath($html, $xpath);
    }

    abstract protected function renderEnctype(FormInterface $form);

    abstract protected function renderLabel(FormInterface $form, $label = null);

    abstract protected function renderErrors(FormInterface $form);

    abstract protected function renderWidget(FormInterface $form, array $vars = array());

    abstract protected function renderRow(FormInterface $form, array $vars = array());

    abstract protected function renderRest(FormInterface $form, array $vars = array());

    public function testEnctype()
    {
        $form = $this->factory->createBuilder('form', 'name')
            ->add('file', 'file')
            ->getForm();

        $this->assertEquals('enctype="multipart/form-data"', $this->renderEnctype($form));
    }

    public function testNoEnctype()
    {
        $form = $this->factory->createBuilder('form', 'name')
            ->add('text', 'text')
            ->getForm();

        $this->assertEquals('', $this->renderEnctype($form));
    }

    public function testLabel()
    {
        $form = $this->factory->create('text', 'name');
        $html = $this->renderLabel($form);

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Name[/trans]"]
'
        );
    }

    public function testLabelWithCustomText()
    {
        $form = $this->factory->create('text', 'name');
        $html = $this->renderLabel($form, 'Custom label');

        $this->assertMatchesXpath($html,
'/label
    [@for="name"]
    [.="[trans]Custom label[/trans]"]
'
        );
    }

    public function testErrors()
    {
        $form = $this->factory->create('text', 'name');
        $form->addError(new FormError('Error 1'));
        $form->addError(new FormError('Error 2'));
        $html = $this->renderErrors($form);

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
        $form = $this->factory->create('checkbox', 'name', array(
            'data' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [@checked="checked"]
    [@value="1"]
'
        );
    }

    public function testCheckedCheckboxWithValue()
    {
        $form = $this->factory->create('checkbox', 'name', array(
            'value' => 'foobar',
            'data' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [@checked="checked"]
    [@value="foobar"]
'
        );
    }

    public function testUncheckedCheckbox()
    {
        $form = $this->factory->create('checkbox', 'name', array(
            'data' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="checkbox"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testSingleChoice()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B'),
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
    [
        ./option[@value="a"][@selected="selected"][.="A"]
        /following-sibling::option[@value="b"][not(@selected)][.="B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceWithPreferred()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B'),
            'preferred_choices' => array('b'),
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array('separator' => '-- sep --'),
'/select
    [@name="name"]
    [
        ./option[@value="b"][not(@selected)][.="B"]
        /following-sibling::option[@disabled="disabled"][not(@selected)][.="-- sep --"]
        /following-sibling::option[@value="a"][@selected="selected"][.="A"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceNonRequired()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B'),
            'required' => false,
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
    [
        ./option[@value=""][.=""]
        /following-sibling::option[@value="a"][@selected="selected"][.="A"]
        /following-sibling::option[@value="b"][not(@selected)][.="B"]
    ]
    [count(./option)=3]
'
        );
    }

    public function testSingleChoiceGrouped()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array(
                'Group1' => array('a' => 'A', 'b' => 'B'),
                'Group2' => array('c' => 'C'),
            ),
            'data' => 'a',
            'multiple' => false,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
    [./optgroup[@label="Group1"]
        [
            ./option[@value="a"][@selected="selected"][.="A"]
            /following-sibling::option[@value="b"][not(@selected)][.="B"]
        ]
        [count(./option)=2]
    ]
    [./optgroup[@label="Group2"]
        [./option[@value="c"][not(@selected)][.="C"]]
        [count(./option)=1]
    ]
    [count(./optgroup)=2]
'
        );
    }

    public function testMultipleChoice()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B'),
            'data' => array('a'),
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name[]"]
    [@multiple="multiple"]
    [
        ./option[@value="a"][@selected="selected"][.="A"]
        /following-sibling::option[@value="b"][not(@selected)][.="B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testMultipleChoiceNonRequired()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B'),
            'data' => array('a'),
            'required' => false,
            'multiple' => true,
            'expanded' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name[]"]
    [@multiple="multiple"]
    [
        ./option[@value="a"][@selected="selected"][.="A"]
        /following-sibling::option[@value="b"][not(@selected)][.="B"]
    ]
    [count(./option)=2]
'
        );
    }

    public function testSingleChoiceExpanded()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B'),
            'data' => 'a',
            'multiple' => false,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/div
    [
        ./input[@type="radio"][@name="name"][@id="name_a"][@checked]
        /following-sibling::label[@for="name_a"][.="[trans]A[/trans]"]
        /following-sibling::input[@type="radio"][@name="name"][@id="name_b"][not(@checked)]
        /following-sibling::label[@for="name_b"][.="[trans]B[/trans]"]
    ]
    [count(./input)=2]
'
        );
    }

    public function testMultipleChoiceExpanded()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array('a' => 'A', 'b' => 'B', 'c' => 'C'),
            'data' => array('a', 'c'),
            'multiple' => true,
            'expanded' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/div
    [
        ./input[@type="checkbox"][@name="name[a]"][@id="name_a"][@checked]
        /following-sibling::label[@for="name_a"][.="[trans]A[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="name[b]"][@id="name_b"][not(@checked)]
        /following-sibling::label[@for="name_b"][.="[trans]B[/trans]"]
        /following-sibling::input[@type="checkbox"][@name="name[c]"][@id="name_c"][@checked]
        /following-sibling::label[@for="name_c"][.="[trans]C[/trans]"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testCountry()
    {
        $form = $this->factory->create('country', 'name', array(
            'data' => 'AT',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
    [./option[@value="AT"][@selected="selected"][.="Austria"]]
    [count(./option)>200]
'
        );
    }

    public function testCsrf()
    {
        $form = $this->factory->create('csrf', 'name');

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="hidden"]
    [string-length(@value)>=40]
'
        );
    }

    public function testDateTime()
    {
        $form = $this->factory->create('datetime', 'name', array(
            'data' => '2011-02-03 04:05:06',
            'input' => 'string',
            'with_seconds' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
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
        $form = $this->factory->create('datetime', 'name', array(
            'data' => '2011-02-03 04:05:06',
            'input' => 'string',
            'with_seconds' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
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

    public function testDateChoice()
    {
        $form = $this->factory->create('date', 'name', array(
            'data' => '2011-02-03',
            'input' => 'string',
            'widget' => 'choice',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
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

    public function testDateText()
    {
        $form = $this->factory->create('date', 'name', array(
            'data' => '2011-02-03',
            'input' => 'string',
            'widget' => 'text',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="Feb 3, 2011"]
'
        );
    }

    public function testFile()
    {
        $form = $this->factory->create('file', 'name');

        $this->assertWidgetMatchesXpath($form, array(),
'/div
    [
        ./input[@type="file"][@id="name_file"]
        /following-sibling::input[@type="hidden"][@id="name_token"]
        /following-sibling::input[@type="hidden"][@id="name_name"]
    ]
    [count(./input)=3]
'
        );
    }

    public function testHidden()
    {
        $form = $this->factory->create('hidden', 'name', array(
            'data' => 'foobar',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="hidden"]
    [@name="name"]
    [@value="foobar"]
'
        );
    }

    public function testInteger()
    {
        $form = $this->factory->create('integer', 'name', array(
            'data' => '123',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="number"]
    [@name="name"]
    [@value="123"]
'
        );
    }

    public function testLanguage()
    {
        $form = $this->factory->create('language', 'name', array(
            'data' => 'de',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
    [./option[@value="de"][@selected="selected"][.="German"]]
    [count(./option)>200]
'
        );
    }

    public function testLocale()
    {
        $form = $this->factory->create('locale', 'name', array(
            'data' => 'de_AT',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
    [./option[@value="de_AT"][@selected="selected"][.="German (Austria)"]]
    [count(./option)>200]
'
        );
    }

    public function testMoney()
    {
        $form = $this->factory->create('money', 'name', array(
            'data' => 1234.56,
            'currency' => 'EUR',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
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
        $form = $this->factory->create('number', 'name', array(
            'data' => 1234.56,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
'
        );
    }

    public function testPassword()
    {
        $form = $this->factory->create('password', 'name', array(
            'data' => 'Pa$sW0rD',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="password"]
    [@name="name"]
    [@value=""]
'
        );
    }

    public function testPasswordWithMaxLength()
    {
        $form = $this->factory->create('password', 'name', array(
            'data' => 'Pa$sW0rD',
            'max_length' => 123,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="password"]
    [@name="name"]
    [@value=""]
    [@maxlength="123"]
'
        );
    }

    public function testPercent()
    {
        $form = $this->factory->create('percent', 'name', array(
            'data' => 0.1,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
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
        $form = $this->factory->create('radio', 'name', array(
            'data' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="radio"]
    [@name="name"]
    [@checked="checked"]
    [@value=""]
'
        );
    }

    public function testCheckedRadioWithValue()
    {
        $form = $this->factory->create('radio', 'name', array(
            'data' => true,
            'value' => 'foobar',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="radio"]
    [@name="name"]
    [@checked="checked"]
    [@value="foobar"]
'
        );
    }

    public function testUncheckedRadio()
    {
        $form = $this->factory->create('radio', 'name', array(
            'data' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="radio"]
    [@name="name"]
    [not(@checked)]
'
        );
    }

    public function testTextarea()
    {
        $form = $this->factory->create('textarea', 'name', array(
            'data' => 'foobar',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/textarea
    [@name="name"]
    [.="foobar"]
'
        );
    }

    public function testText()
    {
        $form = $this->factory->create('text', 'name', array(
            'data' => 'foobar',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="foobar"]
    [not(@maxlength)]
'
        );
    }

    public function testTextWithMaxLength()
    {
        $form = $this->factory->create('text', 'name', array(
            'data' => 'foobar',
            'max_length' => 123,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="text"]
    [@name="name"]
    [@value="foobar"]
    [@maxlength="123"]
'
        );
    }

    public function testTime()
    {
        $form = $this->factory->create('time', 'name', array(
            'data' => '04:05:06',
            'input' => 'string',
            'with_seconds' => false,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/div
    [
        ./select
            [@id="name_hour"]
            [./option[@value="4"][@selected="selected"]]
        /following-sibling::select
            [@id="name_minute"]
            [./option[@value="5"][@selected="selected"]]
    ]
    [count(./select)=2]
'
        );
    }

    public function testTimeWithSeconds()
    {
        $form = $this->factory->create('time', 'name', array(
            'data' => '04:05:06',
            'input' => 'string',
            'with_seconds' => true,
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/div
    [
        ./select
            [@id="name_hour"]
            [./option[@value="4"][@selected="selected"]]
        /following-sibling::select
            [@id="name_minute"]
            [./option[@value="5"][@selected="selected"]]
        /following-sibling::select
            [@id="name_second"]
            [./option[@value="6"][@selected="selected"]]
    ]
    [count(./select)=3]
'
        );
    }

    public function testTimezone()
    {
        $form = $this->factory->create('timezone', 'name', array(
            'data' => 'Europe/Vienna',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/select
    [@name="name"]
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
        $form = $this->factory->create('url', 'name', array(
            'data' => 'http://www.google.com',
        ));

        $this->assertWidgetMatchesXpath($form, array(),
'/input
    [@type="url"]
    [@name="name"]
    [@value="http://www.google.com"]
'
        );
    }
}
