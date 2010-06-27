<?php

namespace Symfony\Tests\Components\Form;

use Symfony\Components\Form\HiddenField;

class HiddenFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    public function setUp()
    {
        $this->field = new HiddenField('name');
    }

    public function testRender()
    {
        $this->field->setData('foobar');

        $html = '<input id="name" name="name" value="foobar" type="hidden" class="foobar" />';

        $this->assertEquals($html, $this->field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testIsHidden()
    {
        $this->assertTrue($this->field->isHidden());
    }
}