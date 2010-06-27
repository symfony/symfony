<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/Fixtures/TestInputField.php';

use Symfony\Components\Form\InputField;
use Symfony\Tests\Components\Form\Fixtures\TestInputField;

class InputFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $field = new TestInputField('name');
        $field->setData('foobar');

        $html = '<input id="name" name="name" value="foobar" class="foobar" />';

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testRender_disabled()
    {
        $field = new TestInputField('name', array('disabled' => true));
        $field->setData('foobar');

        $html = '<input id="name" name="name" value="foobar" disabled="disabled" />';

        $this->assertEquals($html, $field->render());
    }
}