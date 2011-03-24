<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Renderer\Theme\PhpTheme;
use Symfony\Component\Form\Renderer\ThemeRenderer;
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;

abstract class AbstractThemeTest extends \PHPUnit_Framework_TestCase
{
    private $themeFactory;

    public function setUp()
    {
        $this->form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');
        $this->themeFactory = $this->createThemeFactory();
    }

    abstract protected function createThemeFactory();

    public function testTextWidgetDefault()
    {
        $input = $this->renderAsDomElement('text', 'widget', array(
            'id' => 'foo',
            'name' => 'foo',
            'value' => '',
            'class' => '',
            'max_length' => null,
            'disabled' => false,
            'required' => false,
            'size' => null
        ));

        $this->assertEquals('input', $input->tagName);
        $this->assertTrue($input->hasAttribute('id'), "Has id attribute");
        $this->assertEquals('foo', $input->getAttribute('id'), "Id attribute is set to field name.");
        $this->assertEquals('foo', $input->getAttribute('name'), "Field name translated to input name");
        $this->assertEquals('', $input->getAttribute('value'), "By default value is empty");
        $this->assertFalse($input->hasAttribute('class'), "No class attribute by default.");
        $this->assertFalse($input->hasAttribute('maxlength'), "has no maxlength attribute.");
        $this->assertFalse($input->hasAttribute('size'), "has no size attribute");
        $this->assertFalse($input->hasAttribute('disabled'));
        $this->assertFalse($input->hasAttribute('required'));
    }

    public function testTextWidgetFull()
    {
        $input = $this->renderAsDomElement('text', 'widget', array(
            'id' => 'foo',
            'name' => 'foo',
            'value' => 'test',
            'class' => 'foo',
            'max_length' => 128,
            'disabled' => true,
            'required' => true,
            'size' => 20,
            'attr' => array('accesskey' => 'G', 'title' => 'Foo'),
            'renderer' => new ThemeRenderer($this->form, $this->themeFactory, null),
        ));

        $this->assertEquals('test', $input->getAttribute('value'));
        $this->assertEquals('foo', $input->getAttribute('class'));
        $this->assertEquals('128', $input->getAttribute('maxlength'));
        $this->assertTrue($input->hasAttribute('disabled'));
        $this->assertTrue($input->hasAttribute('required'));
        $this->assertEquals('20', $input->getAttribute('size'));
        $this->assertTrue($input->hasAttribute('accesskey'));
        $this->assertEquals('G', $input->getAttribute('accesskey'));
        $this->assertTrue($input->hasAttribute('title'));
        $this->assertEquals('Foo', $input->getAttribute('title'));
    }

    public function testChoiceWidgetDefaults()
    {
        $choiceList = new DefaultChoiceList(array(
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ), array('baz'));

        $input = $this->renderAsDomElement('choice', 'widget', array(
            'id' => 'foo',
            'name' => 'foo',
            'value' => 'foo',
            'class' => 'foo',
            'disabled' => false,
            'required' => false,
            'empty_value' => '---',
            'expanded' => false,
            'multiple' => true,
            'preferred_choices' => $choiceList->getPreferredChoices(),
            'choices' => $choiceList->getOtherChoices(),
            'choice_list' => $choiceList,
            'separator' => '---',
        ));

        $this->assertXpathNodeValue($input, '//select/option[@selected="selected"]', 'Foo');
        $this->assertXpathMatches($input, '//select/option', 5);
        $this->assertXpathNodeValue($input, '//select/option[@disabled="disabled"]', '---');
        $this->assertXpathMatches($input, '//select[@multiple="multiple"]', 1);
    }

    protected function assertXpathNodeValue($element, $expression, $nodeValue)
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodeList = $xpath->evaluate($expression);
        $this->assertEquals(1, $nodeList->length);
        $this->assertEquals($nodeValue, $nodeList->item(0)->nodeValue);
    }

    protected function assertXpathMatches($element, $expression, $matches)
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodeList = $xpath->evaluate($expression);
        $this->assertEquals($matches, $nodeList->length);
    }

    protected function renderAsDomElement($form, $section, $parameters)
    {
        $html = $this->themeFactory->create()->render($form, $section, $parameters);
        $dom = new \DomDocument('UTF-8');
        $dom->loadXml($html);
        return $dom->documentElement;
    }
}