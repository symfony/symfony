<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\HtmlGenerator;

class HtmlGeneratorTest extends \PHPUnit_Framework_TestCase
{
    protected $generator;

    public function setUp()
    {
        HtmlGenerator::setXhtml(true);
        $this->generator = new HtmlGenerator();
    }

    public function testEscape()
    {
        $this->assertEquals('&lt;&amp;abcd', $this->generator->escape('<&abcd'));
    }

    public function testEscapeOnlyOnce()
    {
        $this->assertEquals('&lt;&amp;abcd', $this->generator->escape('<&amp;abcd'));
    }

    public function testAttribute()
    {
        $this->assertEquals('foo="bar"', $this->generator->attribute('foo', 'bar'));
    }

    public function testEscapeAttribute()
    {
        $this->assertEquals('foo="&lt;&gt;"', $this->generator->attribute('foo', '<>'));
    }

    public function testXhtmlAttribute()
    {
        HtmlGenerator::setXhtml(true);
        $this->assertEquals('foo="foo"', $this->generator->attribute('foo', true));
    }

    public function testNonXhtmlAttribute()
    {
        HtmlGenerator::setXhtml(false);
        $this->assertEquals('foo', $this->generator->attribute('foo', true));
    }

    public function testAttributes()
    {
        $html = $this->generator->attributes(array(
            'foo' => 'bar',
            'bar' => 'baz',
        ));
        $this->assertEquals(' foo="bar" bar="baz"', $html);
    }

    public function testXhtmlTag()
    {
        HtmlGenerator::setXhtml(true);
        $html = $this->generator->tag('input', array(
            'type' => 'text',
        ));
        $this->assertEquals('<input type="text" />', $html);
    }

    public function testNonXhtmlTag()
    {
        HtmlGenerator::setXhtml(false);
        $html = $this->generator->tag('input', array(
            'type' => 'text',
        ));
        $this->assertEquals('<input type="text">', $html);
    }

    public function testContentTag()
    {
        $html = $this->generator->contentTag('p', 'asdf', array(
            'class' => 'foo',
        ));
        $this->assertEquals('<p class="foo">asdf</p>', $html);
    }

    // it should be possible to pass the output of the tag() method as body
    // of the content tag
    public function testDontEscapeContentTag()
    {
        $this->assertEquals('<p><&</p>', $this->generator->contentTag('p', '<&'));
    }

}