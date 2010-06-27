<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\TextField;

class TextFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $field = new TextField('name');
        $field->setData('asdf');

        $html = '<input id="name" name="name" value="asdf" type="text" class="foobar" />';

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    public function testRenderWithMaxLength()
    {
        $field = new TextField('name', array('max_length' => 10));
        $field->setData('asdf');

        $html = '<input id="name" name="name" value="asdf" type="text" maxlength="10" />';

        $this->assertEquals($html, $field->render());
    }
}