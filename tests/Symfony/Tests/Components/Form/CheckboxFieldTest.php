<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/../../../../bootstrap.php';

use Symfony\Components\Form\CheckboxField;

class CheckboxFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender()
    {
        $field = new CheckboxField('name');
        $field->setData(true);

        $html = '<input id="name" name="name" checked="checked" type="checkbox" class="foobar" />';

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }
}