<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\RadioField;
use Symfony\Components\Form\FieldGroup;

class RadioFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $field = new RadioField('name');
        $field->setData(true);

        $html = '<input id="name" name="name" checked="checked" type="radio" class="foobar" />';

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    // when a radio button is in a field group, all radio buttons in that group
    // should have the same name
    public function testRenderParentName()
    {
        $field = new RadioField('name');
        $field->setParent(new FieldGroup('parent'));

        $html = '<input id="parent_name" name="parent" type="radio" />';

        $this->assertEquals($html, $field->render());
    }
}