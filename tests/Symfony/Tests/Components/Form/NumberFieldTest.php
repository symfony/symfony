<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Components\Form\NumberField;

class NumberFieldTest extends LocalizedTestCase
{
    public function testRender()
    {
        $field = new NumberField('name');

        $field->setLocale('de_AT');
        $field->setData(1234.5678);

        $html = '<input id="name" name="name" value="1234,568" type="text" class="foobar" />';

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    public function testRenderWithPrecision()
    {
        $field = new NumberField('name', array('precision' => 4));

        $field->setLocale('de_AT');
        $field->setData(1234.5678);

        $html = '<input id="name" name="name" value="1234,5678" type="text" />';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderWithGrouping()
    {
        $field = new NumberField('name', array('grouping' => true));

        $field->setLocale('de_AT');
        $field->setData(1234.5678);

        $html = '<input id="name" name="name" value="1.234,568" type="text" />';

        $this->assertEquals($html, $field->render());
    }
}