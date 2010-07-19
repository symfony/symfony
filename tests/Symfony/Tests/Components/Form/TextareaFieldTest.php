<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\TextareaField;

class TextareaFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $field = new TextareaField('name');
        $field->setData('asdf');

        $html = '<textarea id="name" name="name" rows="4" cols="30" class="foobar">asdf</textarea>';

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    public function testRenderEscapesValue()
    {
        $field = new TextareaField('name');
        $field->setData('<&&amp;');

        $html = '<textarea id="name" name="name" rows="4" cols="30">&lt;&amp;&amp;</textarea>';

        $this->assertEquals($html, $field->render());
    }
}