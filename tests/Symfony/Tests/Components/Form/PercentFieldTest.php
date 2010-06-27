<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Components\Form\PercentField;

class PercentFieldTest extends LocalizedTestCase
{
    public function testRender()
    {
        $field = new PercentField('name');

        $field->setLocale('de_DE');
        $field->setData(0.12);

        $html = '<input id="name" name="name" value="12" type="text" /> %';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderWithPrecision()
    {
        $field = new PercentField('name', array('precision' => 2));

        $field->setLocale('de_DE');
        $field->setData(0.1234);

        $html = '<input id="name" name="name" value="12,34" type="text" /> %';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderWithInteger()
    {
        $field = new PercentField('name', array('type' => 'integer'));

        $field->setLocale('de_DE');
        $field->setData(123);

        $html = '<input id="name" name="name" value="123" type="text" /> %';

        $this->assertEquals($html, $field->render());
    }
}