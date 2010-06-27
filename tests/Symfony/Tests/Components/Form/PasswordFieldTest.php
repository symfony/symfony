<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\PasswordField;

class PasswordFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $field = new PasswordField('name');
        $field->setData('asdf');

        $html = '<input id="name" name="name" value="" type="password" class="foobar" />';

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    // when the user made an error in the form, display the value in the field
    public function testRenderAfterBinding()
    {
        $field = new PasswordField('name');
        $field->bind('asdf');

        $html = '<input id="name" name="name" value="asdf" type="password" />';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderNotAlwaysEmpty()
    {
        $field = new PasswordField('name', array('always_empty' => false));
        $field->setData('asdf');

        $html = '<input id="name" name="name" value="asdf" type="password" />';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderNotAlwaysEmptyAfterBinding()
    {
        $field = new PasswordField('name', array('always_empty' => false));
        $field->bind('asdf');

        $html = '<input id="name" name="name" value="asdf" type="password" />';

        $this->assertEquals($html, $field->render());
    }
}