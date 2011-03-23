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

use Symfony\Component\Form\Renderer\Theme\PhpThemeEngine;

abstract class AbstractThemeEngineTest extends \PHPUnit_Framework_TestCase
{
    private $theme;

    public function setUp()
    {
        $this->theme = $this->createEngine();
    }

    abstract protected function createEngine();

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
            'size' => 20
        ));

        $this->assertEquals('test', $input->getAttribute('value'));
        $this->assertEquals('foo', $input->getAttribute('class'));
        $this->assertEquals('128', $input->getAttribute('maxlength'));
        $this->assertTrue($input->hasAttribute('disabled'));
        $this->assertTrue($input->hasAttribute('required'));
        $this->assertEquals('20', $input->getAttribute('size'));
    }

    public function testChoiceWidgetDefaults()
    {
        $choiceList = new \Symfony\Component\Form\ChoiceList\DefaultChoiceList(array(
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
            'expanded' => false,
            'multiple' => true,
            'preferred_choices' => $choiceList->getPreferredChoices(),
            'choices' => $choiceList->getOtherChoices(),
            'choice_list' => $choiceList,
            'separator' => '---'
        ));

        $this->assertXpathNodeValue($input, '//select/option[@selected="selected"]', 'Foo');
        $this->assertXpathMatches($input, '//select/option', 4);
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

    protected function renderAsDomElement($field, $section, $parameters)
    {
        $html = $this->theme->render($field, $section, $parameters);
        $dom = new \DomDocument('UTF-8');
        $dom->loadXml($html);
        return $dom->documentElement;
    }
}